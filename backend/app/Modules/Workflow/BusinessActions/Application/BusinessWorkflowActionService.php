<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Application;

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowActionPort;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowRecordPort;
use App\Modules\Workflow\BusinessActions\Contracts\BusinessWorkflowActionErrors as Errors;
use App\Repositories\LifecycleRepository;
use App\Services\BusinessLifecycleTargetAdapter;
use App\Services\ContactLifecycleAdapter;
use App\Services\FoundationLocationLifecycleAdapter;
use App\Services\LifecycleService;
use App\Services\LifecycleTargetAdapter;
use App\Services\ResourceNativeStateCatalog;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Canonical application boundary for metadata, options and transitions. */
final class BusinessWorkflowActionService implements BusinessWorkflowActionPort
{
    /** @param ConnectionInterface<mixed,mixed> $db */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly BusinessWorkflowRecordPort $records,
        private readonly ResourceWorkflowStateResolver $states,
        private readonly AuthorizationService $authorization,
        private readonly LifecycleRepository $lifecycles,
    ) {
    }

    /** @return array{mode:string,initial:string|null,states:list<string>} */
    public function states(string $resource, OperationContext $operation): array
    {
        $this->records->assertBusinessResource($resource);
        $profile = (new EffectiveProfileResolver($this->db))->resolve($operation);
        if (! $profile->allows($resource, 'view')) {
            throw new DomainException(Errors::ERROR_AUTHORIZATION_DENIED, [
                'resource' => $resource, 'action' => 'view',
            ]);
        }
        $lifecycleId = $this->states->lifecycleId($resource);
        $codes = $this->states->allowedPolicyStateCodes($resource);
        return [
            'mode' => $lifecycleId === null ? 'NATIVE' : 'LIFECYCLE',
            'initial' => $this->states->initialCode($resource) ?? (in_array('ACTIVE', $codes, true) ? 'ACTIVE' : null),
            'states' => array_values($codes),
        ];
    }

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
    public function options(string $resource, int $id, OperationContext $operation): array
    {
        $row = $this->records->record($resource, $id);
        $stateId = $this->states->persistedStateId($resource, $row);
        $status = strtoupper((string) $row['status']);
        $this->requireAction($resource, 'view', $row, $stateId, $operation);
        $lifecycleId = $this->states->lifecycleId($resource);
        $options = [];
        // A view permission cannot authorize mutation, even when a transition
        // is erroneously configured with an action_code of "view".
        $canSetState = $this->allowedAction($resource, 'setstate', $row, $stateId, $operation);
        if ($lifecycleId !== null && $canSetState) {
            foreach ($this->lifecycles->findTransitions($lifecycleId, 'ACTIVE') as $transition) {
                if ((int) ($transition['from_state_id'] ?? 0) !== $stateId) {
                    continue;
                }
                $action = strtolower((string) ($transition['action_code'] ?? ''));
                $destinationId = (int) ($transition['to_state_id'] ?? 0);
                try {
                    $destination = $this->states->codeForStateId($resource, $destinationId);
                } catch (DomainException) {
                    continue;
                }
                if ($destinationId === $stateId || ! $this->allowedAction($resource, $action, $row, $stateId, $operation)) {
                    continue;
                }
                $options[] = ['action_code' => $action, 'status' => $destination];
            }
        } elseif ($lifecycleId === null && $canSetState) {
            foreach (ResourceNativeStateCatalog::codes($resource) as $candidate) {
                if ($candidate !== $status) {
                    $options[] = ['action_code' => 'setstate', 'status' => $candidate];
                }
            }
        }
        return [
            'mode' => $lifecycleId === null ? 'NATIVE' : 'LIFECYCLE',
            'current' => $status,
            'options' => $options,
        ];
    }

    /** @return array{status:string,action_code:string} */
    public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array
    {
        $this->records->assertBusinessResource($resource);
        if ($this->states->lifecycleId($resource) === null) {
            throw new DomainException(Errors::ERROR_TRANSITION_REQUIRED, ['resource' => $resource]);
        }
        $target = $this->records->record($resource, $id);
        $this->requireAction($resource, 'setstate', $target, $this->states->persistedStateId($resource, $target), $operation);
        $actionCode = strtolower(trim($actionCode));
        if (preg_match('/\A[a-z][a-z0-9_:-]*\z/D', $actionCode) !== 1) {
            throw new DomainException(Errors::ERROR_INVALID_TRANSITION, ['action_code' => $actionCode]);
        }
        $result = (new LifecycleService(adapters: [$this->adapter($resource)], db: $this->db))->execute(
            $resource, $id, $actionCode, $operation,
        );
        return [
            'status' => $this->states->codeForStateId($resource, (int) $result['transition']['to_state_id']),
            'action_code' => $actionCode,
        ];
    }

    public function applyIfBound(string $type, int $id, string $destination, OperationContext $operation): bool
    {
        if ($this->states->lifecycleId($type) === null) {
            return false;
        }
        $adapter = $this->adapter($type);
        $toStateId = $this->states->stateIdForCode($type, $destination);
        (new LifecycleService(adapters: [$adapter], db: $this->db))->execute(
            $type, $id, 'setstate', $operation, $toStateId,
        );
        return true;
    }

    private function adapter(string $resource): LifecycleTargetAdapter
    {
        return match ($resource) {
            'province', 'ward' => new FoundationLocationLifecycleAdapter($resource, $this->db, states: $this->states),
            'organization_contact', 'employee_contact' => new ContactLifecycleAdapter($resource, $this->db, states: $this->states),
            default => new BusinessLifecycleTargetAdapter($resource, $this->db, $this->states),
        };
    }

    /** @param array<string,mixed> $row */
    private function allowedAction(string $resource, string $action, array $row, ?int $stateId, OperationContext $operation): bool
    {
        try {
            return $this->authorization->authorize(
                $operation, $resource, $action,
                new EvaluationOrganizationRequest(persistedTarget: $row), $stateId,
            )->isAllowed();
        } catch (DomainException) {
            // An unknown or unauthorized transition action is not an option.
            return false;
        }
    }

    /** @param array<string,mixed> $row */
    private function requireAction(string $resource, string $action, array $row, ?int $stateId, OperationContext $operation): void
    {
        if (! $this->allowedAction($resource, $action, $row, $stateId, $operation)) {
            throw new DomainException(Errors::ERROR_AUTHORIZATION_DENIED, ['resource' => $resource, 'action' => $action]);
        }
    }
}

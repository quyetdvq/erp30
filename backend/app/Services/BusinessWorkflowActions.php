<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\ContactAuthorizationRegistry;
use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Read/execute workflow actions for business records. Native-only Resources
 * cannot reach this boundary; unknown table names are never interpolated.
 */
final class BusinessWorkflowActions
{
    /** @var array<string,string> */
    private const TABLES = [
        'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
        'department' => 'departments', 'position' => 'positions', 'employee' => 'employees',
        'product_group' => 'product_groups', 'product_uom' => 'product_uoms',
        'product' => 'products', 'price_list' => 'price_lists',
        'organization_contact' => 'contacts', 'employee_contact' => 'contacts',
    ];

    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private ResourceWorkflowStateResolver $states;
    private AuthorizationService $authorization;
    private LifecycleRepository $lifecycles;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->states = new ResourceWorkflowStateResolver($this->db);
        $this->authorization = new AuthorizationService(db: $this->db);
        $this->lifecycles = new LifecycleRepository($this->db);
    }

    /**
     * Configuration metadata for list filters and CREATE previews only.
     * Base Permission gates visibility; record-level PBAC remains mandatory
     * for every list, detail, and state mutation.
     *
     * @return array{mode:string,initial:string|null,states:list<string>}
     */
    public function states(string $resource, OperationContext $operation): array
    {
        $this->assertBusinessResource($resource);
        $profile = (new EffectiveProfileResolver($this->db))->resolve($operation);
        if (! $profile->allows($resource, 'view')) {
            throw new DomainException(LifecycleService::ERROR_AUTHORIZATION_DENIED, [
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

    /**
     * @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>}
     */
    public function options(string $resource, int $id, OperationContext $operation): array
    {
        $row = $this->record($resource, $id);
        $stateId = $this->states->persistedStateId($resource, $row);
        $status = strtoupper((string) $row['status']);
        $this->requireAction($resource, 'view', $row, $stateId, $operation);
        $lifecycleId = $this->states->lifecycleId($resource);
        $options = [];
        // Every state-changing action additionally requires the Resource's
        // dedicated setstate permission. A misconfigured 'view' Transition
        // must never turn a read permission into mutation authority.
        $canSetState = $this->allowedAction($resource, 'setstate', $row, $stateId, $operation);
        if ($lifecycleId !== null && $canSetState) {
            foreach ($this->lifecycles->findTransitions($lifecycleId, 'ACTIVE') as $transition) {
                if ((int) ($transition['from_state_id'] ?? 0) !== $stateId) {
                    continue;
                }
                $action = strtolower((string) ($transition['action_code'] ?? ''));
                $destinationId = (int) ($transition['to_state_id'] ?? 0);
                // An invalid definition cannot be offered as an actionable option.
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
            // Legacy native fallback only for a business Resource without Lifecycle.
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
        $this->assertBusinessResource($resource);
        if ($this->states->lifecycleId($resource) === null) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED, ['resource' => $resource]);
        }
        $target = $this->record($resource, $id);
        $this->requireAction($resource, 'setstate', $target, $this->states->persistedStateId($resource, $target), $operation);
        $actionCode = strtolower(trim($actionCode));
        if (preg_match('/\A[a-z][a-z0-9_:-]*\z/D', $actionCode) !== 1) {
            throw new DomainException(LifecycleService::ERROR_INVALID_TRANSITION, ['action_code' => $actionCode]);
        }
        $adapter = match ($resource) {
            'province', 'ward' => new FoundationLocationLifecycleAdapter($resource, $this->db, states: $this->states),
            'organization_contact', 'employee_contact' => new ContactLifecycleAdapter($resource, $this->db, states: $this->states),
            default => new BusinessLifecycleTargetAdapter($resource, $this->db, $this->states),
        };
        $result = (new LifecycleService(adapters: [$adapter], db: $this->db))->execute(
            $resource, $id, $actionCode, $operation,
        );
        return [
            'status' => $this->states->codeForStateId($resource, (int) $result['transition']['to_state_id']),
            'action_code' => $actionCode,
        ];
    }

    /** @return array<string,mixed> */
    private function record(string $resource, int $id): array
    {
        $this->assertBusinessResource($resource);
        $id = PositiveId::normalize($id, 'target_id', LifecycleService::ERROR_TARGET_NOT_FOUND);
        $row = $this->db->table(self::TABLES[$resource])->where('id', $id)->get()->getRowArray();
        if ($row === null || (str_ends_with($resource, '_contact') && ContactAuthorizationRegistry::resourceFor($row) !== $resource)) {
            throw new DomainException(LifecycleService::ERROR_TARGET_NOT_FOUND, ['target_id' => $id]);
        }
        return $row;
    }

    private function assertBusinessResource(string $resource): void
    {
        if (! isset(self::TABLES[$resource]) || ResourceNativeStateCatalog::isIndependent($resource)) {
            throw new DomainException(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $resource]);
        }
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
            // An unregistered/unauthorized transition action is not an option.
            return false;
        }
    }

    /** @param array<string,mixed> $row */
    private function requireAction(string $resource, string $action, array $row, ?int $stateId, OperationContext $operation): void
    {
        if (! $this->allowedAction($resource, $action, $row, $stateId, $operation)) {
            throw new DomainException(LifecycleService::ERROR_AUTHORIZATION_DENIED, ['resource' => $resource, 'action' => $action]);
        }
    }
}

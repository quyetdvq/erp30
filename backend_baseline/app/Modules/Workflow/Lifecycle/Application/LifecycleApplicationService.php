<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Lifecycle\Application;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleRepositoryPort;
use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use App\Services\LifecycleAuditPort;
use App\Services\LifecycleAuthorizationPort;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Lifecycle configuration invariants and canonical authorized state changes.
 */
final class LifecycleApplicationService implements LifecycleApplicationPort
{
    public const ERROR_LIFECYCLE_NOT_FOUND = 'WF_LIFECYCLE_NOT_FOUND';
    public const ERROR_INVALID_CONFIGURATION = 'WF_INVALID_LIFECYCLE_CONFIGURATION';
    public const ERROR_STATE_NOT_IN_LIFECYCLE = 'WF_STATE_NOT_IN_LIFECYCLE';
    public const ERROR_INVALID_TRANSITION = 'WF_INVALID_TRANSITION';
    public const ERROR_AUTHORIZATION_DENIED = 'AUTH_POLICY_DENIED';
    public const ERROR_TARGET_NOT_FOUND = 'WF_TARGET_NOT_FOUND';
    public const ERROR_TARGET_ADAPTER_UNAVAILABLE = 'WF_TARGET_ADAPTER_UNAVAILABLE';
    public const ERROR_ADAPTER_REGISTRATION_INVALID = 'WF_TARGET_ADAPTER_REGISTRATION_INVALID';

    /** @var array<string, LifecycleTargetAdapter> */
    private array $adapters = [];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /**
     * @param array<int|string, mixed> $adapters
     * @param ConnectionInterface<mixed, mixed>|null $db
     */
    public function __construct(
        private readonly ?LifecycleRepositoryPort $repository = null,
        private readonly ?LifecycleAuthorizationPort $authorization = null,
        private readonly ?LifecycleAuditPort $audit = null,
        array $adapters = [],
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
        foreach ($adapters as $key => $adapter) {
            if (! $adapter instanceof LifecycleTargetAdapter) {
                throw new DomainException(self::ERROR_ADAPTER_REGISTRATION_INVALID, ['key' => (string) $key]);
            }
            $type = $this->resourceType($adapter->resourceType());
            if (is_string($key) && trim($key) !== '' && $this->resourceType($key) !== $type) {
                throw new DomainException(self::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource' => $type]);
            }
            if (isset($this->adapters[$type])) {
                throw new DomainException(self::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource' => $type]);
            }
            $this->adapters[$type] = $adapter;
        }
    }

    public function registerTargetAdapter(LifecycleTargetAdapter $adapter): void
    {
        $type = $this->resourceType($adapter->resourceType());
        if (isset($this->adapters[$type])) {
            throw new DomainException(self::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource' => $type]);
        }
        $this->adapters[$type] = $adapter;
    }

    /**
     * Validate the persisted configuration for a lifecycle.
     *
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function validateConfiguration(int $lifecycleId): array
    {
        $lifecycleId = PositiveId::normalize($lifecycleId, 'lifecycle_id', self::ERROR_INVALID_CONFIGURATION);
        $lifecycle = $this->repo()->findLifecycle($lifecycleId);
        if ($lifecycle === null) {
            throw new DomainException(self::ERROR_LIFECYCLE_NOT_FOUND, ['lifecycle_id' => $lifecycleId]);
        }

        $states = $this->repo()->findLifecycleStates($lifecycleId);
        $transitions = $this->repo()->findTransitions($lifecycleId);
        $this->assertConfiguration($lifecycle, $states, $transitions);

        return ['lifecycle' => $lifecycle, 'states' => $states, 'transitions' => $transitions];
    }

    /**
     * Insert a configuration batch atomically under the lifecycle row lock.
     * Existing configuration is retained; database uniqueness remains the
     * final guard against concurrent duplicate rows.
     *
     * @param list<array<string,mixed>> $states
     * @param list<array<string,mixed>> $transitions
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function configure(
        int $lifecycleId,
        array $states,
        array $transitions,
        ?OperationContext $operation = null,
    ): array {
        $operation ??= $this->operation();
        $lifecycleId = PositiveId::normalize($lifecycleId, 'lifecycle_id', self::ERROR_INVALID_CONFIGURATION);

        return $this->repo()->transactional(function () use ($lifecycleId, $states, $transitions, $operation): array {
            $lifecycle = $this->repo()->lockLifecycle($lifecycleId);
            if ($lifecycle === null) {
                throw new DomainException(self::ERROR_LIFECYCLE_NOT_FOUND, ['lifecycle_id' => $lifecycleId]);
            }

            $existingStates = $this->repo()->findLifecycleStates($lifecycleId);
            $existingTransitions = $this->repo()->findTransitions($lifecycleId);
            $this->assertBatchShape($lifecycle, $existingStates, $existingTransitions, $states, $transitions);

            foreach ($states as $state) {
                $state['lifecycle_id'] = $lifecycleId;
                $this->repo()->insertLifecycleState($state, $operation);
            }
            foreach ($transitions as $transition) {
                $transition['lifecycle_id'] = $lifecycleId;
                $this->repo()->insertTransition($transition, $operation);
            }

            return $this->validateConfiguration($lifecycleId);
        }, 'lifecycle.configure');
    }

    /** @return array<string,mixed> */
    public function resolveTransition(int $lifecycleId, int $currentStateId, string $actionCode): array
    {
        $lifecycleId = PositiveId::normalize($lifecycleId, 'lifecycle_id', self::ERROR_INVALID_CONFIGURATION);
        $currentStateId = PositiveId::normalize($currentStateId, 'state_id', self::ERROR_STATE_NOT_IN_LIFECYCLE);
        $lifecycle = $this->repo()->findLifecycle($lifecycleId);
        if ($lifecycle === null) {
            throw new DomainException(self::ERROR_LIFECYCLE_NOT_FOUND, ['lifecycle_id' => $lifecycleId]);
        }
        if (($lifecycle['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['lifecycle_id' => $lifecycleId]);
        }

        $members = $this->membership($this->repo()->findLifecycleStates($lifecycleId));
        if (! isset($members[$currentStateId])) {
            throw new DomainException(self::ERROR_STATE_NOT_IN_LIFECYCLE, [
                'lifecycle_id' => $lifecycleId,
                'state_id' => $currentStateId,
            ]);
        }

        $actionCode = $this->actionCode($actionCode);
        $transition = $this->repo()->findActiveTransitionByAction($lifecycleId, $currentStateId, $actionCode);
        if ($transition === null) {
            throw new DomainException(self::ERROR_INVALID_TRANSITION, [
                'lifecycle_id' => $lifecycleId,
                'from_state_id' => $currentStateId,
                'action_code' => $actionCode,
            ]);
        }

        $toStateId = PositiveId::normalize($transition['to_state_id'] ?? null, 'to_state_id', self::ERROR_INVALID_TRANSITION);
        if (! isset($members[$toStateId]) || $this->repo()->findActiveWorkflowState($toStateId) === null) {
            throw new DomainException(self::ERROR_INVALID_TRANSITION, [
                'lifecycle_id' => $lifecycleId,
                'from_state_id' => $currentStateId,
                'action_code' => $actionCode,
            ]);
        }

        return $transition;
    }

    /**
     * Execute one canonical state-changing action through a registered target
     * adapter. Caller-supplied state, lifecycle, destination, and organization
     * values are intentionally absent from this API.
     *
     * @return array{decision:AuthorizationDecision,transition:array<string,mixed>,target:LifecycleTargetSnapshot}
     */
    public function execute(
        string $resourceType,
        mixed $targetId,
        string $actionCode,
        ?OperationContext $operation = null,
        ?int $expectedDestinationStateId = null,
    ): array {
        $operation ??= $this->operation();
        $resourceType = $this->resourceType($resourceType);
        $adapter = $this->adapters[$resourceType] ?? null;
        if ($adapter === null) {
            throw new DomainException(self::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $resourceType]);
        }

        $snapshot = $adapter->load($targetId, $operation);
        if ($snapshot->resourceType !== $resourceType) {
            throw new DomainException(self::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $resourceType]);
        }
        $transition = $this->resolveTransition($snapshot->lifecycleId, $snapshot->currentStateId, $actionCode);
        $destinationStateId = PositiveId::normalize($transition['to_state_id'] ?? null, 'to_state_id', self::ERROR_INVALID_TRANSITION);
        // The caller may request a target status, but must not redirect a
        // deterministic lifecycle transition to a different destination.
        if ($expectedDestinationStateId !== null && $destinationStateId !== $expectedDestinationStateId) {
            throw new DomainException(self::ERROR_INVALID_TRANSITION, [
                'action_code' => $actionCode,
                'expected_destination_state_id' => $expectedDestinationStateId,
            ]);
        }
        $actionCode = $this->actionCode($actionCode);
        $request = new EvaluationOrganizationRequest(
            persistedTarget: $snapshot->authorizationTarget,
        );
        $decision = $this->auth()->authorize(
            $operation,
            $resourceType,
            $actionCode,
            $request,
            $snapshot->currentStateId,
        );

        if (! $decision->isAllowed()) {
            $this->audit()->recordIfRequired($decision);
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $actionCode]);
        }

        $adapter->transactional(function () use ($adapter, $snapshot, $operation, $decision, $destinationStateId): void {
            $locked = $adapter->lockAndReload($snapshot, $operation);
            if ($locked->resourceType !== $snapshot->resourceType
                || $locked->targetId !== $snapshot->targetId
                || $locked->lifecycleId !== $snapshot->lifecycleId
                || $locked->currentStateId !== $snapshot->currentStateId
                || $locked->evaluationOrganizationId !== $snapshot->evaluationOrganizationId) {
                throw new ConcurrencyException('lifecycle.state_change');
            }

            $adapter->mutate($locked, $snapshot->currentStateId, $destinationStateId, $operation);
            $this->audit()->recordIfRequired($decision);
        });

        return ['decision' => $decision, 'transition' => $transition, 'target' => $snapshot];
    }

    /**
     * @param array<string, mixed> $lifecycle
     * @param list<array<string, mixed>> $states
     * @param list<array<string, mixed>> $transitions
     */
    private function assertConfiguration(array $lifecycle, array $states, array $transitions): void
    {
        $stateIds = [];
        $initialCount = 0;
        foreach ($states as $state) {
            $stateId = PositiveId::normalize($state['state_id'] ?? null, 'state_id', self::ERROR_INVALID_CONFIGURATION);
            if (isset($stateIds[$stateId])) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['state_id' => $stateId]);
            }
            $stateIds[$stateId] = true;
            if ($this->repo()->findActiveWorkflowState($stateId) === null) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['state_id' => $stateId]);
            }
            if ($this->flag($state['is_initial'] ?? false)) {
                ++$initialCount;
            }
        }
        if (($lifecycle['status'] ?? null) === 'ACTIVE' && $initialCount !== 1) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['initial_count' => $initialCount]);
        }

        $seenTransitions = [];
        foreach ($transitions as $transition) {
            $transitionLifecycleId = PositiveId::normalize($transition['lifecycle_id'] ?? null, 'lifecycle_id', self::ERROR_INVALID_CONFIGURATION);
            $fromStateId = PositiveId::normalize($transition['from_state_id'] ?? null, 'from_state_id', self::ERROR_INVALID_CONFIGURATION);
            $toStateId = PositiveId::normalize($transition['to_state_id'] ?? null, 'to_state_id', self::ERROR_INVALID_CONFIGURATION);
            $actionCode = $this->actionCode((string) ($transition['action_code'] ?? ''));
            if ($transitionLifecycleId !== (int) $lifecycle['id'] || ! isset($stateIds[$fromStateId]) || ! isset($stateIds[$toStateId])) {
                throw new DomainException(self::ERROR_STATE_NOT_IN_LIFECYCLE, ['lifecycle_id' => (int) $lifecycle['id']]);
            }
            $key = $fromStateId . ':' . $actionCode;
            if (isset($seenTransitions[$key])) {
                throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['action_code' => $actionCode]);
            }
            $seenTransitions[$key] = true;
        }
    }

    /**
     * @param array<string, mixed> $lifecycle
     * @param list<array<string,mixed>> $existingStates
     * @param list<array<string,mixed>> $existingTransitions
     * @param list<array<string,mixed>> $states
     * @param list<array<string,mixed>> $transitions
     */
    private function assertBatchShape(array $lifecycle, array $existingStates, array $existingTransitions, array $states, array $transitions): void
    {
        $combinedStates = [...$existingStates, ...array_map(
            static fn (array $state): array => array_merge($state, ['lifecycle_id' => $lifecycle['id']]),
            $states,
        )];
        $combinedTransitions = [...$existingTransitions, ...array_map(
            static fn (array $transition): array => array_merge($transition, ['lifecycle_id' => $lifecycle['id']]),
            $transitions,
        )];
        $this->assertConfiguration($lifecycle, $combinedStates, $combinedTransitions);
    }

    /** @param list<array<string,mixed>> $states
     *  @return array<int, true>
     */
    private function membership(array $states): array
    {
        $members = [];
        foreach ($states as $state) {
            $members[PositiveId::normalize($state['state_id'] ?? null, 'state_id', self::ERROR_INVALID_CONFIGURATION)] = true;
        }

        return $members;
    }

    private function flag(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE'], true);
    }

    private function actionCode(string $actionCode): string
    {
        $normalized = strtolower(trim($actionCode));
        if ($normalized === '' || preg_match('/^[a-z0-9_:-]+$/', $normalized) !== 1) {
            throw new DomainException(self::ERROR_INVALID_CONFIGURATION, ['field' => 'action_code']);
        }

        return $normalized;
    }

    private function resourceType(string $resourceType): string
    {
        $normalized = strtolower(trim($resourceType));
        if ($normalized === '' || preg_match('/^[a-z][a-z0-9_:-]*$/', $normalized) !== 1) {
            throw new DomainException(self::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $resourceType]);
        }

        return $normalized;
    }

    private function repo(): LifecycleRepositoryPort
    {
        return $this->repository ?? new SqlLifecycleRepository($this->db);
    }

    private function auth(): LifecycleAuthorizationPort
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): LifecycleAuditPort
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}

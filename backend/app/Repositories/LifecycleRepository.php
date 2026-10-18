<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleRepositoryPort;
use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Backward-compatible Lifecycle repository facade; canonical SQL lives in the Workflow module. */
final class LifecycleRepository implements LifecycleRepositoryPort
{
    public const ERROR_PERSISTENCE_FAILED = 'LIFECYCLE_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'LIFECYCLE_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'LIFECYCLE_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'LIFECYCLE_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'LIFECYCLE_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'LIFECYCLE_INVALID_ID';
    public const ERROR_MUTATION_FAILED = 'LIFECYCLE_MUTATION_FAILED';

    private readonly SqlLifecycleRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlLifecycleRepository($db);
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array {
        return $this->delegate->find($id);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycle(mixed $id): ?array {
        return $this->delegate->findLifecycle($id);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array {
        return $this->delegate->findByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycleByCode(string $code): ?array {
        return $this->delegate->findLifecycleByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findActive(mixed $id): ?array {
        return $this->delegate->findActive($id);
    }

    /** @return array<string, mixed>|null */
    public function findActiveLifecycle(mixed $id): ?array {
        return $this->delegate->findActiveLifecycle($id);
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(string $code): ?array {
        return $this->delegate->findActiveByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findActiveLifecycleByCode(string $code): ?array {
        return $this->delegate->findActiveLifecycleByCode($code);
    }

    /** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findLifecycles(array $filters = []): array {
        return $this->delegate->findLifecycles($filters);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function createLifecycle(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->createLifecycle($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertLifecycle(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->insertLifecycle($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool {
        return $this->delegate->update($id, $data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateLifecycle(mixed $id, array $data, ?OperationContext $operation = null): bool {
        return $this->delegate->updateLifecycle($id, $data, $operation);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array {
        return $this->delegate->findForUpdate($id);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycleForUpdate(mixed $id): ?array {
        return $this->delegate->findLifecycleForUpdate($id);
    }

    /** @return array<string, mixed>|null */
    public function lockLifecycle(mixed $id): ?array {
        return $this->delegate->lockLifecycle($id);
    }

    /** @return array<string, mixed>|null */
    public function findWorkflowState(mixed $id): ?array {
        return $this->delegate->findWorkflowState($id);
    }

    /** @return array<string, mixed>|null */
    public function findWorkflowStateByCode(string $code): ?array {
        return $this->delegate->findWorkflowStateByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findActiveWorkflowState(mixed $id): ?array {
        return $this->delegate->findActiveWorkflowState($id);
    }

    /** @return array<string, mixed>|null */
    public function findActiveWorkflowStateByCode(string $code): ?array {
        return $this->delegate->findActiveWorkflowStateByCode($code);
    }

    /** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findWorkflowStates(array $filters = []): array {
        return $this->delegate->findWorkflowStates($filters);
    }

    /** @param array<string, mixed> $data */
    public function createWorkflowState(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->createWorkflowState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertWorkflowState(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->insertWorkflowState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateWorkflowState(mixed $id, array $data, ?OperationContext $operation = null): bool {
        return $this->delegate->updateWorkflowState($id, $data, $operation);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycleState(mixed $id): ?array {
        return $this->delegate->findLifecycleState($id);
    }

    /** @return list<array<string, mixed>> */
    public function findLifecycleStates(mixed $lifecycleId): array {
        return $this->delegate->findLifecycleStates($lifecycleId);
    }

    /** @param array<string, mixed> $data */
    public function createLifecycleState(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->createLifecycleState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertLifecycleState(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->insertLifecycleState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateLifecycleState(mixed $id, array $data, ?OperationContext $operation = null): bool {
        return $this->delegate->updateLifecycleState($id, $data, $operation);
    }

    /** @return array<string, mixed>|null */
    public function findTransition(mixed $id): ?array {
        return $this->delegate->findTransition($id);
    }

    /** @return list<array<string, mixed>> */
    public function findTransitions(mixed $lifecycleId, ?string $status = null): array {
        return $this->delegate->findTransitions($lifecycleId, $status);
    }

    /** @return array<string, mixed>|null */
    public function findTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array {
        return $this->delegate->findTransitionByAction($lifecycleId, $fromStateId, $actionCode);
    }

    /** @return array<string, mixed>|null */
    public function findActiveTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array {
        return $this->delegate->findActiveTransitionByAction($lifecycleId, $fromStateId, $actionCode);
    }

    /** @return array<string, mixed>|null */
    public function resolveTransition(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array {
        return $this->delegate->resolveTransition($lifecycleId, $fromStateId, $actionCode);
    }

    /** @param array<string, mixed> $data */
    public function createTransition(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->createTransition($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertTransition(array $data, ?OperationContext $operation = null): int {
        return $this->delegate->insertTransition($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateTransition(mixed $id, array $data, ?OperationContext $operation = null): bool {
        return $this->delegate->updateTransition($id, $data, $operation);
    }

    /**
     * Execute repository operations on this connection without changing the
     * caller's connection/session. Services may use this for batch mutation.
     *
     * @param callable(): mixed $callback
     * @return mixed
     */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed {
        return $this->delegate->transactional($callback, $operation);
    }
}

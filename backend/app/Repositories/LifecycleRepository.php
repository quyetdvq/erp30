<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Persistence primitives for workflow/lifecycle configuration.
 *
 * This repository does not validate lifecycle business rules or authorization.
 * In particular, state membership and transition rows are persisted as
 * supplied; LifecycleService owns cross-row validation and orchestration.
 */
final class LifecycleRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'LIFECYCLE_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'LIFECYCLE_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'LIFECYCLE_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'LIFECYCLE_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'LIFECYCLE_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'LIFECYCLE_INVALID_ID';
    public const ERROR_MUTATION_FAILED = 'LIFECYCLE_MUTATION_FAILED';

    /** @var list<string> */
    private const LIFECYCLE_FIELDS = [
        'code', 'name', 'description', 'status', 'created_by', 'updated_by',
        'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const WORKFLOW_STATE_FIELDS = [
        'code', 'name', 'description', 'status', 'created_by', 'updated_by',
        'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const LIFECYCLE_STATE_FIELDS = [
        'lifecycle_id', 'state_id', 'sequence', 'is_initial', 'is_final',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const TRANSITION_FIELDS = [
        'lifecycle_id', 'from_state_id', 'to_state_id', 'action_code', 'status',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // Lifecycle master -----------------------------------------------------

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findLifecycle($id);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycle(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'lifecycle_id');

        return $this->findRow('findLifecycle', fn () => $this->db->table('lifecycles')->where('id', $normalizedId)->get());
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findLifecycleByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycleByCode(string $code): ?array
    {
        return $this->findRow('findLifecycleByCode', fn () => $this->db->table('lifecycles')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findActive(mixed $id): ?array
    {
        return $this->findActiveLifecycle($id);
    }

    /** @return array<string, mixed>|null */
    public function findActiveLifecycle(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'lifecycle_id');

        return $this->findRow('findActiveLifecycle', fn () => $this->db->table('lifecycles')
            ->where('id', $normalizedId)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(string $code): ?array
    {
        return $this->findActiveLifecycleByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findActiveLifecycleByCode(string $code): ?array
    {
        return $this->findRow('findActiveLifecycleByCode', fn () => $this->db->table('lifecycles')
            ->where('code', $code)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findLifecycles(array $filters = []): array
    {
        return $this->findMany('findLifecycles', 'lifecycles', ['code', 'status'], $filters, ['id' => 'ASC']);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertLifecycle($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function createLifecycle(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertLifecycle($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertLifecycle(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, self::LIFECYCLE_FIELDS, ['code', 'name', 'status']), $this->createdAudit($operation));

        return $this->insertRow('lifecycles', $payload, 'insertLifecycle');
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->updateLifecycle($id, $data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateLifecycle(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'lifecycle_id');
        $payload = $this->payload($data, self::LIFECYCLE_FIELDS);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);

        return $this->updateRow('lifecycles', $normalizedId, $payload, $operation, 'updateLifecycle');
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        return $this->findLifecycleForUpdate($id);
    }

    /** @return array<string, mixed>|null */
    public function findLifecycleForUpdate(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'lifecycle_id');

        return $this->findLockedRow('findLifecycleForUpdate', 'lifecycles', $normalizedId);
    }

    /** Alias for the lifecycle row lock used by configuration mutation services. */
    /** @return array<string, mixed>|null */
    public function lockLifecycle(mixed $id): ?array
    {
        return $this->findLifecycleForUpdate($id);
    }

    // Workflow state catalog ------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findWorkflowState(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'state_id');

        return $this->findRow('findWorkflowState', fn () => $this->db->table('workflow_states')->where('id', $normalizedId)->get());
    }

    /** @return array<string, mixed>|null */
    public function findWorkflowStateByCode(string $code): ?array
    {
        return $this->findRow('findWorkflowStateByCode', fn () => $this->db->table('workflow_states')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findActiveWorkflowState(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'state_id');

        return $this->findRow('findActiveWorkflowState', fn () => $this->db->table('workflow_states')
            ->where('id', $normalizedId)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @return array<string, mixed>|null */
    public function findActiveWorkflowStateByCode(string $code): ?array
    {
        return $this->findRow('findActiveWorkflowStateByCode', fn () => $this->db->table('workflow_states')
            ->where('code', $code)
            ->where('status', 'ACTIVE')
            ->get());
    }

    /** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findWorkflowStates(array $filters = []): array
    {
        return $this->findMany('findWorkflowStates', 'workflow_states', ['code', 'status'], $filters, ['id' => 'ASC']);
    }

    /** @param array<string, mixed> $data */
    public function createWorkflowState(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertWorkflowState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertWorkflowState(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, self::WORKFLOW_STATE_FIELDS, ['code', 'name', 'status']), $this->createdAudit($operation));

        return $this->insertRow('workflow_states', $payload, 'insertWorkflowState');
    }

    /** @param array<string, mixed> $data */
    public function updateWorkflowState(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'state_id');
        $payload = $this->payload($data, self::WORKFLOW_STATE_FIELDS);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);

        return $this->updateRow('workflow_states', $normalizedId, $payload, $operation, 'updateWorkflowState');
    }

    // Lifecycle state membership -------------------------------------------

    /** @return array<string, mixed>|null */
    public function findLifecycleState(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'lifecycle_state_id');

        return $this->findRow('findLifecycleState', fn () => $this->db->table('lifecycle_states')->where('id', $normalizedId)->get());
    }

    /** @return list<array<string, mixed>> */
    public function findLifecycleStates(mixed $lifecycleId): array
    {
        $normalizedId = $this->id($lifecycleId, 'lifecycle_id');

        try {
            $result = $this->db->table('lifecycle_states')
                ->where('lifecycle_id', $normalizedId)
                ->orderBy('sequence', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findLifecycleStates', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findLifecycleStates');
        }

        return array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function createLifecycleState(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertLifecycleState($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertLifecycleState(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->normalizeReferenceFields($data, ['lifecycle_id', 'state_id']);
        $payload = array_merge($this->payload($payload, self::LIFECYCLE_STATE_FIELDS, ['lifecycle_id', 'state_id', 'sequence']), $this->createdAudit($operation));

        return $this->insertRow('lifecycle_states', $payload, 'insertLifecycleState');
    }

    /** @param array<string, mixed> $data */
    public function updateLifecycleState(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'lifecycle_state_id');
        $payload = $this->normalizeReferenceFields($data, ['lifecycle_id', 'state_id']);
        $payload = $this->payload($payload, self::LIFECYCLE_STATE_FIELDS);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);

        return $this->updateRow('lifecycle_states', $normalizedId, $payload, $operation, 'updateLifecycleState');
    }

    // Lifecycle transitions ------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findTransition(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'transition_id');

        return $this->findRow('findTransition', fn () => $this->db->table('lifecycle_transitions')->where('id', $normalizedId)->get());
    }

    /** @return list<array<string, mixed>> */
    public function findTransitions(mixed $lifecycleId, ?string $status = null): array
    {
        $normalizedId = $this->id($lifecycleId, 'lifecycle_id');
        $builder = $this->db->table('lifecycle_transitions')
            ->where('lifecycle_id', $normalizedId)
            ->orderBy('from_state_id', 'ASC')
            ->orderBy('action_code', 'ASC')
            ->orderBy('id', 'ASC');
        if ($status !== null) {
            $builder->where('status', $status);
        }

        try {
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findTransitions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findTransitions');
        }

        return array_values($result->getResultArray());
    }

    /** @return array<string, mixed>|null */
    public function findTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array
    {
        return $this->findTransitionByActionInternal($lifecycleId, $fromStateId, $actionCode, false);
    }

    /** @return array<string, mixed>|null */
    public function findActiveTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array
    {
        return $this->findTransitionByActionInternal($lifecycleId, $fromStateId, $actionCode, true);
    }

    /** Alias used by transition resolution callers; only active rows resolve. */
    /** @return array<string, mixed>|null */
    public function resolveTransition(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array
    {
        return $this->findActiveTransitionByAction($lifecycleId, $fromStateId, $actionCode);
    }

    /** @return array<string, mixed>|null */
    private function findTransitionByActionInternal(
        mixed $lifecycleId,
        mixed $fromStateId,
        string $actionCode,
        bool $activeOnly,
    ): ?array {
        $normalizedLifecycleId = $this->id($lifecycleId, 'lifecycle_id');
        $normalizedFromStateId = $this->id($fromStateId, 'from_state_id');

        return $this->findRow('findTransitionByAction', function () use (
            $normalizedLifecycleId,
            $normalizedFromStateId,
            $actionCode,
            $activeOnly,
        ) {
            $builder = $this->db->table('lifecycle_transitions')
                ->where('lifecycle_id', $normalizedLifecycleId)
                ->where('from_state_id', $normalizedFromStateId)
                ->where('action_code', $actionCode)
                ->orderBy('id', 'ASC');
            if ($activeOnly) {
                $builder->where('status', 'ACTIVE');
            }

            return $builder->get();
        });
    }

    /** @param array<string, mixed> $data */
    public function createTransition(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertTransition($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insertTransition(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->normalizeReferenceFields($data, ['lifecycle_id', 'from_state_id', 'to_state_id']);
        $payload = array_merge($this->payload($payload, self::TRANSITION_FIELDS, ['lifecycle_id', 'from_state_id', 'to_state_id', 'action_code', 'status']), $this->createdAudit($operation));

        return $this->insertRow('lifecycle_transitions', $payload, 'insertTransition');
    }

    /** @param array<string, mixed> $data */
    public function updateTransition(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'transition_id');
        $payload = $this->normalizeReferenceFields($data, ['lifecycle_id', 'from_state_id', 'to_state_id']);
        $payload = $this->payload($payload, self::TRANSITION_FIELDS);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);

        return $this->updateRow('lifecycle_transitions', $normalizedId, $payload, $operation, 'updateTransition');
    }

    /**
     * Execute repository operations on this connection without changing the
     * caller's connection/session. Services may use this for batch mutation.
     *
     * @param callable(): mixed $callback
     * @return mixed
     */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }

        $this->db->transBegin();
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
            }
            $this->db->transCommit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }

            throw new PersistenceException(self::ERROR_MUTATION_FAILED, $operation, $exception);
        }
    }

    /** @param array<string, mixed> $data */
    private function insertRow(string $table, array $data, string $operation): int
    {
        try {
            if (! $this->db->table($table)->insert($data)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    private function updateRow(string $table, int $id, array $data, ?OperationContext $operation, string $operationName): bool
    {
        if ($data === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => $operationName]);
        }
        if ($this->findRow($operationName . '.find', fn () => $this->db->table($table)->select('id')->where('id', $id)->get()) === null) {
            return false;
        }

        try {
            $updated = $this->db->table($table)->where('id', $id)->update(array_merge($data, $this->updatedAudit($operation)));
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operationName, $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operationName);
        }

        return true;
    }

    /** @param callable(): mixed $query
     *  @return array<string, mixed>|null
     */
    private function findRow(string $operation, callable $query): ?array
    {
        try {
            $result = $query();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function findLockedRow(string $operation, string $table, int $id): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        try {
            $builder = $this->db->table($table)->where('id', $id);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param list<string> $filterFields
     *  @param array<string, scalar|null> $filters
     *  @param array<string, string> $orderBy
     *  @return list<array<string, mixed>>
     */
    private function findMany(string $operation, string $table, array $filterFields, array $filters, array $orderBy): array
    {
        try {
            $builder = $this->db->table($table);
            foreach ($filterFields as $field) {
                if (array_key_exists($field, $filters)) {
                    $builder->where($field, $filters[$field]);
                }
            }
            foreach ($orderBy as $field => $direction) {
                $builder->orderBy($field, $direction);
            }
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data
     *  @param list<string> $fields
     *  @param list<string> $required
     *  @return array<string, mixed>
     */
    private function payload(array $data, array $fields, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, $fields, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /** @param array<string, mixed> $data
     *  @param list<string> $fields
     *  @return array<string, mixed>
     */
    private function normalizeReferenceFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->id($data[$field], $field);
            }
        }

        return $data;
    }

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    /** @return array{created_by: int|null, created_at: string} */
    private function createdAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    private function updatedAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }
}

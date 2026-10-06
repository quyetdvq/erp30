<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Persistence primitives for PBAC policies. This class does not evaluate
 * policies or make authorization decisions.
 */
final class PolicyRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'POLICY_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'POLICY_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'POLICY_UPDATE_EMPTY';
    public const ERROR_ACTION_UPDATE_EMPTY = 'POLICY_ACTION_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'POLICY_FIELD_UNSUPPORTED';
    public const ERROR_ACTION_FIELD_UNSUPPORTED = 'POLICY_ACTION_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'POLICY_FIELD_REQUIRED';
    public const ERROR_ACTION_FIELD_REQUIRED = 'POLICY_ACTION_FIELD_REQUIRED';
    public const ERROR_MUTATION_FAILED = 'POLICY_MUTATION_FAILED';

    /** @var list<string> */
    private const POLICY_FIELDS = [
        'organization_id', 'resource_id', 'state_id', 'participant_type',
        'participant_ref_id', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const ACTION_FIELDS = [
        'policy_id', 'resource_action_id', 'effect', 'created_by', 'updated_by',
        'created_at', 'updated_at',
    ];

    /** @var array<string, string> */
    private const PARTICIPANT_TABLES = [
        'GROUP' => 'groups',
        'POSITION' => 'positions',
        'DEPARTMENT' => 'departments',
        'ORGANIZATION' => 'organizations',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        try {
            $result = $this->db->table('policies')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'find');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }

        try {
            $builder = $this->db->table('policies')->where('id', $id);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate', $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        try {
            $builder = $this->db->table('policies')->orderBy('id', 'ASC');
            foreach (['organization_id', 'resource_id', 'participant_type', 'participant_ref_id'] as $field) {
                if (array_key_exists($field, $filters)) {
                    $builder->where($field, $filters[$field]);
                }
            }
            if (array_key_exists('state_id', $filters)) {
                if ($filters['state_id'] === null) {
                    $builder->where('state_id IS NULL', null, false);
                } else {
                    $builder->where('state_id', $filters['state_id']);
                }
            }
            $result = $builder->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function findPolicyActions(int $policyId): array
    {
        try {
            $result = $this->db->table('policy_actions')
                ->where('policy_id', $policyId)
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyActions');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function findActions(int $policyId): array
    {
        return $this->findPolicyActions($policyId);
    }

    /** @return array<string, mixed>|null */
    public function findPolicyAction(int $id): ?array
    {
        try {
            $result = $this->db->table('policy_actions')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyAction', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPolicyAction');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    public function participantTargetExists(string $participantType, int $participantRefId): bool
    {
        $type = strtoupper(trim($participantType));
        $table = $type === 'USER'
            ? (string) config('Auth')->tables['users']
            : (self::PARTICIPANT_TABLES[$type] ?? null);
        if ($table === null) {
            return false;
        }

        try {
            $result = $this->db->table($table)->select('id')->where('id', $participantRefId)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'participantTargetExists', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'participantTargetExists');
        }

        return $result->getRowArray() !== null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->policyPayload($data, ['organization_id', 'resource_id', 'participant_type', 'participant_ref_id']);
        $payload = array_merge($payload, $this->trustedCreateAudit($operation));

        try {
            if (! $this->db->table('policies')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $payload = $this->policyPayload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        return $this->transactional(function () use ($id, $payload, $operation): bool {
            if ($this->findForUpdate($id) === null) {
                return false;
            }
            $payload = array_merge($payload, $this->trustedUpdateAudit($operation));
            try {
                $updated = $this->db->table('policies')->where('id', $id)->update($payload);
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
            }
            if ($updated === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
            }

            return true;
        }, 'update');
    }

    /** @param array<string, mixed> $data */
    public function insertPolicyAction(array $data, ?OperationContext $operation = null): int
    {
        $payload = $this->actionPayload($data, ['policy_id', 'resource_action_id', 'effect']);
        $payload = array_merge($payload, $this->trustedCreateAudit($operation));

        try {
            if (! $this->db->table('policy_actions')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insertPolicyAction']);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertPolicyAction');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function insertAction(array $data, ?OperationContext $operation = null): int
    {
        return $this->insertPolicyAction($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updatePolicyAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $payload = $this->actionPayload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_ACTION_UPDATE_EMPTY, ['operation' => 'updatePolicyAction']);
        }
        $payload = array_merge($payload, $this->trustedUpdateAudit($operation));

        try {
            $updated = $this->db->table('policy_actions')->where('id', $id)->update($payload);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updatePolicyAction', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updatePolicyAction');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function updateAction(int $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->updatePolicyAction($id, $data, $operation);
    }

    /**
     * Insert one Policy and all of its Actions atomically.
     *
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $actions
     */
    public function insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int
    {
        return $this->transactional(function () use ($policy, $actions, $operation): int {
            $policyId = $this->insert($policy, $operation);
            foreach ($actions as $action) {
                $this->insertPolicyAction(array_merge($action, ['policy_id' => $policyId]), $operation);
            }

            return $policyId;
        }, 'insertWithActions');
    }

    /**
     * Replace a Policy row and its Actions atomically. Existing rows are locked
     * before the Policy update and action replacement.
     *
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $actions
     */
    public function replaceWithActions(int $policyId, array $policy, array $actions, ?OperationContext $operation = null): bool
    {
        $payload = $this->policyPayload($policy);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'replaceWithActions']);
        }

        return $this->transactional(function () use ($policyId, $payload, $actions, $operation): bool {
            if ($this->findForUpdate($policyId) === null) {
                return false;
            }
            $changes = array_merge($payload, $this->trustedUpdateAudit($operation));
            try {
                if (! $this->db->table('policies')->where('id', $policyId)->update($changes)) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions.policy');
                }
                if (! $this->db->table('policy_actions')->where('policy_id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions.actions');
                }
            } catch (PersistenceException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceWithActions', $exception);
            }
            foreach ($actions as $action) {
                $this->insertPolicyAction(array_merge($action, ['policy_id' => $policyId]), $operation);
            }

            return true;
        }, 'replaceWithActions');
    }

    /** Delete a Policy and its Actions atomically after locking the Policy row. */
    public function deleteWithActions(int $policyId, ?OperationContext $operation = null): bool
    {
        return $this->transactional(function () use ($policyId): bool {
            if ($this->findForUpdate($policyId) === null) {
                return false;
            }
            try {
                if (! $this->db->table('policy_actions')->where('policy_id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions.actions');
                }
                if (! $this->db->table('policies')->where('id', $policyId)->delete()) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions.policy');
                }
            } catch (PersistenceException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteWithActions', $exception);
            }

            return true;
        }, 'deleteWithActions');
    }

    /**
     * @param callable(): mixed $callback
     * @return mixed
     */
    private function transactional(callable $callback, string $operation): mixed
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

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function policyPayload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::POLICY_FIELDS, true)) {
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

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function actionPayload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::ACTION_FIELDS, true)) {
                throw new DomainException(self::ERROR_ACTION_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_ACTION_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /** @return array{created_by: int|null, created_at: string} */
    private function trustedCreateAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    private function trustedUpdateAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }
}

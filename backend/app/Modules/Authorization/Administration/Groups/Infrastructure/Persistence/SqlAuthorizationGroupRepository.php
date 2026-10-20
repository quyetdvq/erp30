<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Groups\Infrastructure\Persistence;

use App\Infrastructure\Persistence\FindsRepositoryRow;
use App\Modules\Authorization\Administration\Groups\Application\Ports\GroupRepositoryPort;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence primitives for Authorization Groups and group permissions. */
final class SqlAuthorizationGroupRepository implements GroupRepositoryPort
{
    use FindsRepositoryRow;

    public const ERROR_PERSISTENCE_FAILED = 'AUTH_GROUP_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'AUTH_GROUP_CONNECTION_INVALID';
    public const ERROR_FIELD_UNSUPPORTED = 'AUTH_GROUP_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'AUTH_GROUP_FIELD_REQUIRED';
    public const ERROR_UPDATE_EMPTY = 'AUTH_GROUP_UPDATE_EMPTY';
    public const ERROR_MUTATION_FAILED = 'AUTH_GROUP_MUTATION_FAILED';

    /** @var list<string> */
    private const GROUP_FIELDS = [
        'code', 'name', 'description', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at',
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
        return $this->findRow('find', fn () => $this->db->table('groups')->where('id', $id)->get());
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }
        try {
            $builder = $this->db->table('groups')->where('id', $id);
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

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findRow('findByCode', fn () => $this->db->table('groups')->where('code', $code)->get());
    }

    /** @param array<string, scalar|null> $filters
     *  @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        try {
            $builder = $this->db->table('groups')->orderBy('id', 'ASC');
            foreach (['code', 'status'] as $field) {
                if (array_key_exists($field, $filters)) {
                    $builder->where($field, $filters[$field]);
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

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, ['code', 'name', 'status']), $this->createdAudit($operation));
        try {
            if (! $this->db->table('groups')->insert($payload)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }
        $id = (int) $this->db->insertID();
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        if ($this->find($id) === null) {
            return false;
        }
        try {
            $updated = $this->db->table('groups')->where('id', $id)->update(array_merge($payload, $this->updatedAudit($operation)));
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'update');
        }

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function findPermissions(int $groupId): array
    {
        try {
            $result = $this->db->table('group_permissions')->where('group_id', $groupId)->orderBy('resource_action_id', 'ASC')->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPermissions', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findPermissions');
        }

        return array_values($result->getResultArray());
    }

    /** @return array<string, mixed>|null */
    public function findResourceAction(int $resourceActionId): ?array
    {
        try {
            $result = $this->db->table('resource_actions ra')
                ->select('ra.id, ra.resource_id, ra.code, r.type resource_type')
                ->join('resources r', 'r.id = ra.resource_id')
                ->where('ra.id', $resourceActionId)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findResourceAction', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findResourceAction');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    public function userExists(int $userId): bool
    {
        try {
            $result = $this->db->table((string) config('Auth')->tables['users'])->select('id')->where('id', $userId)->where('deleted_at', null)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'userExists', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'userExists');
        }

        return $result->getRowArray() !== null;
    }

    /** @param list<int> $userIds */
    public function replaceMembers(string $groupCode, array $userIds, ?OperationContext $operation = null): bool
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'replaceMembers']);
        }
        $table = (string) config('Auth')->tables['groups_users'];
        $this->db->transBegin();
        try {
            if (! $this->db->table($table)->where('group', strtolower($groupCode))->delete()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceMembers.delete');
            }
            foreach ($userIds as $userId) {
                if (! $this->db->table($table)->insert([
                    'user_id' => $userId,
                    'group' => strtolower($groupCode),
                    'created_at' => ($operation ?? MutationContext::system(service('clock'))->begin())->evaluationTime->utc()->format('Y-m-d H:i:s'),
                ])) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replaceMembers.insert');
                }
            }
            if (! $this->db->transStatus()) {
                throw new DomainException('AUTH_GROUP_MUTATION_FAILED');
            }
            $this->db->transCommit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new PersistenceException(self::ERROR_MUTATION_FAILED, 'replaceMembers', $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    public function findMembers(string $groupCode): array
    {
        $users = (string) config('Auth')->tables['users'];
        try {
            $result = $this->db->table((string) config('Auth')->tables['groups_users'] . ' gu')
                ->select('u.id, u.username, u.active, u.status')
                ->join($users . ' u', 'u.id = gu.user_id')
                ->where('gu.group', strtolower($groupCode))
                ->where('u.deleted_at', null)
                ->orderBy('u.id', 'ASC')->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findMembers', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findMembers');
        }

        return array_values($result->getResultArray());
    }

    /** @param list<int> $resourceActionIds */
    public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'replacePermissions']);
        }
        $ids = array_values(array_unique($resourceActionIds));

        $this->db->transBegin();
        try {
            if ($this->findForUpdate($groupId) === null) {
                $this->db->transRollback();

                return false;
            }
            if (! $this->db->table('group_permissions')->where('group_id', $groupId)->delete()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replacePermissions.delete');
            }
            foreach ($ids as $resourceActionId) {
                $row = [
                    'group_id' => $groupId,
                    'resource_action_id' => $resourceActionId,
                    ...$this->createdAudit($operation),
                ];
                if (! $this->db->table('group_permissions')->insert($row)) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'replacePermissions.insert');
                }
            }
            if (! $this->db->transStatus()) {
                throw new PersistenceException(self::ERROR_MUTATION_FAILED, 'replacePermissions');
            }
            $this->db->transCommit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new PersistenceException(self::ERROR_MUTATION_FAILED, 'replacePermissions', $exception);
        }
    }

    /** @param array<string, mixed> $data
     *  @param list<string> $required
     *  @return array<string, mixed>
     */
    private function payload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::GROUP_FIELDS, true)) {
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

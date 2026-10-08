<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class ResourceCatalogRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'RESOURCE_CATALOG_PERSISTENCE_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function findResourceByType(string $type): ?array
    {
        try {
            $result = $this->db->table('resources')->where('type', $type)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findResourceByType', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findResourceByType');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    public function resourceActionBelongsToResource(int $resourceActionId, int $resourceId): bool
    {
        try {
            $result = $this->db->table('resource_actions')
                ->select('id')
                ->where('id', $resourceActionId)
                ->where('resource_id', $resourceId)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resourceActionBelongsToResource', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'resourceActionBelongsToResource');
        }

        return $result->getRowArray() !== null;
    }

    /** @return array<string, mixed>|null */
    public function findResource(int $id): ?array
    {
        return $this->findRow('findResource', fn () => $this->db->table('resources')->where('id', $id)->get());
    }

    /** @return list<array<string, mixed>> */
    public function findResources(): array
    {
        return $this->findMany('findResources', fn () => $this->db->table('resources')->orderBy('id', 'ASC')->get());
    }

    /** @return list<array<string, mixed>> */
    public function findResourceActions(int $resourceId): array
    {
        return $this->findMany('findResourceActions', fn () => $this->db->table('resource_actions')->where('resource_id', $resourceId)->orderBy('id', 'ASC')->get());
    }

    /** @param list<int> $resourceIds
     *  @return list<array<string, mixed>>
     */
    public function findResourceActionsForResources(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return $this->findMany(
            'findResourceActionsForResources',
            fn () => $this->db->table('resource_actions')
                ->whereIn('resource_id', $resourceIds)
                ->orderBy('resource_id', 'ASC')
                ->orderBy('code', 'ASC')
                ->orderBy('id', 'ASC')
                ->get(),
        );
    }

    /** @return array<string, mixed>|null */
    public function findResourceAction(int $resourceId, int $actionId): ?array
    {
        return $this->findRow('findResourceAction', fn () => $this->db->table('resource_actions')->where('id', $actionId)->where('resource_id', $resourceId)->get());
    }

    /** @return array<string, mixed>|null */
    public function findResourceActionById(int $actionId): ?array
    {
        return $this->findRow('findResourceActionById', fn () => $this->db->table('resource_actions')->where('id', $actionId)->get());
    }

    /** @param array<string, mixed> $data */
    public function insertResource(array $data): int
    {
        try {
            if (! $this->db->table('resources')->insert($data)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResource');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException('RESOURCE_CATALOG_CONNECTION_INVALID');
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResource', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResource');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function updateResource(int $id, array $data): bool
    {
        try {
            $updated = $this->db->table('resources')->where('id', $id)->update($data);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateResource', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateResource');
        }

        return true;
    }

    public function deleteResource(int $id): bool
    {
        try {
            $deleted = $this->db->table('resources')->where('id', $id)->delete();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteResource', $exception);
        }
        if ($deleted === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteResource');
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function insertResourceAction(array $data): int
    {
        try {
            if (! $this->db->table('resource_actions')->insert($data)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResourceAction');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException('RESOURCE_CATALOG_CONNECTION_INVALID');
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResourceAction', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insertResourceAction');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function updateResourceAction(int $actionId, array $data): bool
    {
        try {
            $updated = $this->db->table('resource_actions')->where('id', $actionId)->update($data);
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateResourceAction', $exception);
        }
        if ($updated === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'updateResourceAction');
        }

        return true;
    }

    public function deleteResourceAction(int $actionId): bool
    {
        try {
            $deleted = $this->db->table('resource_actions')->where('id', $actionId)->delete();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteResourceAction', $exception);
        }
        if ($deleted === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'deleteResourceAction');
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

    /** @param callable(): mixed $query
     *  @return list<array<string, mixed>>
     */
    private function findMany(string $operation, callable $query): array
    {
        try {
            $result = $query();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return array_values($result->getResultArray());
    }
}

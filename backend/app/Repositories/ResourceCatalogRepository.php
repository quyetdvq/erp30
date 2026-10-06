<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\PersistenceException;
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
}

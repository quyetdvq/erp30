<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Bootstrap\FoundationFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Foundation\Application\Ports\LocationRepositoryInterface;
use App\Modules\Foundation\Contracts\LocationErrors;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility repository. SQL and persistence behavior have one owner in Foundation. */
final class FoundationLocationRepository implements LocationRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = LocationErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = LocationErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = LocationErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = LocationErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = LocationErrors::ERROR_FIELD_REQUIRED;

    private readonly SqlLocationRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = FoundationFactory::repository(db: $db);
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, int $id): ?array
    {
        return $this->delegate->find($table, $id);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $table, string $code): ?array
    {
        return $this->delegate->findByCode($table, $code);
    }

    /** @return list<array<string, mixed>> */
    public function findAll(string $table): array
    {
        return $this->delegate->findAll($table);
    }

    public function provinceExists(int $id): bool
    {
        return $this->delegate->provinceExists($id);
    }

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool
    {
        return $this->delegate->wardBelongsToProvince($wardId, $provinceId);
    }

    /**
     * A parent change must not invalidate already persisted address pairs.
     * Both tables have an index on ward_id; EXISTS-style queries stop at one
     * conflict instead of materializing all Organization/Contact records.
     */
    public function wardHasConflictingAddress(int $wardId, int $newProvinceId): bool
    {
        return $this->delegate->wardHasConflictingAddress($wardId, $newProvinceId);
    }

    /** @return array<string,mixed>|null */
    public function lockWardForUpdate(int $id): ?array
    {
        return $this->delegate->lockWardForUpdate($id);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     */
    public function insert(string $table, array $data, array $required, OperationContext $operation): int
    {
        return $this->delegate->insert($table, $data, $required, $operation);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $table, int $id, array $data, OperationContext $operation): bool
    {
        return $this->delegate->update($table, $id, $data, $operation);
    }
}

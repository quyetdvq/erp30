<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Configuration\Application\Ports\NumberingSeriesRepositoryInterface;
use App\Modules\Numbering\Configuration\Infrastructure\Persistence\SqlNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for Auto Numbering series persistence. */
final class AutoNumberingSeriesRepository implements NumberingSeriesRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = SqlNumberingSeriesRepository::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = SqlNumberingSeriesRepository::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = SqlNumberingSeriesRepository::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = SqlNumberingSeriesRepository::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = SqlNumberingSeriesRepository::ERROR_FIELD_REQUIRED;
    public const ERROR_INVALID_ID = SqlNumberingSeriesRepository::ERROR_INVALID_ID;
    public const ERROR_DUPLICATE = SqlNumberingSeriesRepository::ERROR_DUPLICATE;
    public const ERROR_MUTATION_FAILED = SqlNumberingSeriesRepository::ERROR_MUTATION_FAILED;
    public const ERROR_RESOURCE_TYPE_AMBIGUOUS = SqlNumberingSeriesRepository::ERROR_RESOURCE_TYPE_AMBIGUOUS;

    private readonly SqlNumberingSeriesRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlNumberingSeriesRepository($db);
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->delegate->find($id);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        return $this->delegate->findForUpdate($id);
    }

    /** @return array<string, mixed>|null */
    public function findByResourceAndSeriesCode(mixed $resourceId, string $seriesCode): ?array
    {
        return $this->delegate->findByResourceAndSeriesCode($resourceId, $seriesCode);
    }

    /** @return list<array<string, mixed>> */
    public function findByResource(mixed $resourceId): array
    {
        return $this->delegate->findByResource($resourceId);
    }

    /** @return list<array<string, mixed>> */
    public function findActiveByResource(mixed $resourceId): array
    {
        return $this->delegate->findActiveByResource($resourceId);
    }

    /** @return list<array<string, mixed>> */
    public function findActiveDefaultsByResource(mixed $resourceId): array
    {
        return $this->delegate->findActiveDefaultsByResource($resourceId);
    }

    /** @return array<string, mixed>|null */
    public function findActiveDefaultByResource(mixed $resourceId): ?array
    {
        return $this->delegate->findActiveDefaultByResource($resourceId);
    }

    /** @return array<string, mixed>|null */
    public function findResource(mixed $resourceId): ?array
    {
        return $this->delegate->findResource($resourceId);
    }

    /** @return array<string, mixed>|null */
    public function findResourceByType(string $resourceType): ?array
    {
        return $this->delegate->findResourceByType($resourceType);
    }

    /** @return array<string, mixed>|null */
    public function lockResource(mixed $resourceId): ?array
    {
        return $this->delegate->lockResource($resourceId);
    }

    public function counterExists(mixed $seriesId): bool
    {
        return $this->delegate->counterExists($seriesId);
    }

    public function issued(mixed $seriesId): bool
    {
        return $this->delegate->issued($seriesId);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->delegate->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->delegate->updateSeries($id, $data, $operation);
    }

    public function delete(mixed $id): bool
    {
        return $this->delegate->delete($id);
    }

    /** @param callable(): mixed $callback */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed
    {
        return $this->delegate->transactional($callback, $operation);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Configuration\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

interface NumberingSeriesRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array;

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByResourceAndSeriesCode(mixed $resourceId, string $seriesCode): ?array;

    /** @return list<array<string, mixed>> */
    public function findByResource(mixed $resourceId): array;

    /** @return list<array<string, mixed>> */
    public function findActiveByResource(mixed $resourceId): array;

    /** @return list<array<string, mixed>> */
    public function findActiveDefaultsByResource(mixed $resourceId): array;

    /** @return array<string, mixed>|null */
    public function findActiveDefaultByResource(mixed $resourceId): ?array;

    /** @return array<string, mixed>|null */
    public function findResource(mixed $resourceId): ?array;

    /** @return array<string, mixed>|null */
    public function findResourceByType(string $resourceType): ?array;

    /** @return array<string, mixed>|null */
    public function lockResource(mixed $resourceId): ?array;

    public function counterExists(mixed $seriesId): bool;

    public function issued(mixed $seriesId): bool;

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int;

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int;

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool;

    /** @param array<string, mixed> $data */
    public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool;

    public function delete(mixed $id): bool;

    /** @param callable(): mixed $callback */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed;
}

<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/** Province/Ward persistence operations; deliberately not a generic ERP repository. */
interface LocationRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(string $table, int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByCode(string $table, string $code): ?array;

    /** @return list<array<string, mixed>> */
    public function findAll(string $table): array;

    public function provinceExists(int $id): bool;

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool;

    public function wardHasConflictingAddress(int $wardId, int $newProvinceId): bool;

    /** @return array<string, mixed>|null */
    public function lockWardForUpdate(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     */
    public function insert(string $table, array $data, array $required, OperationContext $operation): int;

    /** @param array<string, mixed> $data */
    public function update(string $table, int $id, array $data, OperationContext $operation): bool;
}

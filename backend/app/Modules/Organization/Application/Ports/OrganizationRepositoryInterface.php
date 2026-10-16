<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Ports;

/** Persistence contract for the existing organization use cases. */
interface OrganizationRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array;

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array;

    public function provinceExists(int $provinceId): bool;

    public function wardExists(int $wardId): bool;

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool;

    /** @param array<string, mixed> $data */
    public function insert(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool;
}

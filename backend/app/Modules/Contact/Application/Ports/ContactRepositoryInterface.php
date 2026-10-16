<?php

declare(strict_types=1);

namespace App\Modules\Contact\Application\Ports;

interface ContactRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function insert(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool;

    /** @return list<array<string, mixed>> */
    public function findByOwner(string $contactType, int $contactRefId, ?string $status = null): array;

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array;

    public function ownerExists(string $table, int $ownerId): bool;

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool;
}

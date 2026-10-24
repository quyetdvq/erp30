<?php

declare(strict_types=1);

namespace App\Modules\EmployeeStructure\Employee\Application\Ports;

interface EmployeeRepositoryInterface
{
    /** @return array<string, mixed>|null */ public function find(int $id): ?array;
    /** @return array<string, mixed>|null */ public function findByCode(string $code): ?array;
    /** @return array<string, mixed>|null */ public function findByUserId(int $userId): ?array;
    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array;
    /** @param array<string, mixed> $data */ public function insert(array $data): int;
    /** @param array<string, mixed> $data */ public function update(int $id, array $data): bool;
}

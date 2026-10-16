<?php

declare(strict_types=1);

namespace App\Modules\EmployeeStructure\Department\Application\Ports;

interface DepartmentRepositoryInterface
{
    /** @return array<string, mixed>|null */ public function find(int $id): ?array;
    /** @return array<string, mixed>|null */ public function findByCode(string $code): ?array;
    /** @return list<array<string, mixed>> */ public function findAll(): array;
    /** @param array<string, mixed> $data */ public function insert(array $data): int;
    /** @param array<string, mixed> $data */ public function update(int $id, array $data): bool;
}

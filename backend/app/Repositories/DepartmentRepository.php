<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface;
use App\Modules\EmployeeStructure\Department\Contracts\DepartmentErrors;
use App\Modules\EmployeeStructure\Department\Infrastructure\Persistence\SqlDepartmentRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint; existing callers also satisfy the Department module port. */
final class DepartmentRepository implements DepartmentRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = DepartmentErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = DepartmentErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = DepartmentErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = DepartmentErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = DepartmentErrors::ERROR_FIELD_REQUIRED;

    private readonly SqlDepartmentRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null) { $this->delegate = new SqlDepartmentRepository(db: $db); }
    /** @return array<string, mixed>|null */ public function find(int $id): ?array { return $this->delegate->find($id); }
    /** @return array<string, mixed>|null */ public function findByCode(string $code): ?array { return $this->delegate->findByCode($code); }
    /** @return list<array<string, mixed>> */ public function findAll(): array { return $this->delegate->findAll(); }
    /** @param array<string, mixed> $data */ public function insert(array $data): int { return $this->delegate->insert($data); }
    /** @param array<string, mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
}

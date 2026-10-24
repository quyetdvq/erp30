<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface;
use App\Modules\EmployeeStructure\Employee\Contracts\EmployeeErrors;
use App\Modules\EmployeeStructure\Employee\Infrastructure\Persistence\SqlEmployeeRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint; existing callers also satisfy the Employee module port. */
final class EmployeeRepository implements EmployeeRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = EmployeeErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = EmployeeErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = EmployeeErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = EmployeeErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = EmployeeErrors::ERROR_FIELD_REQUIRED;
    private readonly SqlEmployeeRepository $delegate;
    /** @param ConnectionInterface<mixed, mixed>|null $db */ public function __construct(?ConnectionInterface $db = null) { $this->delegate = new SqlEmployeeRepository(db: $db); }
    /** @return array<string, mixed>|null */ public function find(int $id): ?array { return $this->delegate->find($id); }
    /** @return array<string, mixed>|null */ public function findByCode(string $code): ?array { return $this->delegate->findByCode($code); }
    /** @return array<string, mixed>|null */ public function findByUserId(int $userId): ?array { return $this->delegate->findByUserId($userId); }
    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array { return $this->delegate->findAll($filters); }
    /** @param array<string,mixed> $data */ public function insert(array $data): int { return $this->delegate->insert($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
}

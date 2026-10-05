<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Repositories\EmployeeRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

final class EmployeeService
{
    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?EmployeeRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $managerId = $this->managerId($data, false);
        if ($managerId === null) {
            return $this->repo()->insert($data);
        }

        return $this->withManagerLock(function () use ($data, $managerId): int {
            $this->assertManagerCanBeAssigned(null, $managerId);

            return $this->repo()->insert($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Employee does not exist.');
        }

        $managerChange = array_key_exists('manager_employee_id', $data);
        $statusChangeToInactive = ($data['status'] ?? null) === 'INACTIVE';
        if (! $managerChange && ! $statusChangeToInactive) {
            return $this->repo()->update($id, $data);
        }

        return $this->withManagerLock(function () use ($id, $data): bool {
            if (array_key_exists('manager_employee_id', $data)) {
                $managerId = $this->managerId($data, true);
                if ($managerId !== null) {
                    $this->assertManagerCanBeAssigned($id, $managerId);
                }
            }

            return $this->repo()->update($id, $data);
        });
    }

    public function setManager(int $employeeId, ?int $managerEmployeeId): bool
    {
        return $this->update($employeeId, ['manager_employee_id' => $managerEmployeeId]);
    }

    public function inactivate(int $employeeId): bool
    {
        return $this->update($employeeId, [
            'status' => 'INACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function repo(): EmployeeRepository
    {
        return $this->repository ?? new EmployeeRepository($this->db);
    }

    /** @param array<string, mixed> $data */
    private function managerId(array $data, bool $fieldRequired): ?int
    {
        if (! array_key_exists('manager_employee_id', $data)) {
            if ($fieldRequired) {
                throw new InvalidArgumentException('Manager employee id is required.');
            }

            return null;
        }

        $value = $data['manager_employee_id'];
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            $managerId = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $managerId = (int) $value;
        } else {
            throw new InvalidArgumentException('Manager employee id must be an integer or null.');
        }
        if ($managerId < 1) {
            throw new InvalidArgumentException('Manager employee id must be positive.');
        }

        return $managerId;
    }

    private function assertManagerCanBeAssigned(?int $employeeId, int $managerId): void
    {
        if ($employeeId !== null && $employeeId === $managerId) {
            throw new InvalidArgumentException('Employee cannot manage itself.');
        }

        $manager = $this->repo()->find($managerId);
        if ($manager === null) {
            throw new InvalidArgumentException('Manager employee does not exist.');
        }
        if (($manager['status'] ?? null) !== 'ACTIVE') {
            throw new InvalidArgumentException('Inactive employee cannot be selected as manager.');
        }

        $visited = [];
        $cursor = $manager;
        while (true) {
            $cursorId = (int) ($cursor['id'] ?? 0);
            if ($cursorId < 1 || isset($visited[$cursorId])) {
                throw new InvalidArgumentException('Management cycle detected.');
            }
            $visited[$cursorId] = true;
            if ($employeeId !== null && $cursorId === $employeeId) {
                throw new InvalidArgumentException('Management cycle detected.');
            }

            $parentId = $cursor['manager_employee_id'] ?? null;
            if ($parentId === null) {
                break;
            }
            $cursor = $this->repo()->find((int) $parentId);
            if ($cursor === null) {
                throw new InvalidArgumentException('Manager chain contains a missing employee.');
            }
        }
    }

    /** @param callable(): mixed $callback */
    private function withManagerLock(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('Employee manager mutations require a writable database connection.');
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        return $lock->synchronized('employee-manager-tree', function () use ($callback, $db): mixed {
            $db->transBegin();
            try {
                $result = $callback();
                if (! $db->transStatus()) {
                    throw new RuntimeException('Employee manager transaction failed.');
                }
                $db->transCommit();

                return $result;
            } catch (\Throwable $exception) {
                $db->transRollback();
                throw $exception;
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\EmployeeRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class EmployeeService
{
    public const ERROR_MANAGER_SELF = 'EMP_MANAGER_SELF';
    public const ERROR_MANAGER_CYCLE = 'EMP_MANAGER_CYCLE';
    public const ERROR_MANAGER_INACTIVE = 'EMP_MANAGER_INACTIVE';
    public const ERROR_MANAGER_REFERENCE_INVALID = 'EMP_MANAGER_REFERENCE_INVALID';
    public const ERROR_MANAGER_MUTATION_FAILED = 'EMP_MANAGER_MUTATION_FAILED';
    public const ERROR_CODE_DUPLICATE = 'EMPLOYEE_CODE_DUPLICATE';
    public const ERROR_USER_DUPLICATE = 'EMPLOYEE_USER_DUPLICATE';
    public const ERROR_STATUS_INVALID = 'EMPLOYEE_STATUS_INVALID';
    public const ERROR_GENDER_INVALID = 'EMPLOYEE_GENDER_INVALID';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?EmployeeRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $operation = $this->operation();
        $payload = array_merge($this->withoutAuditFields($data), $operation->createdAudit());
        $payload = $this->prepare($payload, true);
        $this->assertUnique($payload);
        $managerId = $this->managerId($payload, false);
        if ($managerId === null) {
            return $this->repo()->insert($payload);
        }

        return $this->withManagerLock(function () use ($payload, $managerId): int {
            $this->assertManagerCanBeAssigned(null, $managerId);

            return $this->repo()->insert($payload);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $operation = $this->operation();
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new DomainException(self::ERROR_MANAGER_REFERENCE_INVALID, ['employee_id' => $id]);
        }

        $payload = array_merge($this->withoutAuditFields($data), $operation->updatedAudit());
        $payload = $this->prepare($payload, false);
        $this->assertUnique($payload, $id);
        $managerChange = array_key_exists('manager_employee_id', $payload);
        $statusChangeToInactive = ($payload['status'] ?? null) === 'INACTIVE';
        if (! $managerChange && ! $statusChangeToInactive) {
            return $this->repo()->update($id, $payload);
        }

        return $this->withManagerLock(function () use ($id, $payload): bool {
            if (array_key_exists('manager_employee_id', $payload)) {
                $managerId = $this->managerId($payload, true);
                if ($managerId !== null) {
                    $this->assertManagerCanBeAssigned($id, $managerId);
                }
            }

            return $this->repo()->update($id, $payload);
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
        ]);
    }

    private function repo(): EmployeeRepository
    {
        return $this->repository ?? new EmployeeRepository($this->db);
    }

    private function operation(): OperationContext
    {
        $context = $this->mutationContext ?? MutationContext::system($this->clock ?? service('clock'));

        return $context->begin();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withoutAuditFields(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepare(array $data, bool $creating): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, [
                'user_id', 'employee_code', 'employee_name', 'gender', 'date_of_birth',
                'place_of_origin', 'hire_date', 'manager_employee_id', 'description', 'status',
                'created_by', 'updated_by', 'created_at', 'updated_at',
            ], true)) {
                throw new DomainException(EmployeeRepository::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if (array_key_exists('employee_code', $data)) {
            if (! is_string($data['employee_code']) || trim($data['employee_code']) === '') {
                throw new DomainException(EmployeeRepository::ERROR_FIELD_REQUIRED, ['field' => 'employee_code']);
            }
            $data['employee_code'] = trim($data['employee_code']);
        }
        if (array_key_exists('employee_name', $data)) {
            if (! is_string($data['employee_name']) || trim($data['employee_name']) === '') {
                throw new DomainException(EmployeeRepository::ERROR_FIELD_REQUIRED, ['field' => 'employee_name']);
            }
            $data['employee_name'] = trim($data['employee_name']);
        }
        if (array_key_exists('gender', $data)
            && $data['gender'] !== null
            && (! is_string($data['gender']) || ! in_array($data['gender'], ['MALE', 'FEMALE', 'OTHER'], true))) {
            throw new DomainException(self::ERROR_GENDER_INVALID, ['field' => 'gender']);
        }
        if (array_key_exists('status', $data)) {
            if (! is_string($data['status'])) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => '']);
            }
            $data['status'] = strtoupper(trim($data['status']));
            if (! in_array($data['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => $data['status']]);
            }
        } elseif ($creating) {
            $data['status'] = 'ACTIVE';
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertUnique(array $data, ?int $excludeId = null): void
    {
        if (array_key_exists('employee_code', $data)) {
            $row = $this->repo()->findByCode((string) $data['employee_code']);
            if ($row !== null && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
                throw new DomainException(self::ERROR_CODE_DUPLICATE, ['field' => 'employee_code']);
            }
        }
        if (array_key_exists('user_id', $data) && $data['user_id'] !== null) {
            $userId = PositiveId::normalize($data['user_id'], 'user_id', self::ERROR_USER_DUPLICATE);
            $row = $this->repo()->findByUserId($userId);
            if ($row !== null && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
                throw new DomainException(self::ERROR_USER_DUPLICATE, ['field' => 'user_id']);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function managerId(array $data, bool $fieldRequired): ?int
    {
        if (! array_key_exists('manager_employee_id', $data)) {
            if ($fieldRequired) {
                throw new DomainException(self::ERROR_MANAGER_REFERENCE_INVALID, ['field' => 'manager_employee_id']);
            }

            return null;
        }

        $value = $data['manager_employee_id'];
        if ($value === null) {
            return null;
        }
        return PositiveId::normalize($value, 'manager_employee_id', self::ERROR_MANAGER_REFERENCE_INVALID);
    }

    private function assertManagerCanBeAssigned(?int $employeeId, int $managerId): void
    {
        if ($employeeId !== null && $employeeId === $managerId) {
            throw new DomainException(self::ERROR_MANAGER_SELF, [
                'employee_id' => $employeeId,
                'manager_employee_id' => $managerId,
            ]);
        }

        $manager = $this->repo()->find($managerId);
        if ($manager === null) {
            throw new DomainException(self::ERROR_MANAGER_REFERENCE_INVALID, ['manager_employee_id' => $managerId]);
        }
        if (($manager['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_MANAGER_INACTIVE, ['manager_employee_id' => $managerId]);
        }

        $visited = [];
        $cursor = $manager;
        while (true) {
            $cursorId = (int) ($cursor['id'] ?? 0);
            if ($cursorId < 1 || isset($visited[$cursorId])) {
                throw new DomainException(self::ERROR_MANAGER_CYCLE, ['manager_employee_id' => $managerId]);
            }
            $visited[$cursorId] = true;
            if ($employeeId !== null && $cursorId === $employeeId) {
                throw new DomainException(self::ERROR_MANAGER_CYCLE, [
                    'employee_id' => $employeeId,
                    'manager_employee_id' => $managerId,
                ]);
            }

            $parentId = $cursor['manager_employee_id'] ?? null;
            if ($parentId === null) {
                break;
            }
            $cursor = $this->repo()->find((int) $parentId);
            if ($cursor === null) {
                throw new DomainException(self::ERROR_MANAGER_REFERENCE_INVALID, ['manager_employee_id' => $parentId]);
            }
        }
    }

    /** @param callable(): mixed $callback */
    private function withManagerLock(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_MANAGER_MUTATION_FAILED, ['operation' => 'manager_mutation']);
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        try {
            return $lock->synchronized('employee-manager-tree', function () use ($callback, $db): mixed {
                $db->transBegin();
                try {
                    $result = $callback();
                    if (! $db->transStatus()) {
                        throw new DomainException(self::ERROR_MANAGER_MUTATION_FAILED, ['operation' => 'manager_mutation']);
                    }
                    $db->transCommit();

                    return $result;
                } catch (\Throwable $exception) {
                    $db->transRollback();
                    throw $exception;
                }
            });
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(
                self::ERROR_MANAGER_MUTATION_FAILED,
                ['operation' => 'manager_mutation'],
                $exception,
            );
        }
    }
}

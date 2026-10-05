<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeImmutable;
use DateTimeZone;

final class EmployeeAssignmentService
{
    public const ERROR_REFERENCE_INVALID = 'EMP_ASSIGNMENT_REFERENCE_INVALID';
    public const ERROR_REFERENCE_INACTIVE = 'EMP_ASSIGNMENT_REFERENCE_INACTIVE';
    public const ERROR_DATE_INVALID = 'EMP_ASSIGNMENT_DATE_INVALID';
    public const ERROR_PRIMARY_OVERLAP = 'EMP_PRIMARY_ASSIGNMENT_OVERLAP';
    public const ERROR_ASSIGNMENT_NOT_FOUND = 'EMP_ASSIGNMENT_NOT_FOUND';
    public const ERROR_EMPLOYEE_IMMUTABLE = 'EMP_ASSIGNMENT_EMPLOYEE_IMMUTABLE';
    public const ERROR_EVALUATION_ORGANIZATION_INVALID = 'EMP_ASSIGNMENT_EVALUATION_ORGANIZATION_INVALID';
    public const ERROR_MUTATION_FAILED = 'EMP_ASSIGNMENT_MUTATION_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?EmployeeAssignmentRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
        private readonly ?EmployeeRepository $employeeRepository = null,
        private readonly ?OrganizationRepository $organizationRepository = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $operation = $this->operation();
        $this->assertSupportedFields($data);
        $payload = $this->prepare($data, true, $operation);
        $employeeId = (int) $payload['employee_id'];

        return $this->withEmployeeLock($employeeId, function () use ($payload): int {
            $this->assertReferences($payload);
            $this->assertPrimaryDoesNotOverlap($payload);

            return $this->repo()->insert($payload);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $operation = $this->operation();
        $assignmentId = PositiveId::normalize($id, 'assignment_id', self::ERROR_REFERENCE_INVALID);
        $payload = $this->withoutAuditFields($data);
        $this->assertSupportedFields($payload);

        return $this->withAssignmentLock($assignmentId, function () use ($assignmentId, $payload, $operation): bool {
            $current = $this->repo()->find($assignmentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_ASSIGNMENT_NOT_FOUND, ['assignment_id' => $assignmentId]);
            }
            $currentEmployeeId = PositiveId::normalize(
                $current['employee_id'] ?? null,
                'employee_id',
                self::ERROR_REFERENCE_INVALID,
            );
            $candidate = $this->prepare(array_merge($current, $payload), false, $operation);
            if ((int) $candidate['employee_id'] !== $currentEmployeeId) {
                throw new DomainException(self::ERROR_EMPLOYEE_IMMUTABLE, ['field' => 'employee_id']);
            }
            $this->assertReferences($candidate);
            $this->assertPrimaryDoesNotOverlap($candidate, $assignmentId);

            $changes = $this->withoutAuditFields($payload);
            foreach (['employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary', 'effective_from', 'effective_to', 'status'] as $field) {
                if (array_key_exists($field, $changes)) {
                    $changes[$field] = $candidate[$field];
                }
            }
            if (array_key_exists('description', $changes)) {
                $changes['description'] = $candidate['description'];
            }
            $changes = array_merge($changes, $operation->updatedAudit());

            return $this->repo()->update($assignmentId, $changes);
        });
    }

    public function end(int $id, ?string $effectiveTo = null): bool
    {
        $assignmentId = PositiveId::normalize($id, 'assignment_id', self::ERROR_REFERENCE_INVALID);
        $operation = $this->operation();

        return $this->withAssignmentLock($assignmentId, function () use ($assignmentId, $effectiveTo, $operation): bool {
            $current = $this->repo()->find($assignmentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_ASSIGNMENT_NOT_FOUND, ['assignment_id' => $assignmentId]);
            }
            $employeeId = PositiveId::normalize($current['employee_id'] ?? null, 'employee_id', self::ERROR_REFERENCE_INVALID);
            $candidate = array_merge($current, ['status' => 'INACTIVE']);
            if ($effectiveTo !== null) {
                $candidate['effective_to'] = $effectiveTo;
            }
            $candidate = $this->prepare($candidate, false, $operation);
            $this->assertReferences($candidate);
            $this->assertPrimaryDoesNotOverlap($candidate, $assignmentId);

            $changes = [
                'status' => 'INACTIVE',
                'updated_by' => $operation->updatedAudit()['updated_by'],
                'updated_at' => $operation->updatedAudit()['updated_at'],
            ];
            if ($effectiveTo !== null) {
                $changes['effective_to'] = $candidate['effective_to'];
            }

            return $this->repo()->update($assignmentId, $changes);
        });
    }

    /** @return list<array<string, mixed>> */
    public function findEffective(int $employeeId, ?EvaluationTime $evaluationTime = null): array
    {
        $normalizedEmployeeId = PositiveId::normalize($employeeId, 'employee_id', self::ERROR_REFERENCE_INVALID);
        $operation = $this->operation($evaluationTime);

        return $this->repo()->findEffective(
            $normalizedEmployeeId,
            $operation->evaluationTime->businessDate(new DateTimeZone('UTC')),
        );
    }

    /** @return array<string, mixed>|null */
    public function resolvePrimary(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array
    {
        $normalizedEmployeeId = PositiveId::normalize($employeeId, 'employee_id', self::ERROR_REFERENCE_INVALID);
        $operation = $this->operation($evaluationTime);
        $rows = $this->repo()->findEffective(
            $normalizedEmployeeId,
            $operation->evaluationTime->businessDate(new DateTimeZone('UTC')),
            true,
        );
        if (count($rows) > 1) {
            throw new DomainException(self::ERROR_PRIMARY_OVERLAP, ['employee_id' => $normalizedEmployeeId]);
        }

        return $rows[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function resolveEligible(
        int $employeeId,
        int $evaluationOrganizationId,
        ?EvaluationTime $evaluationTime = null,
    ): array {
        $normalizedEmployeeId = PositiveId::normalize($employeeId, 'employee_id', self::ERROR_REFERENCE_INVALID);
        $normalizedOrganizationId = PositiveId::normalize(
            $evaluationOrganizationId,
            'evaluation_organization_id',
            self::ERROR_EVALUATION_ORGANIZATION_INVALID,
        );
        $path = $this->organizationPath($normalizedOrganizationId);
        $eligible = [];
        foreach ($this->findEffective($normalizedEmployeeId, $evaluationTime) as $assignment) {
            $assignmentOrganizationId = PositiveId::normalize(
                $assignment['organization_id'] ?? null,
                'organization_id',
                self::ERROR_REFERENCE_INVALID,
            );
            if (in_array($assignmentOrganizationId, $path['ids'], true)) {
                $eligible[] = $assignment;
            }
        }

        return $eligible;
    }

    /** @return array<string, mixed>|null */
    public function resolveDefaultActorContext(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array
    {
        $assignment = $this->resolvePrimary($employeeId, $evaluationTime);
        if ($assignment === null) {
            return null;
        }
        $organizationId = PositiveId::normalize($assignment['organization_id'] ?? null, 'organization_id', self::ERROR_REFERENCE_INVALID);
        $path = $this->organizationPath($organizationId);
        $company = null;
        foreach (array_reverse($path['rows']) as $organization) {
            if (($organization['type'] ?? null) === 'COMPANY') {
                $company = $organization;
                break;
            }
        }

        return [
            'assignment' => $assignment,
            'organization' => $path['rows'][count($path['rows']) - 1],
            'company' => $company,
            'organization_path' => $path['rows'],
        ];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function prepare(array $data, bool $creating, OperationContext $operation): array
    {
        $payload = $this->withoutAuditFields($data);
        if ($creating && ! array_key_exists('employee_id', $payload)) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'employee_id']);
        }
        if ($creating && ! array_key_exists('organization_id', $payload)) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'organization_id']);
        }
        if ($creating && ! array_key_exists('effective_from', $payload)) {
            throw new DomainException(self::ERROR_DATE_INVALID, ['field' => 'effective_from']);
        }
        if (array_key_exists('employee_id', $payload)) {
            $payload['employee_id'] = PositiveId::normalize($payload['employee_id'], 'employee_id', self::ERROR_REFERENCE_INVALID);
        }
        if (array_key_exists('organization_id', $payload)) {
            $payload['organization_id'] = PositiveId::normalize($payload['organization_id'], 'organization_id', self::ERROR_REFERENCE_INVALID);
        }
        foreach (['department_id', 'position_id'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $payload[$field] = PositiveId::normalize($payload[$field], $field, self::ERROR_REFERENCE_INVALID);
            }
        }
        if (array_key_exists('is_primary', $payload)) {
            $payload['is_primary'] = $this->normalizeBoolean($payload['is_primary']);
        } elseif ($creating) {
            $payload['is_primary'] = 0;
        }
        if (array_key_exists('status', $payload)) {
            if (! is_string($payload['status'])) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'status']);
            }
            $payload['status'] = strtoupper(trim($payload['status']));
            if (! in_array($payload['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'status']);
            }
        } elseif ($creating) {
            $payload['status'] = 'ACTIVE';
        }
        $this->assertDateRange($payload);
        if ($creating) {
            $payload = array_merge($payload, $operation->createdAudit());
        }

        return $payload;
    }

    /** @param array<string, mixed> $candidate */
    private function assertReferences(array $candidate): void
    {
        $employeeId = PositiveId::normalize($candidate['employee_id'] ?? null, 'employee_id', self::ERROR_REFERENCE_INVALID);
        $employee = $this->employeeRepo()->find($employeeId);
        if ($employee === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['employee_id' => $employeeId]);
        }
        if (($employee['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_REFERENCE_INACTIVE, ['employee_id' => $employeeId]);
        }

        $organizationId = PositiveId::normalize($candidate['organization_id'] ?? null, 'organization_id', self::ERROR_REFERENCE_INVALID);
        $organization = $this->organizationRepo()->find($organizationId);
        if ($organization === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['organization_id' => $organizationId]);
        }
        if (($organization['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_REFERENCE_INACTIVE, ['organization_id' => $organizationId]);
        }

        foreach (['department_id' => 'departmentIsActive', 'position_id' => 'positionIsActive'] as $field => $method) {
            if (($candidate[$field] ?? null) !== null && ! $this->repo()->{$method}((int) $candidate[$field])) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => $field]);
            }
        }
    }

    /** @param array<string, mixed> $candidate */
    private function assertPrimaryDoesNotOverlap(array $candidate, ?int $excludeId = null): void
    {
        if ((int) ($candidate['is_primary'] ?? 0) !== 1 || ($candidate['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            return;
        }
        $overlaps = $this->repo()->findOverlappingPrimary(
            (int) $candidate['employee_id'],
            (string) $candidate['effective_from'],
            $candidate['effective_to'] === null ? null : (string) $candidate['effective_to'],
            $excludeId,
        );
        if ($overlaps !== []) {
            throw new DomainException(self::ERROR_PRIMARY_OVERLAP, [
                'employee_id' => (int) $candidate['employee_id'],
                'effective_from' => (string) $candidate['effective_from'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertDateRange(array $data): void
    {
        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;
        if (! is_string($from) || ! $this->isDate($from)) {
            throw new DomainException(self::ERROR_DATE_INVALID, ['field' => 'effective_from']);
        }
        if ($to !== null && (! is_string($to) || ! $this->isDate($to))) {
            throw new DomainException(self::ERROR_DATE_INVALID, ['field' => 'effective_to']);
        }
        if ($to !== null && $to < $from) {
            throw new DomainException(self::ERROR_DATE_INVALID, ['field' => 'effective_to']);
        }
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function normalizeBoolean(mixed $value): int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 1;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return 0;
        }

        throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'is_primary']);
    }

    /** @param array<string, mixed> $data */
    private function assertSupportedFields(array $data): void
    {
        $supported = [
            'employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary',
            'effective_from', 'effective_to', 'description', 'status',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true)) {
                throw new DomainException(EmployeeAssignmentRepository::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
    }

    /** @return array{ids:list<int>, rows:list<array<string, mixed>>} */
    private function organizationPath(int $organizationId): array
    {
        $current = $this->organizationRepo()->find($organizationId);
        if ($current === null) {
            throw new DomainException(self::ERROR_EVALUATION_ORGANIZATION_INVALID, ['organization_id' => $organizationId]);
        }
        if (($current['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_EVALUATION_ORGANIZATION_INVALID, ['organization_id' => $organizationId]);
        }

        $rows = [];
        $ids = [];
        $visited = [];
        while (true) {
            $currentId = PositiveId::normalize($current['id'] ?? null, 'organization_id', self::ERROR_EVALUATION_ORGANIZATION_INVALID);
            if (isset($visited[$currentId])) {
                throw new DomainException(self::ERROR_EVALUATION_ORGANIZATION_INVALID, ['organization_id' => $organizationId]);
            }
            $visited[$currentId] = true;
            array_unshift($rows, $current);
            array_unshift($ids, $currentId);
            if (($current['parent_id'] ?? null) === null) {
                break;
            }
            $parentId = PositiveId::normalize($current['parent_id'], 'parent_id', self::ERROR_EVALUATION_ORGANIZATION_INVALID);
            $current = $this->organizationRepo()->find($parentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_EVALUATION_ORGANIZATION_INVALID, ['parent_id' => $parentId]);
            }
        }

        return ['ids' => $ids, 'rows' => $rows];
    }

    private function repo(): EmployeeAssignmentRepository
    {
        return $this->repository ?? new EmployeeAssignmentRepository($this->db);
    }

    private function employeeRepo(): EmployeeRepository
    {
        return $this->employeeRepository ?? new EmployeeRepository($this->db);
    }

    private function organizationRepo(): OrganizationRepository
    {
        return $this->organizationRepository ?? new OrganizationRepository($this->db);
    }

    private function operation(?EvaluationTime $evaluationTime = null): OperationContext
    {
        $context = $this->mutationContext ?? MutationContext::system($this->clock ?? service('clock'));

        return $context->begin($evaluationTime);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutAuditFields(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    /** @param callable(): mixed $callback */
    private function withAssignmentLock(int $assignmentId, callable $callback): mixed
    {
        $current = $this->repo()->find($assignmentId);
        if ($current === null) {
            throw new DomainException(self::ERROR_ASSIGNMENT_NOT_FOUND, ['assignment_id' => $assignmentId]);
        }
        $employeeId = PositiveId::normalize($current['employee_id'] ?? null, 'employee_id', self::ERROR_REFERENCE_INVALID);

        return $this->withEmployeeLock($employeeId, $callback);
    }

    /** @param callable(): mixed $callback */
    private function withEmployeeLock(int $employeeId, callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'employee_assignment_mutation']);
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        try {
            return $lock->synchronized('employee-assignment:' . $employeeId, function () use ($callback, $db): mixed {
                $db->transBegin();
                try {
                    $result = $callback();
                    if (! $db->transStatus()) {
                        throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'employee_assignment_mutation']);
                    }
                    $db->transCommit();

                    return $result;
                } catch (\Throwable $exception) {
                    $db->transRollback();
                    throw $exception;
                }
            });
        } catch (ConcurrencyException $exception) {
            throw $exception;
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'employee_assignment_mutation'], $exception);
        }
    }
}

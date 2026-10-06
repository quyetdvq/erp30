<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Repositories\DepartmentRepository;
use App\Repositories\EmployeeAssignmentRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\PositionRepository;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeImmutable;

/** Application boundary for the CONTRACT_READY P1-API-020 resources. */
final class Api020EmployeeStructureService
{
    /** @var list<string> */
    private const RESOURCES = ['department', 'position', 'employee', 'employee_assignment'];

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?DepartmentRepository $departments = null,
        private readonly ?PositionRepository $positions = null,
        private readonly ?EmployeeRepository $employees = null,
        private readonly ?EmployeeAssignmentRepository $assignments = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?DepartmentService $departmentService = null,
        private readonly ?PositionService $positionService = null,
        private readonly ?EmployeeService $employeeService = null,
        private readonly ?EmployeeAssignmentService $assignmentService = null,
        private readonly ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function list(string $resource, array $query): CommonListResult
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $rows = $this->authorizedRows($resource, $operation);
        $query = $this->applyDataFilters($resource, $query, $rows, $operation);

        $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $this->allowlist($resource),
            new AuthorizedArrayListSource($rows),
        );

        $data = [];
        foreach ($result->data as $row) {
            if (is_array($row)) {
                $data[] = $this->outputRow($resource, $row);
            }
        }

        return new CommonListResult($data, $result->query, $result->total);
    }

    /** @return array<string, mixed> */
    public function get(string $resource, int $id): array
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);

        return $this->outputRow($resource, $row);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $this->authorizeAction($resource, 'create', [], $payload, $operation);
        $id = match ($resource) {
            'department' => $this->departmentService($operation)->create($payload),
            'position' => $this->positionService($operation)->create($payload),
            'employee' => $this->employeeService($operation)->create($payload),
            'employee_assignment' => $this->assignmentService($operation)->create($payload),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };

        return $this->get($resource, $id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $resource, int $id, array $payload): array
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);
        $this->authorizeAction($resource, 'edit', $row, $payload, $operation);
        match ($resource) {
            'department' => $this->departmentService($operation)->update($id, $payload),
            'position' => $this->positionService($operation)->update($id, $payload),
            'employee' => $this->employeeService($operation)->update($id, $payload),
            'employee_assignment' => $this->assignmentService($operation)->update($id, $payload),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };

        return $this->get($resource, $id);
    }

    /** @return array<string, mixed> */
    public function setState(string $resource, int $id, string $status): array
    {
        $this->assertResource($resource);
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);
        $this->authorizeAction($resource, 'setstate', $row, ['status' => $status], $operation);
        match ($resource) {
            'department' => $this->departmentService($operation)->update($id, ['status' => $status]),
            'position' => $this->positionService($operation)->update($id, ['status' => $status]),
            'employee' => $this->employeeService($operation)->update($id, ['status' => $status]),
            'employee_assignment' => $this->assignmentService($operation)->update($id, ['status' => $status]),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };

        return $this->get($resource, $id);
    }

    /** @return list<array<string, mixed>> */
    private function authorizedRows(string $resource, OperationContext $operation): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = match ($resource) {
            'department' => $this->departmentRepo()->findAll(),
            'position' => $this->positionRepo()->findAll(),
            'employee' => $this->employeeRepo()->findAll(),
            'employee_assignment' => $this->assignmentRepo()->findAll(),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };
        $visible = [];
        foreach ($rows as $row) {
            try {
                $this->authorizeAction($resource, 'view', $row, [], $operation);
                $visible[] = $row;
            } catch (DomainException $exception) {
                if ($this->isListDenied($exception)) {
                    continue;
                }
                throw $exception;
            }
        }

        return $visible;
    }

    /**
     * @param array<string, mixed> $query
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function applyDataFilters(string $resource, array $query, array &$rows, OperationContext $operation): array
    {
        $filters = $query['filter'] ?? [];
        if (! is_array($filters)) {
            return $query;
        }

        if ($resource === 'employee') {
            $assignmentFields = ['organization_id', 'department_id', 'position_id'];
            $requested = [];
            foreach ($assignmentFields as $field) {
                if (array_key_exists($field, $filters)) {
                    $requested[$field] = $this->filterValues($filters[$field], $field);
                    unset($filters[$field]);
                }
            }
            if ($requested !== []) {
                $evaluationTime = $operation->evaluationTime;
                $rows = array_values(array_filter($rows, function (array $row) use ($requested, $evaluationTime, $operation): bool {
                    $employeeId = (int) ($row['id'] ?? 0);
                    if ($employeeId < 1) {
                        return false;
                    }
                    $effective = $this->assignmentService($operation)->findEffective($employeeId, $evaluationTime);
                    foreach ($requested as $field => $values) {
                        if (! array_filter($effective, static fn (array $assignment): bool => in_array((string) ($assignment[$field] ?? ''), $values, true))) {
                            return false;
                        }
                    }

                    return true;
                }));
            }
        }

        if ($resource === 'employee_assignment') {
            if (array_key_exists('effective_at', $filters)) {
                $values = $this->filterValues($filters['effective_at'], 'effective_at');
                if (count($values) !== 1 || ! $this->isDate($values[0])) {
                    throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('Malformed list query: filter.effective_at.');
                }
                $date = $values[0];
                $rows = array_values(array_filter($rows, static function (array $row) use ($date): bool {
                    if (($row['status'] ?? null) !== 'ACTIVE') {
                        return false;
                    }
                    return (string) ($row['effective_from'] ?? '') <= $date
                        && (($row['effective_to'] ?? null) === null || (string) $row['effective_to'] >= $date);
                }));
                unset($filters['effective_at']);
            }
            if (array_key_exists('is_primary', $filters)) {
                $filters['is_primary'] = array_map(static function (mixed $value): string {
                    $normalized = strtolower((string) $value);
                    return match ($normalized) {
                        'true', '1' => '1',
                        'false', '0' => '0',
                        default => (string) $value,
                    };
                }, $this->filterValues($filters['is_primary'], 'is_primary'));
            }
        }

        if ($filters === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filters;
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function row(string $resource, int $id): array
    {
        $row = match ($resource) {
            'department' => $this->departmentRepo()->find($id),
            'position' => $this->positionRepo()->find($id),
            'employee' => $this->employeeRepo()->find($id),
            'employee_assignment' => $this->assignmentRepo()->find($id),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };
        if ($row === null) {
            throw new PageNotFoundException('Employee structure target not found.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $input
     */
    private function authorizeAction(string $resource, string $action, array $target, array $input, OperationContext $operation): void
    {
        $decision = $this->auth()->authorize(
            $operation,
            $resource,
            $action,
            new EvaluationOrganizationRequest(
                persistedTarget: $target === [] ? null : $target,
                input: $input,
            ),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(AuthorizationService::REASON_POLICY_DENIED);
        }
    }

    /** @param array<string, mixed> $row */
    private function authorizeVisible(string $resource, array $row, OperationContext $operation): void
    {
        try {
            $this->authorizeAction($resource, 'view', $row, [], $operation);
        } catch (DomainException $exception) {
            if ($this->isListDenied($exception)) {
                throw new PageNotFoundException('Employee structure target is not visible.');
            }
            throw $exception;
        }
    }

    private function isListDenied(DomainException $exception): bool
    {
        $code = strtoupper($exception->getErrorCode());

        return in_array($code, [
            AuthorizationService::REASON_POLICY_DENIED,
            AuthorizationService::REASON_NO_BASE_PERMISSION,
        ], true)
            || str_contains($code, 'AUTH_CONTEXT')
            || str_contains($code, 'EVALUATION_ORGANIZATION')
            || str_contains($code, 'REFERENCE_INVALID');
    }

    /** @return CommonListQueryAllowlist */
    private function allowlist(string $resource): CommonListQueryAllowlist
    {
        return match ($resource) {
            'department' => new CommonListQueryAllowlist(['code', 'name'], ['parent_id', 'status'], ['code', 'name', 'status']),
            'position' => new CommonListQueryAllowlist(['code', 'name'], ['status'], ['code', 'name', 'level', 'status']),
            'employee' => new CommonListQueryAllowlist(['employee_code', 'employee_name'], ['status'], ['employee_code', 'employee_name', 'status']),
            'employee_assignment' => new CommonListQueryAllowlist(['description'], ['employee_id', 'organization_id', 'department_id', 'position_id', 'is_primary', 'status', 'effective_at'], ['effective_from', 'effective_to', 'is_primary', 'status']),
            default => throw new \InvalidArgumentException('Unsupported API-020 resource.'),
        };
    }

    /** @return list<string> */
    private function filterValues(mixed $value, string $field): array
    {
        $values = is_array($value) ? $value : [$value];
        if ($values === [] || ! array_is_list($values)) {
            throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('Malformed list query: filter.' . $field . '.');
        }
        foreach ($values as $item) {
            if (! is_scalar($item)) {
                throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('Malformed list query: filter.' . $field . '.');
            }
        }

        return array_map(static fn (mixed $item): string => (string) $item, $values);
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function outputRow(string $resource, array $row): array
    {
        if ($resource === 'employee_assignment' && array_key_exists('is_primary', $row)) {
            $row['is_primary'] = (bool) ((int) $row['is_primary']);
        }

        return $row;
    }

    private function assertResource(string $resource): void
    {
        if (! in_array($resource, self::RESOURCES, true)) {
            throw new \InvalidArgumentException('Unsupported API-020 resource.');
        }
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }

        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }

    private function departmentRepo(): DepartmentRepository
    {
        return $this->departments ?? new DepartmentRepository($this->db);
    }

    private function positionRepo(): PositionRepository
    {
        return $this->positions ?? new PositionRepository($this->db);
    }

    private function employeeRepo(): EmployeeRepository
    {
        return $this->employees ?? new EmployeeRepository($this->db);
    }

    private function assignmentRepo(): EmployeeAssignmentRepository
    {
        return $this->assignments ?? new EmployeeAssignmentRepository($this->db);
    }

    private function departmentService(OperationContext $operation): DepartmentService
    {
        return $this->departmentService ?? new DepartmentService($this->departmentRepo(), $this->db, null, $this->contextFor($operation));
    }

    private function positionService(OperationContext $operation): PositionService
    {
        return $this->positionService ?? new PositionService($this->positionRepo(), $this->db, $this->contextFor($operation));
    }

    private function employeeService(OperationContext $operation): EmployeeService
    {
        return $this->employeeService ?? new EmployeeService($this->employeeRepo(), $this->db, null, $this->contextFor($operation));
    }

    private function assignmentService(OperationContext $operation): EmployeeAssignmentService
    {
        return $this->assignmentService ?? new EmployeeAssignmentService($this->assignmentRepo(), $this->db, null, $this->contextFor($operation));
    }

    private function contextFor(OperationContext $operation): MutationContext
    {
        return new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, $operation->systemActor);
    }
}

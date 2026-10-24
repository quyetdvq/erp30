<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\Access\ReadAccessBridge;
use App\Modules\People\PeopleReadRepository;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\AuthorizedListPage;
use App\Shared\Read\FlatHierarchy;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * M2.1 compatibility adapter: GET only, no mutations.
 * Every persisted row is PBAC-filtered BEFORE search/count/page or tree projection.
 */
final class ReadCatalogController extends Controller
{
    private const TABLES = [
        'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
        'department' => 'departments', 'position' => 'positions',
        'employee' => 'employees', 'employee_assignment' => 'employee_assignments',
    ];

    /**
     * Explicit route entry points: CI4 must receive a method, not a method/argument path.
     * These methods delegate to a closed resource map; no resource type comes from the URL.
     */
    public function listProvinces(): ResponseInterface
    {
        return $this->index('province');
    }

    public function showProvince(string $id): ResponseInterface
    {
        return $this->show('province', $id);
    }

    public function listWards(): ResponseInterface
    {
        return $this->index('ward');
    }

    public function showWard(string $id): ResponseInterface
    {
        return $this->show('ward', $id);
    }

    public function listOrganizations(): ResponseInterface
    {
        return $this->index('organization');
    }

    public function showOrganization(string $id): ResponseInterface
    {
        return $this->show('organization', $id);
    }

    public function listDepartments(): ResponseInterface
    {
        return $this->index('department');
    }

    public function showDepartment(string $id): ResponseInterface
    {
        return $this->show('department', $id);
    }

    public function listPositions(): ResponseInterface
    {
        return $this->index('position');
    }

    public function showPosition(string $id): ResponseInterface
    {
        return $this->show('position', $id);
    }

    public function listEmployees(): ResponseInterface
    {
        return $this->index('employee');
    }

    public function showEmployee(string $id): ResponseInterface
    {
        return $this->show('employee', $id);
    }

    public function listEmployeeAssignments(): ResponseInterface
    {
        return $this->index('employee_assignment');
    }

    public function showEmployeeAssignment(string $id): ResponseInterface
    {
        return $this->show('employee_assignment', $id);
    }

    private function index(string $resource): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $rows = $this->authorizedRows($resource, new ReadAccessBridge($user, null, $instant));
        $query = $this->request->getGet();
        if (! is_array($query)) {
            throw new BadRequestException('Invalid list query.');
        }
        $query = $this->businessFilters($resource, $rows, $query, $instant);
        $page = AuthorizedListPage::paginate($resource, $rows, $query);
        if ($resource === 'employee_assignment') {
            foreach ($page['data'] as &$row) {
                $row['is_primary'] = (bool) ((int) ($row['is_primary'] ?? 0));
            }
            unset($row);
        }
        return ApiResponder::success($this->response, $page['data'], 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    public function tree(): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $rows = $this->authorizedRows('organization', new ReadAccessBridge($user));
        $query = $this->request->getGet();
        if (! is_array($query)) {
            throw new BadRequestException('Invalid list query.');
        }
        $page = AuthorizedListPage::paginate('organization', $rows, $query);
        $page['data'] = FlatHierarchy::visibleRows($page['data']);
        return ApiResponder::success($this->response, $page['data'], 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    private function show(string $resource, string $id): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $table = $this->table($resource);
        try {
            $id = PositiveId::value($id);
        } catch (\InvalidArgumentException) {
            return ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404);
        }
        $row = db_connect()->table($table)->where('id', $id)->get()->getRowArray();
        if ($row === null || ! (new ReadAccessBridge($user))->canView($resource, $row)) {
            // Never disclose existence of a row outside the actor's scope.
            return ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404);
        }
        if ($resource === 'employee_assignment') {
            $row['is_primary'] = (bool) ((int) $row['is_primary']);
        }
        return ApiResponder::success($this->response, $row);
    }

    public function resolveOrganizationLabels(): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $ids = json_decode((string) $this->request->getBody(), true);
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > 50) {
            throw new BadRequestException('Invalid Organization ID list.');
        }
        $unique = [];
        foreach ($ids as $id) {
            if (! is_int($id) && ! is_string($id)) {
                throw new BadRequestException('Invalid Organization ID.');
            }
            try {
                $normalized = PositiveId::value($id);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestException('Invalid Organization ID.');
            }
            $unique[$normalized] = true;
        }
        $gate = new ReadAccessBridge($user);
        $result = [];
        foreach (array_keys($unique) as $id) {
            $row = db_connect()->table('organizations')->where('id', $id)->get()->getRowArray();
            if ($row !== null && $gate->canView('organization', $row)) {
                $result[] = [
                    'id' => (string) $row['id'], 'code' => (string) $row['code'],
                    'name' => (string) $row['name'], 'type' => (string) $row['type'],
                ];
            }
        }
        return ApiResponder::success($this->response, $result);
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    private function table(string $resource): string
    {
        return self::TABLES[$resource] ?? throw new \InvalidArgumentException('READ_ENDPOINT_UNSUPPORTED');
    }

    /** @return list<array<string,mixed>> */
    private function authorizedRows(string $resource, ReadAccessBridge $gate): array
    {
        $rows = db_connect()->table($this->table($resource))->orderBy('id', 'ASC')->get()->getResultArray();
        $visible = [];
        foreach ($rows as $row) {
            if ($gate->canView($resource, $row)) {
                $visible[] = $row;
            }
        }
        return $visible;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function businessFilters(string $resource, array &$rows, array $query, DateTimeImmutable $instant): array
    {
        if (! in_array($resource, ['employee','employee_assignment'], true)) {
            return $query;
        }
        $filters = $query['filter'] ?? [];
        if (! is_array($filters) || ($filters === [] && array_key_exists('filter', $query))) {
            return $query;
        }
        if ($resource === 'employee') {
            $requested = [];
            foreach (['organization_id', 'department_id', 'position_id'] as $field) {
                if (array_key_exists($field, $filters)) {
                    $requested[$field] = $this->filterValues($filters[$field]);
                    unset($filters[$field]);
                }
            }
            if ($requested !== []) {
                $employeeIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
                $effective = (new PeopleReadRepository())->effectiveAssignmentsForEmployees(
                    $employeeIds, UtcBusinessDate::at($instant));
                $rows = array_values(array_filter($rows, static function (array $row) use ($requested, $effective): bool {
                    $assignments = $effective[(string) $row['id']] ?? [];
                    foreach ($requested as $field => $values) {
                        $matches = false;
                        foreach ($assignments as $assignment) {
                            if (in_array((string) ($assignment[$field] ?? ''), $values, true)) {
                                $matches = true;
                                break;
                            }
                        }
                        if (! $matches) {
                            return false;
                        }
                    }
                    return true;
                }));
            }
        }
        if ($resource === 'employee_assignment') {
            if (array_key_exists('effective_at', $filters)) {
                $values = $this->filterValues($filters['effective_at']);
                $date = $values[0] ?? '';
                if (count($values) !== 1 || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1
                    || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                    throw new BadRequestException('Invalid effective_at filter.');
                }
                $rows = array_values(array_filter($rows, static fn (array $row): bool =>
                    ($row['status'] ?? null) === 'ACTIVE'
                    && (string) ($row['effective_from'] ?? '') <= $date
                    && (($row['effective_to'] ?? null) === null || (string) $row['effective_to'] >= $date)));
                unset($filters['effective_at']);
            }
            if (array_key_exists('is_primary', $filters)) {
                $filters['is_primary'] = array_map(static fn (string $s): string => match (strtolower($s)) {
                    'true','1' => '1', 'false','0' => '0', default => $s,
                }, $this->filterValues($filters['is_primary']));
            }
        }
        if ($filters === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filters;
        }
        return $query;
    }

    /** @return list<string> */
    private function filterValues(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        if ($values === [] || ! array_is_list($values)) {
            throw new BadRequestException('Invalid filter values.');
        }
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                throw new BadRequestException('Invalid filter value.');
            }
        }
        return array_map(static fn (mixed $s): string => (string) $s, $values);
    }
}

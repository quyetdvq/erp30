<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\People\Read\PeopleMasterReadService;
use App\Modules\Organization\Read\OrganizationPeopleReadService;
use App\Modules\Location\Read\LocationMasterReadService;
use App\Shared\Http\ApiResponder;
use App\Shared\Read\FlatHierarchy;
use App\Shared\Read\OrganizationLabelResolveRequest;
use App\Shared\Read\PositiveId;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Fixed GET adapter. Five SQL-migrated resources use mandatory canonical
 * SQL/PBAC scopes. Province/Ward still use canonical row checks until they
 * have independently reviewed SQL scope providers. No runtime mode switch.
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
        $query = $this->request->getGet();
        if (! is_array($query)) {
            throw new BadRequestException('Invalid list query.');
        }
        $instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (in_array($resource, ['employee', 'department', 'position'], true)) {
            $page = $this->peopleSqlService($user, $instant)->list($resource, $query);
        } elseif (in_array($resource, ['organization', 'employee_assignment'], true)) {
            $page = $this->organizationSqlService($user, $instant)->list($resource, $query);
        } elseif (in_array($resource, ['province', 'ward'], true)) {
            $page = $this->locationSqlService($user, $instant)->list($resource, $query);
        } else {
            throw new \UnexpectedValueException('Unreviewed catalog resource.');
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
        $query = $this->request->getGet();
        if (! is_array($query)) {
            throw new BadRequestException('Invalid list query.');
        }
        $page = $this->organizationSqlService($user)->list('organization', $query);
        $visible = FlatHierarchy::visibleRows($page['data']);
        return ApiResponder::success($this->response, $visible, 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    private function show(string $resource, string $id): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        try {
            $id = PositiveId::value($id);
        } catch (\InvalidArgumentException) {
            return ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404);
        }
        if (in_array($resource, ['employee', 'department', 'position'], true)) {
            $row = $this->peopleSqlService($user)->show($resource, $id);
        } elseif (in_array($resource, ['organization', 'employee_assignment'], true)) {
            $row = $this->organizationSqlService($user)->show($resource, $id);
        } elseif (in_array($resource, ['province', 'ward'], true)) {
            $row = $this->locationSqlService($user)->show($resource, $id);
        } else {
            throw new \UnexpectedValueException('Unreviewed catalog resource.');
        }
        if ($row === null) {
            // Never disclose existence outside SQL scope or canonical VIEW permission.
            return ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404);
        }
        return ApiResponder::success($this->response, $row);
    }

    public function resolveOrganizationLabels(): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        try {
            $ids = OrganizationLabelResolveRequest::ids((string) $this->request->getBody());
        } catch (\InvalidArgumentException $error) {
            throw new BadRequestException('Invalid Organization ID list.', 0, $error);
        }
        return ApiResponder::success($this->response,
            $this->organizationSqlService($user)->organizationLabels($ids));
    }

    private function peopleSqlService(User $user, ?DateTimeImmutable $instant = null): PeopleMasterReadService
    {
        return new PeopleMasterReadService($user, db_connect(),
            $instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    private function organizationSqlService(User $user, ?DateTimeImmutable $instant = null): OrganizationPeopleReadService
    {
        return new OrganizationPeopleReadService($user, db_connect(),
            $instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    private function locationSqlService(User $user, ?DateTimeImmutable $instant = null): LocationMasterReadService
    {
        return new LocationMasterReadService($user, db_connect(),
            $instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
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

}

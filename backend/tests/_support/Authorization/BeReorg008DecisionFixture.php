<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

/** Test-only PBAC fixture; never mutates a non-disposable target. */
final class BeReorg008DecisionFixture
{
    private ?int $rootId = null;

    private const STAMP = '2026-10-05 00:00:00';

    /** @var list<int> */
    private array $inactivePolicyIds = [];
    /** @var array<int, string> */
    private array $originalResourceStatuses = [];

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{user_id:int, group_id:int, root_id:int, company_id:int, sibling_id:?int, employee_id:?int, assignment_ids:list<int>} */
    public function seedActor(bool $grantView = true, bool $withSibling = false, bool $withAssignment = true): array
    {
        (new ResourceActionSeeder(new Database()))->run();
        // Group-scoped PBAC requires exactly one GROUP root per test DB;
        // multiple actors in the same test share it and have distinct companies.
        $root = $this->rootId ??= $this->insert('organizations', [
            'code' => 'BE008-ROOT-' . uniqid(), 'name' => 'BE008 root', 'type' => 'GROUP',
            'status' => 'ACTIVE', 'parent_id' => null,
        ]);
        $company = $this->insert('organizations', [
            'code' => 'BE008-C-' . uniqid(), 'name' => 'BE008 company', 'type' => 'COMPANY',
            'status' => 'ACTIVE', 'parent_id' => $root,
        ]);
        $sibling = $withSibling ? $this->insert('organizations', [
            'code' => 'BE008-S-' . uniqid(), 'name' => 'BE008 sibling', 'type' => 'COMPANY',
            'status' => 'ACTIVE', 'parent_id' => $root,
        ]) : null;
        $user = $this->insert('users', [
            'username' => 'be008-' . uniqid(), 'status' => 'active', 'active' => 1,
        ]);
        $code = 'BE008-G-' . uniqid();
        $group = $this->insert('groups', ['code' => $code, 'name' => 'BE008 group', 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $user, 'group' => strtolower($code)]);
        if ($grantView) {
            $this->grant($group, 'price_list', 'view');
        }
        $employee = null;
        $assignments = [];
        if ($withAssignment) {
            $employee = $this->createEmployee($user);
            $assignments[] = $this->addAssignment($employee, $company);
        }

        return [
            'user_id' => $user, 'group_id' => $group, 'root_id' => $root,
            'company_id' => $company, 'sibling_id' => $sibling,
            'employee_id' => $employee, 'assignment_ids' => $assignments,
        ];
    }

    public function grant(int $groupId, string $resourceType, string $action): void
    {
        $id = $this->actionId($resourceType, $action);
        if ($this->db->table('group_permissions')->where(['group_id' => $groupId, 'resource_action_id' => $id])->countAllResults() === 0) {
            $this->insert('group_permissions', ['group_id' => $groupId, 'resource_action_id' => $id]);
        }
    }

    public function createEmployee(int $userId, string $status = 'ACTIVE'): int
    {
        return $this->insert('employees', [
            'user_id' => $userId, 'employee_code' => 'BE008-E-' . uniqid(),
            'employee_name' => 'BE008 employee', 'status' => $status,
        ]);
    }

    public function createDepartment(): int
    {
        return $this->insert('departments', [
            'code' => 'BE008-D-' . uniqid(), 'name' => 'BE008 department', 'status' => 'ACTIVE',
        ]);
    }

    public function addAssignment(int $employeeId, int $organizationId, ?int $departmentId = null, ?int $positionId = null): int
    {
        return $this->insert('employee_assignments', [
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'department_id' => $departmentId, 'position_id' => $positionId,
            'is_primary' => 0, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'ACTIVE',
        ]);
    }

    public function addPolicy(
        int $organizationId,
        string $participantType,
        int $participantRefId,
        string $effect,
        string $resourceType = 'price_list',
        string $action = 'view',
        ?int $stateId = null,
    ): int {
        return (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $organizationId,
            'resource_id' => $this->resourceId($resourceType),
            'state_id' => $stateId,
            'participant_type' => $participantType,
            'participant_ref_id' => $participantRefId,
        ], [['resource_action_id' => $this->actionId($resourceType, $action), 'effect' => $effect]]);
    }

    public function resourceId(string $resourceType): int
    {
        $row = $this->db->table('resources')->select('id')->where('type', $resourceType)->get()->getRowArray();
        if (! is_array($row)) {
            throw new \RuntimeException('BE008 fixture missing seeded resource: ' . $resourceType);
        }
        return (int) $row['id'];
    }

    public function actionId(string $resourceType, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resourceType)->where('ra.code', $action)->get()->getRowArray();
        if (! is_array($row)) {
            throw new \RuntimeException('BE008 fixture missing seeded action: ' . $resourceType . '.' . $action);
        }
        return (int) $row['id'];
    }

    public function operation(int $userId, string $requestId = 'be008-parity'): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')), $requestId, false);
    }

    public function request(int $organizationId): EvaluationOrganizationRequest
    {
        return new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $organizationId]);
    }

    public function trackInactivePolicy(int $policyId): void
    {
        $this->inactivePolicyIds[] = $policyId;
    }

    public function restoreTrackedInactivePolicies(): void
    {
        foreach (array_unique($this->inactivePolicyIds) as $id) {
            $this->db->table('policies')->where('id', $id)->where('status', 'INACTIVE')->update(['status' => 'ACTIVE']);
        }
        $this->inactivePolicyIds = [];
    }

    public function trackResourceStatus(int $resourceId, string $priorStatus): void
    {
        $this->originalResourceStatuses[$resourceId] ??= $priorStatus;
    }

    public function restoreTrackedResourceStatuses(): void
    {
        foreach ($this->originalResourceStatuses as $id => $originalStatus) {
            $this->db->table('resources')->where('id', $id)->update(['status' => $originalStatus]);
        }
        $this->originalResourceStatuses = [];
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => self::STAMP])) {
            throw new \RuntimeException('BE008 fixture insert failed for ' . $table);
        }
        return (int) $this->db->insertID();
    }
}

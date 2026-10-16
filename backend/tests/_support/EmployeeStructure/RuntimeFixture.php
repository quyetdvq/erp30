<?php

declare(strict_types=1);

namespace Tests\Support\EmployeeStructure;

use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Time\Clock;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;
use Tests\Support\Database\WorkflowLifecycleFixture;

/** Disposable API-020 data and permissions for BE-REORG-004 in-process HTTP tests. */
final class RuntimeFixture
{
    public int $group;
    public int $company;
    public int $writer;
    public int $assignmentEmployee;

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
        (new ResourceActionSeeder(new Database()))->run();
        WorkflowLifecycleFixture::bind($db, ['department', 'position', 'employee']);

        $this->group = $this->insert('organizations', [
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'BR004-G',
            'name' => 'Employee Structure test group', 'status' => 'ACTIVE',
        ]);
        $this->company = $this->insert('organizations', [
            'type' => 'COMPANY', 'parent_id' => $this->group, 'code' => 'BR004-C',
            'name' => 'Employee Structure test company', 'status' => 'ACTIVE',
        ]);
        $this->writer = $this->actor('writer', ['view', 'create', 'edit', 'setstate']);
        $this->assignmentEmployee = $this->insert('employees', [
            'employee_code' => 'BR004-TARGET', 'employee_name' => 'Assignment target', 'status' => 'ACTIVE',
        ]);
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T05:30:00Z'); }
        };
    }

    /** @param array<string, mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 05:30:00'])) {
            throw new RuntimeException('Could not create synthetic Employee Structure fixture: ' . $table);
        }

        return (int) $this->db->insertID();
    }

    /** @param list<string> $actions */
    private function actor(string $name, array $actions): int
    {
        $user = $this->insert('users', ['username' => 'br004-' . $name, 'status' => 'active', 'active' => 1]);
        $code = 'BR004-' . strtoupper($name);
        $group = $this->insert('groups', ['code' => $code, 'name' => $name, 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $user, 'group' => strtolower($code)]);
        foreach (['department', 'position', 'employee', 'employee_assignment'] as $resource) {
            foreach ($actions as $action) {
                $this->insert('group_permissions', [
                    'group_id' => $group,
                    'resource_action_id' => $this->actionId($resource, $action),
                ]);
            }
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $this->company);

        return $user;
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)
            ->where('ra.code', $action)->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Missing seeded action: ' . $resource . '.' . $action);
        }

        return (int) $row['id'];
    }
}

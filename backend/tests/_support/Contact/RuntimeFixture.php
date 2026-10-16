<?php

declare(strict_types=1);

namespace Tests\Support\Contact;

use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;

/** Synthetic disposable Contact data and owner-aware permissions for BE-REORG-003. */
final class RuntimeFixture
{
    public int $group;
    public int $company;
    public int $ownerEmployee;
    public int $writer;
    public int $reader;
    /** @var array<string,int> */
    public array $states = [];

    /** @param BaseConnection<mixed,mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
        (new ResourceActionSeeder(new Database()))->run();
        $this->group = $this->insert('organizations', [
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'BR003-G', 'name' => 'Contact test group', 'status' => 'ACTIVE',
        ]);
        $this->company = $this->insert('organizations', [
            'type' => 'COMPANY', 'parent_id' => $this->group, 'code' => 'BR003-C', 'name' => 'Contact test company', 'status' => 'ACTIVE',
        ]);
        $this->ownerEmployee = $this->insert('employees', [
            'employee_code' => 'BR003-OWNER', 'employee_name' => 'Contact owner', 'status' => 'ACTIVE',
        ]);
        $this->insert('employee_assignments', [
            'employee_id' => $this->ownerEmployee, 'organization_id' => $this->company,
            'is_primary' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'ACTIVE',
        ]);
        $this->writer = $this->actor('writer', ['view', 'create', 'edit', 'setstate']);
        $this->reader = $this->actor('reader', ['view']);

        $repo = new LifecycleRepository($db);
        $lifecycle = $repo->insertLifecycle(['code' => 'BR003-LC', 'name' => 'Contact runtime', 'status' => 'ACTIVE']);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $index => $code) {
            $state = $db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $this->states[$code] = $state === null
                ? $repo->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $state['id'];
            $repo->insertLifecycleState([
                'lifecycle_id' => $lifecycle, 'state_id' => $this->states[$code], 'sequence' => $index + 1,
                'is_initial' => $index === 0 ? 1 : 0, 'is_final' => $code === 'CLOSED' ? 1 : 0,
            ]);
        }
        $repo->insertTransition([
            'lifecycle_id' => $lifecycle, 'from_state_id' => $this->states['ACTIVE'],
            'to_state_id' => $this->states['INACTIVE'], 'action_code' => 'setstate', 'status' => 'ACTIVE',
        ]);
        foreach (['organization_contact', 'employee_contact'] as $resource) {
            $db->table('resources')->where('type', $resource)->update(['lifecycle_id' => $lifecycle, 'status' => 'ACTIVE']);
        }
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T07:00:00Z'); }
        };
    }

    public function operation(?int $actor = null): OperationContext
    {
        return new OperationContext($actor ?? $this->writer, new EvaluationTime(self::clock()->now()), 'be-reorg-003', false);
    }

    public function contact(string $type, int $owner, string $name, string $status = 'ACTIVE'): int
    {
        return $this->insert('contacts', [
            'contact_type' => $type, 'contact_ref_id' => $owner, 'name' => $name, 'status' => $status,
        ]);
    }

    public function deny(string $resource, string $action, int $user, ?int $state = null): void
    {
        $row = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Missing seeded Contact resource.');
        }
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $this->company, 'resource_id' => (int) $row['id'], 'state_id' => $state,
            'participant_type' => 'USER', 'participant_ref_id' => $user,
        ], [[
            'resource_action_id' => $this->actionId($resource, $action), 'effect' => 'ABSOLUTE_DENY',
        ]]);
    }

    /**
     * Remove only Contact authorization rows that make migration 000015 intentionally
     * non-reversible. DatabaseTestTrait will rebuild the remaining test data.
     */
    public function cleanupRollbackBlockers(): void
    {
        $resourceRows = $this->db->table('resources')->select('id')
            ->whereIn('type', ['organization_contact', 'employee_contact'])->get()->getResultArray();
        $resourceIds = array_values(array_map(static fn (array $row): int => (int) $row['id'], $resourceRows));
        if ($resourceIds === []) {
            return;
        }

        $actionRows = $this->db->table('resource_actions')->select('id')
            ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
        $actionIds = array_values(array_map(static fn (array $row): int => (int) $row['id'], $actionRows));
        $policyRows = $this->db->table('policies')->select('id')
            ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
        $policyIds = array_values(array_map(static fn (array $row): int => (int) $row['id'], $policyRows));

        $this->db->table('authorization_logs')->whereIn('resource_id', $resourceIds)->delete();
        if ($policyIds !== []) {
            $this->db->table('policy_actions')->whereIn('policy_id', $policyIds)->delete();
        }
        $this->db->table('policies')->whereIn('resource_id', $resourceIds)->delete();
        if ($actionIds !== []) {
            $this->db->table('group_permissions')->whereIn('resource_action_id', $actionIds)->delete();
        }
        $this->db->table('auth_permissions_users')->like('permission', 'contact.', 'before')->delete();
        $this->db->table('auth_permissions_users')->like('permission', '_contact.', 'both')->delete();
    }

    /** @param array<string,mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 07:00:00'])) {
            throw new RuntimeException('Could not create synthetic Contact fixture: ' . $table);
        }
        return (int) $this->db->insertID();
    }

    /** @param list<string> $actions */
    private function actor(string $name, array $actions): int
    {
        $user = $this->insert('users', ['username' => 'br003-' . $name, 'status' => 'active', 'active' => 1]);
        $code = 'BR003-' . strtoupper($name);
        $group = $this->insert('groups', ['code' => $code, 'name' => $name, 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $user, 'group' => strtolower($code)]);
        foreach (['organization_contact', 'employee_contact'] as $resource) {
            foreach ($actions as $action) {
                $this->insert('group_permissions', ['group_id' => $group, 'resource_action_id' => $this->actionId($resource, $action)]);
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

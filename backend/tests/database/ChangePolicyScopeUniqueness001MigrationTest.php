<?php

declare(strict_types=1);

use App\Database\Migrations\EnforcePolicyScopeUniqueness;
use App\Database\Seeds\ResourceActionSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

require_once APPPATH . 'Database/Migrations/2026-10-08-000012_EnforcePolicyScopeUniqueness.php';

/** @internal */
final class ChangePolicyScopeUniqueness001MigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAuditReconcilesLowestPolicyAndQT1UnionAndAllowsConcreteState(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $organizationId = $this->insertOrganization();
        $groupId = $this->insertGroup();
        $resourceId = $this->resourceId('product');
        $viewActionId = $this->resourceActionId('product', 'view');
        $editActionId = $this->resourceActionId('product', 'edit');
        $migration = new EnforcePolicyScopeUniqueness(Config\Database::forge($this->db));
        $migration->down();

        $first = $this->insertPolicy($organizationId, $resourceId, $groupId, null);
        $second = $this->insertPolicy($organizationId, $resourceId, $groupId, null);
        $this->insertAction($first, $viewActionId, 'DENY');
        $this->insertAction($second, $viewActionId, 'ALLOW');
        $this->insertAction($second, $editActionId, 'ABSOLUTE_DENY');

        $audit = $migration->auditDuplicateScopes();
        $this->assertCount(1, $audit);
        $this->assertSame([$first, $second], $audit[0]['policy_ids']);

        $migration->up();

        $this->assertSame(1, (int) $this->db->table('policies')->where('organization_id', $organizationId)->countAllResults());
        $effects = $this->db->table('policy_actions')->where('policy_id', $first)->orderBy('resource_action_id')->get()->getResultArray();
        $this->assertSame(['ALLOW', 'ABSOLUTE_DENY'], array_column($effects, 'effect'));

        $stateId = 990001;
        $this->db->table('workflow_states')->insert([
            'id' => $stateId, 'code' => 'CHANGE_SCOPE_' . uniqid(), 'name' => 'Change scope',
            'status' => 'ACTIVE', 'created_at' => '2026-10-08 00:00:00',
        ]);
        $concrete = $this->insertPolicy($organizationId, $resourceId, $groupId, $stateId);
        $this->assertGreaterThan($first, $concrete);
    }

    public function testUnprovenEffectBlocksBeforeColumnOrCleanup(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $organizationId = $this->insertOrganization();
        $groupId = $this->insertGroup();
        $resourceId = $this->resourceId('product');
        $actionId = $this->resourceActionId('product', 'view');
        $migration = new EnforcePolicyScopeUniqueness(Config\Database::forge($this->db));
        $migration->down();
        $first = $this->insertPolicy($organizationId, $resourceId, $groupId, null);
        $second = $this->insertPolicy($organizationId, $resourceId, $groupId, null);
        $this->insertAction($first, $actionId, 'ALLOW');
        $this->insertAction($second, $actionId, 'NOT_A_CANONICAL_EFFECT');

        $this->expectException(\RuntimeException::class);
        try {
            $migration->up();
        } finally {
            $this->assertSame(2, (int) $this->db->table('policies')->whereIn('id', [$first, $second])->countAllResults());
            $this->db->table('policy_actions')->whereIn('policy_id', [$first, $second])->delete();
            $this->db->table('policies')->whereIn('id', [$first, $second])->delete();
            $migration->up();
        }
    }

    private function insertPolicy(int $organizationId, int $resourceId, int $groupId, ?int $stateId): int
    {
        $this->db->table('policies')->insert([
            'organization_id' => $organizationId, 'resource_id' => $resourceId, 'state_id' => $stateId,
            'participant_type' => 'GROUP', 'participant_ref_id' => $groupId,
            'created_at' => '2026-10-08 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function insertAction(int $policyId, int $actionId, string $effect): void
    {
        $this->db->table('policy_actions')->insert([
            'policy_id' => $policyId, 'resource_action_id' => $actionId, 'effect' => $effect,
            'created_at' => '2026-10-08 00:00:00',
        ]);
    }

    private function insertOrganization(): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => 'CHG-' . uniqid(), 'name' => 'Change organization',
            'status' => 'ACTIVE', 'created_at' => '2026-10-08 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function insertGroup(): int
    {
        $this->db->table('groups')->insert([
            'code' => 'CHG-' . uniqid(), 'name' => 'Change group', 'status' => 'ACTIVE',
            'created_at' => '2026-10-08 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function resourceId(string $type): int
    {
        return (int) $this->db->table('resources')->where('type', $type)->get()->getRowArray()['id'];
    }

    private function resourceActionId(string $resource, string $action): int
    {
        return (int) $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)
            ->get()->getRowArray()['id'];
    }
}

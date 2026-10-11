<?php

declare(strict_types=1);

use App\Database\Migrations\SplitContactAuthorizationResources;
use App\Database\Seeds\ResourceActionSeeder;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal No legacy ALLOW may become a new owner-type ALLOW. */
final class ChangeContactAuth001PolicyMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function tearDown(): void
    {
        try {
            if ($this->db !== null && $this->db->tableExists($this->db->prefixTable('resources'), false)) {
                $rows = $this->db->table('resources')->select('id')
                    ->whereIn('type', ['contact', 'organization_contact', 'employee_contact'])
                    ->get()->getResultArray();
                $resourceIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
                if ($resourceIds !== []) {
                    $policyRows = $this->db->table('policies')->select('id')
                        ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
                    $policyIds = array_map(static fn (array $row): int => (int) $row['id'], $policyRows);
                    if ($policyIds !== []) {
                        $this->db->table('authorization_logs')->whereIn('matched_policy_id', $policyIds)->delete();
                        $this->db->table('policy_actions')->whereIn('policy_id', $policyIds)->delete();
                        $this->db->table('policies')->whereIn('id', $policyIds)->delete();
                    }
                    $this->db->table('resources')->where('type', 'contact')->update(['status' => 'ACTIVE']);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testOnlyRestrictivePolicyActionsAreCarriedToBothNewResources(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('organizations')->insert([
            'code' => uniqid('CONTACTMIG-O-'), 'name' => 'Migration GROUP',
            'type' => 'GROUP', 'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => uniqid('CONTACTMIG-G-'), 'name' => 'Migration group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $legacy = $this->resourceId('contact');
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $organizationId,
            'resource_id' => $legacy,
            'state_id' => null,
            'participant_type' => 'GROUP',
            'participant_ref_id' => $groupId,
        ], [
            ['resource_action_id' => $this->actionId('contact', 'view'), 'effect' => 'ABSOLUTE_DENY'],
            ['resource_action_id' => $this->actionId('contact', 'edit'), 'effect' => 'ALLOW'],
        ]);
        $migration = new SplitContactAuthorizationResources(Config\Database::forge($this->db));
        $migration->up();
        foreach (['organization_contact', 'employee_contact'] as $type) {
            $resourceId = $this->resourceId($type);
            $policy = $this->db->table('policies')->where('resource_id', $resourceId)->get()->getRowArray();
            $this->assertNotNull($policy);
            $this->assertSame($organizationId, (int) $policy['organization_id']);
            $actions = $this->db->table('policy_actions pa')->select('ra.code, pa.effect')
                ->join('resource_actions ra', 'ra.id = pa.resource_action_id')
                ->where('pa.policy_id', (int) $policy['id'])->get()->getResultArray();
            $this->assertCount(1, $actions);
            $this->assertSame('view', $actions[0]['code']);
            $this->assertSame('ABSOLUTE_DENY', $actions[0]['effect']);
            $this->assertSame(0, $this->db->table('group_permissions')
                ->where('resource_action_id', $this->actionId($type, 'view'))->countAllResults());
        }
        $this->assertSame('INACTIVE', $this->db->table('resources')
            ->where('type', 'contact')->get()->getRow('status'));
    }

    public function testLegacyLifecycleIsPreservedForBothOwnerResourcesWithoutCopyingAllow(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('lifecycles')->insert([
            'code' => 'CONTACT-LC-' . uniqid(), 'name' => 'Legacy Contact Lifecycle',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $lifecycleId = (int) $this->db->insertID();
        $this->db->table('workflow_states')->insert([
            'code' => strtoupper(uniqid('CONTACT-LC-INITIAL-')), 'name' => 'Initial',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $unusedId = (int) $this->db->insertID();
        // Native ACTIVE may already be seeded; keep its canonical ID.
        $activeState = $this->db->table('workflow_states')
            ->where('code', 'ACTIVE')->get()->getRowArray();
        if ($activeState === null) {
            $this->db->table('workflow_states')->insert([
                'code' => 'ACTIVE', 'name' => 'Active', 'status' => 'ACTIVE',
                'created_at' => '2026-10-11 00:00:00',
            ]);
            $activeStateId = (int) $this->db->insertID();
        } else {
            $activeStateId = (int) $activeState['id'];
        }
        $this->db->table('lifecycle_states')->insert([
            'lifecycle_id' => $lifecycleId, 'state_id' => $activeStateId,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $this->db->table('lifecycle_states')->insert([
            'lifecycle_id' => $lifecycleId, 'state_id' => $unusedId,
            'sequence' => 2, 'is_initial' => 0, 'is_final' => 1,
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $legacyId = $this->resourceId('contact');
        $this->db->table('resources')->where('id', $legacyId)
            ->update(['lifecycle_id' => $lifecycleId]);
        $this->db->table('organizations')->insert([
            'code' => uniqid('CONTACT-LC-O-'), 'name' => 'Migration Group',
            'type' => 'GROUP', 'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => uniqid('CONTACT-LC-G-'), 'name' => 'Contact policies',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $organizationId, 'resource_id' => $legacyId,
            'state_id' => $activeStateId, 'participant_type' => 'GROUP',
            'participant_ref_id' => $groupId,
        ], [
            ['resource_action_id' => $this->actionId('contact', 'view'), 'effect' => 'ABSOLUTE_DENY'],
            ['resource_action_id' => $this->actionId('contact', 'edit'), 'effect' => 'ALLOW'],
        ]);

        (new SplitContactAuthorizationResources(Config\Database::forge($this->db)))->up();
        foreach (['organization_contact', 'employee_contact'] as $type) {
            $resource = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            $this->assertSame($lifecycleId, (int) $resource['lifecycle_id']);
            $policy = $this->db->table('policies')
                ->where('resource_id', (int) $resource['id'])->get()->getRowArray();
            $this->assertSame($activeStateId, (int) $policy['state_id']);
            $actions = $this->db->table('policy_actions pa')
                ->select('pa.effect, ra.code')
                ->join('resource_actions ra', 'ra.id = pa.resource_action_id')
                ->where('pa.policy_id', (int) $policy['id'])->get()->getResultArray();
            $this->assertCount(1, $actions);
            $this->assertSame('ABSOLUTE_DENY', $actions[0]['effect']);
            $this->assertSame('view', $actions[0]['code']);
        }
        $this->assertSame($lifecycleId, (int) $this->db->table('resources')
            ->where('type', 'contact')->get()->getRow('lifecycle_id'));
    }

    public function testExistingTargetLifecycleConflictRollsBackWithoutRetiringLegacy(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $ids = [];
        foreach (['OLD', 'TARGET'] as $label) {
            $this->db->table('lifecycles')->insert([
                'code' => 'CONTACT-CLASH-' . $label . '-' . uniqid(),
                'name' => 'Contact Lifecycle', 'status' => 'ACTIVE',
                'created_at' => '2026-10-11 00:00:00',
            ]);
            $ids[$label] = (int) $this->db->insertID();
        }
        // The source must be valid so that the conflict is the actual blocker.
        $state = $this->db->table('workflow_states')->where('code', 'ACTIVE')->get()->getRowArray();
        if ($state === null) {
            $this->db->table('workflow_states')->insert([
                'code' => 'ACTIVE', 'name' => 'Active', 'status' => 'ACTIVE',
                'created_at' => '2026-10-11 00:00:00',
            ]);
            $stateId = (int) $this->db->insertID();
        } else {
            $stateId = (int) $state['id'];
        }
        $this->db->table('lifecycle_states')->insert([
            'lifecycle_id' => $ids['OLD'], 'state_id' => $stateId,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $this->db->table('resources')->where('type', 'contact')
            ->update(['lifecycle_id' => $ids['OLD'], 'status' => 'ACTIVE']);
        $this->db->table('resources')->where('type', 'employee_contact')
            ->update(['lifecycle_id' => $ids['TARGET']]);
        try {
            (new SplitContactAuthorizationResources(Config\Database::forge($this->db)))->up();
            $this->fail('Conflicting target Lifecycle must block migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflicting Lifecycle', $exception->getMessage());
        }
        $this->assertSame('ACTIVE', (string) $this->db->table('resources')
            ->where('type', 'contact')->get()->getRow('status'));
        $this->assertSame($ids['TARGET'], (int) $this->db->table('resources')
            ->where('type', 'employee_contact')->get()->getRow('lifecycle_id'));
    }

    public function testLegacyLifecycleWithUnsupportedInitialStateRemainsBlocked(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('lifecycles')->insert([
            'code' => 'CONTACT-INIT-' . uniqid(), 'name' => 'Legacy Contact Lifecycle',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $lifecycleId = (int) $this->db->insertID();
        $this->db->table('workflow_states')->insert([
            'code' => strtoupper(uniqid('CUSTOM-INIT-')), 'name' => 'Custom Initial',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $stateId = (int) $this->db->insertID();
        $this->db->table('lifecycle_states')->insert([
            'lifecycle_id' => $lifecycleId, 'state_id' => $stateId,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $this->db->table('resources')->where('type', 'contact')
            ->update(['lifecycle_id' => $lifecycleId]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('initial State requires review');
        (new SplitContactAuthorizationResources(Config\Database::forge($this->db)))->up();
    }

    private function resourceId(string $type): int
    {
        return (int) $this->db->table('resources')->where('type', $type)->get()->getRow('id');
    }

    private function actionId(string $type, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $type)->where('ra.code', $action)->get()->getRowArray();
        return (int) ($row['id'] ?? 0);
    }
}

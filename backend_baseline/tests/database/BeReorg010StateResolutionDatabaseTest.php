<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Repositories\PolicyRepository;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg009LifecycleFixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal Real DB state/policy regression on disposable erp_test only. */
final class BeReorg010StateResolutionDatabaseTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;
    protected $namespace = null;
    private RuntimeFixture $fixture;
    /** @var list<int> */
    private array $inactivePolicyIds = [];
    /** @var list<int> */
    private array $disabledStateIds = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->inactivePolicyIds as $id) {
                $this->db->table('policies')->where('id', $id)->update(['status' => 'ACTIVE']);
            }
            foreach ($this->disabledStateIds as $id) {
                $this->db->table('workflow_states')->where('id', $id)->update(['status' => 'ACTIVE']);
            }
            $this->db->resetDataCache();
        } finally {
            $this->resetServices();
            parent::tearDown();
        }
    }

    public function testInactivePolicyConflictAndAllStatesPolicyRemainDistinct(): void
    {
        $catalog = $this->db->table('resources')->where('type', 'province')->get()->getRowArray();
        $this->assertNotNull($catalog);
        $resourceId = (int) $catalog['id'];
        $action = $this->db->table('resource_actions')->where('resource_id', $resourceId)
            ->where('code', 'view')->get()->getRowArray();
        $this->assertNotNull($action);
        $policy = new PolicyRepository($this->db);
        $common = [
            'organization_id' => $this->fixture->group, 'resource_id' => $resourceId,
            'participant_type' => 'USER', 'participant_ref_id' => $this->fixture->reader,
        ];
        $actions = [['resource_action_id' => (int) $action['id'], 'effect' => 'ALLOW']];
        $scopedId = $policy->insertWithActions($common + ['state_id' => $this->fixture->states['INACTIVE']], $actions);
        $allStatesId = $policy->insertWithActions($common + ['state_id' => null], $actions);
        $this->assertTrue($this->db->table('policies')->where('id', $scopedId)->update(['status' => 'INACTIVE']));
        $this->inactivePolicyIds[] = $scopedId;
        $candidate = BeReorg009LifecycleFixture::lifecycle($this->db);
        BeReorg009LifecycleFixture::bind($this->db, $candidate, $this->fixture->states['CLOSED'], 1, true);
        $before = $this->db->table('resources')->where('id', $resourceId)->get()->getRowArray();
        $resolver = new ResourceWorkflowStateResolver(db: $this->db);
        try {
            $resolver->assertLifecycleReassignmentSafe('province', $candidate);
            $this->fail('Inactive State-scoped Policy must still prevent reassignment.');
        } catch (DomainException $error) {
            $this->assertSame('RESOURCE_LIFECYCLE_POLICY_CONFLICT', $error->getErrorCode());
            $this->assertSame(['resource' => 'province', 'policy_id' => $scopedId], $error->getContext());
        }
        $this->assertSame('INACTIVE', $policy->find($scopedId)['status']);
        $this->assertSame($before, $this->db->table('resources')->where('id', $resourceId)->get()->getRowArray());
        $this->assertTrue($this->db->table('policy_actions')->where('policy_id', $scopedId)->delete());
        $this->assertTrue($this->db->table('policies')->where('id', $scopedId)->delete());
        $this->inactivePolicyIds = array_values(array_diff($this->inactivePolicyIds, [$scopedId]));
        (new ResourceWorkflowStateResolver(db: $this->db))->assertLifecycleReassignmentSafe('province', $candidate);
        $this->assertNotNull($policy->find($allStatesId));
        $this->assertNull($policy->find($allStatesId)['state_id']);
        $this->assertSame($before, $this->db->table('resources')->where('id', $resourceId)->get()->getRowArray());
    }

    public function testFreshResolverObservesRealDatabaseLifecycleAndStateUpdates(): void
    {
        $reader = new ResourceWorkflowStateResolver(db: $this->db);
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $reader->allowedPolicyStateCodes('province'));
        $stateId = $this->fixture->states['CLOSED'];
        $this->assertTrue($this->db->table('workflow_states')->where('id', $stateId)->update(['status' => 'INACTIVE']));
        $this->disabledStateIds[] = $stateId;
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $reader->allowedPolicyStateCodes('province'),
            'A resolver can hold an in-request snapshot, not a global cross-request cache.');
        try {
            (new ResourceWorkflowStateResolver(db: $this->db))->allowedPolicyStateCodes('province');
            $this->fail('Fresh resolver must observe disabled State and fail closed.');
        } catch (DomainException $error) {
            $this->assertSame('WF_RESOURCE_LIFECYCLE_INVALID', $error->getErrorCode());
            $this->assertSame(['state_id' => $stateId], $error->getContext());
        }
        $this->assertTrue($this->db->table('workflow_states')->where('id', $stateId)->update(['status' => 'ACTIVE']));
        $this->disabledStateIds = [];
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'],
            (new ResourceWorkflowStateResolver(db: $this->db))->allowedPolicyStateCodes('province'));
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }
}

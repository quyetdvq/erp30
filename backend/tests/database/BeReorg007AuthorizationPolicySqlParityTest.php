<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\CommonListQuery;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Compare new SQL ownership to the unchanged legacy PBAC read surface. */
final class BeReorg007AuthorizationPolicySqlParityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    private int $organization;
    private int $actor;
    private int $group;
    private int $resource;
    private int $view;
    private int $edit;
    /** @var list<int> Policies whose test-only INACTIVE state must not block CI4 migration rollback. */
    private array $inactivePolicyIds = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        (new ResourceActionSeeder(new Database()))->run();
        $this->organization = $this->insert('organizations', [
            'code' => 'BE007-O-' . uniqid(), 'name' => 'Authorization root',
            'type' => 'GROUP', 'parent_id' => null, 'status' => 'ACTIVE',
        ]);
        $this->actor = $this->insert('users', [
            'username' => 'be007-reader-' . uniqid(), 'status' => 'active', 'active' => 1,
        ]);
        $this->group = $this->insert('groups', [
            'code' => 'BE007-G-' . uniqid(), 'name' => 'Policy participant', 'status' => 'ACTIVE',
        ]);
        $this->resource = (int) $this->db->table('resources')->where('type', 'product')->get()->getRowArray()['id'];
        $this->view = $this->action('view');
        $this->edit = $this->action('edit');
    }

    protected function tearDown(): void
    {
        // Restore the exact test-created policies before DatabaseTestTrait migrates down.
        foreach ($this->inactivePolicyIds as $id) {
            $this->db->table('policies')->where('id', $id)->where('status', 'INACTIVE')
                ->update(['status' => 'ACTIVE']);
        }
        parent::tearDown();
    }

    public function testApplicablePoliciesMatchLegacyAcrossNullAndConcreteState(): void
    {
        $legacy = new PolicyRepository($this->db);
        $canonical = new SqlPolicyRepository($this->db);
        $base = $this->scope();
        $general = $legacy->insertWithActions($base, [['resource_action_id' => $this->view, 'effect' => 'ALLOW']]);
        $state = $this->insert('workflow_states', ['code' => 'BE007-STATE-' . uniqid(), 'name' => 'State', 'status' => 'ACTIVE']);
        $specific = $legacy->insertWithActions([...$base, 'state_id' => $state], [['resource_action_id' => $this->view, 'effect' => 'DENY']]);
        $all = $legacy->findApplicablePolicyRows($this->resource, [$this->organization], $this->view, $state);
        $this->assertSame($all, $canonical->findApplicablePolicyRows($this->resource, [$this->organization], $this->view, $state));
        $this->assertSame([$general, $specific], array_map(static fn (array $entry): int => (int) $entry['policy']['id'], $all));
        $this->assertSame(['ALLOW', 'DENY'], array_column($all, 'effect'));
        $this->assertSame($legacy->findApplicablePolicyRows($this->resource, [$this->organization], $this->view, null),
            $canonical->findApplicablePolicyRows($this->resource, [$this->organization], $this->view, null));
        $this->assertSame([], $canonical->findApplicablePolicyRows($this->resource, [], $this->view, null));
    }

    public function testAuthorizedSqlPageAndVisibilityBucketsKeepTotalBeforeLimit(): void
    {
        $legacy = new PolicyRepository($this->db);
        $canonical = new SqlPolicyRepository($this->db);
        $legacy->insertWithActions($this->scope(), [['resource_action_id' => $this->view, 'effect' => 'ALLOW']]);
        $legacy->insertWithActions([...$this->scope(), 'participant_type' => 'GROUP', 'participant_ref_id' => $this->group],
            [['resource_action_id' => $this->view, 'effect' => 'DENY']]);
        $this->assertSame($legacy->findPolicyVisibilityBuckets(), $canonical->findPolicyVisibilityBuckets());
        $buckets = [['id' => 1, 'organization_id' => $this->organization, 'status' => 'ACTIVE']];
        $query = new CommonListQuery(null, 1, 1, 0, [], [], []);
        $this->assertTrue($canonical->supportsSqlPolicyList($query));
        $old = $legacy->findAuthorizedPolicyPage($query, $buckets);
        $this->assertSame($old, $canonical->findAuthorizedPolicyPage($query, $buckets));
        $this->assertSame(2, $old['total']);
        $this->assertCount(1, $old['rows']);
        $this->assertArrayNotHasKey('normalized_state_id', $old['rows'][0]);
    }

    public function testPolicyActionsAndBatchReadsStayOrdered(): void
    {
        $legacy = new PolicyRepository($this->db);
        $canonical = new SqlPolicyRepository($this->db);
        $first = $legacy->insertWithActions($this->scope(), [
            ['resource_action_id' => $this->view, 'effect' => 'ALLOW'],
            ['resource_action_id' => $this->edit, 'effect' => 'ABSOLUTE_DENY'],
        ]);
        $second = $legacy->insertWithActions([...$this->scope(), 'participant_type' => 'GROUP', 'participant_ref_id' => $this->group],
            [['resource_action_id' => $this->view, 'effect' => 'DENY']]);
        $this->assertSame($legacy->findPolicyActions($first), $canonical->findPolicyActions($first));
        $this->assertSame($legacy->findAllPolicyActionsForPolicies([$second, $first]),
            $canonical->findAllPolicyActionsForPolicies([$second, $first]));
        $this->assertSame($legacy->findPolicyActionsForPolicies([$first, $second], $this->view),
            $canonical->findPolicyActionsForPolicies([$first, $second], $this->view));
        $this->assertSame([], $canonical->findAllPolicyActionsForPolicies([]));
    }

    public function testScopeUniquenessAndSetStatusCompareAndSwapStayExplicit(): void
    {
        $legacy = new PolicyRepository($this->db);
        $canonical = new SqlPolicyRepository($this->db);
        $id = $legacy->insertWithActions($this->scope(), [['resource_action_id' => $this->view, 'effect' => 'ALLOW']]);
        try {
            $canonical->assertScopeAvailable($this->scope());
            $this->fail('Expected duplicate canonical Policy scope.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyRepository::ERROR_SCOPE_ALREADY_EXISTS, $exception->getErrorCode());
        }
        $operation = MutationContext::system(service('clock'))->begin();
        $this->inactivePolicyIds[] = $id;
        $this->assertTrue($canonical->setStatus($id, 'INACTIVE', $operation, 'ACTIVE'));
        $this->assertSame('INACTIVE', $legacy->find($id)['status']);
        $this->assertSame($canonical->findForUpdate($id), $legacy->findForUpdate($id));
    }

    /** @return array<string, mixed> */
    private function scope(): array
    {
        return [
            'organization_id' => $this->organization, 'resource_id' => $this->resource,
            'state_id' => null, 'participant_type' => 'USER', 'participant_ref_id' => $this->actor,
        ];
    }

    /** @param array<string, mixed> $data */
    private function insert(string $table, array $data): int
    {
        $this->assertTrue($this->db->table($table)->insert($data + ['created_at' => '2026-10-16 00:00:00']));
        return (int) $this->db->insertID();
    }

    private function action(string $code): int
    {
        $row = $this->db->table('resource_actions')->where('resource_id', $this->resource)->where('code', $code)->get()->getRowArray();
        $this->assertIsArray($row);
        return (int) $row['id'];
    }
}

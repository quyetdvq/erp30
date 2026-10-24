<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Bootstrap\FoundationFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Repositories\LifecycleRepository;
use App\Services\BusinessWorkflowWriteBoundary;
use App\Services\ResourceNativeStateCatalog;
use App\Services\ResourceWorkflowStateResolver;
use App\Services\WorkflowBusinessStatus;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture as ActionsFixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg011BusinessActionsFixture.php';

/** @internal Legacy service-level characterization against a disposable database. */
final class BeReorg012WriteBoundaryDatabaseTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;
    private RuntimeFixture $fixture;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
        ActionsFixture::rootWriter($this->db, $this->fixture);
    }

    /** @param array<string,mixed> $context */
    private function fails(string $code, array $context, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected DomainException ' . $code);
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
        }
    }

    public function testUnknownResourceOnReachablePathsFailsClosed(): void
    {
        $resource = 'be012_unknown_resource';
        $code = ResourceWorkflowStateResolver::ERROR_RESOURCE_LIFECYCLE_INVALID;
        $context = ['resource' => $resource];
        $rows = $this->db->table('provinces')->countAllResults();
        $this->fails($code, $context, fn () => BusinessWorkflowWriteBoundary::create($resource, ['x' => 1], $this->db));
        $this->fails($code, $context, fn () => BusinessWorkflowWriteBoundary::assertNoDirectStateMutation(
            $resource, ['status' => 'ACTIVE'], ['status' => 'INACTIVE'], $this->db));
        $this->fails($code, $context, fn () => WorkflowBusinessStatus::normalize(
            $resource, 'ACTIVE', ['ACTIVE'], 'STATUS_INVALID', $this->db));
        $this->assertSame($rows, $this->db->table('provinces')->countAllResults());
    }

    public function testTenNativeResourcesRemainIndependentAndRejectLinkage(): void
    {
        $types = ['authorization_group', 'authorization_log', 'display_setting', 'employee_assignment',
            'import_job', 'lifecycle', 'policy', 'user', 'workflow_state', 'resource'];
        $this->assertSame($types, ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
        $this->assertSame([], ResourceNativeStateCatalog::codes('authorization_log'));
        $this->assertSame([], ResourceNativeStateCatalog::codes('display_setting'));
        foreach ($types as $type) {
            $this->assertNull((new ResourceWorkflowStateResolver($this->db))->lifecycleId($type), $type);
        }
        $lifecycle = (new LifecycleRepository($this->db))->insertLifecycle([
            'code' => 'BE012-N-' . uniqid(), 'name' => 'Forbidden native binding', 'status' => 'ACTIVE',
        ]);
        $this->assertTrue($this->db->table('resources')->where('type', 'policy')->update(['lifecycle_id' => $lifecycle]));
        $this->fails('WF_LIFECYCLE_SELF_REFERENCE', ['resource' => 'policy'],
            fn () => BusinessWorkflowWriteBoundary::create('policy', ['status' => 'ACTIVE'], $this->db));
        $this->assertTrue($this->db->table('resources')->where('type', 'policy')->update(['lifecycle_id' => null]));
    }

    public function testBoundInitialStatePersistsWithoutClientOverride(): void
    {
        $service = FoundationFactory::service(
            db: $this->db,
            mutationContext: new MutationContext(RuntimeFixture::clock(), $this->fixture->writer, 'be012-db', false),
        );
        $initial = (new ResourceWorkflowStateResolver($this->db))->initialCode('province');
        $this->assertSame('ACTIVE', $initial);
        $before = $this->db->table('provinces')->countAllResults();
        $id = $service->createProvince(['code' => ActionsFixture::code('BE012D-'), 'name' => 'BE012 initial']);
        $this->assertSame($initial, $service->province($id)['status']);
        $this->assertSame($before + 1, $this->db->table('provinces')->countAllResults());
        $audit = ActionsFixture::auditCount($this->db);
        $this->fails('WF_INITIAL_STATE_INVALID', ['resource' => 'province', 'initial_status' => $initial],
            fn () => $service->createProvince([
                'code' => ActionsFixture::code('BE012F-'), 'name' => 'forged', 'status' => 'INACTIVE',
            ]));
        $this->assertSame($before + 1, $this->db->table('provinces')->countAllResults());
        $this->assertSame($audit, ActionsFixture::auditCount($this->db));
    }

    public function testInactiveOrNonmemberWorkflowStateIsRejected(): void
    {
        $resolver = new ResourceWorkflowStateResolver($this->db);
        $this->assertSame('ACTIVE', WorkflowBusinessStatus::normalize('province', ' active ', ['FAKE'], 'STATUS_INVALID', $this->db));
        $this->fails('STATUS_INVALID', ['status' => 'DRAFT'], fn () => WorkflowBusinessStatus::normalize(
            'province', 'DRAFT', ['DRAFT'], 'STATUS_INVALID', $this->db));
        $state = $this->fixture->states['CLOSED'];
        $this->assertTrue($this->db->table('workflow_states')->where('id', $state)->update(['status' => 'INACTIVE']));
        $this->fails('WF_RESOURCE_LIFECYCLE_INVALID', ['state_id' => $state], fn () => WorkflowBusinessStatus::normalize(
            'province', 'CLOSED', ['CLOSED'], 'STATUS_INVALID', $this->db));
        $this->assertTrue($this->db->table('workflow_states')->where('id', $state)->update(['status' => 'ACTIVE']));
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $resolver->allowedPolicyStateCodes('province'));
    }

    public function testNativeStatusCodesAndUnboundUpdatesRemainCompatible(): void
    {
        $this->assertNull((new ResourceWorkflowStateResolver($this->db))->lifecycleId('policy'));
        $this->assertSame(['x' => 1], BusinessWorkflowWriteBoundary::create('policy', ['x' => 1], $this->db));
        BusinessWorkflowWriteBoundary::assertNoDirectStateMutation(
            'policy', ['status' => 'ACTIVE'], ['status' => null], $this->db);
        $this->assertSame('INACTIVE', WorkflowBusinessStatus::normalize('policy', ' inactive ', ['INACTIVE'], 'STATUS_INVALID', $this->db));
        $this->fails('STATUS_INVALID', ['status' => 'ACTIVE'], fn () => WorkflowBusinessStatus::normalize(
            'policy', 'active', ['INACTIVE'], 'STATUS_INVALID', $this->db));
        $before = $this->db->table('provinces')->countAllResults();
        $this->assertSame($before, $this->db->table('provinces')->countAllResults());
    }
}

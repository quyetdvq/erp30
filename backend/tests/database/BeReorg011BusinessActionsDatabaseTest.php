<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\BusinessActions\Infrastructure\Persistence\SqlBusinessWorkflowRecordReader;
use App\Services\BusinessLifecycleTransitions;
use App\Services\BusinessWorkflowActions;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg011BusinessActionsFixture.php';

/** @internal Real guarded SQL record ownership and fallback parity. */
final class BeReorg011BusinessActionsDatabaseTest extends CIUnitTestCase
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
        Fixture::rootWriter($this->db, $this->fixture);
    }

    /** @param array<string, scalar|null> $context */
    private function domain(string $code, array $context, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected domain rejection: ' . $code);
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
        }
    }

    public function testTwelveResourceAllowlistAndPositiveIdGuard(): void
    {
        $records = new SqlBusinessWorkflowRecordReader($this->db);
        $map = (new ReflectionClass($records))->getReflectionConstant('TABLES')->getValue();
        $this->assertSame([
            'province', 'ward', 'organization', 'department', 'position', 'employee',
            'product_group', 'product_uom', 'product', 'price_list',
            'organization_contact', 'employee_contact',
        ], array_keys($map));
        $this->assertSame(11, count(array_unique(array_values($map))));
        foreach (array_keys($map) as $resource) {
            $records->assertBusinessResource($resource);
        }
        foreach (['user', 'policy', 'contact', 'Product', ' product ', 'products', 'weird`key', ''] as $resource) {
            $this->domain('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource' => $resource],
                static fn () => $records->assertBusinessResource($resource));
            $this->domain('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource' => $resource],
                static fn () => $records->record($resource, 1));
        }
        foreach ([0, -1] as $id) {
            $this->domain('WF_TARGET_NOT_FOUND', ['field' => 'target_id'],
                static fn () => $records->record('province', $id));
        }
        $this->domain('WF_TARGET_NOT_FOUND', ['target_id' => 2147483647],
            static fn () => $records->record('province', 2147483647));
        $id = $this->fixture->province(Fixture::code('BE011D-'));
        $row = $records->record('province', $id);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('ACTIVE', $row['status']);
    }

    public function testContactSplitMismatchCannotResolveOtherOwner(): void
    {
        $records = new SqlBusinessWorkflowRecordReader($this->db);
        $employee = $this->db->table('employees')->where('user_id', $this->fixture->writer)->get()->getRowArray();
        $this->assertNotNull($employee);
        $organizationId = Fixture::contact($this->db, 'ORGANIZATION', $this->fixture->company);
        $employeeId = Fixture::contact($this->db, 'EMPLOYEE', (int) $employee['id']);
        $this->assertSame('ORGANIZATION', $records->record('organization_contact', $organizationId)['contact_type']);
        $this->assertSame('EMPLOYEE', $records->record('employee_contact', $employeeId)['contact_type']);
        $this->domain('WF_TARGET_NOT_FOUND', ['target_id' => $employeeId],
            static fn () => $records->record('organization_contact', $employeeId));
        $this->domain('WF_TARGET_NOT_FOUND', ['target_id' => $organizationId],
            static fn () => $records->record('employee_contact', $organizationId));
        $this->assertSame('ORGANIZATION', $this->db->table('contacts')->where('id', $organizationId)->get()->getRowArray()['contact_type']);
    }

    public function testNativeFallbackOptionsAndStaticFalseForUnbound(): void
    {
        $this->assertTrue($this->db->table('resources')->where('type', 'province')->update(['lifecycle_id' => null]));
        $province = $this->fixture->province(Fixture::code('BE011N-'));
        $operation = $this->fixture->operation();
        $action = new BusinessWorkflowActions($this->db);
        $before = Fixture::status($this->db, 'provinces', $province);
        $options = $action->options('province', $province, $operation);
        $this->assertSame('NATIVE', $options['mode']);
        $this->assertSame('ACTIVE', $options['current']);
        $this->assertSame([
            ['action_code' => 'setstate', 'status' => 'INACTIVE'],
            ['action_code' => 'setstate', 'status' => 'CLOSED'],
        ], $options['options']);
        $this->assertFalse(BusinessLifecycleTransitions::applyIfBound('province', $province, 'INACTIVE', $operation, $this->db));
        $this->assertSame($before, Fixture::status($this->db, 'provinces', $province));
    }

    public function testExecutionReportsUnknownAndMissingTargetsAsBefore(): void
    {
        $operation = $this->fixture->operation();
        $action = new BusinessWorkflowActions($this->db);
        $this->domain('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource' => 'does_not_exist'],
            static fn () => $action->execute('does_not_exist', 1, 'setstate', $operation));
        $this->domain('WF_TARGET_NOT_FOUND', ['target_id' => 2147483647],
            static fn () => $action->execute('province', 2147483647, 'setstate', $operation));
        $this->domain('WF_TARGET_NOT_FOUND', ['field' => 'target_id'],
            static fn () => $action->execute('province', 0, 'setstate', $operation));
    }

    public function testInactiveTransitionOrDestinationNeverOffered(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011I-'));
        $lifecycle = (int) $this->db->table('resources')->where('type', 'province')->get()->getRowArray()['lifecycle_id'];
        $action = new BusinessWorkflowActions($this->db);
        $operation = $this->fixture->operation();
        $this->assertContains(['action_code' => 'setstate', 'status' => 'INACTIVE'],
            $action->options('province', $province, $operation)['options']);
        $this->assertTrue($this->db->table('lifecycle_transitions')->where('lifecycle_id', $lifecycle)
            ->where('action_code', 'setstate')->update(['status' => 'INACTIVE']));
        $this->assertSame([], (new BusinessWorkflowActions($this->db))->options('province', $province, $operation)['options']);
        $this->assertTrue($this->db->table('lifecycle_transitions')->where('lifecycle_id', $lifecycle)
            ->where('action_code', 'setstate')->update(['status' => 'ACTIVE']));
        $state = $this->fixture->states['INACTIVE'];
        try {
            $this->assertTrue($this->db->table('workflow_states')->where('id', $state)->update(['status' => 'INACTIVE']));
            // BE010's member cache fails closed when *any* Lifecycle member
            // is disabled. No invalid destination or authorization option
            // can be returned while the catalog is inconsistent.
            $this->domain('WF_RESOURCE_LIFECYCLE_INVALID', ['state_id' => $state],
                fn () => (new BusinessWorkflowActions($this->db))->options('province', $province, $operation));
        } finally {
            $this->db->table('workflow_states')->where('id', $state)->update(['status' => 'ACTIVE']);
        }
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $province));
    }
}

<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Exceptions\DomainException;
use App\Modules\Workflow\BusinessActions\Application\BusinessWorkflowActionService;
use App\Modules\Workflow\BusinessActions\Infrastructure\Persistence\SqlBusinessWorkflowRecordReader;
use App\Repositories\LifecycleRepository;
use App\Services\BusinessLifecycleTransitions;
use App\Services\BusinessWorkflowActions;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg011BusinessActionsFixture.php';

/** @internal Canonical/legacy behavior parity with trusted persisted SQL and PBAC. */
final class BeReorg011BusinessActionsApplicationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
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
        Fixture::rootActor($this->db, $this->fixture->reader, $this->fixture->group);
    }

    private function app(): BusinessWorkflowActionService
    {
        return new BusinessWorkflowActionService(
            $this->db,
            new SqlBusinessWorkflowRecordReader($this->db),
            new ResourceWorkflowStateResolver($this->db),
            new AuthorizationService(db: $this->db),
            new LifecycleRepository($this->db),
        );
    }

    /** @param array<string,scalar|null> $context */
    private function domain(string $code, array $context, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected ' . $code);
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
        }
    }

    public function testStatesRequiresBaseViewBeforeMetadata(): void
    {
        $operation = $this->fixture->operation($this->fixture->reader);
        $this->domain('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource' => 'policy'],
            fn () => $this->app()->states('policy', $operation));
        $this->domain('AUTH_POLICY_DENIED', ['resource' => 'product', 'action' => 'view'],
            fn () => $this->app()->states('product', $operation));
        $this->assertSame((new BusinessWorkflowActions($this->db))->states('province', $operation),
            $this->app()->states('province', $operation));
    }

    public function testStatesNativeAndLifecycleMetadataParity(): void
    {
        $operation = $this->fixture->operation();
        $app = $this->app();
        $original = new BusinessWorkflowActions($this->db);
        $lifecycle = $app->states('province', $operation);
        $this->assertSame('LIFECYCLE', $lifecycle['mode']);
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $lifecycle['states']);
        $this->assertSame('ACTIVE', $lifecycle['initial']);
        $this->assertSame($original->states('province', $operation), $lifecycle);
        $this->assertTrue($this->db->table('resources')->where('type', 'ward')->update(['lifecycle_id' => null]));
        $native = $this->app()->states('ward', $operation);
        $this->assertSame('NATIVE', $native['mode']);
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $native['states']);
        $this->assertSame($original->states('ward', $operation), $native);
    }

    public function testOptionsRequiresTargetViewAndSetstateSeparately(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011A-'));
        $reader = $this->fixture->operation($this->fixture->reader);
        $writer = $this->fixture->operation();
        $service = $this->app();
        $readonly = $service->options('province', $id, $reader);
        $this->assertSame('ACTIVE', $readonly['current']);
        $this->assertSame([], $readonly['options']);
        $allowed = $service->options('province', $id, $writer);
        $this->assertContains(['action_code' => 'setstate', 'status' => 'INACTIVE'], $allowed['options']);
        $this->fixture->deny('province', 'view', $this->fixture->reader, $this->fixture->states['ACTIVE']);
        $this->domain('AUTH_POLICY_DENIED', ['resource' => 'province', 'action' => 'view'],
            fn () => $this->app()->options('province', $id, $reader));
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $id));
    }

    public function testOptionsFiltersInactiveInvalidAndUnauthorizedTransitions(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011O-'));
        $row = $this->db->table('resources')->where('type', 'province')->get()->getRowArray();
        $this->assertNotNull($row);
        $lifecycle = (int) $row['lifecycle_id'];
        (new LifecycleRepository($this->db))->insertTransition([
            'lifecycle_id' => $lifecycle,
            'from_state_id' => $this->fixture->states['ACTIVE'],
            'to_state_id' => $this->fixture->states['CLOSED'],
            'action_code' => 'archive', 'status' => 'ACTIVE',
        ]);
        $options = $this->app()->options('province', $id, $this->fixture->operation());
        $this->assertContains(['action_code' => 'setstate', 'status' => 'INACTIVE'], $options['options']);
        $this->assertNotContains(['action_code' => 'archive', 'status' => 'CLOSED'], $options['options'],
            'An unregistered transition action must not be exposed.');
        $this->assertTrue($this->db->table('lifecycle_transitions')->where('lifecycle_id', $lifecycle)
            ->where('action_code', 'setstate')->update(['status' => 'INACTIVE']));
        $this->assertSame([], $this->app()->options('province', $id, $this->fixture->operation())['options']);
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $id));
    }

    public function testExecuteRejectsNativeResourceWithoutMutation(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011N-'));
        $this->assertTrue($this->db->table('resources')->where('type', 'province')->update(['lifecycle_id' => null]));
        $before = $this->db->table('provinces')->where('id', $id)->get()->getRowArray();
        $audit = Fixture::auditCount($this->db);
        $this->domain('WF_TRANSITION_REQUIRED', ['resource' => 'province'],
            fn () => $this->app()->execute('province', $id, 'setstate', $this->fixture->operation()));
        $this->assertSame($before, $this->db->table('provinces')->where('id', $id)->get()->getRowArray());
        $this->assertSame($audit, Fixture::auditCount($this->db));
    }

    public function testExecutePreservesPersistedStateSetstateAndActionCodeValidation(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011E-'));
        $reader = $this->fixture->operation($this->fixture->reader);
        $writer = $this->fixture->operation();
        $app = $this->app();
        $audit = Fixture::auditCount($this->db);
        $this->domain('AUTH_POLICY_DENIED', ['resource' => 'province', 'action' => 'setstate'],
            static fn () => $app->execute('province', $id, '1INVALID', $reader));
        $this->domain('WF_INVALID_TRANSITION', ['action_code' => '1invalid'],
            static fn () => $app->execute('province', $id, ' 1INVALID ', $writer));
        $this->assertSame($audit, Fixture::auditCount($this->db));
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $id));
        $result = $app->execute('province', $id, ' SeTsTaTe ', $writer);
        $this->assertSame(['status' => 'INACTIVE', 'action_code' => 'setstate'], $result);
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $id));
        $this->assertGreaterThan($audit, Fixture::auditCount($this->db));
    }

    public function testApplyIfBoundFalseForUnboundAndExpectedDestinationForBound(): void
    {
        $operation = $this->fixture->operation();
        $ward = $this->fixture->ward($this->fixture->province(Fixture::code('BE011P-')), Fixture::code('BE011W-'));
        $this->assertTrue($this->db->table('resources')->where('type', 'ward')->update(['lifecycle_id' => null]));
        $this->assertFalse($this->app()->applyIfBound('ward', $ward, 'CLOSED', $operation));
        $id = $this->fixture->province(Fixture::code('BE011B-'));
        $this->domain('WF_INVALID_TRANSITION', ['action_code' => 'setstate',
            'expected_destination_state_id' => $this->fixture->states['CLOSED']],
            fn () => $this->app()->applyIfBound('province', $id, 'CLOSED', $operation));
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $id));
        $this->assertTrue($this->app()->applyIfBound('province', $id, 'INACTIVE', $operation));
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $id));
        $this->assertTrue(BusinessLifecycleTransitions::applyIfBound('province', $this->fixture->province(Fixture::code('BE011C-')), 'INACTIVE', $operation, $this->db));
    }
}

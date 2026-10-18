<?php

declare(strict_types=1);

use App\Bootstrap\BusinessWorkflowActionFactory;
use App\Modules\Workflow\BusinessActions\Application\BusinessWorkflowActionService;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowRecordPort;
use App\Modules\Workflow\BusinessActions\Infrastructure\Persistence\SqlBusinessWorkflowRecordReader;
use App\Services\BusinessLifecycleTargetAdapter;
use App\Services\ContactLifecycleAdapter;
use App\Services\FoundationLocationLifecycleAdapter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Guarded real connection; factory itself must not start a transaction. */
final class BeReorg011BusinessActionsFactoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }

    private function field(object $owner, string $name): mixed
    {
        return (new ReflectionProperty($owner, $name))->getValue($owner);
    }

    public function testDefaultsReuseOneConnection(): void
    {
        $engine = BusinessWorkflowActionFactory::create(db: $this->db);
        $this->assertInstanceOf(BusinessWorkflowActionService::class, $engine);
        $this->assertSame($this->db, $this->field($engine, 'db'));
        $reader = $this->field($engine, 'records');
        $this->assertInstanceOf(SqlBusinessWorkflowRecordReader::class, $reader);
        $this->assertSame($this->db, $this->field($reader, 'db'));
        $lifecycle = $this->field($engine, 'lifecycles');
        $this->assertSame($this->db, $this->field($this->field($lifecycle, 'delegate'), 'db'));
        $state = $this->field($engine, 'states');
        $this->assertSame($this->db, $this->field($this->field($this->field($this->field($state, 'engine'), 'reader'), 'resources'), 'db'));
    }

    public function testInjectedRecordPortUsedAsIs(): void
    {
        $port = new class implements BusinessWorkflowRecordPort {
            public array $seen = [];
            public function assertBusinessResource(string $resource): void { $this->seen[] = $resource; }
            public function record(string $resource, int $id): array
            { $this->seen[] = [$resource, $id]; return ['id' => $id, 'status' => 'ACTIVE']; }
        };
        $engine = BusinessWorkflowActionFactory::create(db: $this->db, records: $port);
        $this->assertSame($port, $this->field($engine, 'records'));
        $engineRecords = $this->field($engine, 'records');
        $this->assertSame(['id' => 71, 'status' => 'ACTIVE'], $engineRecords->record('product', 71));
        $this->assertSame([['product', 71]], $port->seen);
        $this->assertNotSame($port, $this->field(BusinessWorkflowActionFactory::create($this->db), 'records'));
    }

    public function testFactoryDoesNotCacheBetweenInvocations(): void
    {
        $a = BusinessWorkflowActionFactory::create($this->db);
        $b = BusinessWorkflowActionFactory::create($this->db);
        $this->assertNotSame($a, $b);
        foreach (['records', 'states', 'authorization', 'lifecycles'] as $property) {
            $this->assertNotSame($this->field($a, $property), $this->field($b, $property), $property);
        }
        $this->assertSame([], (new ReflectionClass(BusinessWorkflowActionFactory::class))->getProperties());
        $this->assertFalse((new ReflectionClass(BusinessWorkflowActionFactory::class))->getConstructor()->isPublic());
    }

    public function testAdapterSelectionUsesCorrectExistingLegacyTypes(): void
    {
        $engine = BusinessWorkflowActionFactory::create($this->db);
        $select = new ReflectionMethod($engine, 'adapter');
        foreach (['province' => FoundationLocationLifecycleAdapter::class,
            'ward' => FoundationLocationLifecycleAdapter::class,
            'organization_contact' => ContactLifecycleAdapter::class,
            'employee_contact' => ContactLifecycleAdapter::class,
            'product' => BusinessLifecycleTargetAdapter::class,
        ] as $resource => $expected) {
            $adapter = $select->invoke($engine, $resource);
            $this->assertInstanceOf($expected, $adapter);
            $this->assertSame($resource, $adapter->resourceType());
        }
    }
}

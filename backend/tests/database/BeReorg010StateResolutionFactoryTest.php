<?php

declare(strict_types=1);

use App\Bootstrap\WorkflowStateResolutionFactory;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService;
use App\Modules\Workflow\StateResolution\Infrastructure\Persistence\ExistingRepositoryStateReadAdapter;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Workflow\BeReorg009LifecycleFixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal BE010 factory and typed legacy persistence adapter on guarded DB. */
final class BeReorg010StateResolutionFactoryTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;
    protected $namespace = null;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }

    private function prop(object $object, string $key): mixed
    {
        return (new ReflectionProperty($object, $key))->getValue($object);
    }

    public function testFactoryCreatesFreshEngineReaderAndEmptyCaches(): void
    {
        $a = WorkflowStateResolutionFactory::create(db: $this->db);
        $b = WorkflowStateResolutionFactory::create(db: $this->db);
        $this->assertInstanceOf(ResourceStateResolutionPort::class, $a);
        $this->assertInstanceOf(ResourceStateResolutionService::class, $a);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($this->prop($a, 'reader'), $this->prop($b, 'reader'));
        foreach (['resourceCache', 'memberCodes', 'nativeIds'] as $property) {
            $this->assertSame([], $this->prop($a, $property));
            $this->assertSame([], $this->prop($b, $property));
        }
        $factory = new ReflectionClass(WorkflowStateResolutionFactory::class);
        $this->assertSame([], $factory->getProperties());
        $this->assertFalse($factory->getConstructor()->isPublic());
    }

    public function testFactoryPreservesEveryInjectedRepositoryIdentity(): void
    {
        $resources = new ResourceCatalogRepository($this->db);
        $lifecycles = new LifecycleRepository($this->db);
        $policies = new PolicyRepository($this->db);
        $engine = WorkflowStateResolutionFactory::create($this->db, $resources, $lifecycles, $policies);
        $reader = $this->prop($engine, 'reader');
        $this->assertInstanceOf(ExistingRepositoryStateReadAdapter::class, $reader);
        foreach (['resources' => $resources, 'lifecycles' => $lifecycles, 'policies' => $policies] as $name => $actual) {
            $this->assertSame($actual, $this->prop($reader, $name), $name);
        }
        $this->assertNotSame($engine, WorkflowStateResolutionFactory::create($this->db, $resources, $lifecycles, $policies));
    }

    public function testFactoryDefaultsUseExactlyTheSuppliedDbConnection(): void
    {
        $engine = WorkflowStateResolutionFactory::create(db: $this->db);
        $reader = $this->prop($engine, 'reader');
        $this->assertSame($this->db, $this->prop($this->prop($reader, 'resources'), 'db'));
        $lifecycle = $this->prop($this->prop($reader, 'lifecycles'), 'delegate');
        $this->assertSame($this->db, $this->prop($lifecycle, 'db'));
        $policy = $this->prop($this->prop($reader, 'policies'), 'delegate');
        $this->assertSame($this->db, $this->prop($policy, 'db'));
        $this->assertSame(['db', 'resources', 'lifecycles', 'policies'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(),
                (new ReflectionMethod(WorkflowStateResolutionFactory::class, 'create'))->getParameters()));
    }

    public function testReadAdapterPassesThroughAllEightReadsIncludingInactivePolicies(): void
    {
        $fixture = new RuntimeFixture($this->db);
        $resources = new ResourceCatalogRepository($this->db);
        $lifecycles = new LifecycleRepository($this->db);
        $policies = new PolicyRepository($this->db);
        $reader = new ExistingRepositoryStateReadAdapter($resources, $lifecycles, $policies);
        $province = $resources->findResourceByType('province');
        $this->assertNotNull($province);
        $this->assertSame($province, $reader->findResourceByType('province'));
        $this->assertSame($province, $reader->findResource((int) $province['id']));
        $lifecycleId = BeReorg009LifecycleFixture::lifecycle($this->db);
        $stateId = BeReorg009LifecycleFixture::state($this->db);
        BeReorg009LifecycleFixture::bind($this->db, $lifecycleId, $stateId, 1, true);
        $lifecycle = $lifecycles->findLifecycle($lifecycleId);
        $state = $lifecycles->findActiveWorkflowState($stateId);
        $this->assertSame($lifecycle, $reader->findLifecycle($lifecycleId));
        $this->assertSame($lifecycles->findLifecycleStates($lifecycleId), $reader->findLifecycleStates($lifecycleId));
        $this->assertSame($state, $reader->findActiveWorkflowState($stateId));
        $this->assertSame($lifecycles->findActiveWorkflowStateByCode((string) $state['code']),
            $reader->findActiveWorkflowStateByCode((string) $state['code']));
        $this->assertSame($lifecycles->findWorkflowStateByCode((string) $state['code']),
            $reader->findWorkflowStateByCode((string) $state['code']));
        $action = $this->db->table('resource_actions')->where('resource_id', (int) $province['id'])
            ->where('code', 'view')->get()->getRowArray();
        $this->assertNotNull($action);
        $inactiveId = $policies->insertWithActions([
            'organization_id' => $fixture->group, 'resource_id' => (int) $province['id'],
            'state_id' => $fixture->states['ACTIVE'],
            'participant_type' => 'USER', 'participant_ref_id' => $fixture->reader,
        ], [['resource_action_id' => (int) $action['id'], 'effect' => 'ALLOW']]);
        $this->assertTrue($this->db->table('policies')->where('id', $inactiveId)->update(['status' => 'INACTIVE']));
        try {
            $actual = $reader->findAllPoliciesForResource((int) $province['id']);
            $this->assertSame($policies->findAll(['resource_id' => (int) $province['id']]), $actual);
            $matches = array_values(array_filter($actual, static fn (array $row): bool => (int) $row['id'] === $inactiveId));
            $this->assertCount(1, $matches);
            $this->assertSame('INACTIVE', $matches[0]['status']);
        } finally {
            $this->db->table('policies')->where('id', $inactiveId)->update(['status' => 'ACTIVE']);
        }
        $source = (string) file_get_contents((new ReflectionClass($reader))->getFileName());
        $this->assertStringContainsString("findAll(['resource_id' => \$resourceId])", $source);
        $this->assertStringNotContainsString("'status' => 'ACTIVE'", $source);
    }
}

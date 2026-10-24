<?php

declare(strict_types=1);

use App\Bootstrap\WorkflowWriteBoundaryFactory;
use App\Exceptions\DomainException;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\WriteBoundary\Application\WorkflowWriteBoundaryService;
use App\Modules\Workflow\WriteBoundary\Infrastructure\Adapters\ExistingStateResolutionLookupAdapter;
use App\Services\InitialCreateStateNormalizer;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Factory identity, per-call isolation and adapter dataflow on guarded erp_test. */
final class BeReorg012WriteBoundaryFactoryTest extends CIUnitTestCase
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

    private function field(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }

    public function testFactoryCreatesFreshEngineWithoutSharedCache(): void
    {
        $first = WorkflowWriteBoundaryFactory::create(db: $this->db);
        $second = WorkflowWriteBoundaryFactory::create(db: $this->db);
        $this->assertInstanceOf(WorkflowWriteBoundaryService::class, $first);
        $this->assertNotSame($first, $second);
        $adapter1 = $this->field($first, 'states');
        $adapter2 = $this->field($second, 'states');
        $this->assertNotSame($adapter1, $adapter2);
        $engine1 = $this->field($adapter1, 'states');
        $engine2 = $this->field($adapter2, 'states');
        $this->assertNotSame($engine1, $engine2);
        $this->assertSame([], $this->field($engine1, 'resourceCache'));
        $this->assertSame([], $this->field($engine2, 'resourceCache'));
        $this->assertSame([], (new ReflectionClass(WorkflowWriteBoundaryFactory::class))->getProperties());
    }

    public function testFactoryPassesProvidedDbToBe010StateResolution(): void
    {
        $application = WorkflowWriteBoundaryFactory::create($this->db);
        $engine = $this->field($this->field($application, 'states'), 'states');
        $reader = $this->field($engine, 'reader');
        $this->assertSame($this->db, $this->field($this->field($reader, 'resources'), 'db'));
        $this->assertSame($this->db, $this->field($this->field($this->field($reader, 'lifecycles'), 'delegate'), 'db'));
        $this->assertSame($this->db, $this->field($this->field($this->field($reader, 'policies'), 'delegate'), 'db'));
    }

    public function testFactoryReusesExactInjectedLegacyResolver(): void
    {
        // The legacy resolver is a real BE010 reader: seed a valid Resource/Lifecycle first.
        new RuntimeFixture($this->db);
        $resolver = new ResourceWorkflowStateResolver($this->db);
        $application = WorkflowWriteBoundaryFactory::create(states: $resolver);
        $adapter = $this->field($application, 'states');
        $this->assertSame($resolver, $this->field($adapter, 'states'));
        $normalizer = new InitialCreateStateNormalizer($resolver);
        $this->assertSame($resolver, $this->field($normalizer, 'states'));
        $this->assertSame('ACTIVE', $normalizer->normalize('province', [])['status'] ?? null);
    }

    public function testAdapterDelegatesThreeReadsWithoutMutation(): void
    {
        $source = $this->createMock(ResourceStateResolutionPort::class);
        $source->expects($this->once())->method('lifecycleId')->with('province')->willReturn(42);
        $source->expects($this->once())->method('allowedPolicyStateCodes')->with('province')->willReturn(['DRAFT']);
        $source->expects($this->once())->method('initialCode')->with('province')->willReturn('DRAFT');
        $adapter = new ExistingStateResolutionLookupAdapter($source);
        $this->assertSame(42, $adapter->lifecycleId('province'));
        $this->assertSame(['DRAFT'], $adapter->allowedPolicyStateCodes('province'));
        $this->assertSame('DRAFT', $adapter->initialCode('province'));
        $error = new DomainException('WF_RESOURCE_LIFECYCLE_INVALID', ['resource' => 'missing']);
        $failing = $this->createMock(ResourceStateResolutionPort::class);
        $failing->method('lifecycleId')->willThrowException($error);
        try {
            (new ExistingStateResolutionLookupAdapter($failing))->lifecycleId('missing');
            $this->fail('Expected BE010 error propagated by identity.');
        } catch (DomainException $actual) {
            $this->assertSame($error, $actual);
        }
    }

    public function testFactoryDoesNotReadDatabaseOnConstruction(): void
    {
        $before = (string) $this->db->getLastQuery();
        $service = WorkflowWriteBoundaryFactory::create(db: $this->db);
        $this->assertSame($before, (string) $this->db->getLastQuery());
        $this->assertSame([], $this->field($this->field($this->field($service, 'states'), 'states'), 'resourceCache'));
    }
}

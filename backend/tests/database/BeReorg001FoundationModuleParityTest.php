<?php

declare(strict_types=1);

use App\Bootstrap\FoundationFactory;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\ConcurrencyException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Foundation\Application\LocationService;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use App\Repositories\FoundationLocationRepository;
use App\Repositories\LifecycleRepository;
use App\Services\FoundationLocationLifecycleAdapter;
use App\Services\FoundationLocationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/** @internal Wiring coverage supplements, never replaces, baseline characterization. */
final class BeReorg001FoundationModuleParityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('resources')->whereIn('type', ['province', 'ward'])->update(['lifecycle_id' => null]);
    }

    /** @return array<string,array{string}> */
    public static function entrypoints(): array
    {
        return ['legacy' => ['legacy'], 'module' => ['module'], 'factory' => ['factory']];
    }

    #[DataProvider('entrypoints')]
    public function testCrudAndRepositoryInteropHaveFixedExpectedResults(string $entrypoint): void
    {
        $repository = new SqlLocationRepository(db: $this->db);
        $context = $this->context(42);
        $service = match ($entrypoint) {
            'legacy' => new FoundationLocationService(db: $this->db, mutationContext: $context),
            'module' => new LocationService(repository: $repository, db: $this->db, mutationContext: $context),
            'factory' => FoundationFactory::service(db: $this->db, mutationContext: $context),
        };
        $province = $service->createProvince(['code' => 'BE001-PARITY-P', 'name' => 'Province', 'status' => 'ACTIVE']);
        $ward = $service->createWard(['province_id' => $province, 'code' => 'BE001-PARITY-W', 'name' => 'Ward', 'status' => 'ACTIVE']);
        $legacyRepository = new FoundationLocationRepository(db: $this->db);
        $this->assertSame($repository->find('wards', $ward), $legacyRepository->find('wards', $ward));
        $this->assertTrue($repository->provinceExists($province));
        $this->assertTrue($legacyRepository->wardBelongsToProvince($ward, $province));
        $this->assertSame($ward, (int) $repository->findByCode('wards', 'BE001-PARITY-W')['id']);
        $this->assertSame([$ward], array_map(static fn (array $row): int => (int) $row['id'], $repository->findAll('wards')));
        $this->assertTrue($service->setWardState($ward, ' inactive '));
        $row = $service->ward($ward);
        $this->assertSame('INACTIVE', $row['status']);
        $this->assertSame(42, (int) $row['created_by']);
        $this->assertSame(42, (int) $row['updated_by']);
        $this->assertSame('2026-10-16 02:30:00', $row['created_at']);
        $this->assertSame('2026-10-16 02:30:00', $row['updated_at']);
    }

    public function testFactoryDoesNotReuseAnActorContextAcrossInstances(): void
    {
        $first = FoundationFactory::service(db: $this->db, mutationContext: $this->context(42));
        $second = FoundationFactory::service(db: $this->db, mutationContext: $this->context(84));
        $this->assertNotSame($first, $second);
        $firstId = $first->createProvince(['code' => 'BE001-ACTOR-1', 'name' => 'First', 'status' => 'ACTIVE']);
        $secondId = $second->createProvince(['code' => 'BE001-ACTOR-2', 'name' => 'Second', 'status' => 'ACTIVE']);
        $this->assertSame(42, (int) $first->province($firstId)['created_by']);
        $this->assertSame(84, (int) $second->province($secondId)['created_by']);
    }

    public function testExplicitConnectionIsUsedForBothServiceAndRepositoryInsideCallerTransaction(): void
    {
        $connection = Config\Database::connect('tests', false);
        $this->assertNotSame($this->db, $connection);
        $this->assertTrue($connection->transBegin());
        try {
            $service = FoundationFactory::service(db: $connection, mutationContext: $this->context(42));
            $id = $service->createProvince(['code' => 'BE001-TX-CONNECTION', 'name' => 'Transient', 'status' => 'ACTIVE']);
            $this->assertSame('Transient', $service->province($id)['name']);
        } finally {
            $connection->transRollback();
            $connection->close();
        }
        $this->assertSame(0, $this->db->table('provinces')->where('code', 'BE001-TX-CONNECTION')->countAllResults());
    }

    public function testFactoryAndLegacyAdapterShareSnapshotAndConfiguredStateMutation(): void
    {
        $states = $this->bindLifecycleAndGroup();
        $service = FoundationFactory::service(db: $this->db, mutationContext: $this->context(42));
        $province = $service->createProvince(['code' => 'BE001-ADAPTER', 'name' => 'Adapter province', 'status' => 'ACTIVE']);
        $operation = $this->context(42)->begin();
        $legacy = new FoundationLocationLifecycleAdapter(type: 'province', db: $this->db);
        $adapter = FoundationFactory::lifecycleAdapter(type: 'province', db: $this->db);
        $before = $adapter->load($province, $operation);
        $this->assertEquals($legacy->load($province, $operation), $before);
        $this->assertSame('province', $adapter->resourceType());
        $this->assertSame($states['ACTIVE'], $before->currentStateId);
        $result = $adapter->transactional(function () use ($adapter, $before, $operation, $states): string {
            $locked = $adapter->lockAndReload($before, $operation);
            $adapter->mutate($locked, $states['ACTIVE'], $states['INACTIVE'], $operation);
            return 'committed';
        });
        $this->assertSame('committed', $result);
        $row = $service->province($province);
        $this->assertSame('INACTIVE', $row['status']);
        $this->assertSame(42, (int) $row['updated_by']);
        $this->assertSame('2026-10-16 02:30:00', $row['updated_at']);
        // Even with a stale snapshot, the persisted status predicate must block a second write.
        try {
            $adapter->mutate($before, $states['ACTIVE'], $states['INACTIVE'], $operation);
            $this->fail('A stale persisted source status must be rejected.');
        } catch (ConcurrencyException $exception) {
            $this->assertSame(['operation' => 'lifecycle.state_change'], $exception->getContext());
        }
    }

    public function testAdapterCallbackFailureRollsBackChangesOnItsExplicitConnection(): void
    {
        $service = FoundationFactory::service(db: $this->db, mutationContext: $this->context(42));
        $id = $service->createProvince(['code' => 'BE001-ROLLBACK', 'name' => 'Before', 'status' => 'ACTIVE']);
        $adapter = FoundationFactory::lifecycleAdapter(type: 'province', db: $this->db);
        $failure = new RuntimeException('Roll back this callback');
        try {
            $adapter->transactional(function () use ($service, $id, $failure): never {
                $service->updateProvince($id, ['name' => 'Must roll back']);
                throw $failure;
            });
            $this->fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertSame('Before', $service->province($id)['name']);
    }

    private function context(int $actor): MutationContext
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T02:30:00Z'); }
        };
        return new MutationContext($clock, $actor, 'be-reorg-001-' . $actor, false);
    }

    /** @return array<string,int> */
    private function bindLifecycleAndGroup(): array
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'BE001-GROUP', 'name' => 'Workflow group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00',
        ]);
        $repository = new LifecycleRepository($this->db);
        $lifecycle = $repository->insertLifecycle(['code' => 'BE001-PARITY-LC', 'name' => 'Locations', 'status' => 'ACTIVE']);
        $states = [];
        foreach (['ACTIVE', 'INACTIVE'] as $sequence => $code) {
            $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $states[$code] = $row === null
                ? $repository->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $row['id'];
            $repository->insertLifecycleState([
                'lifecycle_id' => $lifecycle, 'state_id' => $states[$code], 'sequence' => $sequence + 1,
                'is_initial' => $sequence === 0 ? 1 : 0, 'is_final' => 0,
            ]);
        }
        $this->db->table('resources')->whereIn('type', ['province', 'ward'])->update(['lifecycle_id' => $lifecycle]);
        return $states;
    }
}

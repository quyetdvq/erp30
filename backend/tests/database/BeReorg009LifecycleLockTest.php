<?php

declare(strict_types=1);

use App\Bootstrap\FoundationFactory;
use App\Exceptions\ConcurrencyException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService;
use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg009Audit;
use Tests\Support\Workflow\BeReorg009Authorization;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal Real InnoDB row locking and adapter-owned rollback; never mocks a lock. */
final class BeReorg009LifecycleLockTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    private RuntimeFixture $fixture;
    /** @var list<BaseConnection<mixed,mixed>> */
    private array $peers = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->fixture = new RuntimeFixture($this->db);
        $engine = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$this->db->prefixTable('lifecycles')])->getRowArray();
        $this->assertNotNull($engine);
        $this->assertSame('INNODB', strtoupper((string) $engine['ENGINE']));
    }

    protected function tearDown(): void
    {
        foreach ($this->peers as $peer) {
            try {
                $peer->transRollback();
            } finally {
                $peer->close();
            }
        }
        $this->peers = [];
        parent::tearDown();
    }

    public function testLifecycleForUpdateLocksOnlyOnSharedSuppliedSessionUntilRelease(): void
    {
        $lifecycle = \Tests\Support\Workflow\BeReorg009LifecycleFixture::lifecycle($this->db);
        $holder = db_connect('tests', false);
        $contender = db_connect('tests', false);
        $this->peers = [$holder, $contender];
        $a = $holder->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $b = $contender->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $this->assertSame('erp_test', $a['db']);
        $this->assertSame('erp_test', $b['db']);
        $this->assertNotSame((string) $a['id'], (string) $b['id']);
        $holder->query('SET SESSION innodb_lock_wait_timeout=2');
        $contender->query('SET SESSION innodb_lock_wait_timeout=2');
        $holder->transStrict(false);
        $contender->transStrict(false);
        $repositoryA = new SqlLifecycleRepository($holder);
        $repositoryB = new SqlLifecycleRepository($contender);
        $this->assertTrue($holder->transBegin());
        $this->assertSame($lifecycle, (int) $repositoryA->lockLifecycle($lifecycle)['id']);
        $this->assertTrue($contender->transBegin());
        $failure = null;
        try {
            $repositoryB->lockLifecycle($lifecycle);
        } catch (Throwable $error) {
            $failure = $error;
        }
        $this->assertNotNull($failure, 'A distinct SQL session acquired a held lifecycle lock.');
        $codes = [(int) ($contender->error()['code'] ?? 0)];
        for ($e = $failure; $e !== null; $e = $e->getPrevious()) {
            $codes[] = (int) $e->getCode();
        }
        $this->assertContains(1205, $codes, 'Require server MySQL code 1205, not a timer proxy.');
        $this->assertTrue($contender->transRollback());
        $contender->resetTransStatus();
        $this->assertTrue($holder->transCommit());
        $this->assertTrue($contender->transBegin());
        $this->assertSame($lifecycle, (int) $repositoryB->lockLifecycle($lifecycle)['id']);
        $this->assertTrue($contender->transRollback());
    }

    public function testTargetAdapterStaleStateRaisesConcurrencyAndRollsBack(): void
    {
        $code = 'BE009L-' . uniqid();
        $this->assertLessThanOrEqual(20, strlen($code), 'Fixture must fit provinces.code VARCHAR(20).');
        $province = $this->fixture->province($code);
        $delegate = FoundationFactory::lifecycleAdapter('province', db: $this->db);
        $adapter = new class($delegate) implements LifecycleTargetAdapter {
            public bool $stale = true;
            public int $mutations = 0;

            public function __construct(private readonly LifecycleTargetAdapter $delegate)
            {
            }

            public function resourceType(): string
            {
                return $this->delegate->resourceType();
            }

            public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
            {
                return $this->delegate->load($targetId, $operation);
            }

            public function transactional(callable $callback): mixed
            {
                return $this->delegate->transactional($callback);
            }

            public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
            {
                $locked = $this->delegate->lockAndReload($authorizedTarget, $operation);
                if (! $this->stale) {
                    return $locked;
                }
                return new LifecycleTargetSnapshot($locked->resourceType, $locked->targetId, $locked->currentStateId,
                    $locked->lifecycleId, $locked->evaluationOrganizationId + 1, $locked->authorizationTarget);
            }

            public function mutate(LifecycleTargetSnapshot $lockedTarget, int $expectedCurrentStateId, int $destinationStateId, OperationContext $operation): void
            {
                ++$this->mutations;
                $this->delegate->mutate($lockedTarget, $expectedCurrentStateId, $destinationStateId, $operation);
            }
        };
        $audit = new BeReorg009Audit();
        $service = new LifecycleApplicationService(
            authorization: new BeReorg009Authorization(), audit: $audit, adapters: [$adapter], db: $this->db,
        );
        $operation = $this->fixture->operation();
        $before = $this->db->table('provinces')->where('id', $province)->get()->getRowArray();
        try {
            $service->execute('province', $province, 'setstate', $operation);
            $this->fail('Stale trusted organization requires a concurrency exception.');
        } catch (ConcurrencyException $error) {
            $this->assertSame(['operation' => 'lifecycle.state_change'], $error->getContext());
        }
        $this->assertSame($before, $this->db->table('provinces')->where('id', $province)->get()->getRowArray());
        $this->assertSame(0, $adapter->mutations);
        $this->assertSame(0, $audit->calls);
        $adapter->stale = false;
        $service->execute('province', $province, 'setstate', $operation);
        $this->assertSame(1, $adapter->mutations);
        $this->assertSame('INACTIVE', $this->db->table('provinces')->where('id', $province)->get()->getRowArray()['status']);
        $this->assertSame(1, $audit->calls);
    }
}

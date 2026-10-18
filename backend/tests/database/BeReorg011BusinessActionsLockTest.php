<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Exceptions\PersistenceException;
use App\Bootstrap\BusinessWorkflowActionFactory;
use App\Bootstrap\WorkflowStateResolutionFactory;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Foundation\Contracts\LocationErrors;
use App\Services\BusinessWorkflowActions;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg011BusinessActionsFixture.php';

/** @internal InnoDB 1205 and real peer-written stale State, on erp_test only. */
final class BeReorg011BusinessActionsLockTest extends CIUnitTestCase
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
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
        Fixture::rootWriter($this->db, $this->fixture);
        $row = $this->db->query(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$this->db->prefixTable('provinces')],
        )->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('INNODB', strtoupper((string) $row['ENGINE']));
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->peers as $peer) {
                try {
                    $peer->transRollback();
                } finally {
                    $peer->close();
                }
            }
        } finally {
            $this->peers = [];
            parent::tearDown();
        }
    }

    private function peer(): BaseConnection
    {
        $peer = db_connect('tests', false);
        $this->peers[] = $peer;
        $row = $peer->query('SELECT DATABASE() AS db, CONNECTION_ID() AS id')->getRowArray();
        $this->assertSame('erp_test', $row['db']);
        $this->assertNotSame((string) $this->db->query('SELECT CONNECTION_ID() AS id')->getRowArray()['id'], (string) $row['id']);
        $peer->transStrict(false);
        $peer->query('SET SESSION innodb_lock_wait_timeout=2');
        return $peer;
    }

    public function testRealInnoDbLockTimeoutDoesNotMutateOrAudit(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011L-'));
        $holder = $this->peer();
        $contender = $this->peer();
        $this->assertNotSame((string) $holder->query('SELECT CONNECTION_ID() AS id')->getRowArray()['id'],
            (string) $contender->query('SELECT CONNECTION_ID() AS id')->getRowArray()['id']);
        $beforeAudit = Fixture::auditCount($this->db);
        $this->assertTrue($holder->transBegin());
        $table = str_replace('`', '``', $holder->prefixTable('provinces'));
        $locked = $holder->query("SELECT id FROM `{$table}` WHERE id=? FOR UPDATE", [$id])->getRowArray();
        $this->assertSame($id, (int) $locked['id']);
        $error = null;
        try {
            (new BusinessWorkflowActions($contender))->execute('province', $id, 'setstate', $this->fixture->operation());
        } catch (Throwable $exception) {
            $error = $exception;
        }
        $this->assertNotNull($error, 'The generic BE011 action unexpectedly bypassed the held row lock.');
        // The Lifecycle adapter rolls the contender transaction back before
        // execute() returns the exception. That rollback can clear the driver's
        // 1205 error number, while the adapter may rethrow a domain-level
        // PersistenceException without the original numeric SQL code.
        // Prove the actual server errno in a fresh peer BEFORE its rollback.
        $probe = $this->peer();
        $this->assertTrue($probe->transBegin());
        $probeFailure = null;
        try {
            $probe->query("SELECT id FROM `{$table}` WHERE id=? FOR UPDATE", [$id]);
        } catch (Throwable $exception) {
            $probeFailure = $exception;
        }
        $serverCodes = [(int) ($probe->error()['code'] ?? 0)];
        for ($e = $probeFailure; $e !== null; $e = $e->getPrevious()) {
            $serverCodes[] = (int) $e->getCode();
        }
        $this->assertContains(1205, $serverCodes, 'A separate SQL session must receive genuine MySQL error 1205.');
        $this->assertTrue($probe->transRollback());
        $probe->resetTransStatus();

        // The *generic BE011 action* must also reach the same locked adapter
        // and fail there; unrelated PBAC/configuration exceptions are not
        // evidence that the action was blocked by InnoDB.
        $actionCodes = [(int) ($contender->error()['code'] ?? 0)];
        for ($e = $error; $e !== null; $e = $e->getPrevious()) {
            $actionCodes[] = (int) $e->getCode();
        }
        $adapterRejectedLock = $error instanceof PersistenceException
            && $error->getErrorCode() === LocationErrors::ERROR_PERSISTENCE_FAILED
            && $error->getContext() === ['operation' => 'workflowLock'];
        $this->assertTrue(
            $adapterRejectedLock || in_array(1205, $actionCodes, true),
            'The BE011 action did not fail at the locked row: ' . get_debug_type($error)
                . ' / ' . $error->getMessage() . ' / SQL codes ' . implode(',', $actionCodes),
        );
        $contender->resetTransStatus();
        $this->assertTrue($holder->transRollback());
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $id));
        $this->assertSame($beforeAudit, Fixture::auditCount($this->db));
        $result = (new BusinessWorkflowActions($contender))->execute('province', $id, 'setstate', $this->fixture->operation());
        $this->assertSame('INACTIVE', $result['status']);
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $id));
    }

    public function testStaleStateConcurrentMutationRollsBack(): void
    {
        $id = $this->fixture->province(Fixture::code('BE011L-'));
        $peer = $this->peer();
        $beforeAudit = Fixture::auditCount($this->db);
        $canonical = WorkflowStateResolutionFactory::create(db: $this->db);
        // Decorate BE010's real State resolver through its application port.
        // The second persistedStateId call is BE009 adapter.load AFTER its
        // authorized SELECT, but BEFORE BE009's transactional FOR UPDATE.
        // A separate real MySQL session commits a competing status right then.
        $interleaving = new class($canonical, $peer) implements ResourceStateResolutionPort {
            public int $reads = 0;
            public int $writes = 0;
            /** @param BaseConnection<mixed,mixed> $peer */
            public function __construct(private readonly ResourceStateResolutionPort $delegate, private readonly BaseConnection $peer) {}
            public function resource(string $type): array { return $this->delegate->resource($type); }
            public function lifecycleId(string $type): ?int { return $this->delegate->lifecycleId($type); }
            public function persistedStateId(string $type, array $row): ?int
            {
                $value = $this->delegate->persistedStateId($type, $row);
                if ($type === 'province' && ++$this->reads === 2) {
                    if (! $this->peer->table('provinces')->where('id', (int) $row['id'])
                        ->where('status', 'ACTIVE')->update(['status' => 'INACTIVE'])
                        || $this->peer->affectedRows() !== 1) {
                        throw new RuntimeException('Competing session did not commit a real State change.');
                    }
                    ++$this->writes;
                }
                return $value;
            }
            public function stateIdForCode(string $type, mixed $code): ?int { return $this->delegate->stateIdForCode($type, $code); }
            public function codeForStateId(string $type, int $stateId): string { return $this->delegate->codeForStateId($type, $stateId); }
            public function allowedPolicyStateCodes(string $type): array { return $this->delegate->allowedPolicyStateCodes($type); }
            public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void
            { $this->delegate->assertLifecycleReassignmentSafe($type, $nextLifecycleId); }
            public function initialCode(string $type): ?string { return $this->delegate->initialCode($type); }
            public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void
            { $this->delegate->assertLifecycleStateCompatibility($type, $lifecycleId); }
            public function assertPolicyState(int $resourceId, int $stateId): void
            { $this->delegate->assertPolicyState($resourceId, $stateId); }
            public function stateForAuthorization(string $type, ?array $persistedTarget, ?int $explicitStateId,
                ?array $trustedCatalogRow = null, string $action = '', array $input = []): ?int
            { return $this->delegate->stateForAuthorization($type, $persistedTarget, $explicitStateId, $trustedCatalogRow, $action, $input); }
        };
        $resolver = (new ReflectionClass(ResourceWorkflowStateResolver::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($resolver, 'engine'))->setValue($resolver, $interleaving);
        $engine = BusinessWorkflowActionFactory::create($this->db, states: $resolver);
        $facade = (new ReflectionClass(BusinessWorkflowActions::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($facade, 'engine'))->setValue($facade, $engine);
        try {
            $facade->execute('province', $id, 'setstate', $this->fixture->operation());
            $this->fail('BE011 must detect a committed peer State change before the BE009 lock.');
        } catch (ConcurrencyException $error) {
            $this->assertSame(['operation' => 'lifecycle.state_change'], $error->getContext());
        }
        $this->assertSame(1, $interleaving->writes);
        $this->assertGreaterThanOrEqual(3, $interleaving->reads);
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $id),
            'Only the peer should have changed State; BE011 must not execute another mutation.');
        $this->assertSame($beforeAudit, Fixture::auditCount($this->db));
        $this->assertSame('erp_test', $peer->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }
}

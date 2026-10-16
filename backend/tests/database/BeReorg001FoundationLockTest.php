<?php

declare(strict_types=1);

use App\Bootstrap\FoundationFactory;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\BeforeWardLockRepository;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/**
 * Real MySQL/InnoDB overlapping transactions, no mock DB, fork or timing races.
 * Lock tests require a genuine server error 1205 while the other connection
 * holds the lock, then prove success after release. No skipped lock assertions.
 * @internal
 */
final class BeReorg001FoundationLockTest extends CIUnitTestCase
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
        foreach (['provinces', 'wards'] as $table) {
            $row = $this->db->query(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$this->db->prefixTable($table)],
            )->getRowArray();
            $this->assertNotNull($row);
            $this->assertSame('INNODB', strtoupper((string) $row['ENGINE']), 'Row-lock proof requires InnoDB.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->peers as $peer) {
            try { $peer->transRollback(); } finally { $peer->close(); }
        }
        $this->peers = [];
        parent::tearDown();
    }

    public function testRepositoryLockBlocksAnotherConnectionAndRollbackReleasesIt(): void
    {
        $province = $this->fixture->province('BR001-LOCK-P');
        $ward = $this->fixture->ward($province);
        [$holder, $contender] = $this->connections();
        $a = new SqlLocationRepository($holder);
        $b = new SqlLocationRepository($contender);
        $this->assertTrue($holder->transBegin());
        $this->assertSame($ward, (int) $a->lockWardForUpdate($ward)['id']);
        $this->assertTrue($contender->transBegin());
        $this->assertLockTimeout(fn () => $b->lockWardForUpdate($ward), $contender);
        $this->assertTrue($contender->transRollback());
        $this->assertTrue($holder->transRollback());
        $this->assertTrue($contender->transBegin());
        $this->assertSame($ward, (int) $b->lockWardForUpdate($ward)['id']);
        $this->assertTrue($b->update('wards', $ward, ['name' => 'After release'], $this->fixture->operation()));
        $this->assertTrue($contender->transCommit());
        $this->assertSame('After release', $this->row('wards', $ward)['name']);
    }

    public function testStaleReparentFailsAndRollsBackOnlyTheLosingTransaction(): void
    {
        $old = $this->fixture->province('BR001-RACE-OLD');
        $winnerParent = $this->fixture->province('BR001-RACE-WIN');
        $loserParent = $this->fixture->province('BR001-RACE-LOSE');
        $ward = $this->fixture->ward($old);
        [$loser, $winner] = $this->connections();
        $winnerService = FoundationFactory::service(db: $winner,
            mutationContext: new MutationContext(RuntimeFixture::clock(), $this->fixture->writer, 'winner', false));
        $reachedBoundary = false;
        $interleaved = new BeforeWardLockRepository(new SqlLocationRepository($loser),
            function () use ($loser, $winnerService, $winnerParent, $ward, &$reachedBoundary): void {
                $reachedBoundary = true;
                // This marker is inside A's real transaction. It must roll back.
                $this->assertTrue($loser->table('provinces')->insert([
                    'code' => 'BR001-ROLLBACK', 'name' => 'Losing transaction marker',
                    'status' => 'ACTIVE', 'created_at' => '2026-10-16 02:30:00',
                ]));
                // B commits after A's initial read but before A's locking read.
                $this->assertTrue($winnerService->updateWard($ward, [
                    'province_id' => $winnerParent, 'name' => 'Winner committed',
                ]));
            });
        $service = FoundationFactory::service(repository: $interleaved, db: $loser,
            mutationContext: new MutationContext(RuntimeFixture::clock(), $this->fixture->reader, 'loser', false));
        $failure = null;
        try {
            $service->updateWard($ward, ['province_id' => $loserParent, 'name' => 'Must not overwrite winner']);
        } catch (DomainException $exception) {
            $failure = $exception;
        }
        $this->assertTrue($reachedBoundary);
        $this->assertNotNull($failure);
        $this->assertSame('CONCURRENT_MODIFICATION', $failure->getErrorCode());
        $this->assertSame(['ward_id' => $ward], $failure->getContext());
        $row = $this->row('wards', $ward);
        $this->assertSame($winnerParent, (int) $row['province_id']);
        $this->assertSame('Winner committed', $row['name']);
        $this->assertSame($this->fixture->writer, (int) $row['updated_by']);
        $this->assertSame(0, $this->db->table('provinces')->where('code', 'BR001-ROLLBACK')->countAllResults());
    }

    public function testLifecycleAdapterLockBlocksAnotherConnectionUntilCommit(): void
    {
        $province = $this->fixture->province('BR001-WF-LOCK');
        [$holder, $contender] = $this->connections();
        $operation = $this->fixture->operation();
        $a = FoundationFactory::lifecycleAdapter('province', db: $holder);
        $b = FoundationFactory::lifecycleAdapter('province', db: $contender);
        $snapshot = $a->load($province, $operation);
        $this->assertTrue($holder->transBegin());
        $a->lockAndReload($snapshot, $operation);
        $this->assertTrue($contender->transBegin());
        $this->assertLockTimeout(fn () => $b->lockAndReload($snapshot, $operation), $contender);
        $this->assertTrue($contender->transRollback());
        $this->assertTrue($holder->transCommit());
        $this->assertTrue($contender->transBegin());
        $this->assertSame($province, $b->lockAndReload($snapshot, $operation)->targetId);
        $this->assertTrue($contender->transRollback());
    }

    public function testLifecycleReloadRejectsStaleSourceStateAndPreservesTheWinner(): void
    {
        $province = $this->fixture->province('BR001-WF-STALE');
        [$first, $second] = $this->connections();
        $operation = $this->fixture->operation();
        $a = FoundationFactory::lifecycleAdapter('province', db: $first);
        $b = FoundationFactory::lifecycleAdapter('province', db: $second);
        $authorized = $a->load($province, $operation);
        $b->transactional(function () use ($b, $authorized, $operation): void {
            $locked = $b->lockAndReload($authorized, $operation);
            $b->mutate($locked, $this->fixture->states['ACTIVE'], $this->fixture->states['INACTIVE'], $operation);
        });
        $failure = null;
        try {
            $a->transactional(function () use ($a, $authorized, $operation): void {
                $locked = $a->lockAndReload($authorized, $operation);
                $this->assertSame($this->fixture->states['INACTIVE'], $locked->currentStateId);
                $a->mutate($locked, $authorized->currentStateId, $this->fixture->states['CLOSED'], $operation);
            });
        } catch (ConcurrencyException $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure);
        $this->assertSame('CONCURRENT_MODIFICATION', $failure->getErrorCode());
        $this->assertSame(['operation' => 'lifecycle.state_change'], $failure->getContext());
        $this->assertSame('INACTIVE', $this->row('provinces', $province)['status']);
    }

    /** @return array{BaseConnection<mixed,mixed>,BaseConnection<mixed,mixed>} */
    private function connections(): array
    {
        $a = db_connect('tests', false);
        $this->peers[] = $a;
        $b = db_connect('tests', false);
        $this->peers[] = $b;
        $first = $a->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $second = $b->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $this->assertNotSame((string) $first['id'], (string) $second['id'], 'Connections must not be shared.');
        $this->assertSame('erp_test', $first['db']);
        $this->assertSame('erp_test', $second['db']);
        // These are fresh non-persistent peers, closed in tearDown. No global SET.
        $a->query('SET SESSION innodb_lock_wait_timeout = 2');
        $b->query('SET SESSION innodb_lock_wait_timeout = 2');
        // A timed-out statement is expected; allow the next transaction to start cleanly.
        $a->transStrict(false);
        $b->transStrict(false);
        return [$a, $b];
    }

    /** @param BaseConnection<mixed,mixed> $connection */
    private function assertLockTimeout(callable $attempt, BaseConnection $connection): void
    {
        $failure = null;
        try { $attempt(); } catch (Throwable $exception) { $failure = $exception; }
        $this->assertNotNull($failure, 'Competing lock succeeded while the holder transaction was still open.');
        $codes = [(int) ($connection->error()['code'] ?? 0)];
        for ($error = $failure; $error !== null; $error = $error->getPrevious()) {
            $codes[] = (int) $error->getCode();
        }
        $this->assertContains(1205, $codes, 'Expected genuine MySQL lock wait timeout, not an arbitrary exception.');
    }

    /** @return array<string,mixed> */
    private function row(string $table, int $id): array
    {
        $row = $this->db->table($table)->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row);
        return $row;
    }
}

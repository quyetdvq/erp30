<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Workflow\BeReorg009LifecycleFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal Canonical SQL must preserve every legacy persistence behavior. */
final class BeReorg009LifecycleRepositoryParityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }

    public function testLegacyAndSqlLifecycleCrudHaveIdenticalRowsAndAudit(): void
    {
        $legacy = new LifecycleRepository($this->db);
        $sql = new SqlLifecycleRepository($this->db);
        $id = $legacy->insertLifecycle([
            'code' => 'BE009-CRUD-' . uniqid(), 'name' => 'Before', 'status' => 'ACTIVE',
            'created_by' => 999, 'created_at' => '2000-01-01 00:00:00',
        ], Fixture::operation(42));
        $this->assertSame($legacy->findLifecycle($id), $sql->findLifecycle($id));
        $this->assertSame(42, (int) $sql->find($id)['created_by']);
        $this->assertSame('2026-10-17 05:00:00', $sql->find($id)['created_at']);
        $this->assertTrue($sql->updateLifecycle($id, ['name' => 'After', 'updated_by' => 999], Fixture::operation(33)));
        $this->assertSame($legacy->findLifecycle($id), $sql->findLifecycle($id));
        $this->assertSame(33, (int) $legacy->find($id)['updated_by']);
    }

    public function testStateCatalogCrudsAndPositiveIdsRemainEquivalent(): void
    {
        $legacy = new LifecycleRepository($this->db);
        $sql = new SqlLifecycleRepository($this->db);
        $id = $sql->createWorkflowState(['code' => 'BE009-ST-' . uniqid(), 'name' => 'Initial', 'status' => 'ACTIVE']);
        $this->assertSame($legacy->findWorkflowState((string) $id), $sql->findWorkflowState($id));
        $this->assertTrue($legacy->updateWorkflowState($id, ['name' => 'Changed']));
        $this->assertSame('Changed', $sql->findActiveWorkflowState($id)['name']);
        try {
            $sql->findWorkflowState('not-a-positive-id');
            $this->fail('Invalid id must be rejected');
        } catch (DomainException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_INVALID_ID, $e->getErrorCode());
        }
    }

    public function testMembershipOrderingFlagsAndUniquenessRemainEquivalent(): void
    {
        $legacy = new LifecycleRepository($this->db);
        $sql = new SqlLifecycleRepository($this->db);
        $lifecycle = Fixture::lifecycle($this->db);
        $from = Fixture::state($this->db);
        $to = Fixture::state($this->db);
        Fixture::bind($this->db, $lifecycle, $to, 20, false, true);
        Fixture::bind($this->db, $lifecycle, $from, 10, true);
        $rows = $sql->findLifecycleStates($lifecycle);
        $this->assertSame($legacy->findLifecycleStates($lifecycle), $rows);
        $this->assertSame([$from, $to], array_map(static fn (array $r): int => (int) $r['state_id'], $rows));
        $this->assertSame([1, 0], array_map(static fn (array $r): int => (int) $r['is_initial'], $rows));
        $this->assertSame(1, (int) $rows[1]['is_final']);
        try {
            $sql->insertLifecycleState(['lifecycle_id' => $lifecycle, 'state_id' => $from, 'sequence' => 99]);
            $this->fail('Duplicate membership must fail');
        } catch (PersistenceException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_PERSISTENCE_FAILED, $e->getErrorCode());
        }
    }

    public function testTransitionOrderingActiveFilteringAndAliasesMatch(): void
    {
        [$lifecycle, $from, $to] = Fixture::transition($this->db);
        $legacy = new LifecycleRepository($this->db);
        $sql = new SqlLifecycleRepository($this->db);
        $sql->insertTransition(['lifecycle_id' => $lifecycle, 'from_state_id' => $from,
            'to_state_id' => $to, 'action_code' => 'hold', 'status' => 'INACTIVE']);
        $this->assertSame($legacy->findTransitions($lifecycle), $sql->findTransitions($lifecycle));
        $this->assertCount(1, $sql->findTransitions($lifecycle, 'ACTIVE'));
        $this->assertCount(2, $legacy->findTransitions($lifecycle));
        $this->assertNull($sql->findActiveTransitionByAction($lifecycle, $from, 'hold'));
        $this->assertSame($sql->findTransitionByAction($lifecycle, $from, 'approve'),
            $legacy->resolveTransition($lifecycle, $from, 'approve'));
    }

    public function testFalseQueryMissingUpdateAndInvalidPayloadKeepTypedFailures(): void
    {
        $sql = new SqlLifecycleRepository($this->db);
        try {
            (new ReflectionMethod($sql, 'findRow'))->invoke($sql, 'forced_false', static fn (): false => false);
            $this->fail('A false query result is an error, not not-found');
        } catch (PersistenceException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_PERSISTENCE_FAILED, $e->getErrorCode());
        }
        $this->assertFalse($sql->updateLifecycle(999999999, ['name' => 'no row']));
        try {
            $sql->updateLifecycle(999999999, []);
            $this->fail('Empty update must fail');
        } catch (DomainException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_UPDATE_EMPTY, $e->getErrorCode());
        }
        try {
            $sql->insertLifecycle(['bogus_field' => 7]);
            $this->fail('Unsupported fields must fail');
        } catch (DomainException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_FIELD_UNSUPPORTED, $e->getErrorCode());
        }
    }

    public function testTransactionalRollbackAndLockUseSuppliedConnection(): void
    {
        $sql = new SqlLifecycleRepository($this->db);
        $this->assertSame($this->db, (new ReflectionProperty($sql, 'db'))->getValue($sql));
        $id = Fixture::lifecycle($this->db);
        $row = $sql->transactional(fn (): ?array => $sql->lockLifecycle($id), 'be009.lock');
        $this->assertSame($id, (int) $row['id']);
        try {
            $sql->transactional(function () use ($sql): void {
                $sql->insertLifecycle(['code' => 'BE009-ROLLBACK-' . uniqid(), 'name' => 'Rollback', 'status' => 'ACTIVE']);
                throw new RuntimeException('roll back entire transaction');
            }, 'be009.rollback');
            $this->fail('Unexpected throwable must roll back and wrap');
        } catch (PersistenceException $e) {
            $this->assertSame(SqlLifecycleRepository::ERROR_MUTATION_FAILED, $e->getErrorCode());
            $this->assertSame(['operation' => 'be009.rollback'], $e->getContext());
        }
        $this->assertNotNull($sql->findLifecycle($id));
    }
}

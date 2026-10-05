<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Wf001LifecycleRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testLifecycleCrudUsesTrustedAuditAndCanonicalPositiveIds(): void
    {
        $repository = new LifecycleRepository($this->db);
        $operation = $this->operation(42);
        $id = $repository->createLifecycle([
            'code'       => 'WF001-LIFE-' . uniqid(),
            'name'       => 'Repository lifecycle',
            'description' => 'Persistence test',
            'status'     => 'ACTIVE',
            'created_by' => 999999,
            'created_at' => '1999-01-01 00:00:00',
        ], $operation);

        $row = $repository->find((string) $id);
        $this->assertNotNull($row);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame(42, (int) $row['created_by']);
        $this->assertSame('2026-10-05 04:05:06', $row['created_at']);
        $this->assertSame($id, (int) $repository->findByCode((string) $row['code'])['id']);
        $this->assertSame($id, (int) $repository->findActiveByCode((string) $row['code'])['id']);

        $this->assertTrue($repository->updateLifecycle((string) $id, [
            'name'       => 'Updated lifecycle',
            'updated_by' => 999999,
            'updated_at' => '1999-01-01 00:00:00',
        ], $operation));
        $updated = $repository->find($id);
        $this->assertSame('Updated lifecycle', $updated['name']);
        $this->assertSame(42, (int) $updated['updated_by']);
        $this->assertSame('2026-10-05 04:05:06', $updated['updated_at']);

        $this->assertNull($repository->find(999999999));
        $this->assertFalse($repository->updateLifecycle(999999999, ['name' => 'Missing'], $operation));
    }

    public function testLifecycleCodeUniquenessAndRowLockUseTypedPersistenceSemantics(): void
    {
        $repository = new LifecycleRepository($this->db);
        $code = 'WF001-UNIQUE-' . uniqid();
        $id = $repository->insertLifecycle([
            'code' => $code, 'name' => 'Unique lifecycle', 'status' => 'ACTIVE',
        ]);

        try {
            $repository->insertLifecycle(['code' => $code, 'name' => 'Duplicate', 'status' => 'ACTIVE']);
            $this->fail('Expected duplicate lifecycle code failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(LifecycleRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }

        $this->db->transBegin();
        $locked = $repository->lockLifecycle((string) $id);
        $this->assertSame($id, (int) $locked['id']);
        $this->db->transCommit();
    }

    public function testWorkflowStateUsesCodeIdentityAndSeparatesActiveLookup(): void
    {
        $repository = new LifecycleRepository($this->db);
        $code = 'WF001-STATE-' . uniqid();
        $id = $repository->createWorkflowState([
            'code' => $code, 'name' => 'Localized display label', 'status' => 'ACTIVE',
        ]);

        $this->assertSame($id, (int) $repository->findWorkflowStateByCode($code)['id']);
        $this->assertSame($id, (int) $repository->findActiveWorkflowStateByCode($code)['id']);
        $this->assertNull($repository->findWorkflowStateByCode('Localized display label'));
        $this->assertTrue($repository->updateWorkflowState($id, ['status' => 'INACTIVE']));
        $this->assertSame($id, (int) $repository->findWorkflowStateByCode($code)['id']);
        $this->assertNull($repository->findActiveWorkflowStateByCode($code));
    }

    public function testLifecycleMembershipIsOrderedAndPersistsInitialFinalFlags(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $repository->insertLifecycle([
            'code' => 'WF001-MEMBER-' . uniqid(), 'name' => 'Membership', 'status' => 'ACTIVE',
        ]);
        $firstStateId = $repository->insertWorkflowState([
            'code' => 'WF001-MEMBER-A-' . uniqid(), 'name' => 'A', 'status' => 'ACTIVE',
        ]);
        $secondStateId = $repository->insertWorkflowState([
            'code' => 'WF001-MEMBER-B-' . uniqid(), 'name' => 'B', 'status' => 'ACTIVE',
        ]);

        $repository->insertLifecycleState([
            'lifecycle_id' => (string) $lifecycleId, 'state_id' => $secondStateId,
            'sequence' => 20, 'is_initial' => 0, 'is_final' => 1,
        ]);
        $repository->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $firstStateId,
            'sequence' => 10, 'is_initial' => 1, 'is_final' => 0,
        ]);

        $rows = $repository->findLifecycleStates($lifecycleId);
        $this->assertSame([$firstStateId, $secondStateId], array_map(static fn (array $row): int => (int) $row['state_id'], $rows));
        $this->assertSame('1', (string) $rows[0]['is_initial']);
        $this->assertSame('1', (string) $rows[1]['is_final']);

        try {
            $repository->insertLifecycleState([
                'lifecycle_id' => $lifecycleId, 'state_id' => $firstStateId, 'sequence' => 30,
            ]);
            $this->fail('Expected duplicate lifecycle/state failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(LifecycleRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
    }

    public function testTransitionLookupIsDeterministicAndExcludesInactiveRowsOnlyForActiveResolution(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $repository->insertLifecycle([
            'code' => 'WF001-TRANS-' . uniqid(), 'name' => 'Transitions', 'status' => 'ACTIVE',
        ]);
        $fromStateId = $repository->insertWorkflowState([
            'code' => 'WF001-TRANS-F-' . uniqid(), 'name' => 'From', 'status' => 'ACTIVE',
        ]);
        $toStateId = $repository->insertWorkflowState([
            'code' => 'WF001-TRANS-T-' . uniqid(), 'name' => 'To', 'status' => 'ACTIVE',
        ]);
        $action = 'wf001.action.' . strtolower(uniqid());
        $id = $repository->insertTransition([
            'lifecycle_id' => $lifecycleId, 'from_state_id' => $fromStateId,
            'to_state_id' => $toStateId, 'action_code' => $action, 'status' => 'INACTIVE',
        ]);

        $this->assertSame($id, (int) $repository->findTransitionByAction($lifecycleId, (string) $fromStateId, $action)['id']);
        $this->assertNull($repository->findActiveTransitionByAction($lifecycleId, $fromStateId, $action));
        $this->assertCount(1, $repository->findTransitions($lifecycleId));
        $this->assertCount(0, $repository->findTransitions($lifecycleId, 'ACTIVE'));

        try {
            $repository->insertTransition([
                'lifecycle_id' => $lifecycleId, 'from_state_id' => $fromStateId,
                'to_state_id' => $toStateId, 'action_code' => $action, 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected duplicate lifecycle/from/action failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(LifecycleRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
    }

    public function testTransactionFailureRollsBackRepositoryBatchAndPersistenceFailureIsNotNotFound(): void
    {
        $repository = new LifecycleRepository($this->db);
        $code = 'WF001-ROLLBACK-' . uniqid();
        try {
            $repository->transactional(function () use ($repository, $code): void {
                $repository->insertLifecycle(['code' => $code, 'name' => 'Rolled back', 'status' => 'ACTIVE']);
                throw new \RuntimeException('forced batch failure');
            }, 'wf001.rollback');
            $this->fail('Expected transactional persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(LifecycleRepository::ERROR_MUTATION_FAILED, $exception->getErrorCode());
        }
        $this->assertNull($repository->findByCode($code));

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willThrowException(new \RuntimeException('database unavailable'));
        try {
            (new LifecycleRepository($connection))->find(1);
            $this->fail('Expected persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(LifecycleRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
    }

    private function operation(int $actorId): OperationContext
    {
        return new OperationContext(
            $actorId,
            new EvaluationTime(new \DateTimeImmutable('2026-10-05 04:05:06', new \DateTimeZone('UTC'))),
            'wf-001-test',
            false,
        );
    }
}

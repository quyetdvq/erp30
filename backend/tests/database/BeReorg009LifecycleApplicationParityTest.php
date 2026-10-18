<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleRepositoryPort;
use App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Workflow\BeReorg009Adapter;
use Tests\Support\Workflow\BeReorg009Audit;
use Tests\Support\Workflow\BeReorg009Authorization;
use Tests\Support\Workflow\BeReorg009LifecycleFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal Tests canonical owner against real guarded Lifecycle catalog rows. */
final class BeReorg009LifecycleApplicationParityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private function service(): LifecycleApplicationService
    {
        return new LifecycleApplicationService(repository: new LifecycleRepository($this->db), db: $this->db);
    }

    public function testActiveConfigHasExactlyOneInitialWithMultipleFinalAllowed(): void
    {
        $id = Fixture::lifecycle($this->db);
        $a = Fixture::state($this->db);
        $b = Fixture::state($this->db);
        $c = Fixture::state($this->db);
        Fixture::bind($this->db, $id, $a, 1, true);
        Fixture::bind($this->db, $id, $b, 2, false, true);
        Fixture::bind($this->db, $id, $c, 3, false, true);
        $config = $this->service()->validateConfiguration($id);
        $this->assertCount(3, $config['states']);
        $this->assertSame(2, count(array_filter($config['states'], static fn (array $s): bool => (int) $s['is_final'] === 1)));
        $this->db->table('lifecycle_states')->where('lifecycle_id', $id)->where('state_id', $a)->update(['is_initial' => 0]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(LifecycleApplicationService::ERROR_INVALID_CONFIGURATION);
        $this->service()->validateConfiguration($id);
    }

    public function testInvalidMembershipOrTransitionFailsClosedBeforeWrites(): void
    {
        $id = Fixture::lifecycle($this->db);
        $inactive = Fixture::state($this->db, 'INACTIVE');
        try {
            $this->service()->configure($id, [['state_id' => $inactive, 'sequence' => 1, 'is_initial' => 1]], []);
            $this->fail('Inactive state must not be configured');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_INVALID_CONFIGURATION, $e->getErrorCode());
        }
        $this->assertSame(0, $this->db->table('lifecycle_states')->where('lifecycle_id', $id)->countAllResults());
        $from = Fixture::state($this->db);
        $foreign = Fixture::state($this->db);
        Fixture::bind($this->db, $id, $from, 1, true);
        try {
            $this->service()->configure($id, [], [['from_state_id' => $from, 'to_state_id' => $foreign, 'action_code' => 'submit', 'status' => 'ACTIVE']]);
            $this->fail('Unbound destination must fail');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_STATE_NOT_IN_LIFECYCLE, $e->getErrorCode());
        }
        $this->assertSame(0, $this->db->table('lifecycle_transitions')->where('lifecycle_id', $id)->countAllResults());
    }

    public function testConfigureValidatesCombinedBatchAndAtomicallyRollsBack(): void
    {
        $id = Fixture::lifecycle($this->db);
        $a = Fixture::state($this->db);
        $b = Fixture::state($this->db);
        $service = $this->service();
        $config = $service->configure($id, [
            ['state_id' => $a, 'sequence' => 1, 'is_initial' => 1],
            ['state_id' => $b, 'sequence' => 2, 'is_final' => 1],
        ], [['from_state_id' => $a, 'to_state_id' => $b, 'action_code' => 'submit', 'status' => 'ACTIVE']]);
        $this->assertCount(2, $config['states']);
        $this->assertCount(1, $config['transitions']);
        $c = Fixture::state($this->db);
        try {
            $service->configure($id, [['state_id' => $c, 'sequence' => 3, 'is_initial' => 1]], []);
            $this->fail('Multiple initial states must fail before persistence');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_INVALID_CONFIGURATION, $e->getErrorCode());
        }
        $this->assertSame(2, $this->db->table('lifecycle_states')->where('lifecycle_id', $id)->countAllResults());

        // Force a failure after both State inserts: the supplied repository
        // must roll back the entire batch on the same SQL transaction.
        $rollbackId = Fixture::lifecycle($this->db);
        $initial = Fixture::state($this->db);
        $final = Fixture::state($this->db);
        $repository = new class(new LifecycleRepository($this->db)) implements LifecycleRepositoryPort {
            public function __construct(private readonly LifecycleRepository $delegate)
            {
            }
            public function findLifecycle(mixed $id): ?array
            {
                return $this->delegate->findLifecycle($id);
            }
            public function lockLifecycle(mixed $id): ?array
            {
                return $this->delegate->lockLifecycle($id);
            }
            public function findLifecycleStates(mixed $lifecycleId): array
            {
                return $this->delegate->findLifecycleStates($lifecycleId);
            }
            public function findTransitions(mixed $lifecycleId, ?string $status = null): array
            {
                return $this->delegate->findTransitions($lifecycleId, $status);
            }
            public function findActiveWorkflowState(mixed $id): ?array
            {
                return $this->delegate->findActiveWorkflowState($id);
            }
            public function findActiveTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array
            {
                return $this->delegate->findActiveTransitionByAction($lifecycleId, $fromStateId, $actionCode);
            }
            public function insertLifecycleState(array $data, ?OperationContext $operation = null): int
            {
                return $this->delegate->insertLifecycleState($data, $operation);
            }
            public function insertTransition(array $data, ?OperationContext $operation = null): int
            {
                throw new PersistenceException(LifecycleRepository::ERROR_PERSISTENCE_FAILED, 'be009.forced-late-insert');
            }
            public function transactional(callable $callback, string $operation = 'transaction'): mixed
            {
                return $this->delegate->transactional($callback, $operation);
            }
        };
        $rollbackService = new LifecycleApplicationService(repository: $repository, db: $this->db);
        try {
            $rollbackService->configure($rollbackId, [
                ['state_id' => $initial, 'sequence' => 1, 'is_initial' => 1],
                ['state_id' => $final, 'sequence' => 2, 'is_final' => 1],
            ], [['from_state_id' => $initial, 'to_state_id' => $final, 'action_code' => 'submit', 'status' => 'ACTIVE']]);
            $this->fail('Injected late insertion failure must abort all State inserts');
        } catch (PersistenceException $e) {
            $this->assertSame(LifecycleRepository::ERROR_PERSISTENCE_FAILED, $e->getErrorCode());
        }
        $this->assertSame(0, $this->db->table('lifecycle_states')->where('lifecycle_id', $rollbackId)->countAllResults());
        $this->assertSame(0, $this->db->table('lifecycle_transitions')->where('lifecycle_id', $rollbackId)->countAllResults());
    }

    public function testResolveTransitionRequiresActiveLifecycleAndActiveDestination(): void
    {
        [$id, $from, $to] = Fixture::transition($this->db);
        $this->assertSame($to, (int) $this->service()->resolveTransition($id, $from, 'approve')['to_state_id']);
        $this->db->table('workflow_states')->where('id', $to)->update(['status' => 'INACTIVE']);
        try {
            $this->service()->resolveTransition($id, $from, 'approve');
            $this->fail('Inactive destination must be rejected');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_INVALID_TRANSITION, $e->getErrorCode());
        }
        $this->db->table('workflow_states')->where('id', $to)->update(['status' => 'ACTIVE']);
        $this->db->table('lifecycles')->where('id', $id)->update(['status' => 'INACTIVE']);
        $this->expectExceptionMessage(LifecycleApplicationService::ERROR_INVALID_CONFIGURATION);
        $this->service()->resolveTransition($id, $from, 'approve');
    }

    public function testExecuteDenialAuditsWithoutMutationUsingTrustedState(): void
    {
        [$id, $from] = Fixture::transition($this->db);
        $auth = new BeReorg009Authorization('DENY');
        $audit = new BeReorg009Audit();
        $adapter = new BeReorg009Adapter(Fixture::snapshot($id, $from, 47));
        $app = new LifecycleApplicationService(new LifecycleRepository($this->db), $auth, $audit, [$adapter], $this->db);
        try {
            $app->execute('product', 1, 'approve', Fixture::operation());
            $this->fail('Denied operation must fail closed');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_AUTHORIZATION_DENIED, $e->getErrorCode());
        }
        $this->assertSame(1, $auth->calls);
        $this->assertSame($from, $auth->stateId);
        $this->assertSame(47, (int) $auth->request->persistedTarget['organization_id']);
        $this->assertSame(1, $audit->calls);
        $this->assertSame(0, $adapter->transactions);
        $this->assertSame(0, $adapter->mutations);
    }

    public function testMismatchedDestinationFailsBeforeAnyAuthorization(): void
    {
        [$id, $from, $destination] = Fixture::transition($this->db);
        $auth = new BeReorg009Authorization();
        $audit = new BeReorg009Audit();
        $adapter = new BeReorg009Adapter(Fixture::snapshot($id, $from));
        $app = new LifecycleApplicationService(new LifecycleRepository($this->db), $auth, $audit, [$adapter], $this->db);
        try {
            $app->execute('product', 1, 'approve', Fixture::operation(), $destination + 1000);
            $this->fail('A mismatched destination must fail before PBAC');
        } catch (DomainException $e) {
            $this->assertSame(LifecycleApplicationService::ERROR_INVALID_TRANSITION, $e->getErrorCode());
        }
        $this->assertSame(0, $auth->calls);
        $this->assertSame(0, $audit->calls);
        $this->assertSame(0, $adapter->transactions);
    }

    public function testExecuteReturnsOriginalSnapshotAndRejectsStaleLockedData(): void
    {
        [$id, $from, $to] = Fixture::transition($this->db);
        $snapshot = Fixture::snapshot($id, $from, 47);
        $adapter = new BeReorg009Adapter($snapshot);
        $audit = new BeReorg009Audit();
        $app = new LifecycleApplicationService(new LifecycleRepository($this->db), new BeReorg009Authorization(), $audit, [$adapter], $this->db);
        $result = $app->execute('product', 1, 'approve', Fixture::operation());
        $this->assertSame($snapshot, $result['target']);
        $this->assertSame($to, $adapter->lastDestination);
        $this->assertSame(1, $adapter->mutations);
        $this->assertSame(1, $audit->calls);
        $adapter->lockedOverride = Fixture::snapshot($id, $from, 48);
        try {
            $app->execute('product', 1, 'approve', Fixture::operation());
            $this->fail('Stale locked organization must reject the mutation');
        } catch (ConcurrencyException $e) {
            $this->assertSame(['operation' => 'lifecycle.state_change'], $e->getContext());
        }
        $this->assertSame(1, $adapter->rollbacks);
        $this->assertSame(1, $adapter->mutations);
        $this->assertSame(1, $audit->calls);
    }
}

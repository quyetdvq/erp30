<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Authorization\BeReorg008DecisionFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Read-only batches must use the facade's one canonical engine, never a second snapshot. */
final class BeReorg008DecisionBatchTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    private BeReorg008DecisionFixture $fixture;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new BeReorg008DecisionFixture($this->db);
    }

    protected function tearDown(): void
    {
        $this->fixture->restoreTrackedInactivePolicies();
        $this->fixture->restoreTrackedResourceStatuses();
        $this->resetServices();
        parent::tearDown();
    }

    public function testFacadeCallbackSharesEngineAndBatchesViewQueries(): void
    {
        $actor = $this->fixture->seedActor();
        $policyId = $this->fixture->addPolicy($actor['company_id'], 'GROUP', $actor['group_id'], 'ALLOW');
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->fixture->operation($actor['user_id']);
        $request = $this->fixture->request($actor['company_id']);
        $baselineSql = $this->capturePolicies(fn (): array => [
            $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
            $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
            $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
        ], $baseline);
        $batchedSql = $this->capturePolicies(fn (): array => $service->withReadOnlyBatch($operation,
            static fn (): array => [
                $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
                $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
                $service->authorize($operation, 'price_list', 'view', $request)->toArray(),
            ]), $batched);
        $this->assertSame($baseline, $batched);
        $this->assertSame([$policyId], $batched[0]['decisive_policy_ids']);
        $this->assertSame(3, $baselineSql);
        $this->assertSame(1, $batchedSql);
    }

    public function testOperationChangeBypassesCacheAndKeepsEvaluationInstant(): void
    {
        $actor = $this->fixture->seedActor();
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->fixture->operation($actor['user_id']);
        $other = new OperationContext($actor['user_id'],
            new EvaluationTime(new DateTimeImmutable('2026-10-06T12:00:00+00:00')), 'be008-other', false);
        $request = $this->fixture->request($actor['company_id']);
        $calls = $this->capturePolicies(fn (): array => $service->withReadOnlyBatch($operation,
            static fn (): array => [
                $service->authorize($other, 'price_list', 'view', $request)->toArray(),
                $service->authorize($other, 'price_list', 'view', $request)->toArray(),
            ]), $decisions);
        $this->assertSame(2, $calls, 'Mismatched operation contexts cannot borrow the batch memoizer.');
        $this->assertSame($decisions[0], $decisions[1]);
        $this->assertSame('be008-other', $decisions[0]['request_id']);
        $this->assertSame('2026-10-06T12:00:00+00:00', $decisions[0]['evaluation_time']);
    }

    public function testNestedBatchThrowsWithoutDiscardingOuterContext(): void
    {
        $actor = $this->fixture->seedActor();
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->fixture->operation($actor['user_id']);
        $request = $this->fixture->request($actor['company_id']);
        $engine = (new ReflectionProperty($service, 'engine'))->getValue($service);
        $result = $service->withReadOnlyBatch($operation, function () use ($service, $operation, $request, $engine): array {
            try {
                $service->withReadOnlyBatch($operation, static fn (): string => 'unexpected');
                $this->fail('Nested read-only batches must throw.');
            } catch (LogicException $exception) {
                $this->assertSame('Nested authorization read batches are not supported.', $exception->getMessage());
            }
            $this->assertNotNull((new ReflectionProperty($engine, 'readOnlyContext'))->getValue($engine));
            return $service->authorize($operation, 'price_list', 'view', $request)->toArray();
        });
        $this->assertSame('ALLOW', $result['decision']);
        $this->assertNull((new ReflectionProperty($engine, 'readOnlyContext'))->getValue($engine));
    }

    public function testCallbackExceptionReleasesSnapshotAndDoesNotMemoizeFailure(): void
    {
        $actor = $this->fixture->seedActor();
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->fixture->operation($actor['user_id']);
        $request = $this->fixture->request($actor['company_id']);
        try {
            $service->withReadOnlyBatch($operation, static function () use ($service, $operation, $request): void {
                $service->authorize($operation, 'price_list', 'view', $request);
                throw new DomainException('BE008_READ_CALLBACK_FAILED');
            });
            $this->fail('Expected callback exception.');
        } catch (DomainException $exception) {
            $this->assertSame('BE008_READ_CALLBACK_FAILED', $exception->getErrorCode());
        }
        $engine = (new ReflectionProperty($service, 'engine'))->getValue($service);
        $this->assertNull((new ReflectionProperty($engine, 'readOnlyContext'))->getValue($engine));
        $this->fixture->addPolicy($actor['company_id'], 'USER', $actor['user_id'], 'ABSOLUTE_DENY');
        $fresh = $service->authorize($operation, 'price_list', 'view', $request);
        $this->assertSame('DENY', $fresh->decision);
        $this->assertSame('AUTH_ABSOLUTE_DENY', $fresh->reasonCode);
    }

    public function testNonViewInvalidatesSnapshotAndIndependentCallsNeverCacheDecisions(): void
    {
        $actor = $this->fixture->seedActor();
        $service = new AuthorizationService(db: $this->db);
        $operation = $this->fixture->operation($actor['user_id']);
        $request = $this->fixture->request($actor['company_id']);
        $service->withReadOnlyBatch($operation, function () use ($actor, $service, $operation, $request): void {
            $this->assertSame('ALLOW', $service->authorize($operation, 'price_list', 'view', $request)->decision);
            $this->assertSame('DENY', $service->authorize($operation, 'price_list', 'edit', $request)->decision);
            $this->fixture->addPolicy($actor['company_id'], 'USER', $actor['user_id'], 'ABSOLUTE_DENY');
            $this->assertSame('DENY', $service->authorize($operation, 'price_list', 'view', $request)->decision);
        });
        $viewId = $this->fixture->actionId('price_list', 'view');
        $this->assertTrue($this->db->table('group_permissions')
            ->where(['group_id' => $actor['group_id'], 'resource_action_id' => $viewId])->delete());
        $new = $service->authorize($operation, 'price_list', 'view', $request);
        $this->assertSame('DENY', $new->decision);
        $this->assertSame('AUTH_NO_BASE_PERMISSION', $new->reasonCode);
    }

    /** @param callable(): array<int,mixed> $action @param array<int,mixed>|null $result */
    private function capturePolicies(callable $action, ?array &$result): int
    {
        $count = 0;
        $listener = static function ($query) use (&$count): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $count++;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $result = $action();
        } finally {
            Events::removeListener('DBQuery', $listener);
        }
        return $count;
    }
}

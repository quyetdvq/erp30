<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\LifecycleRepository;
use App\Services\LifecycleAuditPort;
use App\Services\LifecycleAuthorizationPort;
use App\Services\LifecycleService;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Wf002LifecycleServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testActiveLifecycleRequiresExactlyOneInitialStateAndAllowsMultipleFinalStates(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $first = $this->state($repository, 'ACTIVE');
        $second = $this->state($repository, 'ACTIVE');
        $third = $this->state($repository, 'ACTIVE');
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $first, 'sequence' => 1, 'is_initial' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $second, 'sequence' => 2, 'is_final' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $third, 'sequence' => 3, 'is_final' => 1]);

        $configuration = (new LifecycleService(repository: $repository))->validateConfiguration($lifecycleId);

        $this->assertCount(3, $configuration['states']);
        $this->assertSame(2, count(array_filter($configuration['states'], static fn (array $row): bool => (int) $row['is_final'] === 1)));
    }

    public function testZeroOrMultipleInitialStatesAreRejected(): void
    {
        $repository = new LifecycleRepository($this->db);
        $service = new LifecycleService(repository: $repository);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $first = $this->state($repository, 'ACTIVE');
        $second = $this->state($repository, 'ACTIVE');
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $first, 'sequence' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $second, 'sequence' => 2]);

        try {
            $service->validateConfiguration($lifecycleId);
            $this->fail('Expected zero-initial configuration rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_CONFIGURATION, $exception->getErrorCode());
        }

        $repository->updateLifecycleState($this->membershipId($lifecycleId, $first), ['is_initial' => 1]);
        $repository->updateLifecycleState($this->membershipId($lifecycleId, $second), ['is_initial' => 1]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(LifecycleService::ERROR_INVALID_CONFIGURATION);
        $service->validateConfiguration($lifecycleId);
    }

    public function testInactiveStateAndOutOfMembershipTransitionAreRejected(): void
    {
        $repository = new LifecycleRepository($this->db);
        $service = new LifecycleService(repository: $repository);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $active = $this->state($repository, 'ACTIVE');
        $inactive = $this->state($repository, 'INACTIVE');
        $outside = $this->state($repository, 'ACTIVE');

        try {
            $service->configure($lifecycleId, [[
                'state_id' => $inactive, 'sequence' => 1, 'is_initial' => 1,
            ]], []);
            $this->fail('Expected inactive state rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_CONFIGURATION, $exception->getErrorCode());
        }

        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $active, 'sequence' => 1, 'is_initial' => 1]);
        try {
            $service->configure($lifecycleId, [], [[
                'from_state_id' => $active, 'to_state_id' => $outside, 'action_code' => 'submit', 'status' => 'ACTIVE',
            ]]);
            $this->fail('Expected transition membership rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_STATE_NOT_IN_LIFECYCLE, $exception->getErrorCode());
        }
    }

    public function testBatchRollsBackWhenLaterConfigurationRowFails(): void
    {
        $repository = new LifecycleRepository($this->db);
        $service = new LifecycleService(repository: $repository);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $first = $this->state($repository, 'ACTIVE');
        $second = $this->state($repository, 'ACTIVE');

        try {
            $service->configure($lifecycleId, [
                ['state_id' => $first, 'sequence' => 1, 'is_initial' => 1],
                ['state_id' => $second, 'sequence' => 2, 'is_initial' => 1],
            ], []);
            $this->fail('Expected atomic batch validation failure.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_CONFIGURATION, $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('lifecycle_states')->where('lifecycle_id', $lifecycleId)->countAllResults());
    }

    public function testActiveTransitionResolutionIgnoresInactiveRowsAndUsesExactDestination(): void
    {
        $repository = new LifecycleRepository($this->db);
        $service = new LifecycleService(repository: $repository);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $from = $this->state($repository, 'ACTIVE');
        $inactiveDestination = $this->state($repository, 'ACTIVE');
        $destination = $this->state($repository, 'ACTIVE');
        foreach ([[$from, 1], [$inactiveDestination, 2], [$destination, 3]] as [$stateId, $sequence]) {
            $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $stateId, 'sequence' => $sequence, 'is_initial' => $sequence === 1 ? 1 : 0]);
        }
        $repository->insertTransition(['lifecycle_id' => $lifecycleId, 'from_state_id' => $from, 'to_state_id' => $inactiveDestination, 'action_code' => 'submit', 'status' => 'INACTIVE']);
        $repository->insertTransition(['lifecycle_id' => $lifecycleId, 'from_state_id' => $from, 'to_state_id' => $destination, 'action_code' => 'approve', 'status' => 'ACTIVE']);

        $resolved = $service->resolveTransition($lifecycleId, $from, 'approve');

        $this->assertSame((string) $destination, (string) $resolved['to_state_id']);
        $this->expectExceptionMessage(LifecycleService::ERROR_INVALID_TRANSITION);
        $service->resolveTransition($lifecycleId, $from, 'submit');
    }

    public function testUnknownAdapterFailsClosedAndInvalidTransitionPrecedesAuthorization(): void
    {
        $adapter = new TestLifecycleAdapter($this->snapshot('product', 1, 10, 20, 30));
        $authorization = new TestLifecycleAuthorization();
        $service = new LifecycleService(
            authorization: $authorization,
            audit: new TestLifecycleAudit(),
            adapters: ['product' => $adapter],
            repository: new LifecycleRepository($this->db),
        );

        $this->expectExceptionMessage(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE);
        try {
            $service->execute('unknown', 1, 'submit', $this->operation());
        } finally {
            $this->assertSame(0, $authorization->calls);
        }
    }

    public function testAuthorizedExecutionMutatesExactlyCatalogDestination(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $from = $this->state($repository, 'ACTIVE');
        $destination = $this->state($repository, 'ACTIVE');
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $from, 'sequence' => 1, 'is_initial' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $destination, 'sequence' => 2]);
        $repository->insertTransition(['lifecycle_id' => $lifecycleId, 'from_state_id' => $from, 'to_state_id' => $destination, 'action_code' => 'approve', 'status' => 'ACTIVE']);
        $adapter = new TestLifecycleAdapter($this->snapshot('product', 1, $from, $lifecycleId, 30));
        $audit = new TestLifecycleAudit();
        $service = new LifecycleService(
            repository: $repository,
            authorization: new TestLifecycleAuthorization(),
            audit: $audit,
            adapters: [$adapter],
        );

        $result = $service->execute('product', 1, 'approve', $this->operation());

        $this->assertSame($destination, $adapter->mutatedDestination);
        $this->assertSame($from, $adapter->mutatedExpected);
        $this->assertSame(1, $audit->calls);
        $this->assertSame($destination, (int) $result['transition']['to_state_id']);
    }

    public function testDeniedExecutionDoesNotMutateAndConcurrentReloadFails(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycleId = $this->lifecycle($repository, 'ACTIVE');
        $from = $this->state($repository, 'ACTIVE');
        $destination = $this->state($repository, 'ACTIVE');
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $from, 'sequence' => 1, 'is_initial' => 1]);
        $repository->insertLifecycleState(['lifecycle_id' => $lifecycleId, 'state_id' => $destination, 'sequence' => 2]);
        $repository->insertTransition(['lifecycle_id' => $lifecycleId, 'from_state_id' => $from, 'to_state_id' => $destination, 'action_code' => 'approve', 'status' => 'ACTIVE']);

        $deniedAdapter = new TestLifecycleAdapter($this->snapshot('product', 1, $from, $lifecycleId, 30));
        $deniedAudit = new TestLifecycleAudit();
        $service = new LifecycleService(repository: $repository, authorization: new TestLifecycleAuthorization('DENY'), audit: $deniedAudit, adapters: [$deniedAdapter]);
        try {
            $service->execute('product', 1, 'approve', $this->operation());
            $this->fail('Expected authorization denial.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertNull($deniedAdapter->mutatedDestination);
        $this->assertSame(1, $deniedAudit->calls);

        $racedAdapter = new TestLifecycleAdapter($this->snapshot('product', 1, $from, $lifecycleId, 30));
        $racedAdapter->reload = $this->snapshot('product', 1, $destination, $lifecycleId, 30);
        $this->expectException(ConcurrencyException::class);
        (new LifecycleService(repository: $repository, authorization: new TestLifecycleAuthorization(), audit: new TestLifecycleAudit(), adapters: [$racedAdapter]))->execute('product', 1, 'approve', $this->operation());
    }

    private function lifecycle(LifecycleRepository $repository, string $status): int
    {
        return $repository->insertLifecycle(['code' => uniqid('WF002-L-', true), 'name' => 'WF002', 'status' => $status]);
    }

    private function state(LifecycleRepository $repository, string $status): int
    {
        return $repository->insertWorkflowState(['code' => uniqid('WF002-S-', true), 'name' => 'WF002', 'status' => $status]);
    }

    private function operation(): OperationContext
    {
        return new OperationContext(55, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:00:00+00:00')), 'wf-002-test', false);
    }

    private function snapshot(string $resource, int $targetId, int $stateId, int $lifecycleId, int $organizationId): LifecycleTargetSnapshot
    {
        return new LifecycleTargetSnapshot($resource, $targetId, $stateId, $lifecycleId, $organizationId, ['id' => $targetId, 'organization_id' => $organizationId]);
    }

    private function membershipId(int $lifecycleId, int $stateId): int
    {
        $result = $this->db->table('lifecycle_states')->where('lifecycle_id', $lifecycleId)->where('state_id', $stateId)->get();
        $this->assertNotFalse($result);
        $row = $result->getRowArray();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);

        return (int) $row['id'];
    }
}

final class TestLifecycleAuthorization implements LifecycleAuthorizationPort
{
    public int $calls = 0;

    public function __construct(private readonly string $decision = 'ALLOW')
    {
    }

    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        ++$this->calls;

        return new AuthorizationDecision(
            $this->decision,
            $this->decision === 'ALLOW' ? AuthorizationService::REASON_POLICY_ALLOWED : AuthorizationService::REASON_POLICY_DENIED,
            $operation->actorUserId ?? 55,
            $resourceType,
            $action,
            (int) ($contextRequest->persistedTarget['organization_id'] ?? 1),
            (int) $stateId,
            $operation->evaluationTime,
            [$resourceType . '.' . $action],
            [],
            [],
            [],
            $operation->requestId,
        );
    }
}

final class TestLifecycleAudit implements LifecycleAuditPort
{
    public int $calls = 0;

    public function recordIfRequired(AuthorizationDecision $decision): ?int
    {
        ++$this->calls;

        return $this->calls > 0 ? $this->calls : null;
    }
}

final class TestLifecycleAdapter implements LifecycleTargetAdapter
{
    public ?LifecycleTargetSnapshot $reload = null;
    public ?int $mutatedExpected = null;
    public ?int $mutatedDestination = null;
    public int $transactionCalls = 0;

    public function __construct(private readonly LifecycleTargetSnapshot $snapshot)
    {
    }

    public function resourceType(): string
    {
        return $this->snapshot->resourceType;
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->snapshot;
    }

    public function transactional(callable $callback): mixed
    {
        ++$this->transactionCalls;

        return $callback();
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        return $this->reload ?? $this->snapshot;
    }

    public function mutate(LifecycleTargetSnapshot $lockedTarget, int $expectedCurrentStateId, int $destinationStateId, OperationContext $operation): void
    {
        $this->mutatedExpected = $expectedCurrentStateId;
        $this->mutatedDestination = $destinationStateId;
    }
}

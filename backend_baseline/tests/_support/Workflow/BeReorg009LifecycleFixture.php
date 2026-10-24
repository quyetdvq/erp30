<?php

declare(strict_types=1);

namespace Tests\Support\Workflow;

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\LifecycleRepository;
use App\Services\LifecycleAuditPort;
use App\Services\LifecycleAuthorizationPort;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Database\ConnectionInterface;

/** Test-only construction of fresh catalog rows on a supplied guarded erp_test connection. */
final class BeReorg009LifecycleFixture
{
    public static function lifecycle(ConnectionInterface $db, string $status = 'ACTIVE'): int
    {
        return (new LifecycleRepository($db))->insertLifecycle([
            'code' => 'BE009-L-' . uniqid('', true), 'name' => 'BE009', 'status' => $status,
        ]);
    }

    public static function state(ConnectionInterface $db, string $status = 'ACTIVE'): int
    {
        return (new LifecycleRepository($db))->insertWorkflowState([
            'code' => 'BE009-S-' . uniqid('', true), 'name' => 'BE009', 'status' => $status,
        ]);
    }

    public static function bind(ConnectionInterface $db, int $lifecycleId, int $stateId, int $sequence, bool $initial = false, bool $final = false): int
    {
        return (new LifecycleRepository($db))->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $stateId, 'sequence' => $sequence,
            'is_initial' => $initial ? 1 : 0, 'is_final' => $final ? 1 : 0,
        ]);
    }

    public static function operation(int $actor = 55): OperationContext
    {
        return new OperationContext($actor, new EvaluationTime(new \DateTimeImmutable('2026-10-17T05:00:00+00:00')), 'be009-test', false);
    }

    public static function snapshot(int $lifecycleId, int $stateId, int $orgId = 30): LifecycleTargetSnapshot
    {
        return new LifecycleTargetSnapshot('product', 1, $stateId, $lifecycleId, $orgId, ['id' => 1, 'organization_id' => $orgId]);
    }

    /** @return array{int,int,int} */
    public static function transition(ConnectionInterface $db): array
    {
        $lifecycleId = self::lifecycle($db);
        $from = self::state($db);
        $to = self::state($db);
        self::bind($db, $lifecycleId, $from, 1, true);
        self::bind($db, $lifecycleId, $to, 2, false, true);
        (new LifecycleRepository($db))->insertTransition([
            'lifecycle_id' => $lifecycleId, 'from_state_id' => $from,
            'to_state_id' => $to, 'action_code' => 'approve', 'status' => 'ACTIVE',
        ]);
        return [$lifecycleId, $from, $to];
    }
}

/** Test port with observable trusted authorization input; never involved in production wiring. */
final class BeReorg009Authorization implements LifecycleAuthorizationPort
{
    public int $calls = 0;
    public ?EvaluationOrganizationRequest $request = null;
    public mixed $stateId = null;

    public function __construct(public string $decision = 'ALLOW')
    {
    }

    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        ++$this->calls;
        $this->request = $contextRequest;
        $this->stateId = $stateId;
        return new AuthorizationDecision(
            $this->decision, $this->decision === 'ALLOW' ? AuthorizationService::REASON_POLICY_ALLOWED : AuthorizationService::REASON_POLICY_DENIED,
            $operation->actorUserId ?? 55, $resourceType, $action,
            (int) ($contextRequest->persistedTarget['organization_id'] ?? 30), (int) $stateId,
            $operation->evaluationTime, [$resourceType . '.' . $action], [], [], [], $operation->requestId,
        );
    }
}

final class BeReorg009Audit implements LifecycleAuditPort
{
    public int $calls = 0;
    public ?AuthorizationDecision $last = null;

    public function recordIfRequired(AuthorizationDecision $decision): ?int
    {
        ++$this->calls;
        $this->last = $decision;
        return $this->calls;
    }
}

/** Adapter with transaction-failure recording, useful for snapshot parity and fail-closed tests. */
final class BeReorg009Adapter implements LifecycleTargetAdapter
{
    public int $transactions = 0;
    public int $rollbacks = 0;
    public int $locks = 0;
    public int $mutations = 0;
    public ?int $lastDestination = null;
    public ?LifecycleTargetSnapshot $lockedOverride = null;

    public function __construct(public LifecycleTargetSnapshot $snapshot)
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
        ++$this->transactions;
        try {
            return $callback();
        } catch (\Throwable $exception) {
            ++$this->rollbacks;
            throw $exception;
        }
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        ++$this->locks;
        return $this->lockedOverride ?? $this->snapshot;
    }

    public function mutate(LifecycleTargetSnapshot $lockedTarget, int $expectedCurrentStateId, int $destinationStateId, OperationContext $operation): void
    {
        ++$this->mutations;
        $this->lastDestination = $destinationStateId;
    }
}

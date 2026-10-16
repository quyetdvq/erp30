<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Authorization\BeReorg008DecisionFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Independent BE007 oracle versus canonical BE008 engine on the same real DB fixtures. */
final class BeReorg008DecisionParityTest extends CIUnitTestCase
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
        if (isset($this->fixture)) {
            $this->fixture->restoreTrackedInactivePolicies();
            $this->fixture->restoreTrackedResourceStatuses();
        }
        $this->resetServices();
        parent::tearDown();
    }

    public function testNoBasePermissionAndEligibleNoMatchReturnSameEvidence(): void
    {
        $denied = $this->fixture->seedActor(false);
        $result = $this->parity($denied);
        $this->assertSame('DENY', $result->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $result->reasonCode);
        $this->assertSame([], $result->basePermissionSource);

        $eligible = $this->fixture->seedActor();
        $allowed = $this->parity($eligible);
        $this->assertSame('ALLOW', $allowed->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $allowed->reasonCode);
        $this->assertCount(1, $allowed->participatingAssignmentIds);
        $this->assertSame('be008-parity', $allowed->requestId);
        $this->assertSame('2026-10-05T12:00:00+00:00', $allowed->toArray()['evaluation_time']);
        $this->assertSame(['price_list.view'], $allowed->basePermissionSource);
    }

    public function testAllowDenyAndAbsoluteDenyHaveSameLaneEvidence(): void
    {
        $actor = $this->fixture->seedActor();
        $deny = $this->fixture->addPolicy($actor['company_id'], 'USER', $actor['user_id'], 'DENY');
        $allow = $this->fixture->addPolicy($actor['company_id'], 'GROUP', $actor['group_id'], 'ALLOW');
        $decision = $this->parity($actor);
        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $decision->reasonCode);
        $matched = [$deny, $allow];
        sort($matched);
        $this->assertSame($matched, $decision->matchedPolicyIds);
        $this->assertSame([$allow], $decision->decisivePolicyIds);

        $absolute = $this->fixture->addPolicy($actor['root_id'], 'USER', $actor['user_id'], 'ABSOLUTE_DENY');
        $blocked = $this->parity($actor);
        $this->assertSame('DENY', $blocked->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $blocked->reasonCode);
        $this->assertSame([$absolute], $blocked->decisivePolicyIds);
    }

    public function testAssignmentIntersectionAndInheritedScopeMatch(): void
    {
        $actor = $this->fixture->seedActor(true, true, false);
        $employee = $this->fixture->createEmployee($actor['user_id']);
        $departments = [$this->fixture->createDepartment(), $this->fixture->createDepartment()];
        foreach ($departments as $department) {
            $this->fixture->addAssignment($employee, $actor['company_id'], $department);
            $this->fixture->addPolicy($actor['company_id'], 'DEPARTMENT', $department, 'DENY');
        }
        $this->fixture->addPolicy($actor['root_id'], 'USER', $actor['user_id'], 'DENY');
        $result = $this->parity($actor);
        $this->assertSame('DENY', $result->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_DENIED, $result->reasonCode);
        $this->assertCount(2, $result->participatingAssignmentIds);
        $this->assertCount(3, $result->decisivePolicyIds);

        $this->assertNotNull($actor['sibling_id']);
        $siblingPolicy = $this->fixture->addPolicy($actor['sibling_id'], 'GROUP', $actor['group_id'], 'ABSOLUTE_DENY');
        $stillScoped = $this->parity($actor);
        $this->assertNotContains($siblingPolicy, $stillScoped->matchedPolicyIds);
        $this->assertSame($result->toArray(), $stillScoped->toArray());
    }

    public function testEmployeeEligibilityAndSystemMasterScopeMatch(): void
    {
        $noEmployee = $this->fixture->seedActor(true, false, false);
        $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $this->parity($noEmployee)->reasonCode);

        $inactive = $this->fixture->seedActor(true, false, false);
        $this->fixture->createEmployee($inactive['user_id'], 'INACTIVE');
        $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $this->parity($inactive)->reasonCode);

        $noAssignment = $this->fixture->seedActor(true, false, false);
        $this->fixture->createEmployee($noAssignment['user_id']);
        $this->assertSame(AuthorizationService::REASON_NO_EFFECTIVE_ASSIGNMENT, $this->parity($noAssignment)->reasonCode);

        $actor = $this->fixture->seedActor(true, true, false);
        $this->assertNotNull($actor['sibling_id']);
        $employee = $this->fixture->createEmployee($actor['user_id']);
        $this->fixture->addAssignment($employee, $actor['sibling_id']);
        $this->assertSame(AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE, $this->parity($actor)->reasonCode);
        foreach (['user', 'product'] as $resource) {
            $this->fixture->grant($actor['group_id'], $resource, 'view');
            $decision = $this->parity($actor, $resource);
            $this->assertSame('ALLOW', $decision->decision, $resource);
            $this->assertSame($actor['root_id'], $decision->evaluationOrganizationId, $resource);
        }
    }

    public function testInactiveResourceAndSetstateRecoveryMatch(): void
    {
        $actor = $this->fixture->seedActor();
        $resourceId = $this->fixture->resourceId('price_list');
        $prior = (string) $this->db->table('resources')->where('id', $resourceId)->get()->getRowArray()['status'];
        $this->fixture->trackResourceStatus($resourceId, $prior);
        $this->assertTrue($this->db->table('resources')->where('id', $resourceId)->update(['status' => 'INACTIVE']));
        $blocked = $this->parity($actor);
        $this->assertSame('DENY', $blocked->decision);
        $this->assertSame(AuthorizationService::REASON_RESOURCE_INACTIVE, $blocked->reasonCode);

        // The existing resource.setstate recovery action still needs ordinary base permission.
        $recovery = $this->parity($actor, 'resource', 'setstate',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $actor['root_id']]));
        $this->assertSame('DENY', $recovery->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $recovery->reasonCode);
    }

    public function testTrustedStateAndClientContextMismatchErrorsMatch(): void
    {
        $actor = $this->fixture->seedActor(true, true);
        $this->assertNotNull($actor['sibling_id']);
        $operation = $this->fixture->operation($actor['user_id']);
        $trusted = new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $actor['company_id']],
            clientEvaluationOrganizationId: $actor['sibling_id'],
        );
        $this->compareFailure($operation, 'price_list', 'view', $trusted,
            'EVALUATION_ORGANIZATION_HINT_MISMATCH');
        $this->compareFailure($operation, 'price_list', 'view', $this->fixture->request($actor['company_id']),
            'WF_AUTHORIZATION_STATE_MISMATCH', 999999);
    }

    public function testInvalidInputsEffectsAndPersistenceFailuresStayTypedAndFailClosed(): void
    {
        $actor = $this->fixture->seedActor();
        $request = $this->fixture->request($actor['company_id']);
        $operation = $this->fixture->operation($actor['user_id']);
        $this->compareFailure(new OperationContext(null, $operation->evaluationTime, 'be008-system', true),
            'price_list', 'view', $request, AuthorizationService::REASON_CONTEXT_REQUIRED);
        $this->compareFailure($operation, 'price_list', 'invalid.action', $request, AuthorizationService::ERROR_ACTION_INVALID);
        $this->compareFailure($operation, 'unregistered_type', 'view', $request, AuthorizationService::ERROR_RESOURCE_INVALID);
        $this->compareFailure($operation, 'contact', 'view', $request, 'CONTACT_LEGACY_RESOURCE_RETIRED');

        $policyId = $this->fixture->addPolicy($actor['company_id'], 'USER', $actor['user_id'], 'ALLOW');
        $this->assertTrue($this->db->table('policy_actions')->where('policy_id', $policyId)->update(['effect' => 'NOT_AN_EFFECT']));
        try {
            $this->compareFailure($operation, 'price_list', 'view', $request, AuthorizationService::ERROR_POLICY_INVALID);
        } finally {
            $this->db->table('policy_actions')->where('policy_id', $policyId)->update(['effect' => 'ALLOW']);
        }

        // Preserve the accepted failure seam: only PolicyRepository has a failing connection.
        $fault = $this->createMock(ConnectionInterface::class);
        $fault->method('table')->willThrowException(new RuntimeException('simulated Policy DB unavailable'));
        $policy = new PolicyRepository($fault);
        $first = $this->capture(fn (): AuthorizationDecision =>
            (new AuthorizationService(policies: $policy, db: $this->db))->authorize($operation, 'price_list', 'view', $request));
        $second = $this->capture(fn (): AuthorizationDecision =>
            (new PbacDecisionEngine(policies: $policy, db: $this->db))->authorize($operation, 'price_list', 'view', $request));
        $this->assertSame($first::class, $second::class);
        $this->assertInstanceOf(PersistenceException::class, $first);
        $this->assertSame($first->getErrorCode(), $second->getErrorCode());
        $this->assertSame($first->getContext(), $second->getContext());
    }

    /** @param array{user_id:int,company_id:int} $actor */
    private function parity(array $actor, string $resource = 'price_list', string $action = 'view', ?EvaluationOrganizationRequest $request = null): AuthorizationDecision
    {
        $operation = $this->fixture->operation($actor['user_id']);
        $target = $request ?? $this->fixture->request($actor['company_id']);
        $expected = (new AuthorizationService(db: $this->db))->authorize($operation, $resource, $action, $target);
        $actual = (new PbacDecisionEngine(db: $this->db))->authorize($operation, $resource, $action, $target);
        $this->assertSame($expected->toArray(), $actual->toArray());
        return $actual;
    }

    private function compareFailure(OperationContext $operation, string $resource, string $action,
        EvaluationOrganizationRequest $request, string $code, mixed $stateId = null): void
    {
        $expected = $this->capture(fn (): AuthorizationDecision => (new AuthorizationService(db: $this->db))
            ->authorize($operation, $resource, $action, $request, $stateId));
        $actual = $this->capture(fn (): AuthorizationDecision => (new PbacDecisionEngine(db: $this->db))
            ->authorize($operation, $resource, $action, $request, $stateId));
        $this->assertSame($expected::class, $actual::class);
        $this->assertInstanceOf(DomainException::class, $actual);
        $this->assertSame($code, $expected->getErrorCode());
        $this->assertSame($expected->getErrorCode(), $actual->getErrorCode());
        $this->assertSame($expected->getContext(), $actual->getContext());
    }

    private function capture(callable $operation): Throwable
    {
        try {
            $operation();
            $this->fail('Expected a typed authorization error.');
        } catch (DomainException $exception) {
            return $exception;
        }
    }
}

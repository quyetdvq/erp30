<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationRequest;
use App\Bootstrap\AuthorizationDecisionFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use App\Services\LifecycleAuthorizationPort;
use App\Services\NumberingAuthorizationPort;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Exact existing entrypoint contract: consumers do not change. */
final class BeReorg008DecisionCompatibilityTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;
    protected $namespace = null;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }

    public function testLegacyConstructorRetainsTenOptionalNamedParameters(): void
    {
        $facade = new ReflectionClass(AuthorizationService::class);
        $this->assertTrue($facade->isFinal());
        $args = $facade->getConstructor()->getParameters();
        $this->assertSame(['profiles', 'contexts', 'policies', 'employees', 'assignments',
            'organizations', 'identities', 'db', 'catalog', 'workflowStates'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $args));
        $this->assertSame([
            'App\\Authorization\\EffectiveProfileResolver',
            'App\\Authorization\\EvaluationOrganizationResolver',
            'App\\Repositories\\PolicyRepository',
            'App\\Repositories\\EmployeeRepository',
            'App\\Services\\EmployeeAssignmentService',
            'App\\Services\\OrganizationService',
            'App\\Authorization\\ShieldIdentityAdapter',
            ConnectionInterface::class,
            'App\\Repositories\\ResourceCatalogRepository',
            'App\\Services\\ResourceWorkflowStateResolver',
        ], array_map(static fn (ReflectionParameter $p): string => ltrim((string) $p->getType(), '?'), $args));
        foreach ($args as $argument) {
            $this->assertTrue($argument->allowsNull());
            $this->assertTrue($argument->isDefaultValueAvailable());
            $this->assertNull($argument->getDefaultValue());
        }
        $named = new AuthorizationService(db: $this->db);
        $positional = new AuthorizationService(null, null, null, null, null, null, null, $this->db);
        $this->assertInstanceOf(AuthorizationService::class, $named);
        $this->assertInstanceOf(AuthorizationService::class, $positional);
    }

    public function testLegacyFifteenConstantsRetainValues(): void
    {
        $constants = [
            'DECISION_ALLOW' => 'ALLOW', 'DECISION_DENY' => 'DENY',
            'REASON_NO_BASE_PERMISSION' => 'AUTH_NO_BASE_PERMISSION',
            'REASON_EMPLOYEE_INELIGIBLE' => 'AUTH_EMPLOYEE_INELIGIBLE',
            'REASON_NO_EFFECTIVE_ASSIGNMENT' => 'AUTH_NO_EFFECTIVE_ASSIGNMENT',
            'REASON_OUT_OF_ASSIGNMENT_SCOPE' => 'AUTH_OUT_OF_ASSIGNMENT_SCOPE',
            'REASON_POLICY_DENIED' => 'AUTH_POLICY_DENIED',
            'REASON_ABSOLUTE_DENY' => 'AUTH_ABSOLUTE_DENY',
            'REASON_NO_MATCH' => 'AUTH_NO_MATCH_ALLOW',
            'REASON_POLICY_ALLOWED' => 'AUTH_POLICY_ALLOWED',
            'REASON_RESOURCE_INACTIVE' => 'AUTH_RESOURCE_INACTIVE',
            'REASON_CONTEXT_REQUIRED' => 'AUTH_CONTEXT_REQUIRED',
            'ERROR_RESOURCE_INVALID' => 'AUTH_RESOURCE_INVALID',
            'ERROR_ACTION_INVALID' => 'AUTH_ACTION_INVALID',
            'ERROR_POLICY_INVALID' => 'AUTH_POLICY_INVALID',
        ];
        $this->assertCount(15, $constants);
        $this->assertSame($constants, (new ReflectionClass(AuthorizationService::class))->getConstants());
        $this->assertSame($constants, (new ReflectionClass(PbacDecisionEngine::class))->getConstants());
    }

    public function testLegacyPortsAndAuthorizeBatchSignaturesMatchBaseline(): void
    {
        $legacy = new ReflectionClass(AuthorizationService::class);
        $this->assertTrue($legacy->implementsInterface(LifecycleAuthorizationPort::class));
        $this->assertTrue($legacy->implementsInterface(NumberingAuthorizationPort::class));
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $legacy->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($methods);
        $this->assertSame(['__construct', 'authorize', 'withReadOnlyBatch'], $methods);
        $this->assertSame(['operation', 'resourceType', 'action', 'contextRequest', 'stateId'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getMethod('authorize')->getParameters()));
        $this->assertSame(AuthorizationDecision::class, (string) $legacy->getMethod('authorize')->getReturnType());
        $this->assertSame('mixed', (string) $legacy->getMethod('withReadOnlyBatch')->getReturnType());
        $this->assertStringContainsString('@template T', (string) $legacy->getMethod('withReadOnlyBatch')->getDocComment());
        $this->assertSame((string) (new ReflectionClass(PbacDecisionPort::class))->getMethod('authorize')->getReturnType(),
            (string) $legacy->getMethod('authorize')->getReturnType());
    }

    public function testPositionalAndNamedConstructionPreservesDtoContracts(): void
    {
        $service = new AuthorizationService(db: $this->db);
        $engine = (new ReflectionProperty($service, 'engine'))->getValue($service);
        $this->assertInstanceOf(PbacDecisionPort::class, $engine);
        $this->assertFalse((new ReflectionClass(AuthorizationService::class))->hasProperty('readOnlyContext'));
        $request = new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => 7], input: ['status' => 'ACTIVE']);
        $this->assertSame(7, $request->persistedTarget['organization_id']);
        $time = new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00'));
        $decision = new AuthorizationDecision('DENY', 'AUTH_POLICY_DENIED', 4, 'price_list', 'view', 7, null, $time,
            ['price_list.view'], [9, 3, 9], [9], [12, 12], 'be008-contract');
        $this->assertSame(['decision', 'reason_code', 'user_id', 'resource_type', 'resource_action',
            'evaluation_organization_id', 'state_id', 'evaluation_time', 'base_permission_source',
            'matched_policy_ids', 'decisive_policy_ids', 'participating_assignment_ids', 'request_id'],
            array_keys($decision->toArray()));
        $this->assertSame([3, 9], $decision->matchedPolicyIds);
        $this->assertSame([12], $decision->participatingAssignmentIds);
        $this->assertSame('be008-contract', $decision->requestId);
    }
}

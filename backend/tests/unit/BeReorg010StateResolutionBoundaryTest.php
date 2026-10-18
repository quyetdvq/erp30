<?php

declare(strict_types=1);

use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;
use App\Modules\Workflow\StateResolution\Infrastructure\Persistence\ExistingRepositoryStateReadAdapter;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use PHPUnit\Framework\TestCase;

/** @internal BE010 module boundary guard. */
final class BeReorg010StateResolutionBoundaryTest extends TestCase
{
    public function testApplicationPortDeclaresExactlyElevenOperations(): void
    {
        $class = new ReflectionClass(ResourceStateResolutionPort::class);
        $this->assertTrue($class->isInterface());
        $actual = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $class->getMethods());
        sort($actual);
        $expected = ['resource', 'lifecycleId', 'persistedStateId', 'stateIdForCode', 'codeForStateId',
            'allowedPolicyStateCodes', 'assertLifecycleReassignmentSafe', 'initialCode',
            'assertLifecycleStateCompatibility', 'assertPolicyState', 'stateForAuthorization'];
        sort($expected);
        $this->assertSame($expected, $actual);
        $this->assertSame(['type', 'persistedTarget', 'explicitStateId', 'trustedCatalogRow', 'action', 'input'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $class->getMethod('stateForAuthorization')->getParameters()));
        $this->assertSame('array', (string) $class->getMethod('resource')->getReturnType());
        $this->assertSame('?int', (string) $class->getMethod('stateForAuthorization')->getReturnType());
    }

    public function testReadPortDeclaresExactlyEightReadOnlyOperations(): void
    {
        $class = new ReflectionClass(ResourceStateReadPort::class);
        $this->assertTrue($class->isInterface());
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $class->getMethods());
        sort($names);
        $expected = ['findResourceByType', 'findResource', 'findLifecycle', 'findLifecycleStates',
            'findActiveWorkflowState', 'findActiveWorkflowStateByCode', 'findWorkflowStateByCode', 'findAllPoliciesForResource'];
        sort($expected);
        $this->assertSame($expected, $names);
        $this->assertSame('int', (string) $class->getMethod('findAllPoliciesForResource')->getParameters()[0]->getType());
        $this->assertSame('array', (string) $class->getMethod('findLifecycleStates')->getReturnType());
    }

    public function testContractsKeepEightPublicErrorsAndInternalConflictCode(): void
    {
        $r = new ReflectionClass(ResourceStateResolutionErrors::class);
        $this->assertTrue($r->isFinal());
        $this->assertFalse($r->getConstructor()->isPublic());
        $this->assertSame([
            'ERROR_STATE_NOT_MAPPED' => 'WF_RESOURCE_STATE_NOT_MAPPED',
            'ERROR_RESOURCE_LIFECYCLE_INVALID' => 'WF_RESOURCE_LIFECYCLE_INVALID',
            'ERROR_INITIAL_STATE_INVALID' => 'WF_INITIAL_STATE_INVALID',
            'ERROR_POLICY_STATE_INVALID' => 'POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE',
            'ERROR_STATE_CONTEXT_MISMATCH' => 'WF_AUTHORIZATION_STATE_MISMATCH',
            'ERROR_LIFECYCLE_SELF_REFERENCE' => 'WF_LIFECYCLE_SELF_REFERENCE',
            'ERROR_INITIAL_STATE_REQUIRED' => 'WF_INITIAL_STATE_REQUIRED',
            'ERROR_TRANSITION_REQUIRED' => 'WF_TRANSITION_REQUIRED',
            'ERROR_LIFECYCLE_POLICY_CONFLICT' => 'RESOURCE_LIFECYCLE_POLICY_CONFLICT',
        ], $r->getConstants());
    }

    public function testReadAdapterImplementsPortWithThreeExistingCollaborators(): void
    {
        $r = new ReflectionClass(ExistingRepositoryStateReadAdapter::class);
        $this->assertTrue($r->isFinal());
        $this->assertTrue($r->implementsInterface(ResourceStateReadPort::class));
        $ctor = $r->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(['resources', 'lifecycles', 'policies'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $ctor->getParameters()));
        $this->assertSame([ResourceCatalogRepository::class, LifecycleRepository::class, PolicyRepository::class],
            array_map(static fn (ReflectionParameter $p): string => (string) $p->getType(), $ctor->getParameters()));
        foreach (['resources', 'lifecycles', 'policies'] as $property) {
            $this->assertTrue($r->getProperty($property)->isPrivate());
            $this->assertTrue($r->getProperty($property)->isReadOnly());
        }
    }

    public function testPortAndAdapterDoNotExposeMutationOrSqlSurface(): void
    {
        $r = new ReflectionClass(ExistingRepositoryStateReadAdapter::class);
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $r->getMethods(ReflectionMethod::IS_PUBLIC));
        $this->assertCount(9, $names); // eight reads + constructor
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/^(insert|update|delete|trans|table|execute|authorize)/i', $name);
        }
        $contents = (string) file_get_contents($r->getFileName());
        foreach (['->table(', '->query(', 'transBegin(', 'FOR UPDATE', '->where(', '->orderBy('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $contents);
        }
        $this->assertSame([], array_filter((new ReflectionClass(ResourceStateReadPort::class))->getMethods(),
            static fn (ReflectionMethod $m): bool => preg_match('/^(insert|update|delete|trans|table)/i', $m->getName()) === 1));
    }
}

<?php

declare(strict_types=1);

use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort;
use App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleRepositoryPort;
use PHPUnit\Framework\TestCase;

/** @internal */
final class BeReorg009LifecycleBoundaryTest extends TestCase
{
    public function testApplicationPortDeclaresFiveCanonicalOperations(): void
    {
        $this->assertTrue(interface_exists(LifecycleApplicationPort::class));
        $interface = new ReflectionClass(LifecycleApplicationPort::class);
        $names = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $interface->getMethods());
        sort($names);
        $this->assertSame(['configure', 'execute', 'registerTargetAdapter', 'resolveTransition', 'validateConfiguration'], $names);
        $this->assertSame(['resourceType', 'targetId', 'actionCode', 'operation', 'expectedDestinationStateId'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $interface->getMethod('execute')->getParameters()));
        $this->assertSame('void', (string) $interface->getMethod('registerTargetAdapter')->getReturnType());
        $this->assertSame('array', (string) $interface->getMethod('configure')->getReturnType());
        $this->assertStringContainsString('list<array<string,mixed>>', str_replace(' ', '', $interface->getMethod('configure')->getDocComment() ?: ''));
    }

    public function testRepositoryPortExposesOnlyNineApplicationOperations(): void
    {
        $this->assertTrue(interface_exists(LifecycleRepositoryPort::class));
        $interface = new ReflectionClass(LifecycleRepositoryPort::class);
        $names = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $interface->getMethods());
        sort($names);
        $this->assertSame([
            'findActiveTransitionByAction', 'findActiveWorkflowState', 'findLifecycle', 'findLifecycleStates',
            'findTransitions', 'insertLifecycleState', 'insertTransition', 'lockLifecycle', 'transactional',
        ], $names);
        $this->assertSame('mixed', (string) $interface->getMethod('transactional')->getReturnType());
        $this->assertSame('transaction', $interface->getMethod('transactional')->getParameters()[1]->getDefaultValue());
        $this->assertSame(['id'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $interface->getMethod('findLifecycle')->getParameters()));
    }
    public function testCanonicalOwnersDoNotImportLegacyFacades(): void
    {
        $app = \App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService::class;
        $sql = \App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository::class;
        $this->assertTrue(class_exists($app));
        $this->assertTrue(class_exists($sql));
        $this->assertTrue((new ReflectionClass($app))->isFinal());
        $this->assertTrue((new ReflectionClass($sql))->isFinal());
        $this->assertTrue(is_subclass_of($app, LifecycleApplicationPort::class));
        $this->assertTrue(is_subclass_of($sql, LifecycleRepositoryPort::class));
        foreach ([new ReflectionClass($app), new ReflectionClass($sql)] as $owner) {
            $contents = file_get_contents($owner->getFileName());
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('use App\\Repositories\\LifecycleRepository;', $contents);
            $this->assertStringNotContainsString('use App\\Services\\LifecycleService;', $contents);
        }
        foreach (['assertConfiguration', 'assertBatchShape', 'membership', 'flag', 'actionCode', 'resourceType', 'repo', 'auth', 'audit', 'operation'] as $method) {
            $this->assertTrue(method_exists($app, $method), $method);
            $this->assertTrue((new ReflectionMethod($app, $method))->isPrivate(), $method);
        }
    }

    public function testFacadesDelegateWithoutBusinessOrSqlImplementation(): void
    {
        foreach ([\App\Repositories\LifecycleRepository::class, \App\Services\LifecycleService::class] as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isFinal());
            $code = file_get_contents($reflection->getFileName());
            $this->assertIsString($code);
            $this->assertStringNotContainsString('->table(', $code);
            $this->assertStringNotContainsString('transBegin(', $code);
            $this->assertStringNotContainsString('function assertConfiguration(', $code);
            $this->assertStringNotContainsString('function findLockedRow(', $code);
            $this->assertStringNotContainsString('function __call(', $code);
            $this->assertTrue($reflection->hasProperty($class === \App\Services\LifecycleService::class ? 'engine' : 'delegate'));
        }
    }

    public function testExistingAdaptersAndDtoContractsRemainUnchanged(): void
    {
        $adapter = new ReflectionClass(\App\Services\LifecycleTargetAdapter::class);
        $this->assertTrue($adapter->isInterface());
        $this->assertCount(5, $adapter->getMethods());
        $dto = new ReflectionClass(\App\Services\LifecycleTargetSnapshot::class);
        $this->assertTrue($dto->isReadOnly());
        $this->assertSame(['resourceType', 'targetId', 'currentStateId', 'lifecycleId', 'evaluationOrganizationId', 'authorizationTarget'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $dto->getConstructor()->getParameters()));
        $this->assertCount(1, (new ReflectionClass(\App\Services\LifecycleAuthorizationPort::class))->getMethods());
        $this->assertCount(1, (new ReflectionClass(\App\Services\LifecycleAuditPort::class))->getMethods());
        $this->assertCount(10, \App\Services\ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
    }
}

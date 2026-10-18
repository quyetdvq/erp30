<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService;
use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\ResourceWorkflowStateResolver;
use PHPUnit\Framework\TestCase;
use Tests\Support\Workflow\BeReorg010StateReadFixture as ReadFixture;

/** @internal Constructor/API signatures are frozen from accepted BE009. */
final class BeReorg010StateResolutionCompatibilityTest extends TestCase
{
    private function injected(): ResourceWorkflowStateResolver
    {
        return new ResourceWorkflowStateResolver(
            resources: (new ReflectionClass(ResourceCatalogRepository::class))->newInstanceWithoutConstructor(),
            lifecycles: (new ReflectionClass(LifecycleRepository::class))->newInstanceWithoutConstructor(),
            policies: (new ReflectionClass(PolicyRepository::class))->newInstanceWithoutConstructor(),
        );
    }

    public function testFacadeConstructorRetainsFourPositionalAndNamedParameters(): void
    {
        $ctor = (new ReflectionClass(ResourceWorkflowStateResolver::class))->getConstructor();
        $this->assertNotNull($ctor);
        $params = $ctor->getParameters();
        $this->assertSame(['db', 'resources', 'lifecycles', 'policies'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $params));
        $this->assertSame(['?CodeIgniter\\Database\\ConnectionInterface', '?'.ResourceCatalogRepository::class,
            '?'.LifecycleRepository::class, '?'.PolicyRepository::class],
            array_map(static fn (ReflectionParameter $p): string => (string) $p->getType(), $params));
        foreach ($params as $p) {
            $this->assertTrue($p->isDefaultValueAvailable());
            $this->assertNull($p->getDefaultValue());
        }
        $this->assertInstanceOf(ResourceWorkflowStateResolver::class, $this->injected());
    }

    public function testFacadeRetainsEightConstantsAndElevenMethodSignatures(): void
    {
        $legacy = new ReflectionClass(ResourceWorkflowStateResolver::class);
        $port = new ReflectionClass(ResourceStateResolutionPort::class);
        $this->assertTrue($legacy->isFinal());
        $expected = (new ReflectionClass(ResourceStateResolutionErrors::class))->getConstants();
        unset($expected['ERROR_LIFECYCLE_POLICY_CONFLICT']);
        $this->assertSame($expected, $legacy->getConstants());
        $public = array_values(array_filter($legacy->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $m): bool => $m->getName() !== '__construct'));
        $this->assertCount(11, $public);
        foreach ($port->getMethods() as $method) {
            $facade = $legacy->getMethod($method->getName());
            $this->assertSame((string) $method->getReturnType(), (string) $facade->getReturnType(), $method->getName());
            foreach (['getName', 'getType', 'isOptional'] as $check) {
                $this->assertSame(array_map(static fn (ReflectionParameter $p): string => (string) $p->$check(), $method->getParameters()),
                    array_map(static fn (ReflectionParameter $p): string => (string) $p->$check(), $facade->getParameters()),
                    $method->getName() . '/' . $check);
            }
        }
    }

    public function testFacadeKeepsSingleEngineWithExplicitDelegation(): void
    {
        $facade = $this->injected();
        $engine = (new ReflectionProperty($facade, 'engine'))->getValue($facade);
        $this->assertInstanceOf(ResourceStateResolutionPort::class, $engine);
        $props = array_map(static fn (ReflectionProperty $p): string => $p->getName(),
            (new ReflectionClass($facade))->getProperties());
        $this->assertSame(['engine'], $props);
        $this->assertSame($engine, (new ReflectionProperty($facade, 'engine'))->getValue($facade));
        $this->assertFalse(method_exists($facade, '__call'));
        $source = (string) file_get_contents((new ReflectionClass($facade))->getFileName());
        foreach (['function members(', 'resourceCache', 'memberCodes', 'nativeIds', '->table(', '->query('] as $internal) {
            $this->assertStringNotContainsString($internal, $source);
        }
        foreach ((new ReflectionClass(ResourceStateResolutionPort::class))->getMethods() as $method) {
            $this->assertStringContainsString('$this->engine->' . $method->getName() . '(', $source);
        }
    }

    public function testFacadePreservesReturnShapesAndDomainErrorContext(): void
    {
        $engine = new ResourceStateResolutionService(ReadFixture::standard());
        $legacy = (new ReflectionClass(ResourceWorkflowStateResolver::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($legacy, 'engine'))->setValue($legacy, $engine);
        $this->assertSame(['ACTIVE', 'INACTIVE'], $legacy->allowedPolicyStateCodes('policy'));
        $this->assertNull($legacy->initialCode('policy'));
        $this->assertSame(101, $legacy->persistedStateId('policy', ['status' => 'ACTIVE']));
        try {
            $legacy->stateForAuthorization('policy', ['id' => 55, 'status' => 'ACTIVE'], 102);
            $this->fail('An explicit wrong state ID must throw, not authorize.');
        } catch (DomainException $error) {
            $this->assertSame('WF_AUTHORIZATION_STATE_MISMATCH', $error->getErrorCode());
            $this->assertSame(['resource' => 'policy'], $error->getContext());
        }
    }

    public function testBe007AndBe008ReflectionPathsStillVerifyRepositoryConnection(): void
    {
        $facade = $this->injected();
        $engine = (new ReflectionProperty($facade, 'engine'))->getValue($facade);
        $adapter = (new ReflectionProperty($engine, 'reader'))->getValue($engine);
        foreach (['resources' => ResourceCatalogRepository::class, 'lifecycles' => LifecycleRepository::class,
            'policies' => PolicyRepository::class] as $key => $class) {
            $this->assertInstanceOf($class, (new ReflectionProperty($adapter, $key))->getValue($adapter));
        }
        $src7 = (string) file_get_contents(__DIR__ . '/BeReorg007AuthorizationFactoryTest.php');
        $src8 = (string) file_get_contents(__DIR__ . '/BeReorg008DecisionFactoryTest.php');
        foreach ([$src7, $src8] as $src) {
            $this->assertStringContainsString("'engine'", $src);
            $this->assertStringContainsString("'reader'", $src);
            $this->assertStringContainsString("'resources'", $src);
            $this->assertStringContainsString("'db'", $src);
        }
    }
}

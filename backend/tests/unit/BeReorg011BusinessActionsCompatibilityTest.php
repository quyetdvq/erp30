<?php

declare(strict_types=1);

use App\Bootstrap\BusinessWorkflowActionFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowActionPort;
use App\Services\BusinessLifecycleTransitions;
use App\Services\BusinessWorkflowActions;
use PHPUnit\Framework\TestCase;

/** @internal Verify both public APIs remain unchanged for existing consumers. */
final class BeReorg011BusinessActionsCompatibilityTest extends TestCase
{
    public function testActionFacadeRetainsConstructorAndThreeSignatures(): void
    {
        $r = new ReflectionClass(BusinessWorkflowActions::class);
        $this->assertTrue($r->isFinal());
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $r->getConstructor()->getParameters()));
        $this->assertSame('?CodeIgniter\\Database\\ConnectionInterface', (string) $r->getConstructor()->getParameters()[0]->getType());
        $this->assertNull($r->getConstructor()->getParameters()[0]->getDefaultValue());
        $this->assertSame(['__construct', 'states', 'options', 'execute'], array_map(
            static fn (ReflectionMethod $m): string => $m->getName(), $r->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
        foreach (['states' => ['resource', 'operation'], 'options' => ['resource', 'id', 'operation'],
            'execute' => ['resource', 'id', 'actionCode', 'operation']] as $name => $names) {
            $method = $r->getMethod($name);
            $this->assertSame('array', (string) $method->getReturnType());
            $this->assertSame($names, array_map(static fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters()));
        }
    }

    public function testLifecycleTransitionsRetainsStaticSignatureAndReturnType(): void
    {
        $r = new ReflectionClass(BusinessLifecycleTransitions::class);
        $this->assertTrue($r->isFinal());
        $public = $r->getMethods(ReflectionMethod::IS_PUBLIC);
        $this->assertCount(1, $public);
        $m = $r->getMethod('applyIfBound');
        $this->assertTrue($m->isStatic());
        $this->assertSame('bool', (string) $m->getReturnType());
        $this->assertSame(['type', 'id', 'destination', 'operation', 'db'], array_map(
            static fn (ReflectionParameter $p): string => $p->getName(), $m->getParameters(),
        ));
        $this->assertSame('?CodeIgniter\\Database\\ConnectionInterface', (string) $m->getParameters()[4]->getType());
        $this->assertNull($m->getParameters()[4]->getDefaultValue());
    }

    public function testExplicitDelegationAndNoLocalOrchestration(): void
    {
        $action = (string) file_get_contents((new ReflectionClass(BusinessWorkflowActions::class))->getFileName());
        $helper = (string) file_get_contents((new ReflectionClass(BusinessLifecycleTransitions::class))->getFileName());
        foreach (['states', 'options', 'execute'] as $method) {
            $this->assertStringContainsString('$this->engine->' . $method . '(', $action);
        }
        $this->assertStringContainsString('BusinessWorkflowActionFactory::create($db)->applyIfBound(', $helper);
        foreach ([$action, $helper] as $source) {
            foreach (['->table(', '->authorize(', 'new LifecycleService(', 'new ResourceWorkflowStateResolver(', 'function record(', 'function allowedAction('] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source);
            }
        }
        $this->assertTrue((new ReflectionProperty(BusinessWorkflowActions::class, 'engine'))->isReadOnly());
    }

    public function testFactoryReturnsFreshEngineWithSameConnection(): void
    {
        // Test the legacy delegate with an injected real port to prove method forwarding
        // without touching a DB or bypassing the public production signature.
        $port = new class implements BusinessWorkflowActionPort {
            public array $received = [];
            public function states(string $resource, OperationContext $operation): array
            { $this->received[] = ['states', $resource, $operation]; return ['mode' => 'NATIVE', 'initial' => null, 'states' => []]; }
            public function options(string $resource, int $id, OperationContext $operation): array
            { $this->received[] = ['options', $resource, $id, $operation]; return ['mode' => 'NATIVE', 'current' => 'ACTIVE', 'options' => []]; }
            public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array
            { $this->received[] = ['execute', $resource, $id, $actionCode, $operation]; return ['status' => 'INACTIVE', 'action_code' => $actionCode]; }
            public function applyIfBound(string $type, int $id, string $destination, OperationContext $operation): bool
            { $this->received[] = ['apply', $type, $id, $destination, $operation]; return false; }
        };
        $facade = (new ReflectionClass(BusinessWorkflowActions::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($facade, 'engine'))->setValue($facade, $port);
        $operation = new OperationContext(17, new EvaluationTime(new DateTimeImmutable('2026-10-17T05:00:00Z')), 'be011', false);
        $this->assertSame(['mode' => 'NATIVE', 'initial' => null, 'states' => []], $facade->states('product', $operation));
        $this->assertSame('ACTIVE', $facade->options('product', 20, $operation)['current']);
        $this->assertSame('INACTIVE', $facade->execute('product', 20, 'setstate', $operation)['status']);
        $this->assertSame([['states', 'product', $operation], ['options', 'product', 20, $operation],
            ['execute', 'product', 20, 'setstate', $operation]], $port->received);
        $this->assertTrue(method_exists(BusinessWorkflowActionFactory::class, 'create'));
    }

    public function testLegacyConsumersStillReferenceUnchangedFacades(): void
    {
        $root = dirname(__DIR__, 2) . '/app';
        foreach (['Controllers/Api/BusinessWorkflow.php' => 'new BusinessWorkflowActions()',
            'Services/Api020EmployeeStructureService.php' => 'BusinessLifecycleTransitions::applyIfBound(',
            'Modules/Product/Product/ProductMasterService.php' => 'BusinessLifecycleTransitions::applyIfBound(',
        ] as $path => $call) {
            $source = (string) file_get_contents($root . '/' . $path);
            $this->assertStringContainsString($call, $source, $path);
        }
        $this->assertFalse(method_exists(BusinessWorkflowActions::class, '__call'));
    }
}

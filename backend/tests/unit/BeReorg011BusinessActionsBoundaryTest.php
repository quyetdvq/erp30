<?php

declare(strict_types=1);

use App\Modules\Workflow\BusinessActions\Application\BusinessWorkflowActionService;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowActionPort;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowRecordPort;
use App\Modules\Workflow\BusinessActions\Contracts\BusinessWorkflowActionErrors;
use App\Modules\Workflow\BusinessActions\Infrastructure\Persistence\SqlBusinessWorkflowRecordReader;
use App\Services\LifecycleService;
use App\Services\ResourceWorkflowStateResolver;
use PHPUnit\Framework\TestCase;

/** @internal Frozen Workflow module boundary and error vocabulary. */
final class BeReorg011BusinessActionsBoundaryTest extends TestCase
{
    public function testApplicationPortDefinesFourOperations(): void
    {
        $r = new ReflectionClass(BusinessWorkflowActionPort::class);
        $this->assertTrue($r->isInterface());
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $r->getMethods());
        sort($names);
        $this->assertSame(['applyIfBound', 'execute', 'options', 'states'], $names);
        $this->assertSame('bool', (string) $r->getMethod('applyIfBound')->getReturnType());
        foreach (['states', 'options', 'execute'] as $name) {
            $this->assertSame('array', (string) $r->getMethod($name)->getReturnType());
        }
        $this->assertSame(['type', 'id', 'destination', 'operation'], array_map(
            static fn (ReflectionParameter $p): string => $p->getName(),
            $r->getMethod('applyIfBound')->getParameters(),
        ));
    }

    public function testRecordPortExposesReadOnlyOperations(): void
    {
        $r = new ReflectionClass(BusinessWorkflowRecordPort::class);
        $this->assertTrue($r->isInterface());
        $this->assertSame(['assertBusinessResource', 'record'], array_map(
            static fn (ReflectionMethod $m): string => $m->getName(), $r->getMethods(),
        ));
        $this->assertSame('void', (string) $r->getMethod('assertBusinessResource')->getReturnType());
        $this->assertSame('array', (string) $r->getMethod('record')->getReturnType());
        $this->assertTrue((new ReflectionClass(SqlBusinessWorkflowRecordReader::class))->implementsInterface(BusinessWorkflowRecordPort::class));
    }

    public function testModuleLayersAndContractsAreIsolated(): void
    {
        $application = new ReflectionClass(BusinessWorkflowActionService::class);
        $reader = new ReflectionClass(SqlBusinessWorkflowRecordReader::class);
        $this->assertTrue($application->isFinal());
        $this->assertTrue($reader->isFinal());
        $this->assertTrue($application->implementsInterface(BusinessWorkflowActionPort::class));
        $this->assertSame(['db', 'records', 'states', 'authorization', 'lifecycles'], array_map(
            static fn (ReflectionParameter $p): string => $p->getName(),
            $application->getConstructor()->getParameters(),
        ));
        $this->assertSame('db', $reader->getConstructor()->getParameters()[0]->getName());
        foreach (['table(', 'query(', 'transBegin(', 'FOR UPDATE'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, (string) file_get_contents($application->getFileName()));
        }
    }

    public function testNoRouteOrAuthorizationInfrastructureChanges(): void
    {
        $r = new ReflectionClass(SqlBusinessWorkflowRecordReader::class);
        $code = (string) file_get_contents($r->getFileName());
        foreach (['->insert(', '->update(', '->delete(', 'transBegin(', '->query(', 'FOR UPDATE', '->authorize('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code);
        }
        $this->assertStringContainsString('ContactAuthorizationRegistry::resourceFor($row)', $code);
        $this->assertStringContainsString('PositiveId::normalize(', $code);
        $this->assertSame(12, count((new ReflectionClass(SqlBusinessWorkflowRecordReader::class))->getReflectionConstant('TABLES')->getValue()));
        $this->assertSame(11, count(array_unique((new ReflectionClass(SqlBusinessWorkflowRecordReader::class))->getReflectionConstant('TABLES')->getValue())));
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/app/Config/Routes.php');
        $this->assertStringContainsString("business-workflow/(:segment)/(:num)/actions/(:segment)", $routes);
    }

    public function testErrorConstantsPreserveLegacyValues(): void
    {
        $expected = [
            'ERROR_TARGET_ADAPTER_UNAVAILABLE' => LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE,
            'ERROR_TARGET_NOT_FOUND' => LifecycleService::ERROR_TARGET_NOT_FOUND,
            'ERROR_AUTHORIZATION_DENIED' => LifecycleService::ERROR_AUTHORIZATION_DENIED,
            'ERROR_INVALID_TRANSITION' => LifecycleService::ERROR_INVALID_TRANSITION,
            'ERROR_TRANSITION_REQUIRED' => ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED,
        ];
        $this->assertSame($expected, (new ReflectionClass(BusinessWorkflowActionErrors::class))->getConstants());
        $this->assertTrue((new ReflectionClass(BusinessWorkflowActionErrors::class))->isFinal());
    }
}

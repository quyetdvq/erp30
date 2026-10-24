<?php

declare(strict_types=1);

use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowStateLookupPort;
use App\Modules\Workflow\WriteBoundary\Infrastructure\Adapters\ExistingStateResolutionLookupAdapter;
use App\Services\ResourceWorkflowStateResolver;
use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowWriteBoundaryPort;
use App\Modules\Workflow\WriteBoundary\Application\WorkflowWriteBoundaryService;
use App\Modules\Workflow\WriteBoundary\Application\InitialStateRules;
use App\Modules\Workflow\WriteBoundary\Contracts\WorkflowWriteBoundaryErrors;
use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;
use Tests\Support\Workflow\BeReorg012WriteBoundaryFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg012WriteBoundaryFixture.php';
use PHPUnit\Framework\TestCase;

/** @internal Frozen port and dependency boundary. */
final class BeReorg012WriteBoundaryBoundaryTest extends TestCase
{
    public function testReadPortContainsOnlyThreeStateLookups(): void
    {
        $reflection = new ReflectionClass(WorkflowStateLookupPort::class);
        $this->assertTrue($reflection->isInterface());
        $methods = array_map(static fn (ReflectionMethod $method) => $method->getName(), $reflection->getMethods());
        sort($methods);
        $this->assertSame(['allowedPolicyStateCodes', 'initialCode', 'lifecycleId'], $methods);
        foreach (['allowedPolicyStateCodes' => 'array', 'initialCode' => '?string', 'lifecycleId' => '?int'] as $name => $return) {
            $method = $reflection->getMethod($name);
            $this->assertSame(['type'], array_map(static fn (ReflectionParameter $p) => $p->getName(), $method->getParameters()));
            $this->assertSame($return, (string) $method->getReturnType());
        }
    }

    public function testAdapterWrapsBothBe010PortAndLegacyFacade(): void
    {
        // Construct typed sources without executing the default DB composition path.
        // Real injected/default DB wiring is tested by the guarded factory class.
        $port = (new ReflectionClass(\App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService::class))
            ->newInstanceWithoutConstructor();
        $legacy = (new ReflectionClass(ResourceWorkflowStateResolver::class))->newInstanceWithoutConstructor();
        foreach ([$port, $legacy] as $source) {
            $adapter = new ExistingStateResolutionLookupAdapter($source);
            $this->assertInstanceOf(WorkflowStateLookupPort::class, $adapter);
            $this->assertSame($source, (new ReflectionProperty($adapter, 'states'))->getValue($adapter));
        }
        $constructor = (new ReflectionClass(ExistingStateResolutionLookupAdapter::class))->getConstructor();
        $this->assertSame('App\\Modules\\Workflow\\StateResolution\\Application\\Ports\\ResourceStateResolutionPort|App\\Services\\ResourceWorkflowStateResolver',
            implode('|', array_map(static fn (ReflectionNamedType $t) => $t->getName(), $constructor->getParameters()[0]->getType()->getTypes())));
    }

    public function testApplicationPortDefinesFourCanonicalOperations(): void
    {
        $reflection = new ReflectionClass(WorkflowWriteBoundaryPort::class);
        $this->assertTrue($reflection->isInterface());
        $expected = [
            'create' => [['resource', 'data'], 'array'],
            'assertNoDirectStateMutation' => [['resource', 'current', 'changes'], 'void'],
            'normalizeStatus' => [['type', 'value', 'nativeCodes', 'errorCode'], 'string'],
            'normalizeInitial' => [['resource', 'payload'], 'array'],
        ];
        $this->assertSame(array_keys($expected), array_map(static fn (ReflectionMethod $m) => $m->getName(), $reflection->getMethods()));
        foreach ($expected as $method => [$args, $return]) {
            $m = $reflection->getMethod($method);
            $this->assertSame($args, array_map(static fn (ReflectionParameter $p) => $p->getName(), $m->getParameters()));
            $this->assertSame($return, (string) $m->getReturnType());
        }
        $this->assertTrue((new ReflectionClass(WorkflowWriteBoundaryService::class))->implementsInterface(WorkflowWriteBoundaryPort::class));
    }

    public function testApplicationDoesNotImportLegacyServicesOrSql(): void
    {
        $reflection = new ReflectionClass(WorkflowWriteBoundaryService::class);
        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['states'], array_map(static fn (ReflectionParameter $p) => $p->getName(),
            $reflection->getConstructor()->getParameters()));
        $source = (string) file_get_contents($reflection->getFileName());
        foreach (['App\\Services\\', 'CodeIgniter\\Database', '->query(', '->table(',
            '->transBegin(', '->insert(', '->update(', '->delete(', '->authorize(', 'LifecycleService'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
        $this->assertStringContainsString('WorkflowStateLookupPort', $source);
    }

    public function testErrorsPreserveLegacyCodesAndContexts(): void
    {
        $this->assertSame([
            'ERROR_UNSUPPORTED_INITIAL' => 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE',
            'ERROR_INITIAL_STATE_INVALID' => ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID,
            'ERROR_TRANSITION_REQUIRED' => ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED,
        ], (new ReflectionClass(WorkflowWriteBoundaryErrors::class))->getConstants());
        $fake = new Fixture();
        $fake->lifecycles['province'] = 1;
        try {
            (new WorkflowWriteBoundaryService($fake))->assertNoDirectStateMutation(
                'province', ['status' => 'ACTIVE'], ['status' => 'CLOSED']);
            $this->fail('Expected transition-required error.');
        } catch (DomainException $error) {
            $this->assertSame('WF_TRANSITION_REQUIRED', $error->getErrorCode());
            $this->assertSame(['resource' => 'province'], $error->getContext());
        }
        try {
            InitialStateRules::withInitial('province', [], 'lowercase');
            $this->fail('Expected unsupported-initial error.');
        } catch (DomainException $error) {
            $this->assertSame('WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE', $error->getErrorCode());
            $this->assertSame(['resource' => 'province', 'initial_status' => 'lowercase'], $error->getContext());
        }
    }
}

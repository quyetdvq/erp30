<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal BE008 decision ownership, not an alternative PBAC authority. */
final class BeReorg008DecisionBoundaryTest extends CIUnitTestCase
{
    public function testDecisionPortDeclaresOnlyTwoOperations(): void
    {
        $contract = new ReflectionClass(PbacDecisionPort::class);
        $this->assertTrue($contract->isInterface());
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $contract->getMethods());
        sort($methods);
        $this->assertSame(['authorize', 'withReadOnlyBatch'], $methods);
        $authorize = $contract->getMethod('authorize');
        $this->assertSame(['operation', 'resourceType', 'action', 'contextRequest', 'stateId'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $authorize->getParameters()));
        $this->assertSame([OperationContext::class, 'string', 'string', EvaluationOrganizationRequest::class, 'mixed'],
            array_map(static fn (ReflectionParameter $p): string => (string) $p->getType(), $authorize->getParameters()));
        $this->assertSame(AuthorizationDecision::class, (string) $authorize->getReturnType());
        $this->assertNull($authorize->getParameters()[4]->getDefaultValue());
        $batch = $contract->getMethod('withReadOnlyBatch');
        $this->assertSame(['operation', 'read'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $batch->getParameters()));
        $this->assertSame('mixed', (string) $batch->getReturnType());
        $this->assertSame('callable', (string) $batch->getParameters()[1]->getType());
        $this->assertStringContainsString('@template T', (string) $batch->getDocComment());
    }

    public function testEngineImplementsPortAndOwnsPrivatePipeline(): void
    {
        $engine = new ReflectionClass(PbacDecisionEngine::class);
        $this->assertTrue($engine->isFinal());
        $this->assertTrue($engine->implementsInterface(PbacDecisionPort::class));
        $this->assertSame('App\Modules\Authorization\Decision\Application', $engine->getNamespaceName());
        foreach (['readThrough', 'loadPolicies', 'matching', 'evaluateLane', 'uniqueSorted', 'token', 'decision'] as $name) {
            $this->assertTrue($engine->getMethod($name)->isPrivate(), $name);
        }
        $snapshot = $engine->getProperty('readOnlyContext');
        $this->assertTrue($snapshot->isPrivate());
        $this->assertSame('?App\Authorization\ReadOnlyAuthorizationContext', (string) $snapshot->getType());
    }

    public function testDecisionModuleHasNoLegacyFacadeControllerOrAdministrationImports(): void
    {
        $path = (new ReflectionClass(PbacDecisionEngine::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringNotContainsString('use App\Authorization\AuthorizationService;', $source);
        $this->assertStringNotContainsString('use App\Controllers\\', $source);
        $this->assertStringNotContainsString('use App\Services\\Api030AuthorizationWorkflowService;', $source);
        $this->assertStringNotContainsString('use App\Modules\Authorization\Administration\\', $source);
        $this->assertStringContainsString('use App\Repositories\\PolicyRepository;', $source);
    }

    public function testLegacyFacadeDelegatesInsteadOfEmbeddingPbacRules(): void
    {
        $facade = new ReflectionClass(AuthorizationService::class);
        $this->assertTrue($facade->isFinal());
        $source = file_get_contents((string) $facade->getFileName());
        $this->assertIsString($source);
        $this->assertSame(1, substr_count($source, 'AuthorizationDecisionFactory::create('));
        $this->assertStringContainsString('$this->engine->authorize(', $source);
        $this->assertStringContainsString('$this->engine->withReadOnlyBatch(', $source);
        $this->assertStringNotContainsString('private function matching(', $source);
        $this->assertStringNotContainsString('private function evaluateLane(', $source);
        $this->assertStringNotContainsString('private function loadPolicies(', $source);
        $this->assertStringNotContainsString('ReadOnlyAuthorizationContext', $source);
        $this->assertStringNotContainsString('Api030AuthorizationWorkflowService', $source);
        $this->assertStringNotContainsString('Modules\\Authorization\\Administration', $source);
    }
}

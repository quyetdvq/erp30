<?php

declare(strict_types=1);

use App\Modules\Authorization\Administration\Groups\Application\Ports\GroupRepositoryPort;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;
use PHPUnit\Framework\TestCase;

/** @internal BE-REORG-007 administration boundary; PBAC decisions remain outside. */
final class BeReorg007AuthorizationBoundaryTest extends TestCase
{
    public function testCanonicalPortsMatchSpecAndExistingSignatures(): void
    {
        $expected = [
            GroupRepositoryPort::class => ['find', 'findForUpdate', 'findByCode', 'insert', 'update', 'findPermissions', 'findResourceAction', 'userExists', 'replacePermissions'],
            PolicyAdministrationRepositoryPort::class => ['find', 'assertScopeAvailable', 'participantTargetExists', 'insertWithActions', 'update', 'replaceWithActions', 'deleteWithActions'],
        ];
        foreach ($expected as $interface => $names) {
            $reflection = new ReflectionClass($interface);
            $this->assertTrue($reflection->isInterface());
            $actual = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $reflection->getMethods());
            sort($names);
            sort($actual);
            $this->assertSame($names, $actual, $interface);
        }
        $group = new ReflectionMethod(GroupRepositoryPort::class, 'replacePermissions');
        $this->assertSame(['groupId', 'resourceActionIds', 'operation'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $group->getParameters()));
        $this->assertSame('?App\\Infrastructure\\Mutation\\OperationContext', (string) $group->getParameters()[2]->getType());
        $this->assertNull($group->getParameters()[2]->getDefaultValue());
        $policy = new ReflectionMethod(PolicyAdministrationRepositoryPort::class, 'assertScopeAvailable');
        $this->assertSame(['scope', 'excludePolicyId', 'lock'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $policy->getParameters()));
        $this->assertFalse($policy->getParameters()[2]->getDefaultValue());
    }

    public function testCanonicalModuleDoesNotEmbedAuthorizationDecisionEngine(): void
    {
        foreach ($this->moduleFiles() as $file) {
            $body = (string) file_get_contents($file);
            $this->assertStringNotContainsString('class AuthorizationDecision', $body);
            $this->assertStringNotContainsString('class AuthorizationService', $body);
            $this->assertStringNotContainsString('namespace App\\Controllers', $body);
            $this->assertStringNotContainsString('namespace App\\Authorization;', $body);
        }
    }

    public function testPolicySharedReadBoundaryRemainsExplicitlyOutsideApplicationPorts(): void
    {
        $port = new ReflectionClass(PolicyAdministrationRepositoryPort::class);
        foreach (['findApplicablePolicyRows', 'findPolicyVisibilityBuckets', 'findAuthorizedPolicyPage', 'findPolicyActionsForPolicies', 'setStatus'] as $decisionMethod) {
            $this->assertFalse($port->hasMethod($decisionMethod), $decisionMethod);
        }
        $engine = new ReflectionClass(\App\Authorization\AuthorizationService::class);
        $this->assertSame('App\\Authorization', $engine->getNamespaceName());
        $this->assertTrue($engine->hasMethod('authorize'));
        $this->assertSame(
            realpath(dirname(__DIR__, 2) . '/app/Authorization/AuthorizationService.php'),
            realpath((string) $engine->getFileName()),
        );
    }

    public function testOutOfScopeCallerPathsRemainOutsideModule(): void
    {
        $root = dirname(__DIR__, 2) . '/app';
        foreach (['Authorization/AuthorizationService.php', 'Authorization/ShieldIdentityAdapter.php', 'Services/Api030AuthorizationWorkflowService.php', 'Controllers/Api/AuthorizationWorkflow.php', 'Config/Routes.php'] as $relative) {
            $this->assertFileExists($root . '/' . $relative);
        }
        foreach ($this->moduleFiles() as $file) {
            $this->assertNotContains(basename($file), ['AuthorizationWorkflow.php', 'ShieldIdentityAdapter.php', 'AuthorizationDecision.php']);
        }
    }

    /** @return list<string> */
    private function moduleFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/app/Modules/Authorization/Administration';
        $this->assertDirectoryExists($root);
        $paths = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);
        return $paths;
    }
}

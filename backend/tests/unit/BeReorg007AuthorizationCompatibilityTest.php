<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal BE-REORG-007 explicit facade/callable contracts. */
final class BeReorg007AuthorizationCompatibilityTest extends TestCase
{
    public function testLegacyGroupRepositoryKeepsAllMethodsConstantsAndExplicitDelegation(): void
    {
        $this->assertFacade(
            App\Repositories\AuthorizationGroupRepository::class,
            App\Modules\Authorization\Administration\Groups\Infrastructure\Persistence\SqlAuthorizationGroupRepository::class,
            ['find', 'findForUpdate', 'findByCode', 'findAll', 'insert', 'update', 'findPermissions', 'findResourceAction', 'userExists', 'replaceMembers', 'findMembers', 'replacePermissions'],
            6,
        );
    }

    public function testLegacyPolicyRepositoryKeepsAllDecisionReadsAndConstants(): void
    {
        $this->assertFacade(
            App\Repositories\PolicyRepository::class,
            App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository::class,
            ['find', 'findForUpdate', 'findAll', 'findApplicablePolicyRows', 'findPolicyVisibilityBuckets', 'supportsSqlPolicyList', 'findAuthorizedPolicyPage', 'hasActiveStateScopedPolicies', 'assertScopeAvailable', 'setStatus', 'findPolicyActions', 'findAllPolicyActionsForPolicies', 'findPolicyActionsForPolicies', 'findActions', 'findPolicyAction', 'participantTargetExists', 'insert', 'update', 'insertPolicyAction', 'insertAction', 'updatePolicyAction', 'updateAction', 'insertWithActions', 'replaceWithActions', 'deleteWithActions'],
            10,
        );
        $reflection = new ReflectionMethod(App\Repositories\PolicyRepository::class, 'setStatus');
        $this->assertSame(['policyId', 'status', 'operation', 'expectedStatus'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $reflection->getParameters()));
        $this->assertNull($reflection->getParameters()[3]->getDefaultValue());
    }

    public function testParticipantValidatorKeepsTypeVocabularyAndNamedConstructor(): void
    {
        $legacy = new ReflectionClass(App\Authorization\PolicyParticipantValidator::class);
        $canonical = new ReflectionClass(App\Modules\Authorization\Administration\Policies\Validation\PolicyParticipantValidator::class);
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['repository'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()->getParameters()));
        $this->assertSame('App\\Repositories\\PolicyRepository', (string) $legacy->getConstructor()->getParameters()[0]->getType());
        foreach (['validate', 'validatePolicy'] as $method) {
            $this->assertTrue($legacy->hasMethod($method));
            $this->assertTrue($canonical->hasMethod($method));
        }
        $this->assertSame('POLICY_INVALID_PARTICIPANT', $legacy->getConstant('ERROR_INVALID_PARTICIPANT'));
        $this->assertSame($legacy->getConstant('ERROR_INVALID_PARTICIPANT'), $canonical->getConstant('ERROR_INVALID_PARTICIPANT'));
    }

    public function testPolicyValidatorKeepsActionEffectsAndDependencies(): void
    {
        $legacy = new ReflectionClass(App\Authorization\PolicyValidator::class);
        $canonical = new ReflectionClass(App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator::class);
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['repository', 'participants', 'catalog', 'workflowStates'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()->getParameters()));
        foreach (['validatePolicy', 'validateAction', 'validateActions'] as $method) {
            $this->assertTrue($legacy->hasMethod($method));
            $this->assertTrue($canonical->hasMethod($method));
            $this->assertSame((string) $legacy->getMethod($method)->getReturnType(), (string) $canonical->getMethod($method)->getReturnType());
        }
        $this->assertSame('POLICY_RESOURCE_ACTION_MISMATCH', $legacy->getConstant('ERROR_RESOURCE_ACTION_MISMATCH'));
        $this->assertSame('POLICY_INVALID_EFFECT', $legacy->getConstant('ERROR_INVALID_EFFECT'));
        $this->assertSame(['ALLOW', 'DENY', 'ABSOLUTE_DENY'], $canonical->getReflectionConstant('EFFECTS')->getValue());
    }

    public function testLegacyGroupServiceRetainsAllMutationAndAuthorizationSignatures(): void
    {
        $legacy = new ReflectionClass(App\Services\AuthorizationGroupService::class);
        $canonical = new ReflectionClass(App\Modules\Authorization\Administration\Groups\Application\AuthorizationGroupService::class);
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['repository', 'authorization', 'identities', 'db', 'mutationContext', 'clock'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()->getParameters()));
        foreach ($legacy->getConstructor()->getParameters() as $p) {
            $this->assertTrue($p->isDefaultValueAvailable());
            $this->assertNull($p->getDefaultValue());
        }
        $expected = ['create', 'update', 'inactivate', 'assignMember', 'assertAssignableForUserCreation', 'replacePermissions', 'assignPermission', 'assignDirectPermission'];
        foreach ($expected as $method) {
            $this->assertTrue($legacy->hasMethod($method));
            $this->assertTrue($canonical->hasMethod($method));
            $this->assertSame((string) $legacy->getMethod($method)->getReturnType(), (string) $canonical->getMethod($method)->getReturnType());
        }
        $this->assertSame('AUTH_GROUP_AUTHORIZATION_DENIED', $legacy->getConstant('ERROR_DENIED'));
        $this->assertSame('AUTH_GROUP_INACTIVE', $legacy->getConstant('ERROR_INACTIVE'));
        $this->assertSame('lock', $legacy->getMethod('assertAssignableForUserCreation')->getParameters()[2]->getName());
        $this->assertFalse($legacy->getMethod('assertAssignableForUserCreation')->getParameters()[2]->getDefaultValue());
    }

    public function testLegacyPolicyServicePreservesValidatorInjectionAndMutationOrder(): void
    {
        $legacy = new ReflectionClass(App\Services\PolicyAdministrationService::class);
        $canonical = new ReflectionClass(App\Modules\Authorization\Administration\Policies\Application\PolicyAdministrationService::class);
        $this->assertFalse($legacy->getParentClass());
        $this->assertFalse($legacy->hasMethod('__call'));
        $this->assertTrue($legacy->hasProperty('delegate'));
        $this->assertSame(['repository', 'validator', 'authorization', 'db', 'mutationContext', 'clock'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()->getParameters()));
        foreach ($legacy->getConstructor()->getParameters() as $parameter) {
            $this->assertTrue($parameter->isDefaultValueAvailable());
            $this->assertNull($parameter->getDefaultValue());
        }
        $this->assertSame('?App\\Authorization\\PolicyValidator', (string) $legacy->getConstructor()->getParameters()[1]->getType());
        foreach (['create', 'update', 'delete'] as $method) {
            $this->assertTrue($legacy->hasMethod($method));
            $this->assertTrue($canonical->hasMethod($method));
            $this->assertSame((string) $legacy->getMethod($method)->getReturnType(), (string) $canonical->getMethod($method)->getReturnType());
        }
        $this->assertSame('POLICY_ACTIONS_REQUIRED', $legacy->getConstant('ERROR_ACTIONS_REQUIRED'));
        $this->assertSame('POLICY_AUTHORIZATION_DENIED', $legacy->getConstant('ERROR_DENIED'));
        $this->assertSame('POLICY_NOT_FOUND', $legacy->getConstant('ERROR_NOT_FOUND'));
    }

    /** @param list<string> $methodNames */
    private function assertFacade(string $legacy, string $canonical, array $methodNames, int $constantCount): void
    {
        $old = new ReflectionClass($legacy);
        $new = new ReflectionClass($canonical);
        $this->assertFalse($old->getParentClass());
        $this->assertFalse($old->hasMethod('__call'));
        $this->assertTrue($old->hasProperty('delegate'));
        $names = array_values(array_filter(array_map(static fn (ReflectionMethod $m): string => $m->getName(), $old->getMethods(ReflectionMethod::IS_PUBLIC)), static fn (string $m): bool => $m !== '__construct'));
        sort($names);
        sort($methodNames);
        $this->assertSame($methodNames, $names);
        foreach ($names as $name) {
            $this->assertTrue($new->hasMethod($name), $name);
            $oldParameters = $old->getMethod($name)->getParameters();
            $newParameters = $new->getMethod($name)->getParameters();
            $this->assertCount(count($oldParameters), $newParameters, $name);
            foreach ($oldParameters as $i => $parameter) {
                $this->assertSame($parameter->getName(), $newParameters[$i]->getName(), $name);
                $this->assertSame((string) $parameter->getType(), (string) $newParameters[$i]->getType(), $name);
            }
        }
        $publicConstants = array_filter($old->getReflectionConstants(), static fn (ReflectionClassConstant $c): bool => $c->isPublic());
        $this->assertCount($constantCount, $publicConstants);
        foreach ($publicConstants as $constant) {
            $this->assertSame($constant->getValue(), $new->getConstant($constant->getName()), $constant->getName());
        }
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $old->getConstructor()?->getParameters() ?? []));
    }
}

<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService;
use App\Services\ResourceNativeStateCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\Workflow\BeReorg010StateReadFixture as ReadFixture;

/** @internal BE010 fail-closed characterization via read port, no DB. */
final class BeReorg010StateResolutionDomainTest extends TestCase
{
    /** @return DomainException */
    private function fails(string $code, array $context, callable $operation): DomainException
    {
        try {
            $operation();
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
            return $error;
        }
        $this->fail('Expected DomainException: ' . $code);
    }

    public function testTenIndependentResourcesAndTwoAllStatesOnlyBehaveExactly(): void
    {
        $this->assertSame(['authorization_group', 'authorization_log', 'display_setting', 'employee_assignment',
            'import_job', 'lifecycle', 'policy', 'user', 'workflow_state', 'resource'], ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
        $reader = ReadFixture::standard();
        $engine = new ResourceStateResolutionService($reader);
        foreach (['authorization_log', 'display_setting'] as $type) {
            $this->assertSame([], $engine->allowedPolicyStateCodes($type));
            $this->assertNull($engine->persistedStateId($type, ['something' => 'not status']));
            $this->fails('WF_LIFECYCLE_SELF_REFERENCE', ['resource' => $type],
                static fn () => $engine->assertLifecycleStateCompatibility($type, 300));
        }
        $reader->resourcesByType['policy']['lifecycle_id'] = 300;
        $this->fails('WF_LIFECYCLE_SELF_REFERENCE', ['resource' => 'policy'],
            static fn () => (new ResourceStateResolutionService($reader))->lifecycleId('policy'));
    }

    public function testLifecycleBoundResourceAcceptsActiveMemberCodesBeyondNativeVocabulary(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $engine = new ResourceStateResolutionService($reader);
        $this->assertSame(['DRAFT', 'APPROVED'], $engine->allowedPolicyStateCodes('province'));
        $this->assertSame(105, $engine->stateIdForCode('province', 'approved'));
        $this->assertSame('DRAFT', $engine->codeForStateId('province', 104));
        $this->assertSame('DRAFT', $engine->initialCode('province'));
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'],
            (new ResourceStateResolutionService(ReadFixture::standard()))->allowedPolicyStateCodes('province'));
        $this->assertFalse(ResourceNativeStateCatalog::isIndependent('province'));
    }

    public function testShieldStateUsesPersistedStatusAndValidatedActiveFlag(): void
    {
        $engine = new ResourceStateResolutionService(ReadFixture::standard());
        foreach ([true, 1, '1'] as $active) {
            $this->assertSame(101, $engine->persistedStateId('user', ['status' => 'active', 'active' => $active]));
        }
        foreach ([false, 0, '0'] as $inactive) {
            $this->assertSame(102, $engine->persistedStateId('user', ['status' => 'active', 'active' => $inactive]));
        }
        $this->assertSame(102, $engine->persistedStateId('user', ['status' => 'LOCKED', 'active' => true]));
        foreach ([2, 'true', null] as $invalid) {
            $this->fails('WF_RESOURCE_STATE_NOT_MAPPED', ['resource' => 'user'],
                static fn () => $engine->persistedStateId('user', ['status' => 'ACTIVE', 'active' => $invalid]));
        }
    }

    public function testDisabledWorkflowStateCanReactivateButCannotAuthorizeOtherNativeResource(): void
    {
        $reader = ReadFixture::standard();
        $reader->statesById[102]['status'] = 'INACTIVE';
        $engine = new ResourceStateResolutionService($reader);
        $this->assertSame(102, $engine->stateIdForCode('workflow_state', 'INACTIVE'));
        foreach (['policy', 'resource'] as $type) {
            $this->fails('WF_RESOURCE_STATE_NOT_MAPPED', ['resource' => $type, 'status' => 'INACTIVE'],
                static fn () => $engine->stateIdForCode($type, 'INACTIVE'));
        }
        $this->assertContains('anyCode:INACTIVE', $reader->calls);
        $this->assertContains('activeCode:INACTIVE', $reader->calls);
    }

    public function testReassignmentRejectsInactivePolicyOutsideNextLifecycle(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $id = (int) $reader->resourcesByType['province']['id'];
        $reader->policiesByResourceId[$id] = [
            ['id' => 901, 'state_id' => null, 'status' => 'ACTIVE'],
            ['id' => 902, 'state_id' => 104, 'status' => 'INACTIVE'],
        ];
        $engine = new ResourceStateResolutionService($reader);
        $this->fails('RESOURCE_LIFECYCLE_POLICY_CONFLICT', ['resource' => 'province', 'policy_id' => 902],
            static fn () => $engine->assertLifecycleReassignmentSafe('province', 301));
        $reader->policiesByResourceId[$id] = [['id' => 901, 'state_id' => null, 'status' => 'ACTIVE']];
        (new ResourceStateResolutionService($reader))->assertLifecycleReassignmentSafe('province', 301);
        $this->assertContains('policies:' . $id, $reader->calls);
    }

    public function testInitialStateAndMemberValidationFailClosedWithStableCodes(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $reader->membersByLifecycle[300][0]['is_initial'] = 0;
        $engine = new ResourceStateResolutionService($reader);
        $this->fails('WF_INITIAL_STATE_INVALID', ['resource' => 'province'],
            static fn () => $engine->initialCode('province'));
        $reader->membersByLifecycle[300][0]['is_initial'] = 1;
        $reader->membersByLifecycle[300][1]['is_initial'] = 1;
        $this->fails('WF_INITIAL_STATE_INVALID', ['resource' => 'province', 'lifecycle_id' => 300],
            static fn () => (new ResourceStateResolutionService($reader))->assertLifecycleStateCompatibility('province', 300));
        $reader->membersByLifecycle[300][1]['is_initial'] = 0;
        $reader->lifecyclesById[300]['status'] = 'INACTIVE';
        $this->fails('WF_RESOURCE_LIFECYCLE_INVALID', ['lifecycle_id' => 300],
            static fn () => (new ResourceStateResolutionService($reader))->lifecycleId('province'));
        $reader->lifecyclesById[300]['status'] = 'ACTIVE';
        $reader->statesById[104]['status'] = 'INACTIVE';
        $this->fails('WF_RESOURCE_LIFECYCLE_INVALID', ['state_id' => 104],
            static fn () => (new ResourceStateResolutionService($reader))->lifecycleId('province'));
    }

    public function testStateIdAndCodeMappingRejectInvalidOrNonmemberStates(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $engine = new ResourceStateResolutionService($reader);
        $this->assertSame(105, $engine->stateIdForCode('province', ' approved '));
        $this->fails('WF_RESOURCE_STATE_NOT_MAPPED', ['resource' => 'province', 'status' => 'CLOSED'],
            static fn () => $engine->stateIdForCode('province', 'CLOSED'));
        $this->fails('WF_RESOURCE_STATE_NOT_MAPPED', ['resource' => 'province', 'state_id' => 101],
            static fn () => $engine->codeForStateId('province', 101));
        $this->fails('WF_RESOURCE_STATE_NOT_MAPPED', ['resource' => 'province'],
            static fn () => $engine->stateIdForCode('province', 123));
        $this->fails('POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE', ['resource_id' => (int) $reader->resourcesByType['province']['id'], 'state_id' => 101],
            static fn () => $engine->assertPolicyState((int) $reader->resourcesByType['province']['id'], 101));
    }

    public function testAuthorizationUsesPersistedStateAndRejectsClientHints(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $engine = new ResourceStateResolutionService($reader);
        $this->assertSame(104, $engine->stateForAuthorization('province', ['id' => 66, 'status' => 'DRAFT'], 104));
        $this->assertNull($engine->stateForAuthorization('province', ['organization_id' => 10], null));
        $this->fails('WF_AUTHORIZATION_STATE_MISMATCH', ['resource' => 'province'],
            static fn () => $engine->stateForAuthorization('province', ['organization_id' => 10], 105));
        $this->fails('WF_AUTHORIZATION_STATE_MISMATCH', ['resource' => 'province'],
            static fn () => $engine->stateForAuthorization('province', ['id' => 66, 'status' => 'DRAFT'], 105));
        $this->fails('WF_INITIAL_STATE_REQUIRED', ['resource' => 'province', 'initial' => 'DRAFT'],
            static fn () => $engine->stateForAuthorization('province', null, null, action: 'create', input: ['status' => 'APPROVED']));
        $this->fails('WF_TRANSITION_REQUIRED', ['resource' => 'province'],
            static fn () => $engine->stateForAuthorization('province', ['id' => 66, 'status' => 'DRAFT'], 104, action: 'edit', input: ['status' => 'APPROVED']));
        $this->fails('WF_TRANSITION_REQUIRED', ['resource' => 'province'],
            static fn () => $engine->stateForAuthorization('province', ['id' => 66, 'status' => 'DRAFT'], null, action: 'setstate', input: ['status' => 'APPROVED']));
        $this->assertSame(104, $engine->stateForAuthorization('province', ['id' => 66, 'status' => 'DRAFT'], 104, action: 'setstate', input: ['status' => 'APPROVED']));
    }
}

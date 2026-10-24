<?php

declare(strict_types=1);

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\PolicyValidator;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\FoundationLocationRepository;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\Api010FoundationOrganizationContactService;
use App\Services\Api050ProductCatalogService;
use App\Services\FoundationLocationLifecycleAdapter;
use App\Services\FoundationLocationService;
use App\Services\LifecycleService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal Stage C: real DB fixtures; never use a production database. */
final class P1Wf006StateAwarePbacAndLocationTransitionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testProvinceInactivePolicyDeniesOnlyInactiveAndListTotalExcludesIt(): void
    {
        $f = $this->fixture();
        $active = $this->province('WF006-P-A', 'ACTIVE');
        $inactive = $this->province('WF006-P-I', 'INACTIVE');
        $this->policy('province', $f['group'], $f['user'], $f['states']['INACTIVE'], 'view', 'ABSOLUTE_DENY');

        $service = $this->api();
        $rows = $this->authorizedRows($service, 'province', $f['user']);
        $this->assertSame([$active], array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $result = (new CommonListQueryAdapter())->paginate(
            ['page' => '1', 'page_size' => '1', 'sort' => 'name'],
            new CommonListQueryAllowlist(['name'], ['status', 'code', 'name'], ['name']),
            new AuthorizedArrayListSource($rows),
        );
        $this->assertSame('1', $result->total);
        $this->assertCount(1, iterator_to_array($result->data));
        $this->assertVisible($service, 'province', $active, $f['user']);
        try {
            $this->assertVisible($service, 'province', $inactive, $f['user']);
            $this->fail('INACTIVE should be invisible when state-specific policy is ABSOLUTE_DENY:view.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function testWardAllStatesDeniesBothActiveAndInactive(): void
    {
        $f = $this->fixture();
        $provinceId = $this->province('WF006-P', 'ACTIVE');
        $this->ward($provinceId, 'WF006-W-A', 'ACTIVE');
        $this->ward($provinceId, 'WF006-W-I', 'INACTIVE');
        $this->policy('ward', $f['group'], $f['user'], null, 'view', 'ABSOLUTE_DENY');
        $this->assertSame([], $this->authorizedRows($this->api(), 'ward', $f['user']));
    }

    public function testMissingEmployeeIsDeniedAndMasterStatePolicyUsesGroupScopeAfterAssignment(): void
    {
        $f = $this->fixture(withAssignment: false);
        $id = $this->province('WF006-UNASSIGNED', 'ACTIVE');
        $decision = $this->authorize($f['user'], 'province', 'view', $f['states']['ACTIVE']);
        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_EMPLOYEE_INELIGIBLE, $decision->reasonCode);

        EligibleActorFixture::assignAtOrganization($this->db, $f['user'], $f['company']);
        $decision = $this->authorize($f['user'], 'province', 'view', $f['states']['ACTIVE']);
        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame($f['group'], $decision->evaluationOrganizationId);
        $this->assertVisible($this->api(), 'province', $id, $f['user']);
    }

    public function testOrganizationEligibilityStillBlocksSiblingCompany(): void
    {
        $f = $this->fixture();
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'parent_id' => $f['group'], 'code' => 'WF006-OTHER-' . uniqid(),
            'name' => 'Company B', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $otherCompany = (int) $this->db->insertID();
        $service = new AuthorizationService(db: $this->db);
        $own = $this->db->table('organizations')->where('id', $f['company'])->get()->getRowArray();
        $other = $this->db->table('organizations')->where('id', $otherCompany)->get()->getRowArray();
        $this->assertNotNull($own);
        $this->assertNotNull($other);
        $allowed = $service->authorize(
            $this->operation($f['user']), 'organization', 'view',
            new EvaluationOrganizationRequest(persistedTarget: $own),
        );
        $denied = $service->authorize(
            $this->operation($f['user']), 'organization', 'view',
            new EvaluationOrganizationRequest(persistedTarget: $other),
        );
        $this->assertSame('ALLOW', $allowed->decision);
        $this->assertSame('DENY', $denied->decision);
        $this->assertSame(AuthorizationService::REASON_OUT_OF_ASSIGNMENT_SCOPE, $denied->reasonCode);
    }

    public function testPolicyValidatorRejectsStateOutsideResourceLifecycle(): void
    {
        $f = $this->fixture();
        $stateOutside = $this->workflowState('WF006-OUTSIDE-' . uniqid());
        $validator = new PolicyValidator(
            new PolicyRepository($this->db),
            catalog: new ResourceCatalogRepository($this->db),
            workflowStates: new ResourceWorkflowStateResolver($this->db),
        );
        $resourceId = $this->resourceId('province');
        $policy = [
            'resource_id' => $resourceId,
            'organization_id' => $f['group'],
            'state_id' => $stateOutside,
            'participant_type' => 'USER',
            'participant_ref_id' => $f['user'],
        ];
        try {
            $validator->validatePolicy($policy);
            $this->fail('A policy must not reference a workflow state outside its resource lifecycle.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_POLICY_STATE_INVALID, $exception->getErrorCode());
        }
        $policy['state_id'] = $f['states']['INACTIVE'];
        $this->assertSame($f['states']['INACTIVE'], $validator->validatePolicy($policy)['state_id']);
    }

    public function testUnknownPersistedStatusFailsClosedInsteadOfMatchingAllStates(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-CORRUPT', 'ACTIVE');
        $this->assertTrue($this->db->table('provinces')->where('id', $id)->update(['status' => 'UNKNOWN']));
        try {
            $this->authorizedRows($this->api(), 'province', $f['user']);
            $this->fail('Unmapped persisted state must not silently fall back to All States.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_STATE_NOT_MAPPED, $exception->getErrorCode());
        }
    }

    public function testTransitionUsesFromStateAndWritesConfiguredDestinationAtomically(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-TRANSITION', 'ACTIVE');
        // Denying only INACTIVE:setstate must not block an ACTIVE -> INACTIVE
        // transition, since authorization is evaluated on the From State.
        $this->policy('province', $f['group'], $f['user'], $f['states']['INACTIVE'], 'setstate', 'ABSOLUTE_DENY');
        $result = $this->engine()->execute('province', $id, 'setstate', $this->operation($f['user']), $f['states']['INACTIVE']);
        $this->assertSame('ALLOW', $result['decision']->decision);
        $this->assertSame($f['states']['ACTIVE'], $result['decision']->stateId);
        $this->assertSame('INACTIVE', $this->location('province', $id)['status']);
        $log = $this->db->table('authorization_logs')->where('resource_id', $this->resourceId('province'))->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame((string) $f['states']['ACTIVE'], (string) $log['state_id']);
    }

    public function testFromStateAbsoluteDenyPreventsTransitionAndLeavesRowUntouched(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-TR-DENY', 'ACTIVE');
        $this->policy('province', $f['group'], $f['user'], $f['states']['ACTIVE'], 'setstate', 'ABSOLUTE_DENY');
        try {
            $this->engine()->execute('province', $id, 'setstate', $this->operation($f['user']), $f['states']['INACTIVE']);
            $this->fail('A From State ABSOLUTE_DENY must block mutation.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertSame('ACTIVE', $this->location('province', $id)['status']);
    }

    public function testRequestedDestinationCannotOverrideConfiguredTransition(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-TR-MISMATCH', 'ACTIVE');
        try {
            $this->engine()->execute('province', $id, 'setstate', $this->operation($f['user']), $f['states']['CLOSED']);
            $this->fail('An arbitrary To State must not override the transition catalog.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_TRANSITION, $exception->getErrorCode());
        }
        $this->assertSame('ACTIVE', $this->location('province', $id)['status']);
    }

    public function testOrdinaryEditCannotBypassConfiguredLifecycleTransition(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-EDIT', 'ACTIVE');
        try {
            (new FoundationLocationService(db: $this->db))->updateProvince($id, ['status' => 'INACTIVE']);
            $this->fail('Status mutation must not bypass lifecycle through an edit service.');
        } catch (DomainException $exception) {
            $this->assertSame(Api010FoundationOrganizationContactService::ERROR_TRANSITION_REQUIRED, $exception->getErrorCode());
        }
        $this->assertSame('ACTIVE', $this->location('province', $id)['status']);
        $this->assertTrue((new FoundationLocationService(db: $this->db))->updateProvince($id, ['name' => 'Renamed']));
    }

    public function testConfiguredInitialStateMustMatchCreationStatus(): void
    {
        $f = $this->fixture();
        $service = new FoundationLocationService(db: $this->db);
        try {
            $service->createProvince(['code' => 'WF006-INITIAL-BAD', 'name' => 'Mismatch', 'status' => 'INACTIVE']);
            $this->fail('The initial lifecycle state must be used for new records.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('provinces')->where('code', 'WF006-INITIAL-BAD')->countAllResults());
        $this->assertGreaterThan(0, $service->createProvince(['code' => 'WF006-INITIAL-GOOD', 'name' => 'Valid', 'status' => 'ACTIVE']));
    }

    public function testMissingOrInactiveTransitionIsRejectedWithoutChangingTheRow(): void
    {
        $f = $this->fixture();
        $id = $this->province('WF006-NO-TRANSITION', 'INACTIVE');
        try {
            $this->engine()->execute('province', $id, 'setstate', $this->operation($f['user']), $f['states']['ACTIVE']);
            $this->fail('No configured transition out of INACTIVE should be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_TRANSITION, $exception->getErrorCode());
        }
        $this->assertSame('INACTIVE', $this->location('province', $id)['status']);
    }

    public function testResourceWithoutLifecycleKeepsLegacyStateMutation(): void
    {
        $this->fixture();
        $this->db->table('resources')->where('type', 'province')->update(['lifecycle_id' => null]);
        $id = $this->province('WF006-LEGACY', 'ACTIVE');
        $this->assertTrue((new FoundationLocationService(db: $this->db))->setProvinceState($id, 'INACTIVE'));
        $this->assertSame('INACTIVE', $this->location('province', $id)['status']);
    }

    public function testWardTransitionUsesTheSameLifecycleRules(): void
    {
        $f = $this->fixture();
        $provinceId = $this->province('WF006-PARENT', 'ACTIVE');
        $id = $this->ward($provinceId, 'WF006-WARD', 'ACTIVE');
        $result = $this->engine()->execute('ward', $id, 'setstate', $this->operation($f['user']), $f['states']['INACTIVE']);
        $this->assertSame('ALLOW', $result['decision']->decision);
        $this->assertSame('INACTIVE', $this->location('ward', $id)['status']);
    }

    public function testCoreAuthorizationUsesPersistedOrganizationStateWithoutCallerHint(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('organization');
        $this->policy('organization', $f['group'], $f['user'], $f['states']['INACTIVE'], 'view', 'ABSOLUTE_DENY');
        $active = $this->db->table('organizations')->where('id', $f['company'])->get()->getRowArray();
        $this->assertNotNull($active);
        $this->db->table('organizations')->insert([
            'type' => 'BRANCH', 'parent_id' => $f['company'], 'code' => 'WF006-B-' . uniqid(),
            'name' => 'Inactive Branch', 'status' => 'INACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $inactive = $this->db->table('organizations')->where('id', (int) $this->db->insertID())->get()->getRowArray();
        $this->assertNotNull($inactive);

        $auth = new AuthorizationService(db: $this->db);
        $allow = $auth->authorize($this->operation($f['user']), 'organization', 'view', new EvaluationOrganizationRequest(persistedTarget: $active));
        $deny = $auth->authorize($this->operation($f['user']), 'organization', 'view', new EvaluationOrganizationRequest(persistedTarget: $inactive));
        $this->assertSame('ALLOW', $allow->decision);
        $this->assertSame($f['states']['ACTIVE'], $allow->stateId);
        $this->assertSame('DENY', $deny->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $deny->reasonCode);
        $this->assertSame($f['states']['INACTIVE'], $deny->stateId);
    }

    public function testForgedExplicitStateCannotOverridePersistedState(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('organization');
        $target = $this->db->table('organizations')->where('id', $f['company'])->get()->getRowArray();
        $this->assertNotNull($target);
        try {
            (new AuthorizationService(db: $this->db))->authorize(
                $this->operation($f['user']), 'organization', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $target), $f['states']['INACTIVE'],
            );
            $this->fail('A supplied state may not override persisted ACTIVE.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_STATE_CONTEXT_MISMATCH, $exception->getErrorCode());
        }
    }

    public function testStateOutsideAssignedLifecycleFailsClosedForOtherResources(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('organization');
        $this->db->table('organizations')->where('id', $f['company'])->update(['status' => 'UNMAPPED']);
        $target = $this->db->table('organizations')->where('id', $f['company'])->get()->getRowArray();
        $this->assertNotNull($target);
        try {
            (new AuthorizationService(db: $this->db))->authorize(
                $this->operation($f['user']), 'organization', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $target),
            );
            $this->fail('An invalid persisted State must fail closed for any resource.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_STATE_NOT_MAPPED, $exception->getErrorCode());
        }
    }

    public function testInitialAndEditStateContractIsNotSpecificToProvince(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('organization');
        $auth = new AuthorizationService(db: $this->db);
        try {
            $auth->authorize(
                $this->operation($f['user']), 'organization', 'create',
                new EvaluationOrganizationRequest(input: ['status' => 'INACTIVE', 'parent_id' => $f['company']]),
            );
            $this->fail('Creation must use the configured initial State.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_REQUIRED, $exception->getErrorCode());
        }
        $target = $this->db->table('organizations')->where('id', $f['company'])->get()->getRowArray();
        $this->assertNotNull($target);
        try {
            $auth->authorize(
                $this->operation($f['user']), 'organization', 'edit',
                new EvaluationOrganizationRequest(persistedTarget: $target, input: ['status' => 'INACTIVE']),
            );
            $this->fail('An ordinary edit must not smuggle a State change.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED, $exception->getErrorCode());
        }
        try {
            $auth->authorize(
                $this->operation($f['user']), 'organization', 'setstate',
                new EvaluationOrganizationRequest(persistedTarget: $target, input: ['status' => 'INACTIVE']),
            );
            $this->fail('A legacy setState call cannot bypass the canonical LifecycleService.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED, $exception->getErrorCode());
        }
    }

    public function testProductGroupListFiltersStatePoliciesBeforePaginationAndTotal(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('product_group');
        $this->db->table('group_permissions')->insert([
            'group_id' => $f['auth_group'],
            'resource_action_id' => $this->actionId('product_group', 'view'),
            'created_at' => '2026-10-06 00:00:00',
        ]);
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->db->table('product_groups')->insert([
                'code' => 'WF006-PG-' . $status . '-' . uniqid(),
                'name' => $status . ' group', 'status' => $status,
                'created_at' => '2026-10-06 00:00:00',
            ]);
        }
        $this->policy('product_group', $f['group'], $f['user'], $f['states']['INACTIVE'], 'view', 'ABSOLUTE_DENY');
        $api = new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $f['user'], 'wf006-product-group-list', false),
        );
        $page = $api->listGroups(['page' => '1', 'page_size' => '1', 'sort' => 'code']);
        $this->assertSame(1, (int) $page['total']);
        $this->assertCount(1, $page['items']);
        $this->assertSame('ACTIVE', $page['items'][0]['status']);
    }

    public function testProductSqlPaginationFallsBackToAuthorizationBeforeCounting(): void
    {
        $f = $this->fixture();
        $this->linkLifecycle('product');
        $this->db->table('group_permissions')->insert([
            'group_id' => $f['auth_group'],
            'resource_action_id' => $this->actionId('product', 'view'),
            'created_at' => '2026-10-06 00:00:00',
        ]);
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->assertTrue($this->db->table('products')->insert([
                'code' => 'WF006-PR-' . $status . '-' . uniqid(),
                'product_type' => 'STANDARD', 'product_name' => $status . ' product',
                'status' => $status, 'created_at' => '2026-10-06 00:00:00',
            ]));
        }
        $this->policy('product', $f['group'], $f['user'], $f['states']['INACTIVE'], 'view', 'ABSOLUTE_DENY');
        $api = new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $f['user'], 'wf006-product-list', false),
        );
        $result = $api->listProducts(['page' => '1', 'page_size' => '1', 'sort' => 'id']);
        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('ACTIVE', $result['items'][0]['status']);
    }

    public function testLifecycleCatalogManagesItsOwnStateWithoutSelfReference(): void
    {
        $f = $this->fixture();
        $states = new ResourceWorkflowStateResolver($this->db);
        $this->assertNull($states->lifecycleId('lifecycle'));
        $this->assertSame($f['states']['ACTIVE'], $states->persistedStateId('lifecycle', ['status' => 'ACTIVE']));
        $states->assertPolicyState($this->resourceId('lifecycle'), $f['states']['INACTIVE']);
        try {
            $states->assertPolicyState($this->resourceId('lifecycle'), $f['states']['CLOSED']);
            $this->fail('Lifecycle policies may only address the independent administrative statuses.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_POLICY_STATE_INVALID, $exception->getErrorCode());
        }
    }

    private function linkLifecycle(string $type): void
    {
        $province = $this->db->table('resources')->where('type', 'province')->get()->getRowArray();
        $this->assertNotNull($province);
        $this->assertTrue($this->db->table('resources')->where('type', $type)->update(['lifecycle_id' => $province['lifecycle_id']]));
    }

    /** @return array{user:int,group:int,company:int,auth_group:int,states:array<string,int>} */
    private function fixture(bool $withAssignment = true): array
    {
        (new ResourceActionSeeder(new Config\Database(), $this->db))->run();
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'WF006-G-' . uniqid(),
            'name' => 'Workflow Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $group = (int) $this->db->insertID();
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'parent_id' => $group, 'code' => 'WF006-C-' . uniqid(),
            'name' => 'Company A', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $company = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'username' => 'wf006-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $user = (int) $this->db->insertID();
        $groupCode = 'WF006-AUTH-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'Workflow Authorizers', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $authorizationGroup = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $user, 'group' => strtolower($groupCode), 'created_at' => '2026-10-06 00:00:00',
        ]);
        foreach (['province', 'ward', 'organization'] as $resource) {
            foreach ($resource === 'organization' ? ['view'] : ['view', 'setstate'] as $action) {
                $this->db->table('group_permissions')->insert([
                    'group_id' => $authorizationGroup,
                    'resource_action_id' => $this->actionId($resource, $action),
                    'created_at' => '2026-10-06 00:00:00',
                ]);
            }
        }
        if ($withAssignment) {
            EligibleActorFixture::assignAtOrganization($this->db, $user, $company);
        }

        $lifecycle = (new LifecycleRepository($this->db))->insertLifecycle([
            'code' => 'WF006-LC-' . uniqid(), 'name' => 'Province/Ward', 'status' => 'ACTIVE',
        ]);
        $states = [];
        $repo = new LifecycleRepository($this->db);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $i => $code) {
            $states[$code] = $this->workflowState($code);
            $repo->insertLifecycleState([
                'lifecycle_id' => $lifecycle,
                'state_id' => $states[$code],
                'sequence' => $i + 1,
                'is_initial' => $i === 0 ? 1 : 0,
                'is_final' => $code === 'CLOSED' ? 1 : 0,
            ]);
        }
        $repo->insertTransition([
            'lifecycle_id' => $lifecycle, 'from_state_id' => $states['ACTIVE'],
            'to_state_id' => $states['INACTIVE'], 'action_code' => 'setstate', 'status' => 'ACTIVE',
        ]);
        foreach (['province', 'ward'] as $type) {
            $this->db->table('resources')->where('type', $type)->update(['lifecycle_id' => $lifecycle]);
        }

        return ['user' => $user, 'group' => $group, 'company' => $company, 'auth_group' => $authorizationGroup, 'states' => $states];
    }

    private function workflowState(string $code): int
    {
        $existing = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return (new LifecycleRepository($this->db))->insertWorkflowState([
            'code' => $code, 'name' => $code, 'status' => 'ACTIVE',
        ]);
    }

    private function policy(string $resource, int $group, int $user, ?int $stateId, string $action, string $effect): int
    {
        return (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $group,
            'resource_id' => $this->resourceId($resource),
            'state_id' => $stateId,
            'participant_type' => 'USER',
            'participant_ref_id' => $user,
        ], [['resource_action_id' => $this->actionId($resource, $action), 'effect' => $effect]]);
    }

    private function resourceId(string $resource): int
    {
        $row = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['id'];
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $action)->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['id'];
    }

    private function province(string $code, string $status): int
    {
        $this->db->table('provinces')->insert([
            'code' => $code, 'name' => $code, 'status' => $status, 'created_at' => '2026-10-06 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function ward(int $provinceId, string $code, string $status): int
    {
        $this->db->table('wards')->insert([
            'province_id' => $provinceId, 'code' => $code, 'name' => $code, 'status' => $status,
            'created_at' => '2026-10-06 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    /** @return array<string,mixed> */
    private function location(string $resource, int $id): array
    {
        $row = (new FoundationLocationRepository($this->db))->find($resource === 'province' ? 'provinces' : 'wards', $id);
        $this->assertNotNull($row);
        return $row;
    }

    private function api(): Api010FoundationOrganizationContactService
    {
        return new Api010FoundationOrganizationContactService(
            locations: new FoundationLocationRepository($this->db),
            authorization: new AuthorizationService(db: $this->db),
            workflowStates: new ResourceWorkflowStateResolver($this->db),
        );
    }

    /** @return list<array<string,mixed>> */
    private function authorizedRows(Api010FoundationOrganizationContactService $service, string $resource, int $userId): array
    {
        $method = new ReflectionMethod($service, 'authorizedRows');
        return $method->invoke($service, $resource, $this->operation($userId));
    }

    private function assertVisible(Api010FoundationOrganizationContactService $service, string $resource, int $id, int $userId): void
    {
        $method = new ReflectionMethod($service, 'authorizeVisible');
        $method->invoke($service, $resource, $this->location($resource, $id), $this->operation($userId));
    }

    private function authorize(int $user, string $resource, string $action, int $stateId): \App\Authorization\AuthorizationDecision
    {
        return (new AuthorizationService(db: $this->db))->authorize(
            $this->operation($user), $resource, $action, new EvaluationOrganizationRequest(), $stateId,
        );
    }

    private function engine(): LifecycleService
    {
        return new LifecycleService(
            repository: new LifecycleRepository($this->db),
            authorization: new AuthorizationService(db: $this->db),
            audit: new AuthorizationAuditService(db: $this->db),
            adapters: [
                new FoundationLocationLifecycleAdapter('province', $this->db),
                new FoundationLocationLifecycleAdapter('ward', $this->db),
            ],
            db: $this->db,
        );
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext(
            $userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T12:00:00+00:00')),
            'wf006-test', false,
        );
    }
}

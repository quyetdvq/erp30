<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use App\Repositories\LifecycleRepository;
use App\Services\Api030AuthorizationWorkflowService;
use App\Services\Api050ProductCatalogService;
use App\Services\ResourceNativeStateCatalog;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal Database regression: run only against disposable PHPUnit DB. */
final class P1Wf007NativeStateContractTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        try {
            // DatabaseTestTrait refreshes schemas before each test by invoking
            // migration down(). Restore test-only INACTIVE records first so the
            // production rollback guard remains strict and future tests can
            // safely rebuild their disposable database.
            if ($this->db !== null) {
                $this->db->resetDataCache();
                foreach (['policies', 'resources'] as $table) {
                    // tableExists(..., false) queries the PHYSICAL table name.
                    // The tests connection uses a prefix (e.g. dinktech_), so
                    // passing the logical name silently skips cleanup.
                    $physicalTable = $this->db->prefixTable($table);
                    if (! $this->db->tableExists($physicalTable, false)
                        || ! $this->db->fieldExists('status', $table)) {
                        continue;
                    }

                    $updated = $this->db->table($table)
                        ->where('status !=', 'ACTIVE')
                        ->update(['status' => 'ACTIVE']);
                    if ($updated === false || $this->db->table($table)
                        ->where('status !=', 'ACTIVE')->countAllResults() !== 0) {
                        throw new \RuntimeException('WF007_TEST_STATUS_CLEANUP_FAILED: ' . $table);
                    }
                }
                $this->db->resetDataCache();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testIndependentContractAndStatelessResources(): void
    {
        // The PHPUnit connection may have a DBPrefix: verify the physical
        // table check used by tearDown() before a test creates INACTIVE rows.
        $this->assertTrue($this->db->tableExists($this->db->prefixTable('policies'), false));
        $this->assertTrue($this->db->tableExists($this->db->prefixTable('resources'), false));
        $this->assertSame([
            'authorization_group', 'authorization_log', 'display_setting',
            'employee_assignment', 'import_job', 'lifecycle', 'policy', 'user',
            'workflow_state', 'resource',
        ], ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
        $this->assertFalse(ResourceNativeStateCatalog::isIndependent('organization_contact'));
        $this->assertFalse(ResourceNativeStateCatalog::isIndependent('employee_contact'));
        $this->assertSame(['ACTIVE', 'INACTIVE'], ResourceNativeStateCatalog::codes('policy'));
        $this->assertSame(['ACTIVE', 'INACTIVE'], ResourceNativeStateCatalog::codes('resource'));
        $this->assertSame([], ResourceNativeStateCatalog::codes('authorization_log'));
        $this->assertSame([], ResourceNativeStateCatalog::codes('display_setting'));
        $this->assertSame(['PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'], ResourceNativeStateCatalog::codes('import_job'));
    }

    public function testAllTenIndependentResourcesRejectLifecycleAssociation(): void
    {
        $this->fixture([]);
        $resolver = new ResourceWorkflowStateResolver($this->db);
        foreach (ResourceNativeStateCatalog::INDEPENDENT_RESOURCES as $type) {
            $this->assertNull($resolver->initialCode($type), $type);
            $this->assertSame(ResourceNativeStateCatalog::codes($type), $resolver->allowedPolicyStateCodes($type), $type);
            try {
                $resolver->assertLifecycleReassignmentSafe($type, 123);
                $this->fail('Independent Resource must reject Lifecycle: ' . $type);
            } catch (DomainException $exception) {
                $this->assertSame(ResourceWorkflowStateResolver::ERROR_LIFECYCLE_SELF_REFERENCE, $exception->getErrorCode(), $type);
            }
        }
    }

    public function testDisabledWorkflowStateCacheCannotBroadenNativeStateAuthorization(): void
    {
        $this->fixture([]);
        $inactive = $this->stateId('INACTIVE');
        $this->assertTrue($this->db->table('workflow_states')->where('id', $inactive)->update(['status' => 'INACTIVE']));
        try {
            $resolver = new ResourceWorkflowStateResolver($this->db);
            // A disabled Workflow State must be accessible for its own reactivation.
            $this->assertSame($inactive, $resolver->stateIdForCode('workflow_state', 'INACTIVE'));
            // But that cached ID must not make Policy or Resource treat it as active.
            foreach (['policy', 'resource', 'authorization_group'] as $type) {
                try {
                    $resolver->stateIdForCode($type, 'INACTIVE');
                    $this->fail('Inactive catalog entry must not authorize ' . $type);
                } catch (DomainException $exception) {
                    $this->assertSame(ResourceWorkflowStateResolver::ERROR_STATE_NOT_MAPPED, $exception->getErrorCode(), $type);
                }
            }
        } finally {
            $this->assertTrue($this->db->table('workflow_states')->where('id', $inactive)->update(['status' => 'ACTIVE']));
        }
    }

    public function testAdditiveStatusesPreserveLegacyDefaultActiveAndSeparatePolicyStateId(): void
    {
        $f = $this->fixture(['policy.view']);
        $this->assertTrue($this->db->fieldExists('status', 'resources'));
        $this->assertTrue($this->db->fieldExists('status', 'policies'));
        $resource = $this->db->table('resources')->where('type', 'policy')->get()->getRowArray();
        $this->assertNotNull($resource);
        $this->assertSame('ACTIVE', $resource['status']);
        $state = $this->stateId('INACTIVE');
        $id = $this->policy('policy', $f, $state, 'view', 'ALLOW');
        $policy = (new PolicyRepository($this->db))->find($id);
        $this->assertNotNull($policy);
        $this->assertSame('ACTIVE', $policy['status']);
        $this->assertSame($state, (int) $policy['state_id']);
        (new PolicyRepository($this->db))->setStatus($id, 'INACTIVE', $this->operation($f['user']));
        $updated = (new PolicyRepository($this->db))->find($id);
        $this->assertSame('INACTIVE', $updated['status']);
        $this->assertSame($state, (int) $updated['state_id']);
    }

    public function testNativeStateValidatedWithoutLifecycleAndStatelessPolicyFailsClosed(): void
    {
        $this->fixture([]);
        $resolver = new ResourceWorkflowStateResolver($this->db);
        $inactive = $this->stateId('INACTIVE');
        $closed = $this->stateId('CLOSED');
        $resolver->assertPolicyState($this->resourceId('policy'), $inactive);
        $resolver->assertPolicyState($this->resourceId('resource'), $inactive);
        $this->assertSame($inactive, $resolver->persistedStateId('policy', ['status' => 'INACTIVE']));
        $this->assertNull($resolver->persistedStateId('authorization_log', ['state_id' => $inactive]));
        foreach (['resource', 'authorization_log', 'display_setting'] as $resource) {
            try {
                $resolver->assertPolicyState($this->resourceId($resource), $closed);
                $this->fail('Invalid concrete State must not be permitted for ' . $resource);
            } catch (DomainException $exception) {
                $this->assertSame(ResourceWorkflowStateResolver::ERROR_POLICY_STATE_INVALID, $exception->getErrorCode());
            }
        }
    }

    public function testShieldUserNativeStateRequiresBothActiveStatusAndEnabledFlag(): void
    {
        $this->fixture([]);
        $resolver = new ResourceWorkflowStateResolver($this->db);
        $active = $this->stateId('ACTIVE');
        $inactive = $this->stateId('INACTIVE');
        $this->assertSame($active, $resolver->persistedStateId('user', ['status' => 'active', 'active' => 1]));
        $this->assertSame($inactive, $resolver->persistedStateId('user', ['status' => 'active', 'active' => 0]));
        $this->assertSame($inactive, $resolver->persistedStateId('user', ['status' => 'suspended', 'active' => 1]));
    }

    public function testInactivePolicyStopsApplyingAbsoluteDenyToNativeState(): void
    {
        $f = $this->fixture(['authorization_group.view']);
        // The protected target must differ from the actor's permission
        // group: disabling the actor's group would revoke Base Permission.
        $this->assertTrue($this->db->table('groups')->insert([
            'code' => 'WF007-T-' . uniqid(), 'name' => 'Protected target',
            'status' => 'INACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]));
        $targetId = (int) $this->db->insertID();
        $row = $this->db->table('groups')->where('id', $targetId)->get()->getRowArray();
        $this->assertNotNull($row);
        $policyId = $this->policy('authorization_group', $f, $this->stateId('INACTIVE'), 'view', 'ABSOLUTE_DENY');
        $denied = $this->authorize($f, 'authorization_group', 'view', $row);
        $this->assertSame('DENY', $denied->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $denied->reasonCode);
        (new PolicyRepository($this->db))->setStatus($policyId, 'INACTIVE', $this->operation($f['user']));
        $allowed = $this->authorize($f, 'authorization_group', 'view', $row);
        $this->assertSame('ALLOW', $allowed->decision);
        $this->assertNotContains($policyId, $allowed->matchedPolicyIds);
    }

    public function testStoredNativeStateCannotBeForgedByCallerHint(): void
    {
        $f = $this->fixture(['authorization_group.view']);
        $row = $this->db->table('groups')->where('id', $f['authorization_group'])->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('ACTIVE', $row['status']);
        try {
            (new AuthorizationService(db: $this->db))->authorize(
                $this->operation($f['user']), 'authorization_group', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $row), $this->stateId('INACTIVE'),
            );
            $this->fail('A caller hint must not replace persisted State.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_STATE_CONTEXT_MISMATCH, $exception->getErrorCode());
        }
    }

    public function testInactiveResourceTypeDeniesNormalActionsButDedicatedRecoveryIsEligible(): void
    {
        $f = $this->fixture(['resource.view', 'resource.setstate']);
        $typeRow = $this->db->table('resources')->where('type', 'resource')->get()->getRowArray();
        $this->assertNotNull($typeRow);
        $this->assertTrue($this->db->table('resources')->where('id', $typeRow['id'])->update(['status' => 'INACTIVE']));
        $typeRow['status'] = 'INACTIVE';
        $normal = $this->authorize($f, 'resource', 'view', $typeRow);
        $this->assertSame('DENY', $normal->decision);
        $this->assertSame(AuthorizationService::REASON_RESOURCE_INACTIVE, $normal->reasonCode);
        $recovery = $this->authorize($f, 'resource', 'setstate', $typeRow);
        $this->assertSame('ALLOW', $recovery->decision);
        $service = new Api030AuthorizationWorkflowService(
            db: $this->db, mutationContext: $this->mutation($f['user']),
        );
        $output = $service->setState('resource', (int) $typeRow['id'], 'ACTIVE');
        $this->assertSame(['id' => (int) $typeRow['id'], 'status' => 'ACTIVE'], $output);
        $this->assertSame('ACTIVE', $this->db->table('resources')->where('id', $typeRow['id'])->get()->getRowArray()['status']);
    }

    public function testResourceRecoveryNeverBypassesBasePermission(): void
    {
        $f = $this->fixture(['resource.view']);
        $row = $this->db->table('resources')->where('type', 'resource')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertTrue($this->db->table('resources')->where('id', $row['id'])->update(['status' => 'INACTIVE']));
        $row['status'] = 'INACTIVE';
        $decision = $this->authorize($f, 'resource', 'setstate', $row);
        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $decision->reasonCode);
    }

    public function testPolicyStatusMutationRequiresSeparatePermissionAndCannotUseOrdinaryEdit(): void
    {
        $f = $this->fixture(['policy.view', 'policy.edit', 'policy.setstate']);
        $id = $this->policy('authorization_group', $f, null, 'view', 'DENY');
        $service = new Api030AuthorizationWorkflowService(db: $this->db, mutationContext: $this->mutation($f['user']));
        try {
            $service->update('policy', $id, ['status' => 'INACTIVE']);
            $this->fail('The edit endpoint cannot toggle a policy.');
        } catch (DomainException $exception) {
            $this->assertSame('NATIVE_STATE_REQUIRES_SETSTATE', $exception->getErrorCode());
        }
        $out = $service->setState('policy', $id, 'INACTIVE');
        $this->assertSame(['id' => $id, 'status' => 'INACTIVE'], $out);
        $policy = $this->db->table('policies')->where('id', $id)->get()->getRowArray();
        $this->assertSame('INACTIVE', $policy['status']);
        $this->assertSame($f['user'], (int) $policy['updated_by']);
    }

    public function testNativeProductStateDenyFiltersBeforeTotalAndPagination(): void
    {
        $f = $this->fixture(['product.view']);
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->assertTrue($this->db->table('products')->insert([
                'code' => 'WF007-' . $status . '-' . uniqid(),
                'product_type' => 'STANDARD', 'product_name' => $status . ' item',
                'status' => $status, 'created_at' => '2026-10-06 00:00:00',
            ]));
        }
        $this->policy('product', $f, $this->stateId('INACTIVE'), 'view', 'ABSOLUTE_DENY');
        $api = new Api050ProductCatalogService(
            db: $this->db, authorization: new AuthorizationService(db: $this->db),
            mutationContext: $this->mutation($f['user']),
        );
        $result = $api->listProducts(['page' => '1', 'page_size' => '1', 'sort' => 'id']);
        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('ACTIVE', $result['items'][0]['status']);
    }

    public function testPolicyStateOptionsForIndependentAndStatelessResources(): void
    {
        $f = $this->fixture(['resource.view']);
        $api = new Api030AuthorizationWorkflowService(db: $this->db, mutationContext: $this->mutation($f['user']));
        $policy = $api->get('resource', $this->resourceId('policy'));
        $this->assertSame(['ACTIVE', 'INACTIVE'], $policy['policy_state_codes']);
        $log = $api->get('resource', $this->resourceId('authorization_log'));
        $this->assertSame([], $log['policy_state_codes']);
    }

    public function testLifecycleRemovalCannotInvalidateExistingStatePolicies(): void
    {
        $f = $this->fixture(['resource.view', 'resource.edit']);
        $repo = new LifecycleRepository($this->db);
        $draft = $repo->insertWorkflowState([
            'code' => 'WF007-D-' . uniqid(), 'name' => 'Draft State', 'status' => 'ACTIVE',
        ]);
        $lifecycleId = $repo->insertLifecycle([
            'code' => 'WF007-L-' . uniqid(), 'name' => 'Native Reassignment Check', 'status' => 'ACTIVE',
        ]);
        $repo->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $draft,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
        ]);
        $resourceId = $this->resourceId('province');
        $this->assertTrue($this->db->table('resources')->where('id', $resourceId)->update(['lifecycle_id' => $lifecycleId]));
        $policyId = $this->policy('province', $f, $draft, 'view', 'ALLOW');
        // Even an INACTIVE policy may be re-enabled later, so it is retained.
        (new PolicyRepository($this->db))->setStatus($policyId, 'INACTIVE', $this->operation($f['user']));
        $api = new Api030AuthorizationWorkflowService(db: $this->db, mutationContext: $this->mutation($f['user']));
        try {
            $api->update('resource', $resourceId, ['lifecycle_id' => null]);
            $this->fail('Removing Lifecycle must not silently invalidate State PBAC.');
        } catch (DomainException $exception) {
            $this->assertSame('RESOURCE_LIFECYCLE_POLICY_CONFLICT', $exception->getErrorCode());
        }
        $stored = $this->db->table('resources')->where('id', $resourceId)->get()->getRowArray();
        $this->assertSame($lifecycleId, (int) $stored['lifecycle_id']);
    }

    public function testDisabledInactiveWorkflowStateDefinitionCanBeReactivated(): void
    {
        $f = $this->fixture(['workflow_state.view', 'workflow_state.setstate']);
        $id = $this->stateId('INACTIVE');
        $this->assertTrue($this->db->table('workflow_states')->where('id', $id)->update(['status' => 'INACTIVE']));
        $api = new Api030AuthorizationWorkflowService(db: $this->db, mutationContext: $this->mutation($f['user']));
        $result = $api->setState('workflow_state', $id, 'ACTIVE');
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('ACTIVE', $this->db->table('workflow_states')->where('id', $id)->get()->getRowArray()['status']);
    }

    /** @param list<string> $permissions
     *  @return array{user:int, group:int, company:int, authorization_group:int}
     */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database(), $this->db))->run();
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'WF007-G-' . uniqid(),
            'name' => 'Native State Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $group = (int) $this->db->insertID();
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'parent_id' => $group, 'code' => 'WF007-C-' . uniqid(),
            'name' => 'Native State Company', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $company = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'username' => 'wf007-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $user = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => 'WF007-A-' . uniqid(), 'name' => 'Native Security Group', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $authorizationGroup = (int) $this->db->insertID();
        $authCode = (string) $this->db->table('groups')->where('id', $authorizationGroup)->get()->getRow('code');
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $user, 'group' => strtolower($authCode), 'created_at' => '2026-10-06 00:00:00',
        ]);
        foreach ($permissions as $permission) {
            [$resource, $action] = explode('.', $permission, 2);
            $this->db->table('group_permissions')->insert([
                'group_id' => $authorizationGroup,
                'resource_action_id' => $this->actionId($resource, $action),
                'created_at' => '2026-10-06 00:00:00',
            ]);
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $group);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $status) {
            $this->stateId($status);
        }
        return ['user' => $user, 'group' => $group, 'company' => $company, 'authorization_group' => $authorizationGroup];
    }

    /** @param array{user:int,group:int,company:int,authorization_group:int} $f */
    private function policy(string $type, array $f, ?int $stateId, string $action, string $effect): int
    {
        return (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $f['group'], 'resource_id' => $this->resourceId($type),
            'state_id' => $stateId, 'participant_type' => 'USER', 'participant_ref_id' => $f['user'],
        ], [['resource_action_id' => $this->actionId($type, $action), 'effect' => $effect]]);
    }

    /** @param array{user:int,group:int,company:int,authorization_group:int} $f
     *  @param array<string,mixed> $persisted
     */
    private function authorize(array $f, string $resource, string $action, array $persisted): \App\Authorization\AuthorizationDecision
    {
        return (new AuthorizationService(db: $this->db))->authorize(
            $this->operation($f['user']), $resource, $action,
            new EvaluationOrganizationRequest(persistedTarget: $persisted),
        );
    }

    private function resourceId(string $type): int
    {
        $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['id'];
    }

    private function actionId(string $type, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')->where('r.type', $type)
            ->where('ra.code', $action)->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['id'];
    }

    private function stateId(string $code): int
    {
        $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('workflow_states')->insert([
            'code' => $code, 'name' => $code, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function operation(int $user): OperationContext
    {
        return new OperationContext($user, new EvaluationTime(new DateTimeImmutable('2026-10-06T12:00:00+00:00')), 'wf007-native', false);
    }

    private function mutation(int $user): MutationContext
    {
        return new MutationContext(service('clock'), $user, 'wf007-native', false);
    }
}

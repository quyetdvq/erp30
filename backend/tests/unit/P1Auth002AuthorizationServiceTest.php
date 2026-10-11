<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveListBucketCompiler;
use App\Authorization\EvaluationOrganizationRequest;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
use App\Infrastructure\Mutation\MutationContext;
use App\Services\Api030AuthorizationWorkflowService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use App\Services\Api020EmployeeStructureService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Auth002AuthorizationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testMissingBasePermissionDeniesAndDoesNotUsePolicyAsPermission(): void
    {
        [$userId, , $companyId] = $this->fixture(false);
        $decision = $this->service()->authorize(
            $this->operation($userId),
            'price_list',
            'view',
            $this->request($companyId),
        );

        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $decision->reasonCode);
    }

    public function testBasePermissionAndNoMatchAllowAfterEmploymentEligibility(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $decision->reasonCode);
        $this->assertCount(1, $decision->participatingAssignmentIds);
        $this->assertSame([$this->permissionKey('price_list', 'view')], $decision->basePermissionSource);
        $this->assertSame('auth-002-test', $decision->requestId);
        $this->assertSame('2026-10-05T12:00:00+00:00', $decision->evaluationTime->utc()->format(DATE_ATOM));
    }

    public function testAllowWinsOverDenyInOneLane(): void
    {
        [$userId, $groupId, $companyId] = $this->fixture(true);
        $resourceId = $this->resourceId('price_list');
        $actionId = $this->resourceActionId('price_list', 'view');
        $repository = new PolicyRepository($this->db);
        $denyPolicyId = $repository->insertWithActions($this->policy($companyId, 'USER', $userId), [['resource_action_id' => $actionId, 'effect' => 'DENY']]);
        $allowPolicyId = $repository->insertWithActions($this->policy($companyId, 'GROUP', $groupId), [['resource_action_id' => $actionId, 'effect' => 'ALLOW']]);

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $decision->reasonCode);
        $this->assertSame([$allowPolicyId], $decision->decisivePolicyIds);
        $this->assertSame([$denyPolicyId, $allowPolicyId], $decision->matchedPolicyIds);
    }

    public function testAbsoluteDenyInheritedFromGroupOrganizationSurvivesAllLanes(): void
    {
        [$userId, , $companyId, $rootId] = $this->fixture(true);
        $repository = new PolicyRepository($this->db);
        $absolutePolicyId = $repository->insertWithActions(
            $this->policy($rootId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'ABSOLUTE_DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('DENY', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $decision->reasonCode);
        $this->assertSame([$absolutePolicyId], $decision->decisivePolicyIds);
    }

    public function testAssignmentDenyIsOnlyOneLaneAndDoesNotDenyActorNoMatch(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false, false);
        $departmentId = $this->insertDepartment();
        $employeeId = $this->insertEmployee($userId);
        $this->insertAssignment($employeeId, $companyId, $departmentId, null, false);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($companyId, 'DEPARTMENT', $departmentId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_NO_MATCH, $decision->reasonCode);
        $this->assertSame([], $decision->decisivePolicyIds);
        $this->assertCount(1, $decision->participatingAssignmentIds);
    }

    public function testTwoDeniedAssignmentLanesDenyByIntersection(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false, false);
        $firstDepartment = $this->insertDepartment();
        $secondDepartment = $this->insertDepartment();
        $employeeId = $this->insertEmployee($userId);
        $this->insertAssignment($employeeId, $companyId, $firstDepartment, null, false);
        $this->insertAssignment($employeeId, $companyId, $secondDepartment, null, false);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );
        foreach ([$firstDepartment, $secondDepartment] as $departmentId) {
            $repository->insertWithActions(
                $this->policy($companyId, 'DEPARTMENT', $departmentId),
                [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
            );
        }

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('DENY', $decision->decision);
        $this->assertCount(2, $decision->participatingAssignmentIds);
    }

    public function testSiblingPolicyDoesNotBleedIntoEvaluationOrganization(): void
    {
        [$userId, , $companyId, $rootId, $siblingId] = $this->fixture(true, true);
        $this->assertNotNull($siblingId);
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($siblingId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'DENY']],
        );

        $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));

        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame($companyId, $decision->evaluationOrganizationId);
        $this->assertNotSame($rootId, $decision->evaluationOrganizationId);
    }

    public function testClientContextMismatchIsRejectedByAuthoritativeResolver(): void
    {
        [$userId, , $companyId, , $siblingId] = $this->fixture(true, true);

        try {
            $this->service()->authorize(
                $this->operation($userId),
                'price_list',
                'view',
                new EvaluationOrganizationRequest(
                    persistedTarget: ['organization_id' => $companyId],
                    clientEvaluationOrganizationId: $siblingId,
                ),
            );
            $this->fail('Expected authoritative context mismatch.');
        } catch (DomainException $exception) {
            $this->assertSame('EVALUATION_ORGANIZATION_HINT_MISMATCH', $exception->getErrorCode());
        }
    }

    public function testBatchedPolicyActionsPreserveScopeStateLaneAndAuditWithOneQuery(): void
    {
        [$userId, $groupId, $companyId, $rootId, $siblingId] = $this->fixture(true, true);
        $this->assertNotNull($siblingId);
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('price_list', 'view');
        $edit = $this->resourceActionId('price_list', 'edit');

        // Noise policies are in scope but do not match either the user or
        // their assignment's department. They must still be fetched in one batch.
        for ($i = 0; $i < 12; $i++) {
            $repository->insertWithActions(
                $this->policy($companyId, 'DEPARTMENT', $this->insertDepartment()),
                [['resource_action_id' => $view, 'effect' => 'DENY']],
            );
        }
        $denyId = $repository->insertWithActions(
            $this->policy($rootId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'DENY']],
        );
        $allowId = $repository->insertWithActions(
            $this->policy($companyId, 'GROUP', $groupId),
            [
                ['resource_action_id' => $view, 'effect' => 'ALLOW'],
                ['resource_action_id' => $edit, 'effect' => 'ALLOW'],
            ],
        );
        // Keep the deliberately invalid effect short enough for older test
        // schemas; its action is unrelated to the evaluated `view` action.
        $unrelatedActionUpdated = $this->db->table('policy_actions')
            ->where('policy_id', $allowId)
            ->where('resource_action_id', $edit)
            ->update(['effect' => 'NOT_AN_EFFECT']);
        $this->assertTrue($unrelatedActionUpdated, 'Unrelated action fixture update failed: ' . json_encode($this->db->error()));
        $repository->insertWithActions(
            $this->policy($siblingId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'ABSOLUTE_DENY']],
        );
        $stateCode = 'AUTH002-N1-' . uniqid();
        $stateInserted = $this->db->table('workflow_states')->insert([
            'code' => $stateCode, 'name' => 'Unrelated state',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $this->assertTrue($stateInserted, 'Workflow state fixture insert failed: ' . json_encode($this->db->error()));
        // Resolve the persisted row by its unique code rather than assuming
        // that a prior failed write left a usable connection insert ID.
        $stateRow = $this->db->table('workflow_states')->select('id')->where('code', $stateCode)->get()->getRowArray();
        $this->assertNotNull($stateRow, 'New workflow state must be queryable before creating its policy.');
        $stateId = (int) $stateRow['id'];
        $this->assertGreaterThan(0, $stateId);
        $statePolicy = $this->policy($companyId, 'USER', $userId);
        $statePolicy['state_id'] = $stateId;
        $repository->insertWithActions($statePolicy, [['resource_action_id' => $view, 'effect' => 'ABSOLUTE_DENY']]);

        $queries = [];
        $listener = static function ($query) use (&$queries): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $queries[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $decision = $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        $this->assertCount(1, $queries, 'PBAC must batch actions for all eligible policies.');
        $this->assertSame('ALLOW', $decision->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $decision->reasonCode);
        $expectedMatched = [$denyId, $allowId];
        sort($expectedMatched);
        $this->assertSame($expectedMatched, $decision->matchedPolicyIds);
        $this->assertSame([$allowId], $decision->decisivePolicyIds);
        $this->assertCount(1, $decision->participatingAssignmentIds);
    }

    public function testBatchedPolicyActionsRejectInvalidEffectForEvaluatedAction(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('price_list', 'view');
        $policyId = $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'ALLOW']],
        );
        $this->db->table('policy_actions')
            ->where('policy_id', $policyId)
            ->where('resource_action_id', $view)
            ->update(['effect' => 'NOT_AN_EFFECT']);

        try {
            $this->service()->authorize($this->operation($userId), 'price_list', 'view', $this->request($companyId));
            $this->fail('An invalid effect for the evaluated resource action must fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(AuthorizationService::ERROR_POLICY_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * Real API030 Policy list: compare effective SQL buckets against the
     * existing per-row AuthorizationService oracle. Exercises Base Permission,
     * assignment scope, State-specific ABSOLUTE_DENY, ordinary DENY vs ALLOW,
     * count/order/page, and conservative fallback on unsafe string filters.
     */
    public function testPolicyListSqlBucketsMatchAuthoritativePerRowDecisions(): void
    {
        [$userId, $groupId, $companyId, , $siblingId] = $this->fixture(true, true);
        self::assertNotNull($siblingId);
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $this->resourceActionId('policy', 'view'),
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $repository = new PolicyRepository($this->db);
        $priceList = $this->resourceId('price_list');
        $viewPrice = $this->resourceActionId('price_list', 'view');
        $viewPolicy = $this->resourceActionId('policy', 'view');
        $make = static fn (int $org, int $res, string $type, int $ref, ?int $state = null): array => [
            'organization_id' => $org,
            'resource_id' => $res,
            'state_id' => $state,
            'participant_type' => $type,
            'participant_ref_id' => $ref,
        ];
        $firstId = $repository->insertWithActions(
            $make($companyId, $priceList, 'USER', $userId),
            [['resource_action_id' => $viewPrice, 'effect' => 'ALLOW']],
        );
        $repository->insertWithActions(
            $make($siblingId, $priceList, 'USER', $userId),
            [['resource_action_id' => $viewPrice, 'effect' => 'DENY']],
        );
        $inactiveId = $repository->insertWithActions(
            $make($companyId, $priceList, 'GROUP', $groupId),
            [['resource_action_id' => $viewPrice, 'effect' => 'ALLOW']],
        );
        $inactiveStateId = (int) $this->db->table('workflow_states')
            ->select('id')->where('code', 'INACTIVE')->get()->getRowArray()['id'];
        self::assertGreaterThan(0, $inactiveStateId);
        // A normal DENY in the USER lane is overridden by a GROUP ALLOW.
        $repository->insertWithActions(
            $make($companyId, $this->resourceId('policy'), 'USER', $userId),
            [['resource_action_id' => $viewPolicy, 'effect' => 'DENY']],
        );
        $repository->insertWithActions(
            $make($companyId, $this->resourceId('policy'), 'GROUP', $groupId),
            [['resource_action_id' => $viewPolicy, 'effect' => 'ALLOW']],
        );
        // A State-specific ABSOLUTE_DENY wins in every lane for INACTIVE rows.
        $repository->insertWithActions(
            $make($companyId, $this->resourceId('policy'), 'USER', $userId, $inactiveStateId),
            [['resource_action_id' => $viewPolicy, 'effect' => 'ABSOLUTE_DENY']],
        );
        $this->db->table('policies')->where('id', $inactiveId)->update(['status' => 'INACTIVE']);

        $mutation = new MutationContext(service('clock'), $userId, 'perf-005-policy', false);
        $operation = $mutation->begin();
        $authority = new AuthorizationService(db: $this->db);
        $api = new Api030AuthorizationWorkflowService(
            authorization: $authority,
            policyRepository: $repository,
            db: $this->db,
            mutationContext: $mutation,
        );
        $allowlist = new CommonListQueryAllowlist(
            [],
            ['organization_id', 'resource_id', 'state_id', 'status', 'participant_type', 'participant_ref_id'],
            ['id', 'organization_id', 'resource_id', 'status'],
        );
        $adapter = new CommonListQueryAdapter();
        $queries = [
            ['page' => '1', 'page_size' => '2'],
            ['page' => '2', 'page_size' => '2', 'sort' => '-id'],
            ['page' => '1', 'page_size' => '10', 'filter' => ['status' => 'ACTIVE']],
            ['page' => '1', 'page_size' => '10', 'filter' => ['status' => 'INACTIVE']],
            ['page' => '1', 'page_size' => '10', 'sort' => '-status'],
            ['page' => '1', 'page_size' => '10', 'filter' => ['resource_id' => (string) $priceList]],
            ['page' => '1', 'page_size' => '10', 'filter' => ['state_id' => '']],
            ['page' => '1', 'page_size' => '10', 'filter' => ['participant_type' => 'USER']],
            ['page' => '1', 'page_size' => '10', 'filter' => ['resource_id' => '0' . $priceList]],
        ];
        try {
            foreach ($queries as $query) {
                $visibleRows = [];
                foreach ($repository->findAll() as $row) {
                    $decision = $authority->authorize(
                        $operation,
                        'policy',
                        'view',
                        new EvaluationOrganizationRequest(persistedTarget: $row),
                    );
                    if ($decision->isAllowed()) {
                        $visibleRows[] = $row;
                    }
                }
                $baseline = $adapter->paginate($query, $allowlist, new AuthorizedArrayListSource($visibleRows));
                $optimized = $api->list('policy', $query);
                $baselineIds = array_map('intval', array_column(iterator_to_array($baseline->data), 'id'));
                $optimizedRows = iterator_to_array($optimized->data);
                self::assertSame($baseline->total, $optimized->total, 'Visibility-safe total must match.');
                self::assertSame($baselineIds, array_map('intval', array_column($optimizedRows, 'id')),
                    'The PBAC SQL filter must preserve the authorized page and order.');
                foreach ($optimizedRows as $row) {
                    self::assertArrayHasKey('actions', $row, 'Increment 1 action hydration must be preserved.');
                }
            }
            self::assertContains($firstId, array_map('intval', array_column(
                iterator_to_array($api->list('policy', ['page' => '1', 'page_size' => '20'])->data), 'id',
            )));
            self::assertSame('DENY', $authority->authorize(
                $operation, 'policy', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $repository->find($inactiveId)),
            )->decision);
            // No Base Permission means no row, regardless of default NO_MATCH.
            $this->db->table('group_permissions')
                ->where('group_id', $groupId)
                ->where('resource_action_id', $viewPolicy)
                ->delete();
            try {
                self::assertSame('0', $api->list('policy', ['page' => '1', 'page_size' => '20'])->total);
            } finally {
                $this->db->table('group_permissions')->insert([
                    'group_id' => $groupId, 'resource_action_id' => $viewPolicy,
                    'created_at' => '2026-10-05 00:00:00',
                ]);
            }
            // Resource disabled must be fail-closed even for an authorized actor.
            $this->db->table('resources')->where('type', 'policy')->update(['status' => 'INACTIVE']);
            try {
                self::assertSame('0', $api->list('policy', ['page' => '1', 'page_size' => '20'])->total);
            } finally {
                $this->db->table('resources')->where('type', 'policy')->update(['status' => 'ACTIVE']);
            }
        } finally {
            // Do not leave an INACTIVE Policy behind: WF007 migration down()
            // intentionally blocks rollback of real inactive business data.
            $this->db->table('policies')->where('id', $inactiveId)->update(['status' => 'ACTIVE']);
        }
    }

    public function testReadOnlyBatchMatchesAllDecisionEvidenceAndReducesDatabaseLookups(): void
    {
        [$userId, $groupId, $companyId] = $this->fixture(true);
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('price_list', 'view');
        $denyId = $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $view, 'effect' => 'DENY']],
        );
        $allowId = $repository->insertWithActions(
            $this->policy($companyId, 'GROUP', $groupId),
            [['resource_action_id' => $view, 'effect' => 'ALLOW']],
        );
        $this->assertGreaterThan(0, $denyId);
        $this->assertGreaterThan(0, $allowId);
        $operation = $this->operation($userId);
        $request = $this->request($companyId);
        $service = $this->service();

        $baselineSql = [];
        $listener = static function ($query) use (&$baselineSql): void {
            $baselineSql[] = strtolower((string) $query);
        };
        Events::on('DBQuery', $listener);
        try {
            $baseline = [];
            for ($i = 0; $i < 5; $i++) {
                $baseline[] = $service->authorize($operation, 'price_list', 'view', $request)->toArray();
            }
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        $optimizedSql = [];
        $listener = static function ($query) use (&$optimizedSql): void {
            $optimizedSql[] = strtolower((string) $query);
        };
        Events::on('DBQuery', $listener);
        try {
            $optimized = $service->withReadOnlyBatch($operation, function () use ($service, $operation, $request): array {
                $decisions = [];
                for ($i = 0; $i < 5; $i++) {
                    $decisions[] = $service->authorize($operation, 'price_list', 'view', $request)->toArray();
                }
                return $decisions;
            });
        } finally {
            Events::removeListener('DBQuery', $listener);
        }
        $this->assertSame($baseline, $optimized, 'PBAC decisions, reasons, policy IDs, and audit must be identical.');
        $this->assertSame('ALLOW', $optimized[0]['decision']);
        $this->assertSame([$allowId], $optimized[0]['decisive_policy_ids']);
        $this->assertSame(5, count(array_filter($baselineSql, static fn (string $sql): bool => str_contains($sql, 'policy_actions'))));
        $this->assertSame(1, count(array_filter($optimizedSql, static fn (string $sql): bool => str_contains($sql, 'policy_actions'))));
        $this->assertLessThan(count($baselineSql), count($optimizedSql), 'Batch must reduce executed DB statements.');
    }

    public function testReadOnlyBatchKeepsScopeSeparateAndReleasesSnapshotAfterException(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $operation = $this->operation($userId);
        $service = $this->service();
        $repository = new PolicyRepository($this->db);
        $repository->insertWithActions(
            $this->policy($branchId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'ABSOLUTE_DENY']],
        );
        $company = $this->request($companyId);
        $branch = $this->request($branchId);
        $oracleCompany = $service->authorize($operation, 'price_list', 'view', $company)->toArray();
        $oracleBranch = $service->authorize($operation, 'price_list', 'view', $branch)->toArray();
        $this->assertSame('ALLOW', $oracleCompany['decision']);
        $this->assertSame('DENY', $oracleBranch['decision']);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $oracleBranch['reason_code']);

        $this->assertSame([$oracleCompany, $oracleBranch, $oracleCompany], $service->withReadOnlyBatch(
            $operation,
            static fn (): array => [
                $service->authorize($operation, 'price_list', 'view', $company)->toArray(),
                $service->authorize($operation, 'price_list', 'view', $branch)->toArray(),
                $service->authorize($operation, 'price_list', 'view', $company)->toArray(),
            ],
        ));

        try {
            $service->withReadOnlyBatch($operation, static function () use ($service, $operation, $company): void {
                $service->authorize($operation, 'price_list', 'view', $company);
                throw new \RuntimeException('read failure');
            });
            $this->fail('Expected simulated read failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('read failure', $exception->getMessage());
        }
        // A separate operation on the same AuthorizationService instance must
        // read the new policy: failed batches cannot leak stale allow decisions.
        $repository->insertWithActions(
            $this->policy($companyId, 'USER', $userId),
            [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'ABSOLUTE_DENY']],
        );
        $after = $service->authorize($operation, 'price_list', 'view', $company);
        $this->assertSame('DENY', $after->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $after->reasonCode);
    }

    public function testReadOnlyBatchNeverCachesDecisionsForDifferentOperationContext(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $service = $this->service();
        $operation = $this->operation($userId);
        $other = new OperationContext(
            $userId,
            new EvaluationTime(new DateTimeImmutable('2026-10-06T12:00:00+00:00')),
            'other-request-id',
            false,
        );
        $request = $this->request($companyId);
        $sql = [];
        $listener = static function ($query) use (&$sql): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $sql[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $decisions = $service->withReadOnlyBatch($operation, static fn (): array => [
                $service->authorize($other, 'price_list', 'view', $request)->toArray(),
                $service->authorize($other, 'price_list', 'view', $request)->toArray(),
            ]);
        } finally {
            Events::removeListener('DBQuery', $listener);
        }
        $this->assertSame($decisions[0], $decisions[1]);
        $this->assertCount(2, $sql, 'Different operation must never hit an unrelated read-only snapshot.');
    }

    public function testNonViewAuthorizationInvalidatesAnyActiveReadOnlyBatch(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $service = $this->service();
        $operation = $this->operation($userId);
        $request = $this->request($companyId);
        $repository = new PolicyRepository($this->db);
        $service->withReadOnlyBatch($operation, function () use (
            $service, $operation, $request, $repository, $companyId, $userId,
        ): void {
            $this->assertSame('ALLOW', $service->authorize($operation, 'price_list', 'view', $request)->decision);
            // Even an unsuccessful mutation-action authorization clears the
            // snapshot defensively. The list caller must never mix writes and
            // reads, but this guard prevents an accidental stale reuse.
            $this->assertSame('DENY', $service->authorize($operation, 'price_list', 'edit', $request)->decision);
            $repository->insertWithActions(
                $this->policy($companyId, 'USER', $userId),
                [['resource_action_id' => $this->resourceActionId('price_list', 'view'), 'effect' => 'ABSOLUTE_DENY']],
            );
            $this->assertSame('DENY', $service->authorize($operation, 'price_list', 'view', $request)->decision);
        });
    }

    public function testNativeStateSqlBucketsMatchAuthoritativeMasterDecisions(): void
    {
        [$userId, $groupId, , $rootId] = $this->fixture(true);
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $this->resourceActionId('employee', 'view'),
            'created_at' => '2026-10-05 00:00:00',
        ]);
        foreach (['ACTIVE', 'INACTIVE', 'ACTIVE'] as $status) {
            $this->db->table('employees')->insert([
                'employee_code' => 'AUTH002-PBAC-SQL-' . uniqid(), 'employee_name' => $status,
                'status' => $status, 'created_at' => '2026-10-05 00:00:00',
            ]);
        }
        $inactiveState = $this->db->table('workflow_states')->select('id')->where('code', 'INACTIVE')->get()->getRowArray();
        $this->assertNotNull($inactiveState);
        $policy = $this->policy($rootId, 'USER', $userId);
        $policy['resource_id'] = $this->resourceId('employee');
        $policy['state_id'] = (int) $inactiveState['id'];
        (new PolicyRepository($this->db))->insertWithActions(
            $policy,
            [['resource_action_id' => $this->resourceActionId('employee', 'view'), 'effect' => 'ABSOLUTE_DENY']],
        );
        $operation = $this->operation($userId);
        $service = $this->service();
        $buckets = $service->withReadOnlyBatch($operation, fn (): ?array =>
            (new EffectiveListBucketCompiler($this->db, $service))->compile('employee', $operation, static fn (): bool => false));
        $this->assertSame([['status' => 'ACTIVE']], $buckets);

        $legacy = [];
        foreach ($this->db->table('employees')->orderBy('id', 'ASC')->get()->getResultArray() as $row) {
            if ($service->authorize($operation, 'employee', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $row))->isAllowed()) {
                $legacy[] = $row;
            }
        }
        $query = (new CommonListQueryAdapter())->parse(
            ['page_size' => '2', 'page' => '2', 'sort' => '-id'],
            new CommonListQueryAllowlist(['employee_name'], ['status'], ['id']),
        );
        $sql = new SqlAuthorizedBucketListSource($this->db, 'employee', $buckets ?? []);
        $php = new AuthorizedArrayListSource($legacy);
        $this->assertSame($php->countAuthorized($query), $sql->countAuthorized($query));
        $this->assertSame($php->fetchAuthorized($query), $sql->fetchAuthorized($query));

        // Exercise the actual Employee list boundary as well as the source.
        $api = new Api020EmployeeStructureService(
            authorization: $service,
            db: $this->db,
            mutationContext: new MutationContext(service('clock'), $userId, 'auth-002-perf-list', false),
        );
        $result = $api->list('employee', ['page_size' => '1']);
        $this->assertSame((string) count($legacy), $result->total);
        $this->assertCount(1, iterator_to_array($result->data));
    }

    public function testOrganizationScopedSqlBucketsDoNotGrantSiblingAssignments(): void
    {
        [$userId, $groupId, $companyId, , $siblingId] = $this->fixture(true, true);
        $this->assertNotNull($siblingId);
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $this->resourceActionId('employee_assignment', 'view'),
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $otherEmployee = $this->insertEmployee(9900000 + random_int(1, 100000));
        $this->insertAssignment($otherEmployee, $siblingId, $this->insertDepartment(), null, false);
        $operation = $this->operation($userId);
        $service = $this->service();
        $buckets = $service->withReadOnlyBatch($operation, fn (): ?array =>
            (new EffectiveListBucketCompiler($this->db, $service))->compile(
                'employee_assignment', $operation, static fn (): bool => false));
        $this->assertContains(['status' => 'ACTIVE', 'organization_id' => $companyId], $buckets);
        $this->assertNotContains(['status' => 'ACTIVE', 'organization_id' => $siblingId], $buckets);
        $sql = new SqlAuthorizedBucketListSource($this->db, 'employee_assignment', $buckets ?? []);
        $query = (new CommonListQueryAdapter())->parse(
            [], new CommonListQueryAllowlist([], ['organization_id'], ['id']));
        $this->assertGreaterThan(0, $sql->countAuthorized($query));
        foreach ($sql->fetchAuthorized($query) as $row) {
            $this->assertSame($companyId, (int) $row['organization_id']);
            $this->assertTrue($service->authorize($operation, 'employee_assignment', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $row))->isAllowed());
        }
    }

    /** @return array{int,int,int,int,?int} */
    private function fixture(bool $basePermission, bool $secondCompany = false, bool $withAssignment = true): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $rootId);
        $siblingId = $secondCompany ? $this->insertOrganization('COMPANY', $rootId) : null;
        $this->db->table('users')->insert([
            'username' => 'auth002-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $groupCode = 'AUTH002-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'Auth 002', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-05 00:00:00']);
        if ($basePermission) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $groupId,
                'resource_action_id' => $this->resourceActionId('price_list', 'view'),
                'created_at' => '2026-10-05 00:00:00',
            ]);
        }

        if ($withAssignment) {
            $employeeId = $this->insertEmployee($userId);
            $this->insertAssignment($employeeId, $companyId, $this->insertDepartment(), null, false);
        }

        return [$userId, $groupId, $companyId, $rootId, $siblingId];
    }

    private function service(): AuthorizationService
    {
        return new AuthorizationService(db: $this->db);
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')), 'auth-002-test', false);
    }

    private function request(int $organizationId): EvaluationOrganizationRequest
    {
        return new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $organizationId]);
    }

    /** @return array<string, mixed> */
    private function policy(int $organizationId, string $type, int $reference): array
    {
        return [
            'organization_id' => $organizationId,
            'resource_id' => $this->resourceId('price_list'),
            'state_id' => null,
            'participant_type' => $type,
            'participant_ref_id' => $reference,
        ];
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('AUTH002-O-', true),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDepartment(): int
    {
        $this->db->table('departments')->insert(['code' => uniqid('AUTH002-D-', true), 'name' => 'Department', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(int $userId): int
    {
        $this->db->table('employees')->insert(['user_id' => $userId, 'employee_code' => uniqid('AUTH002-E-', true), 'employee_name' => 'Employee', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00']);

        return (int) $this->db->insertID();
    }

    private function insertAssignment(int $employeeId, int $organizationId, int $departmentId, ?int $positionId, bool $primary): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'department_id' => $departmentId, 'position_id' => $positionId,
            'is_primary' => $primary ? 1 : 0, 'effective_from' => '2026-01-01',
            'effective_to' => null, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function resourceId(string $type): int
    {
        $result = $this->db->table('resources')->where('type', $type)->get();
        $row = $result === false ? null : $result->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function resourceActionId(string $resource, string $action): int
    {
        $result = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get();
        $row = $result === false ? null : $result->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function permissionKey(string $resource, string $action): string
    {
        return strtolower($resource) . '.' . strtolower($action);
    }
}

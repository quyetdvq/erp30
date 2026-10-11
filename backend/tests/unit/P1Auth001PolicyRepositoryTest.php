<?php

declare(strict_types=1);

use App\Authorization\PolicyParticipantValidator;
use App\Authorization\PolicyValidator;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Auth001PolicyRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testCanonicalParticipantTypesResolveAgainstTheirDeclaredTargets(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $validator = new PolicyParticipantValidator(new PolicyRepository($this->db));

        foreach ($refs as $type => $id) {
            $this->assertSame([
                'participant_type' => $type,
                'participant_ref_id' => $id,
            ], $validator->validate($type, (string) $id));
        }
    }

    public function testGroupAndOrganizationReferencesCannotCrossTheirTables(): void
    {
        $this->seedCatalog();
        $this->db->table('groups')->insertBatch([
            ['id' => 900002, 'code' => 'AUTH-GROUP-CROSS-1', 'name' => 'Cross 1', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00'],
            ['id' => 900003, 'code' => 'AUTH-GROUP-CROSS-2', 'name' => 'Cross 2', 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00'],
        ]);
        $this->db->table('organizations')->insert([
            'id' => 900001,
            'type' => 'COMPANY', 'code' => 'AUTH-ORG-CROSS-1', 'name' => 'Cross organization',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $organizationId = 900001;
        $groupId = 900002;
        $validator = new PolicyParticipantValidator(new PolicyRepository($this->db));

        $this->assertInvalidParticipant($validator, 'GROUP', $organizationId);
        $this->assertInvalidParticipant($validator, 'ORGANIZATION', $groupId);
    }

    public function testRoleUnknownAndMissingParticipantsFailClosed(): void
    {
        $this->seedCatalog();
        $validator = new PolicyParticipantValidator(new PolicyRepository($this->db));

        foreach ([['ROLE', 1], ['UNKNOWN', 1], ['GROUP', 999999999]] as [$type, $id]) {
            $this->assertInvalidParticipant($validator, $type, $id);
        }
    }

    public function testNullStateIsPersistedAndReturnedAsAllStates(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $resourceId = $this->resourceId('product');
        $repository = new PolicyRepository($this->db);
        $policyId = $repository->insert([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $resourceId,
            'state_id' => null,
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
        ]);

        $this->assertNull($repository->find($policyId)['state_id']);
        $this->assertCount(1, $repository->findAll(['state_id' => null]));
    }

    public function testGeneratedScopeColumnIsNotExposedByPolicyReads(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $policyId = $repository->insert([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $this->resourceId('product'),
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
        ]);

        $row = $repository->find($policyId);
        $lockedRow = $repository->findForUpdate($policyId);
        $this->assertNotNull($row);
        $this->assertNotNull($lockedRow);
        $this->assertArrayNotHasKey('normalized_state_id', $row);
        $this->assertArrayNotHasKey('normalized_state_id', $lockedRow);
        $this->assertArrayNotHasKey('normalized_state_id', $repository->findAll()[0]);
    }

    public function testPolicyActionMustBelongToPolicyResource(): void
    {
        $this->seedCatalog();
        $validator = new PolicyValidator(new PolicyRepository($this->db));
        $productId = $this->resourceId('product');
        $organizationActionId = $this->resourceActionId('organization', 'view');

        try {
            $validator->validateAction([
                'resource_action_id' => $organizationActionId,
                'effect' => 'ALLOW',
            ], $productId);
            $this->fail('Expected resource/action mismatch.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyValidator::ERROR_RESOURCE_ACTION_MISMATCH, $exception->getErrorCode());
        }

        $valid = $validator->validateAction([
            'resource_action_id' => $this->resourceActionId('product', 'view'),
            'effect' => 'allow',
        ], $productId);
        $this->assertSame('ALLOW', $valid['effect']);
    }

    public function testPolicyMutationDoesNotCreateBasePermission(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $before = [
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'auth_permissions_users' => (int) $this->db->table('auth_permissions_users')->countAllResults(),
        ];
        $policyId = $repository->insertWithActions([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $this->resourceId('product'),
            'state_id' => null,
            'participant_type' => 'GROUP',
            'participant_ref_id' => $refs['GROUP'],
        ], [[
            'resource_action_id' => $this->resourceActionId('product', 'view'),
            'effect' => 'ALLOW',
        ]]);

        $this->assertGreaterThan(0, $policyId);
        $this->assertSame($before, [
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'auth_permissions_users' => (int) $this->db->table('auth_permissions_users')->countAllResults(),
        ]);
    }

    public function testCanonicalScopeRejectsDuplicateNullStateAndAllowsConcreteState(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $resourceId = $this->resourceId('product');
        $actionId = $this->resourceActionId('product', 'view');
        $payload = [
            'organization_id' => $refs['ORGANIZATION'], 'resource_id' => $resourceId,
            'state_id' => null, 'participant_type' => 'GROUP', 'participant_ref_id' => $refs['GROUP'],
        ];
        $repository->insertWithActions($payload, [['resource_action_id' => $actionId, 'effect' => 'ALLOW']]);

        try {
            $repository->insertWithActions($payload, [['resource_action_id' => $actionId, 'effect' => 'ALLOW']]);
            $this->fail('Expected duplicate Policy scope.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyRepository::ERROR_SCOPE_ALREADY_EXISTS, $exception->getErrorCode());
        }

        $this->db->table('workflow_states')->insert([
            'id' => 990002, 'code' => 'AUTH_SCOPE_' . uniqid(), 'name' => 'Concrete state',
            'status' => 'ACTIVE', 'created_at' => '2026-10-08 00:00:00',
        ]);
        $concrete = $payload;
        $concrete['state_id'] = 990002;
        $concreteId = $repository->insertWithActions($concrete, [['resource_action_id' => $actionId, 'effect' => 'ALLOW']]);
        $this->assertGreaterThan(0, $concreteId);
        try {
            $repository->update($concreteId, ['state_id' => null]);
            $this->fail('Expected duplicate Policy scope on update.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyRepository::ERROR_SCOPE_ALREADY_EXISTS, $exception->getErrorCode());
        }
    }

    public function testCallerAuditFieldsAreIgnoredInFavorOfTrustedOperationContext(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $operation = new OperationContext(
            $refs['USER'],
            new EvaluationTime(new \DateTimeImmutable('2026-10-05 04:05:06', new \DateTimeZone('UTC'))),
            'auth-001-audit',
            false,
        );
        $id = (new PolicyRepository($this->db))->insert([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $this->resourceId('product'),
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
            'created_by' => 999999999,
            'created_at' => '1999-01-01 00:00:00',
        ], $operation);
        $row = $this->db->table('policies')->where('id', $id)->get()->getRowArray();

        $this->assertSame((string) $refs['USER'], (string) $row['created_by']);
        $this->assertSame('2026-10-05 04:05:06', $row['created_at']);
    }

    public function testPersistenceFailureIsDistinctFromMissingParticipantTarget(): void
    {
        $missing = new PolicyParticipantValidator(new PolicyRepository($this->db));
        try {
            $missing->validate('GROUP', 999999999);
            $this->fail('Expected missing target failure.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT, $exception->getErrorCode());
        }

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willThrowException(new \RuntimeException('database unavailable'));
        $failed = new PolicyParticipantValidator(new PolicyRepository($connection));
        $this->expectException(PersistenceException::class);
        $failed->validate('GROUP', 1);
    }

    public function testPolicyAndActionsRollbackTogetherOnMutationFailure(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $resourceId = $this->resourceId('product');
        $actionId = $this->resourceActionId('product', 'view');

        try {
            $repository->insertWithActions([
                'organization_id' => $refs['ORGANIZATION'],
                'resource_id' => $resourceId,
                'participant_type' => 'GROUP',
                'participant_ref_id' => $refs['GROUP'],
            ], [
                ['resource_action_id' => $actionId, 'effect' => 'ALLOW'],
                ['resource_action_id' => $actionId, 'effect' => 'DENY'],
            ]);
            $this->fail('Expected unique action failure.');
        } catch (PersistenceException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->db->table('policies')
            ->where('participant_type', 'GROUP')
            ->where('participant_ref_id', $refs['GROUP'])
            ->countAllResults());
    }

    public function testBatchPolicyActionReadFiltersResourceActionAndBoundsQueryCount(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('product', 'view');
        $edit = $this->resourceActionId('product', 'edit');
        $policyId = $repository->insertWithActions([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $this->resourceId('product'),
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
        ], [
            ['resource_action_id' => $view, 'effect' => 'ALLOW'],
            ['resource_action_id' => $edit, 'effect' => 'DENY'],
        ]);

        $queries = [];
        $listener = static function ($query) use (&$queries): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $queries[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $this->assertSame([], $repository->findPolicyActionsForPolicies([], $view));
            $this->assertCount(0, $queries, 'An empty policy selection must not query policy_actions.');

            // 1001 nonexistent IDs exercise three bounded batches without
            // creating a thousand database fixtures.
            $ids = array_merge([$policyId, $policyId], range(900000000, 900001000));
            $rows = $repository->findPolicyActionsForPolicies($ids, $view);
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        $this->assertCount(3, $queries, '1002 unique IDs must use 3 queries, not one query per policy.');
        $this->assertCount(1, $rows);
        $this->assertSame($policyId, (int) $rows[0]['policy_id']);
        $this->assertSame($view, (int) $rows[0]['resource_action_id']);
        $this->assertSame('ALLOW', $rows[0]['effect']);
        $this->assertCount(2, $repository->findPolicyActions($policyId), 'Existing administration reads remain unchanged.');
    }

    public function testAdministrativePolicyActionBatchFetchKeepsAllActionsAndBoundedSql(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $view = $this->resourceActionId('product', 'view');
        $edit = $this->resourceActionId('product', 'edit');
        $policyId = $repository->insertWithActions([
            'organization_id' => $refs['ORGANIZATION'],
            'resource_id' => $this->resourceId('product'),
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
        ], [
            ['resource_action_id' => $view, 'effect' => 'ALLOW'],
            ['resource_action_id' => $edit, 'effect' => 'ABSOLUTE_DENY'],
        ]);

        $queries = [];
        $listener = static function ($query) use (&$queries): void {
            if (str_contains(strtolower((string) $query), 'policy_actions')) {
                $queries[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            self::assertSame([], $repository->findAllPolicyActionsForPolicies([]));
            self::assertCount(0, $queries);
            $fetched = $repository->findAllPolicyActionsForPolicies([$policyId, $policyId]);
            self::assertCount(1, $queries, 'One list page must not execute one SELECT per Policy.');
            $many = $repository->findAllPolicyActionsForPolicies(array_merge([$policyId], range(900000000, 900001000)));
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        self::assertCount(4, $queries, '1002 unique Policy IDs use three additional bounded SELECTs.');
        self::assertCount(2, $fetched);
        self::assertSame([$view, $edit], array_map(static fn (array $row): int => (int) $row['resource_action_id'], $fetched));
        self::assertSame(['ALLOW', 'ABSOLUTE_DENY'], array_column($fetched, 'effect'));
        self::assertSame($fetched, $many, 'Batch boundary cannot lose an action or change its effect.');
    }

    public function testPolicySqlPushdownMatchesLegacySelectionWithOneSelect(): void
    {
        $this->seedCatalog();
        $refs = $this->insertReferences();
        $repository = new PolicyRepository($this->db);
        $resourceId = $this->resourceId('product');
        $view = $this->resourceActionId('product', 'view');
        $edit = $this->resourceActionId('product', 'edit');
        $this->db->table('workflow_states')->insert([
            'code' => 'PERF004-' . uniqid(), 'name' => 'Performance Test',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $stateId = (int) $this->db->insertID();
        $orgId = $refs['ORGANIZATION'];
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => 'PERF004-' . uniqid(), 'name' => 'Other scope',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $otherOrg = (int) $this->db->insertID();
        $base = [
            'organization_id' => $orgId,
            'resource_id' => $resourceId,
            'participant_type' => 'USER',
            'participant_ref_id' => $refs['USER'],
        ];
        $allStates = $repository->insertWithActions($base, [
            ['resource_action_id' => $view, 'effect' => 'ALLOW'],
            ['resource_action_id' => $edit, 'effect' => 'DENY'],
        ]);
        $specific = $repository->insertWithActions([...$base, 'state_id' => $stateId], [
            ['resource_action_id' => $view, 'effect' => 'DENY'],
        ]);
        $inactive = $repository->insertWithActions([
            ...$base, 'participant_type' => 'GROUP', 'participant_ref_id' => $refs['GROUP'],
        ], [['resource_action_id' => $view, 'effect' => 'ABSOLUTE_DENY']]);
        $repository->insertWithActions([
            ...$base, 'organization_id' => $otherOrg,
        ], [['resource_action_id' => $view, 'effect' => 'DENY']]);
        $this->db->table('policies')->where('id', $inactive)->update(['status' => 'INACTIVE']);
        try {
            $queries = [];
            $listener = static function ($query) use (&$queries): void {
                if (str_contains(strtolower((string) $query), 'policy_actions')) {
                    $queries[] = (string) $query;
                }
            };
            Events::on('DBQuery', $listener);
            try {
                $actual = $repository->findApplicablePolicyRows($resourceId, [$orgId], $view, $stateId);
            } finally {
                Events::removeListener('DBQuery', $listener);
            }
            self::assertCount(1, $queries, 'Policy selection must use one filtered JOIN, not catalog-wide reads.');
            self::assertSame([$allStates, $specific], array_map(
                static fn (array $entry): int => (int) $entry['policy']['id'], $actual,
            ));
            self::assertSame(['ALLOW', 'DENY'], array_column($actual, 'effect'));
            self::assertSame([$allStates], array_map(
                static fn (array $entry): int => (int) $entry['policy']['id'],
                $repository->findApplicablePolicyRows($resourceId, [$orgId], $view, null),
            ));
            self::assertSame([], $repository->findApplicablePolicyRows($resourceId, [], $view, $stateId));
            self::assertSame([], $repository->findApplicablePolicyRows($resourceId, [$orgId], $view + 900000, $stateId));
            self::assertSame([$allStates], array_map(
                static fn (array $entry): int => (int) $entry['policy']['id'],
                $repository->findApplicablePolicyRows($resourceId, [$orgId], $view, null),
            ));
            // On a case-insensitive MariaDB collation, the malformed value
            // 'active' must NOT silently become an active Policy.
            $this->db->table('policies')->where('id', $inactive)->update(['status' => 'active']);
            self::assertNull($repository->findPolicyVisibilityBuckets(),
                'Unexpected/case-mismatched status must force row-by-row fallback.');
            self::assertSame([$allStates], array_map(
                static fn (array $entry): int => (int) $entry['policy']['id'],
                $repository->findApplicablePolicyRows($resourceId, [$orgId], $view, null),
            ));
        } finally {
            // Prevent rollback guard from seeing fixture INACTIVE statuses.
            $this->db->table('policies')->where('id', $inactive)->update(['status' => 'ACTIVE']);
        }
    }

    /** @return array<string, int> */
    private function insertReferences(): array
    {
        $this->db->table('users')->insert([
            'username' => 'auth001-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => 'AUTH001-' . uniqid(), 'name' => 'Auth Group', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('positions')->insert([
            'code' => 'AUTH001-' . uniqid(), 'name' => 'Auth Position', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $positionId = (int) $this->db->insertID();
        $this->db->table('departments')->insert([
            'code' => 'AUTH001-' . uniqid(), 'name' => 'Auth Department', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $departmentId = (int) $this->db->insertID();
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => 'AUTH001-' . uniqid(), 'name' => 'Auth Organization',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();

        return [
            'USER' => $userId, 'GROUP' => $groupId, 'POSITION' => $positionId,
            'DEPARTMENT' => $departmentId, 'ORGANIZATION' => $organizationId,
        ];
    }

    private function seedCatalog(): void
    {
        (new App\Database\Seeds\ResourceActionSeeder(new Config\Database()))->run();
    }

    private function resourceId(string $type): int
    {
        return (int) $this->db->table('resources')->where('type', $type)->get()->getRowArray()['id'];
    }

    private function resourceActionId(string $resource, string $action): int
    {
        return (int) $this->db->table('resource_actions ra')
            ->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)
            ->where('ra.code', $action)
            ->get()->getRowArray()['id'];
    }

    private function assertInvalidParticipant(PolicyParticipantValidator $validator, mixed $type, mixed $id): void
    {
        try {
            $validator->validate($type, $id);
            $this->fail('Expected invalid participant.');
        } catch (DomainException $exception) {
            $this->assertSame(PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT, $exception->getErrorCode());
        }
    }
}

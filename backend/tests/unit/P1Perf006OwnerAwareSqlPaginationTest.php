<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\ContactAuthorizationRegistry;
use App\Authorization\EffectiveOrganizationScopeIndex;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\OwnerAwareListBucketCompiler;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlOwnerAwareListSource;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Increment 2C2: differential owner-aware PBAC SQL pagination. */
final class P1Perf006OwnerAwareSqlPaginationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function tearDown(): void
    {
        try {
            if ($this->db !== null && $this->db->tableExists($this->db->prefixTable('resources'), false)) {
                $resources = $this->db->table('resources')->select('id')
                    ->whereIn('type', ['contact', 'organization_contact', 'employee_contact'])->get()->getResultArray();
                $resourceIds = array_map(static fn (array $row): int => (int) $row['id'], $resources);
                if ($resourceIds !== []) {
                    $this->db->table('authorization_logs')->whereIn('resource_id', $resourceIds)->delete();
                    $policyRows = $this->db->table('policies')->select('id')
                        ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
                    $policyIds = array_map(static fn (array $row): int => (int) $row['id'], $policyRows);
                    if ($policyIds !== []) {
                        $this->db->table('policy_actions')->whereIn('policy_id', $policyIds)->delete();
                        $this->db->table('policies')->whereIn('id', $policyIds)->delete();
                    }
                    $actions = $this->db->table('resource_actions')->select('id')
                        ->whereIn('resource_id', $resourceIds)->get()->getResultArray();
                    $ids = array_map(static fn (array $row): int => (int) $row['id'], $actions);
                    if ($ids !== []) {
                        $this->db->table('group_permissions')->whereIn('resource_action_id', $ids)->delete();
                    }
                }
                $this->db->table('auth_permissions_users')->like('permission', 'contact.', 'before')->delete();
                $this->db->table('resources')->where('type', 'contact')->update(['status' => 'ACTIVE']);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testSqlFastPathRejectsSearchUnicodeSortAndNonCanonicalIds(): void
    {
        $adapter = new CommonListQueryAdapter();
        $org = $this->organizationAllowlist();
        $contact = $this->contactAllowlist();
        self::assertTrue(SqlOwnerAwareListSource::supports('organization', $adapter->parse([], $org)));
        self::assertTrue(SqlOwnerAwareListSource::supports('contact', $adapter->parse(['sort' => '-id'], $contact)));
        self::assertFalse(SqlOwnerAwareListSource::supports('contact', $adapter->parse(['q' => 'An'], $contact)));
        self::assertFalse(SqlOwnerAwareListSource::supports('organization', $adapter->parse(['sort' => 'name'], $org)));
        self::assertFalse(SqlOwnerAwareListSource::supports('contact', $adapter->parse(['filter' => ['contact_ref_id' => '01']], $contact)));
        self::assertFalse(SqlOwnerAwareListSource::supports('organization', $adapter->parse(['filter' => ['parent_id' => '-1']], $org)));
        self::assertFalse(SqlOwnerAwareListSource::supports('organization', $adapter->parse(['filter' => ['parent_id' => '99999999999999999999999']], $org)));
        self::assertFalse(SqlOwnerAwareListSource::supports('employee', $adapter->parse([], $org)));
    }

    public function testSqlOrganizationListMatchesStrictStatusAndNullableParentFilters(): void
    {
        $ids = [];
        foreach (['ACTIVE', 'active', 'INACTIVE', 'ACTIVE'] as $i => $status) {
            $ids[] = $this->organization('COMPANY', null, $status);
        }
        $authorized = $this->db->table('organizations')->whereIn('id', [$ids[0], $ids[3]])
            ->orderBy('id')->get()->getResultArray();
        $source = new SqlOwnerAwareListSource($this->db, 'organization', [
            ['id' => $ids[0], 'status' => 'ACTIVE'],
            ['id' => $ids[3], 'status' => 'ACTIVE'],
        ]);
        $adapter = new CommonListQueryAdapter();
        foreach ([
            ['page_size' => '1'],
            ['page' => '2', 'page_size' => '1', 'sort' => '-id'],
            ['filter' => ['status' => ['ACTIVE', 'active']]],
            ['filter' => ['status' => 'active']],
            ['filter' => ['parent_id' => '']],
            ['filter' => ['parent_id' => '03']], // Guarded fallback, not SQL.
        ] as $input) {
            $query = $adapter->parse($input, $this->organizationAllowlist());
            if (! SqlOwnerAwareListSource::supports('organization', $query)) {
                continue;
            }
            $php = new AuthorizedArrayListSource($authorized);
            self::assertSame($php->countAuthorized($query), $source->countAuthorized($query));
            self::assertSame($php->fetchAuthorized($query), $source->fetchAuthorized($query));
        }
    }

    public function testOrganizationBucketsRetainNumericOnlyHexStateCodes(): void
    {
        // Both ACTIVE and DRAFT produce digit-only HEX strings. Without a
        // nonnumeric map-key prefix, PHP converts their keys to integers.
        $states = ['ACTIVE', 'DRAFT', 'INACTIVE'];
        $approved = [];
        $ids = [];
        foreach ($states as $status) {
            $id = $this->organization('COMPANY', null, $status);
            $ids[] = $id;
            $approved[] = ['id' => $id, 'status' => $status];
        }
        $this->organization('COMPANY', null, 'active');

        $authorized = $this->db->table('organizations')
            ->whereIn('id', $ids)->orderBy('id')->get()->getResultArray();
        $sql = new SqlOwnerAwareListSource($this->db, 'organization', $approved);
        $php = new AuthorizedArrayListSource($authorized);
        $adapter = new CommonListQueryAdapter();

        foreach ([
            ['page_size' => '1'],
            ['page' => '2', 'page_size' => '1'],
            ['filter' => ['status' => 'ACTIVE']],
            ['filter' => ['status' => 'DRAFT']],
            ['filter' => ['status' => 'active']],
        ] as $input) {
            $query = $adapter->parse($input, $this->organizationAllowlist());
            self::assertSame($php->countAuthorized($query), $sql->countAuthorized($query), json_encode($input));
            self::assertSame(
                array_map('intval', array_column($php->fetchAuthorized($query), 'id')),
                array_map('intval', array_column($sql->fetchAuthorized($query), 'id')),
                json_encode($input),
            );
        }
    }

    public function testSqlContactListCannotCrossOwnerKindOrNativeState(): void
    {
        $orgId = $this->organization('COMPANY');
        $otherOrg = $this->organization('COMPANY');
        $this->contact('ORGANIZATION', $orgId, 'ACTIVE');
        $this->contact('ORGANIZATION', $orgId, 'active');
        $this->contact('EMPLOYEE', $orgId, 'ACTIVE');
        $this->contact('ORGANIZATION', $otherOrg, 'ACTIVE');
        $this->contact('SUPPLIER', $orgId, 'ACTIVE');
        $this->contact('ORGANIZATION', $orgId, 'INACTIVE');
        $rows = $this->db->table('contacts')->orderBy('id')->get()->getResultArray();
        $approved = array_values(array_filter($rows, static fn (array $row): bool =>
            $row['contact_type'] === 'ORGANIZATION'
            && (int) $row['contact_ref_id'] === $orgId
            && in_array($row['status'], ['ACTIVE', 'INACTIVE'], true)));
        $source = new SqlOwnerAwareListSource($this->db, 'contact', [
            ['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $orgId, 'status' => 'ACTIVE'],
            ['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $orgId, 'status' => 'INACTIVE'],
        ]);
        $adapter = new CommonListQueryAdapter();
        foreach ([
            ['page_size' => '1'],
            ['page' => '2', 'page_size' => '1', 'sort' => '-id'],
            ['filter' => ['contact_type' => ['ORGANIZATION', 'EMPLOYEE']]],
            ['filter' => ['status' => ['ACTIVE', 'active']]],
            ['filter' => ['contact_ref_id' => (string) $otherOrg]],
            ['filter' => ['contact_type' => 'EMPLOYEE']],
        ] as $input) {
            $query = $adapter->parse($input, $this->contactAllowlist());
            $php = new AuthorizedArrayListSource($approved);
            self::assertSame($php->countAuthorized($query), $source->countAuthorized($query), json_encode($input));
            self::assertSame($php->fetchAuthorized($query), $source->fetchAuthorized($query), json_encode($input));
        }
    }

    public function testPbacScopeSignaturesAndSqlBucketsMatchOriginalEngine(): void
    {
        [$actor, $group, $root, $company, $branch, $sibling, $ownerEmployee] = $this->fixture();
        $operation = $this->operation($actor);
        $index = EffectiveOrganizationScopeIndex::build($this->db, $operation, ['organization']);
        self::assertNotNull($index);
        self::assertSame($index->signature($company), $index->signature($branch),
            'Without an intervening Policy boundary, child and parent reuse assignment/Policy inputs.');
        self::assertNotSame($index->signature($company), $index->signature($sibling));

        $this->contact('ORGANIZATION', $company, 'ACTIVE');
        for ($i = 0; $i < 12; $i++) {
            $this->contact('ORGANIZATION', $company, 'ACTIVE');
        }
        $this->contact('ORGANIZATION', $company, 'active');
        $this->contact('ORGANIZATION', $branch, 'ACTIVE');
        $this->contact('ORGANIZATION', $branch, 'INACTIVE');
        $this->contact('ORGANIZATION', $sibling, 'ACTIVE');
        $this->contact('EMPLOYEE', $ownerEmployee, 'ACTIVE');
        $noPrimary = $this->employee(null);
        $this->contact('EMPLOYEE', $noPrimary, 'ACTIVE');
        $ambiguous = $this->employee(null);
        $this->assignment($ambiguous, $branch);
        $this->assignment($ambiguous, $branch);
        $this->contact('EMPLOYEE', $ambiguous, 'ACTIVE');
        $this->contact('SUPPLIER', $company, 'ACTIVE');
        $this->contact('ORGANIZATION', 999999999, 'ACTIVE'); // Missing owner, invisible.

        $active = $this->db->table('workflow_states')->select('id')
            ->where('code', 'ACTIVE')->get()->getRowArray();
        self::assertNotNull($active);
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $branch,
            'resource_id' => $this->resourceId('organization_contact'),
            'state_id' => (int) $active['id'],
            'participant_type' => 'USER', 'participant_ref_id' => $actor,
        ], [[
            'resource_action_id' => $this->actionId('organization_contact', 'view'),
            'effect' => 'ABSOLUTE_DENY',
        ]]);
        $indexAfter = EffectiveOrganizationScopeIndex::build($this->db, $operation, ['organization_contact', 'employee_contact']);
        self::assertNotNull($indexAfter);
        self::assertNotSame($indexAfter->signature($company), $indexAfter->signature($branch),
            'State-scoped ABSOLUTE_DENY creates a Scope boundary.');
        $authorization = new AuthorizationService(db: $this->db);
        $compiler = new OwnerAwareListBucketCompiler($this->db, $authorization);
        $contactBuckets = $compiler->contacts($operation);
        $organizationBuckets = $compiler->organizations($operation);
        self::assertNotNull($contactBuckets);
        self::assertNotNull($organizationBuckets);
        self::assertContains(['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $company, 'status' => 'ACTIVE'], $contactBuckets);
        self::assertContains(['contact_type' => 'EMPLOYEE', 'contact_ref_id' => $ownerEmployee, 'status' => 'ACTIVE'], $contactBuckets);
        self::assertContains(['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $branch, 'status' => 'INACTIVE'], $contactBuckets);
        self::assertNotContains(['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $branch, 'status' => 'ACTIVE'], $contactBuckets);
        self::assertNotContains(['contact_type' => 'EMPLOYEE', 'contact_ref_id' => $noPrimary, 'status' => 'ACTIVE'], $contactBuckets);
        self::assertNotContains(['contact_type' => 'EMPLOYEE', 'contact_ref_id' => $ambiguous, 'status' => 'ACTIVE'], $contactBuckets);

        $this->assertDifferential('organization', $organizationBuckets, $authorization, $operation);
        $this->assertDifferential('contact', $contactBuckets, $authorization, $operation);
    }

    /** @param list<array<string,mixed>> $approved */
    private function assertDifferential(string $resource, array $approved, AuthorizationService $auth, OperationContext $operation): void
    {
        $all = $this->db->table($resource === 'contact' ? 'contacts' : 'organizations')
            ->orderBy('id')->get()->getResultArray();
        $legacy = [];
        foreach ($all as $row) {
            try {
                $type = $resource === 'contact'
                    ? ContactAuthorizationRegistry::resourceFor($row) : $resource;
                if ($auth->authorize($operation, $type, 'view',
                    new EvaluationOrganizationRequest(persistedTarget: $row))->isAllowed()) {
                    $legacy[] = $row;
                }
            } catch (DomainException $exception) {
                if (! in_array($exception->getErrorCode(), [
                    ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED,
                    'EVALUATION_ORGANIZATION_NOT_FOUND',
                    'CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE',
                ], true)) {
                    throw $exception;
                }
            }
        }
        $adapter = new CommonListQueryAdapter();
        $allowlist = $resource === 'contact' ? $this->contactAllowlist() : $this->organizationAllowlist();
        foreach ([
            ['page_size' => '2'],
            ['page' => '2', 'page_size' => '2'],
            ['page_size' => '3', 'sort' => '-id'],
            ['filter' => ['status' => 'ACTIVE']],
        ] as $input) {
            $query = $adapter->parse($input, $allowlist);
            $php = new AuthorizedArrayListSource($legacy);
            $sql = new SqlOwnerAwareListSource($this->db, $resource, $approved);
            self::assertSame($php->countAuthorized($query), $sql->countAuthorized($query), json_encode($input));
            self::assertSame(
                array_map('intval', array_column($php->fetchAuthorized($query), 'id')),
                array_map('intval', array_column($sql->fetchAuthorized($query), 'id')),
                json_encode($input),
            );
        }
    }

    private function fixture(): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $root = $this->organization('GROUP');
        $company = $this->organization('COMPANY', $root);
        $branch = $this->organization('BRANCH', $company);
        $sibling = $this->organization('COMPANY', $root);
        $this->db->table('users')->insert([
            'username' => 'perf006-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $actor = (int) $this->db->insertID();
        $code = strtoupper('PERF006' . uniqid());
        $this->db->table('groups')->insert([
            'code' => $code, 'name' => 'Performance', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $group = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $actor, 'group' => strtolower($code), 'created_at' => '2026-10-05 00:00:00',
        ]);
        foreach (['organization', 'organization_contact', 'employee_contact'] as $resource) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $group, 'resource_action_id' => $this->actionId($resource, 'view'),
                'created_at' => '2026-10-05 00:00:00',
            ]);
        }
        $actorEmployee = $this->employee($actor);
        $ownerEmployee = $this->employee(null);
        $this->assignment($actorEmployee, $company);
        $this->assignment($ownerEmployee, $branch);
        return [$actor, $group, $root, $company, $branch, $sibling, $ownerEmployee];
    }

    private function organization(string $type, ?int $parent = null, string $status = 'ACTIVE'): int
    {
        $this->db->table('organizations')->insert([
            'type' => $type, 'parent_id' => $parent,
            'code' => 'P006-O-' . uniqid(), 'name' => $type,
            'status' => $status, 'created_at' => '2026-10-05 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function contact(string $type, int $ref, string $status): int
    {
        $this->db->table('contacts')->insert([
            'contact_type' => $type, 'contact_ref_id' => $ref,
            'name' => 'PBAC SQL sample', 'status' => $status,
            'created_at' => '2026-10-05 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function employee(?int $userId): int
    {
        $this->db->table('employees')->insert([
            'user_id' => $userId, 'employee_code' => 'P006-E-' . uniqid(),
            'employee_name' => 'Employee', 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function assignment(int $employeeId, int $organizationId): void
    {
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => $organizationId,
            'is_primary' => 1, 'status' => 'ACTIVE', 'effective_from' => '2026-01-01',
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }

    private function resourceId(string $resource): int
    {
        return (int) $this->db->table('resources')->select('id')->where('type', $resource)->get()->getRow('id');
    }

    private function actionId(string $resource, string $action): int
    {
        return (int) $this->db->table('resource_actions a')->select('a.id')
            ->join('resources r', 'r.id = a.resource_id')
            ->where('r.type', $resource)->where('a.code', $action)->get()->getRow('id');
    }

    private function operation(int $actor): OperationContext
    {
        return new OperationContext($actor,
            new EvaluationTime(new DateTimeImmutable('2026-10-11T12:00:00+00:00')),
            'perf-006', false);
    }

    private function organizationAllowlist(): CommonListQueryAllowlist
    {
        return new CommonListQueryAllowlist(['code', 'name'], ['parent_id', 'type', 'status'],
            ['id', 'name']);
    }

    private function contactAllowlist(): CommonListQueryAllowlist
    {
        return new CommonListQueryAllowlist(['name'], ['contact_type', 'contact_ref_id', 'status'],
            ['id', 'name']);
    }
}

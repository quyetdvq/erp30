<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\PriceListAuthorizedBucketCompiler;
use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Modules\Pricing\PriceList\Application\SqlPriceListListSource;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\PolicyRepository;
use App\Services\Api050ProductCatalogService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal Price List SQL pagination: exact Owner/State PBAC differential. */
final class P1Perf008PriceListSqlPaginationTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testFastPathQueryGuardRejectsUnsafeSearchSortAndOwnerFilters(): void
    {
        $adapter = new CommonListQueryAdapter();
        $allow = $this->allowlist();
        self::assertTrue(SqlPriceListListSource::supports($adapter->parse([], $allow)));
        self::assertTrue(SqlPriceListListSource::supports($adapter->parse(['sort' => 'priority'], $allow)));
        self::assertTrue(SqlPriceListListSource::supports($adapter->parse(['sort' => '-priority'], $allow)));
        self::assertTrue(SqlPriceListListSource::supports($adapter->parse(['sort' => '-id'], $allow)));
        self::assertTrue(SqlPriceListListSource::supports($adapter->parse([
            'sort' => 'priority', 'filter' => ['organization_id' => ['2', '3'], 'status' => 'ACTIVE'],
        ], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['q' => 'sua'], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['sort' => 'name'], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['sort' => 'priority,-name'], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['filter' => ['code' => 'A']], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['filter' => ['organization_id' => '01']], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse(['filter' => ['organization_id' => '-1']], $allow)));
        self::assertFalse(SqlPriceListListSource::supports($adapter->parse([
            'filter' => ['organization_id' => '999999999999999999999'],
        ], $allow)));
    }

    public function testSqlCountPageAndExactStatusFilterMatchLegacySource(): void
    {
        $root = $this->root();
        $owner = $this->organization('COMPANY', $root);
        $other = $this->organization('COMPANY', $root);
        $this->priceList($owner, 'ACTIVE', 0);
        $this->priceList($owner, 'active', 1); // Case-insensitive DB collation must not mix this row.
        $this->priceList($owner, 'INACTIVE', 10);
        $this->priceList($other, 'ACTIVE', 3);
        $this->priceList($owner, 'ACTIVE', 2);
        $this->priceList($owner, 'ACTIVE', 2);
        $all = $this->db->table('price_lists')->get()->getResultArray();
        $visible = array_values(array_filter($all, static fn (array $row): bool =>
            (int) $row['organization_id'] === $owner
            && in_array($row['status'], ['ACTIVE', 'INACTIVE'], true)));
        $approved = [
            ['organization_id' => $owner, 'status' => 'ACTIVE'],
            ['organization_id' => $owner, 'status' => 'INACTIVE'],
        ];
        $sql = new SqlPriceListListSource($this->db, $approved);
        $php = new AuthorizedArrayListSource($visible);
        $adapter = new CommonListQueryAdapter();
        foreach ([
            ['sort' => 'id', 'page_size' => '2'],
            ['sort' => '-id', 'page_size' => '2', 'page' => '2'],
            ['sort' => 'priority', 'page_size' => '2'],
            ['sort' => '-priority', 'page_size' => '2', 'page' => '2'],
            ['sort' => 'priority', 'filter' => ['status' => ['ACTIVE', 'active']]],
            ['sort' => 'id', 'filter' => ['organization_id' => (string) $other]],
            ['sort' => 'id', 'filter' => ['organization_id' => (string) $owner, 'status' => 'INACTIVE']],
        ] as $input) {
            $query = $adapter->parse($input, $this->allowlist());
            self::assertTrue($sql->canServe($query), json_encode($input));
            self::assertSame($php->countAuthorized($query), $sql->countAuthorized($query), json_encode($input));
            self::assertSame($php->fetchAuthorized($query), $sql->fetchAuthorized($query), json_encode($input));
        }
        $none = new SqlPriceListListSource($this->db, []);
        $query = $adapter->parse([], $this->allowlist());
        self::assertSame(0, $none->countAuthorized($query));
        self::assertSame([], $none->fetchAuthorized($query));
    }

    public function testNegativePriorityRequiresLegacyComparatorButIdSortStillWorks(): void
    {
        $owner = $this->organization('COMPANY', $this->root());
        $this->priceList($owner, 'ACTIVE', -7);
        $this->priceList($owner, 'ACTIVE', 3);
        $sql = new SqlPriceListListSource($this->db, [['organization_id' => $owner, 'status' => 'ACTIVE']]);
        $adapter = new CommonListQueryAdapter();
        $priority = $adapter->parse(['sort' => 'priority'], $this->allowlist());
        self::assertTrue(SqlPriceListListSource::supports($priority));
        self::assertFalse($sql->canServe($priority), 'A signed priority changes the legacy PHP comparator ordering.');
        self::assertTrue($sql->canServe($adapter->parse(['sort' => 'id'], $this->allowlist())));
    }

    public function testStateScopedAbsoluteDenyAndOwnerScopeMatchLegacyEngine(): void
    {
        (new ResourceActionSeeder(new Config\Database(), $this->db))->run();
        $root = $this->root();
        $company = $this->organization('COMPANY', $root);
        $sibling = $this->organization('COMPANY', $root);
        $actor = $this->actorAt($company);
        $operation = $this->operation($actor);
        $firstActive = $this->priceList($company, 'ACTIVE', 1);
        $inactive = $this->priceList($company, 'INACTIVE', 5);
        $this->priceList($company, 'ACTIVE', 8);
        $this->priceList($sibling, 'ACTIVE', 2);
        $this->priceList($root, 'ACTIVE', 2);
        $auth = new AuthorizationService(db: $this->db);
        $compiler = new PriceListAuthorizedBucketCompiler($this->db, $auth);
        $before = $compiler->compile($operation);
        self::assertNotNull($before);
        self::assertContains(['organization_id' => $company, 'status' => 'ACTIVE'], $before);
        self::assertContains(['organization_id' => $company, 'status' => 'INACTIVE'], $before);
        self::assertNotContains(['organization_id' => $sibling, 'status' => 'ACTIVE'], $before);
        self::assertNotContains(['organization_id' => $root, 'status' => 'ACTIVE'], $before);

        $resource = $this->db->table('resources')->where('type', 'price_list')->get()->getRowArray();
        $activeState = $this->db->table('workflow_states')->where('code', 'ACTIVE')->get()->getRowArray();
        self::assertNotNull($resource);
        self::assertNotNull($activeState);
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $company,
            'resource_id' => (int) $resource['id'],
            'state_id' => (int) $activeState['id'],
            'participant_type' => 'USER', 'participant_ref_id' => $actor,
        ], [[
            'resource_action_id' => $this->actionId('price_list', 'view'),
            'effect' => 'ABSOLUTE_DENY',
        ]]);

        $auth = new AuthorizationService(db: $this->db);
        $approved = (new PriceListAuthorizedBucketCompiler($this->db, $auth))->compile($operation);
        self::assertSame([['organization_id' => $company, 'status' => 'INACTIVE']], $approved);
        $legacy = [];
        foreach ($this->db->table('price_lists')->get()->getResultArray() as $row) {
            if ($auth->authorize($operation, 'price_list', 'view',
                new EvaluationOrganizationRequest(persistedTarget: $row))->isAllowed()) {
                $legacy[] = $row;
            }
        }
        self::assertSame([$inactive], array_map('intval', array_column($legacy, 'id')));
        $adapter = new CommonListQueryAdapter();
        $sql = new SqlPriceListListSource($this->db, $approved);
        $php = new AuthorizedArrayListSource($legacy);
        foreach ([
            ['sort' => 'priority', 'page_size' => '1'],
            ['sort' => '-id', 'page_size' => '1'],
            ['sort' => 'id', 'filter' => ['status' => 'ACTIVE']],
            ['sort' => 'id', 'filter' => ['organization_id' => (string) $company]],
        ] as $input) {
            $query = $adapter->parse($input, $this->allowlist());
            self::assertSame($php->countAuthorized($query), $sql->countAuthorized($query), json_encode($input));
            self::assertSame($php->fetchAuthorized($query), $sql->fetchAuthorized($query), json_encode($input));
        }

        $service = new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $actor, 'perf008-price', false),
        );
        $page = $service->listPriceLists(['sort' => 'priority', 'page_size' => '1']);
        self::assertSame('1', (string) $page['total']);
        self::assertSame([$inactive], array_map('intval', array_column($page['items'], 'id')));
        self::assertNotContains($firstActive, array_map('intval', array_column($page['items'], 'id')));
        // Revoking Base Permission must clear all authorized SQL buckets.
        $this->db->table('group_permissions')->where('resource_action_id',
            $this->actionId('price_list', 'view'))->delete();
        self::assertSame([], (new PriceListAuthorizedBucketCompiler(
            $this->db, new AuthorizationService(db: $this->db),
        ))->compile($operation));
        // A customized Resource Domain may never reuse ORGANIZATION buckets.
        $this->db->table('resources')->where('id', (int) $resource['id'])
            ->update(['domain' => 'MASTER']);
        self::assertNull((new PriceListAuthorizedBucketCompiler(
            $this->db, new AuthorizationService(db: $this->db),
        ))->compile($operation));
    }

    public function testApiUsesLegacyOrderingWhenAnyPriorityIsNegative(): void
    {
        (new ResourceActionSeeder(new Config\Database(), $this->db))->run();
        $owner = $this->organization('COMPANY', $this->root());
        $actor = $this->actorAt($owner);
        $this->priceList($owner, 'ACTIVE', -2);
        $this->priceList($owner, 'ACTIVE', 0);
        $this->priceList($owner, 'ACTIVE', 7);
        $this->priceList($owner, 'ACTIVE', -8);

        $source = new AuthorizedArrayListSource(
            $this->db->table('price_lists')->get()->getResultArray(),
        );
        $query = (new CommonListQueryAdapter())->parse(
            ['sort' => 'priority', 'page_size' => '2', 'page' => '1'], $this->allowlist(),
        );
        $expected = array_map('intval', array_column($source->fetchAuthorized($query), 'id'));
        $service = new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $actor, 'perf008-fallback', false),
        );
        $page = $service->listPriceLists(['sort' => 'priority', 'page_size' => '2', 'page' => '1']);
        self::assertSame('4', (string) $page['total']);
        self::assertSame($expected, array_map('intval', array_column($page['items'], 'id')));
    }

    private function allowlist(): CommonListQueryAllowlist
    {
        return new CommonListQueryAllowlist(
            search: ['code', 'name'],
            filters: ['code', 'name', 'organization_id', 'status'],
            sort: ['id', 'code', 'effective_from', 'name', 'organization_id', 'priority', 'status'],
        );
    }

    private function root(): int
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')
            ->where('parent_id', null)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('organizations')->insert([
            'code' => 'PERF008-G-' . uniqid(), 'name' => 'Price root', 'type' => 'GROUP',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function organization(string $type, int $parent): int
    {
        $this->db->table('organizations')->insert([
            'code' => 'PERF008-O-' . uniqid(), 'name' => $type, 'type' => $type,
            'parent_id' => $parent, 'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function priceList(int $owner, string $status, int $priority): int
    {
        $this->db->table('price_lists')->insert([
            'code' => 'PERF008-P-' . uniqid(), 'name' => 'Price list ' . $priority,
            'organization_id' => $owner, 'status' => $status,
            'effective_from' => '2026-01-01 00:00:00',
            'priority' => $priority, 'created_at' => '2026-10-11 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function actorAt(int $organizationId): int
    {
        $this->db->table('users')->insert([
            'username' => 'perf008-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        EligibleActorFixture::assignAtOrganization($this->db, $userId, $organizationId);
        $code = 'PERF008-A-' . substr(uniqid(), -8);
        $this->db->table('groups')->insert([
            'code' => $code, 'name' => 'Price List readers', 'status' => 'ACTIVE',
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $group = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($code),
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $this->db->table('group_permissions')->insert([
            'group_id' => $group, 'resource_action_id' => $this->actionId('price_list', 'view'),
            'created_at' => '2026-10-11 00:00:00',
        ]);
        return $userId;
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions a')->select('a.id')
            ->join('resources r', 'r.id = a.resource_id')
            ->where('r.type', $resource)->where('a.code', $action)->get()->getRowArray();
        self::assertNotNull($row);
        return (int) $row['id'];
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId,
            new EvaluationTime(new DateTimeImmutable('2026-10-11T12:00:00+00:00')),
            'perf008', false);
    }
}

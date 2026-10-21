<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQuery;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlProductListSource;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Product\Product\ProductRepository;
use App\Repositories\PolicyRepository;
use App\Modules\Product\Product\ProductRoleService;
use App\Services\Api050ProductCatalogService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal Product SQL pagination must preserve the shared array source result. */
final class P1Api050ProductSqlPaginationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testPageCountLastPageDescendingOrderAndCrossPageProductRoles(): void
    {
        $this->insertProducts(242);
        $parent = $this->insertProduct('SQL050-PARENT', 'STANDARD', 'ACTIVE', null, 'Parent');
        $child = $this->insertProduct('SQL050-CHILD', 'STANDARD', 'ACTIVE', $parent, null, 'Blue');
        $this->insertProduct('SQL050-COMBO', 'COMBO', 'ACTIVE', null, 'Bundle');

        foreach ([
            ['page' => '1', 'page_size' => '200'],
            ['page' => '2', 'page_size' => '200'],
            ['page' => '5', 'page_size' => '200'],
            ['page' => '1', 'page_size' => '1'],
            ['page' => '244', 'page_size' => '1'],
            ['page' => '1', 'page_size' => '50', 'sort' => '-id'],
            ['page' => '5', 'page_size' => '50', 'sort' => '-id'],
        ] as $input) {
            $this->assertSqlMatchesLegacy($input);
        }

        $repository = new ProductRepository($this->db);
        $source = new SqlProductListSource($repository, new ProductRoleService(db: $this->db));
        // Auto-increment IDs can have gaps; page numbers follow sorted row
        // positions rather than assuming ID === ordinal position.
        $orderedIds = array_map(static fn (array $row): int => (int) $row['id'], $repository->listAll());
        sort($orderedIds, SORT_NUMERIC);
        $childOrdinal = array_search($child, $orderedIds, true);
        $parentOrdinal = array_search($parent, $orderedIds, true);
        $this->assertIsInt($childOrdinal);
        $this->assertIsInt($parentOrdinal);
        $childQuery = $this->query(['page' => (string) ($childOrdinal + 1), 'page_size' => '1']);
        $childRows = iterator_to_array($source->fetchAuthorized($childQuery));
        $this->assertCount(1, $childRows);
        $this->assertSame($child, (int) $childRows[0]['id']);
        $this->assertSame('CHILD', $childRows[0]['role']);
        $this->assertSame('Parent - Blue', $childRows[0]['full_name']);

        $parentQuery = $this->query(['page' => (string) ($parentOrdinal + 1), 'page_size' => '1']);
        $parentRows = iterator_to_array($source->fetchAuthorized($parentQuery));
        $this->assertSame('PARENT', $parentRows[0]['role']);
        $this->assertFalse($parentRows[0]['selectable']);
    }

    public function testPagedRolesRejectNestedChildOutsideSelectedPage(): void
    {
        $parent = $this->insertProduct('SQL050-BAD-ROOT', 'STANDARD', 'ACTIVE', null, 'Root');
        $child = $this->insertProduct('SQL050-BAD-CHILD', 'STANDARD', 'ACTIVE', $parent, null, 'Child');
        $this->insertProduct('SQL050-BAD-GRANDCHILD', 'STANDARD', 'ACTIVE', $child, null, 'Bad');

        $repo = new ProductRepository($this->db);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $repo->listAll());
        sort($ids, SORT_NUMERIC);
        $ordinal = array_search($parent, $ids, true);
        $this->assertIsInt($ordinal);
        $source = new SqlProductListSource($repo, new ProductRoleService(db: $this->db));
        try {
            $source->fetchAuthorized($this->query(['page' => (string) ($ordinal + 1), 'page_size' => '1']));
            $this->fail('A root with a nested child must be rejected even if the grandchild is outside the page.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductRoleService::ERROR_INVALID_ROLE, $exception->getErrorCode());
        }
    }

    public function testInvalidProductOutsideSelectedPageAndFilterFailsClosed(): void
    {
        $valid = $this->insertProduct('SQL050-VALID-EARLY', 'STANDARD', 'ACTIVE', null, 'Visible');
        $root = $this->insertProduct('SQL050-INVALID-ROOT', 'STANDARD', 'INACTIVE', null, 'Root');
        $child = $this->insertProduct('SQL050-INVALID-CHILD', 'STANDARD', 'INACTIVE', $root, null, 'Child');
        $this->insertProduct('SQL050-INVALID-GRANDCHILD', 'STANDARD', 'INACTIVE', $child, null, 'Nested');

        $repo = new ProductRepository($this->db);
        foreach ([
            ['page_size' => '1', 'sort' => 'id', 'filter' => ['status' => 'ACTIVE']],
            ['page_size' => '1', 'sort' => 'product_code', 'filter' => ['status' => 'ACTIVE']],
        ] as $input) {
            $source = new SqlProductListSource($repo, new ProductRoleService(db: $this->db));
            $query = $this->query($input);
            try {
                $source->countAuthorized($query);
                $this->fail('A corrupt Product outside both the page and filter must reject the entire list.');
            } catch (DomainException $exception) {
                $this->assertSame(ProductRoleService::ERROR_INVALID_ROLE, $exception->getErrorCode());
            }
        }
        $this->assertNotNull($repo->findById($valid));
    }

    public function testInvalidNameOutsidePageFailsClosedAndValidCatalogStillPaginates(): void
    {
        $this->insertProduct('SQL050-NAME-VALID', 'STANDARD', 'ACTIVE', null, 'Visible');
        $badId = $this->insertProduct('SQL050-NAME-BAD', 'STANDARD', 'INACTIVE', null, " \t ");
        $repo = new ProductRepository($this->db);
        $query = $this->query(['page_size' => '1', 'filter' => ['status' => 'ACTIVE']]);

        try {
            $repo->withConsistentListSnapshot(static fn (): int =>
                (new SqlProductListSource($repo, new ProductRoleService(repository: $repo)))->countAuthorized($query));
            $this->fail('A Product with a blank name outside the page must fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductRoleService::ERROR_INVALID_ROLE, $exception->getErrorCode());
        }
        $this->assertTrue($this->db->table('products')->where('id', $badId)->update(['product_name' => 'Repaired']));
        $source = new SqlProductListSource($repo, new ProductRoleService(db: $this->db));
        $result = $repo->withConsistentListSnapshot(static function () use ($source, $query): array {
            $total = $source->countAuthorized($query);
            return [$total, array_values(iterator_to_array($source->fetchAuthorized($query)))];
        });
        $this->assertSame(1, $result[0]);
        $this->assertCount(1, $result[1]);
    }

    public function testRepeatableReadSnapshotExcludesInsertBetweenCountAndSqlPage(): void
    {
        $originalId = $this->insertProduct('SQL050-SNAP-OLD', 'STANDARD', 'ACTIVE', null, 'Original');
        $beforeCount = (int) $this->db->table('products')->countAllResults();
        $repo = new ProductRepository($this->db);
        $other = \Config\Database::connect('tests', false);
        $this->assertNotSame($this->db, $other, 'The concurrent writer must use a separate database connection.');
        $inserted = false;
        $listener = static function ($statement) use (&$inserted, $other): void {
            $sql = (string) $statement;
            if ($inserted || stripos($sql, 'products') === false || preg_match('/COUNT\s*\(/i', $sql) !== 1) {
                return;
            }
            $inserted = true;
            if (! $other->table('products')->insert([
                'code' => 'SQL050-SNAP-NEW', 'product_name' => 'Concurrent',
                'product_type' => 'STANDARD', 'status' => 'ACTIVE',
                'created_at' => '2026-10-06 00:00:00',
            ])) {
                throw new \RuntimeException('Concurrent Product insert failed.');
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $response = $repo->withConsistentListSnapshot(function () use ($repo): array {
                $adapter = new CommonListQueryAdapter();
                $result = $adapter->paginate(
                    ['sort' => '-id', 'page_size' => '1'],
                    $this->productAllowlist(),
                    new SqlProductListSource($repo, new ProductRoleService(db: $this->db)),
                );
                return ['total' => $result->total, 'rows' => array_values(iterator_to_array($result->data))];
            });
        } finally {
            Events::removeListener('DBQuery', $listener);
            $other->close();
        }
        $this->assertTrue($inserted, 'Insert should occur between COUNT and page SQL.');
        $this->assertSame((string) $beforeCount, $response['total']);
        $this->assertCount(1, $response['rows']);
        $this->assertSame($originalId, (int) $response['rows'][0]['id']);
        $this->assertSame($beforeCount + 1, (int) $this->db->table('products')->countAllResults());
    }

    public function testRepeatableReadSnapshotKeepsProjectedKeysAndFullRowConsistent(): void
    {
        $id = $this->insertProduct('SQL050-SNAP-PROJECT', 'STANDARD', 'ACTIVE', null, 'Before');
        $repo = new ProductRepository($this->db);
        $other = \Config\Database::connect('tests', false);
        $updated = false;
        $listener = static function ($statement) use (&$updated, $other, $id): void {
            $sql = (string) $statement;
            if ($updated || stripos($sql, 'products') === false
                || stripos($sql, 'ORDER BY') === false || stripos($sql, 'code') === false
                || stripos($sql, 'SELECT') === false || stripos($sql, 'product_name') === false) {
                return;
            }
            $updated = true;
            if (! $other->table('products')->where('id', $id)->update(['product_name' => 'After'])) {
                throw new \RuntimeException('Concurrent Product update failed.');
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $response = $repo->withConsistentListSnapshot(function () use ($repo): array {
                $adapter = new CommonListQueryAdapter();
                $result = $adapter->paginate(
                    ['sort' => 'product_code', 'page_size' => '1'],
                    $this->productAllowlist(),
                    new SqlProductListSource($repo, new ProductRoleService(db: $this->db)),
                );
                return ['total' => $result->total, 'rows' => array_values(iterator_to_array($result->data))];
            });
        } finally {
            Events::removeListener('DBQuery', $listener);
            $other->close();
        }
        $this->assertTrue($updated, 'Concurrent update should occur after key projection, before the full-row read.');
        $this->assertSame('1', $response['total']);
        $this->assertSame('Before', $response['rows'][0]['product_name']);
        $this->assertSame('After', (new ProductRepository($this->db))->findById($id)['product_name']);
    }

    public function testReadCommittedCannotSilentlyProduceMixedListSnapshot(): void
    {
        $connection = \Config\Database::connect('tests', false);
        try {
            $this->assertTrue($connection->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'));
            $repository = new ProductRepository($connection);
            // The list must use REPEATABLE READ even if the session default
            // is READ COMMITTED, and must not change that session default.
            $this->assertSame(42, $repository->withConsistentListSnapshot(static fn (): int => 42));
            $this->assertSame(0, $connection->transDepth);
            $isolation = $connection->query('SELECT @@tx_isolation AS isolation_level')->getRowArray();
            $this->assertSame(
                'READ-COMMITTED',
                strtoupper(str_replace('_', '-', (string) $isolation['isolation_level'])),
            );
            $this->assertNotFalse($connection->table('products')->countAllResults());
        } finally {
            $connection->close();
        }
    }

    public function testNestedTransactionIsRejectedWithoutChangingOuterTransaction(): void
    {
        $connection = \Config\Database::connect('tests', false);
        try {
            $this->assertTrue($connection->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'));
            $this->assertTrue($connection->transBegin());
            try {
                $repository = new ProductRepository($connection);
                try {
                    $repository->withConsistentListSnapshot(static fn (): int => 42);
                    $this->fail('An existing transaction cannot be upgraded to a fresh list snapshot.');
                } catch (PersistenceException $exception) {
                    $this->assertSame(ProductRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
                    $this->assertSame('nestedProductListSnapshot', $exception->getContext()['operation']);
                }
                $this->assertSame(1, $connection->transDepth, 'List rejection must not end the enclosing transaction.');
                $this->assertNotFalse($connection->table('products')->countAllResults());
            } finally {
                $this->assertTrue($connection->transRollback());
            }
        } finally {
            $connection->close();
        }
    }

    public function testSqlFiltersPreserveExactStatusNullFkRepeatedValuesAndPageTotals(): void
    {
        $parent = $this->insertProduct('SQL050-FPARENT', 'STANDARD', 'ACTIVE', null, 'Parent');
        $this->insertProduct('SQL050-FCHILD', 'STANDARD', 'ACTIVE', $parent, null, 'Red');
        $this->insertProduct('SQL050-FINACTIVE', 'STANDARD', 'INACTIVE', null, 'Inactive');
        $this->insertProduct('SQL050-FLOWER', 'STANDARD', 'active', null, 'Lowercase');
        foreach ([
            ['page_size' => '1', 'filter' => ['parent_product_id' => '']],
            ['page_size' => '1', 'page' => '2', 'filter' => ['parent_product_id' => ['', (string) $parent]]],
            ['filter' => ['parent_product_id' => (string) $parent]],
            ['filter' => ['status' => ['ACTIVE', 'INACTIVE']]],
            ['filter' => ['status' => 'active']],
            ['filter' => ['status' => 'active', 'product_type' => 'STANDARD']],
            ['filter' => ['base_uom_id' => '']],
            ['filter' => ['product_group_id' => '0']],
        ] as $input) {
            $this->assertSqlMatchesLegacy($input);
        }
    }

    public function testSqlFetchDoesNotQueryEntireCatalogForAnOrdinaryPage(): void
    {
        $this->insertProducts(1200);
        $source = new SqlProductListSource(new ProductRepository($this->db), new ProductRoleService(db: $this->db));
        $query = $this->query(['page' => '2', 'page_size' => '50']);
        $queries = [];
        $listener = static function ($statement) use (&$queries): void {
            if (str_contains(strtolower((string) $statement), 'products')) {
                $queries[] = (string) $statement;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $this->assertSame(1200, $source->countAuthorized($query));
            $this->assertCount(50, iterator_to_array($source->fetchAuthorized($query)));
        } finally {
            Events::removeListener('DBQuery', $listener);
        }
        $this->assertGreaterThanOrEqual(3, count($queries));
        $this->assertLessThanOrEqual(4, count($queries), 'Count, page and one relationship query should suffice for root-only records.');
        $this->assertTrue(
            count(array_filter($queries, static fn (string $statement): bool => stripos($statement, 'LIMIT 50') !== false)) >= 1,
            'The SQL page must use LIMIT/OFFSET, not fetch the full Product table.',
        );
    }

    public function testProjectedKeysPreservePhpUnicodeSearchStringSortAndNumericAwareCodes(): void
    {
        $this->insertProducts(215);
        $this->insertProduct('2', 'STANDARD', 'ACTIVE', null, 'Mau xanh');
        $this->insertProduct('10', 'STANDARD', 'ACTIVE', null, 'Mau do');
        $this->insertProduct('01', 'STANDARD', 'ACTIVE', null, 'Ao so mi');
        $this->insertProduct('2A', 'STANDARD', 'ACTIVE', null, 'AO HOA');
        $this->insertProduct('SQL050-UNICODE', 'STANDARD', 'ACTIVE', null, 'Áo nữ');
        $this->insertProduct('SQL050-UNDERSCORE', 'STANDARD', 'ACTIVE', null, 'Code_100%');
        foreach ([
            ['sort' => 'product_code', 'page_size' => '200'],
            ['sort' => 'product_code', 'page' => '2', 'page_size' => '25'],
            ['sort' => '-product_name', 'page_size' => '50'],
            ['sort' => 'product_type,status', 'page_size' => '100'],
            ['q' => 'ÁO', 'sort' => 'product_code'],
            ['q' => '_', 'sort' => 'product_code'],
            ['q' => '%', 'sort' => 'product_name'],
            ['filter' => ['status' => 'ACTIVE'], 'sort' => 'product_code', 'page_size' => '20'],
            ['filter' => ['product_type' => ['STANDARD', 'COMBO']], 'q' => 'SQL050', 'sort' => '-product_code'],
        ] as $input) {
            $this->assertProjectedMatchesLegacy($input);
        }
    }

    public function testUnsupportedCollationAndNoncanonicalNumericQueriesStayOnLegacyPath(): void
    {
        $repository = new ProductRepository($this->db);
        foreach ([
            ['q' => 'Pham'],
            ['q' => '%_'],
            ['sort' => 'product_code'],
            ['sort' => '-product_name'],
            ['sort' => 'status,-id'],
            ['filter' => ['parent_product_id' => '01']],
            ['filter' => ['product_group_id' => '-1']],
            ['filter' => ['base_uom_id' => '1.0']],
        ] as $input) {
            $this->assertFalse($repository->supportsSqlList($this->query($input)), json_encode($input));
        }
        foreach ([[], ['sort' => '-id'], ['filter' => ['status' => 'ACTIVE']]] as $input) {
            $this->assertTrue($repository->supportsSqlList($this->query($input)), json_encode($input));
        }
        foreach ([['q' => 'Pham'], ['sort' => 'product_code'], ['q' => '_', 'sort' => '-product_name']] as $input) {
            $this->assertTrue($repository->supportsSqlProjection($this->query($input)), json_encode($input));
        }
        $this->assertFalse($repository->supportsSqlProjection($this->query(['filter' => ['parent_product_id' => '01']])));
    }

    public function testServiceRequiresEmployeeAssignmentBeforeSqlPagination(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'SQL050-G-' . uniqid(), 'name' => 'Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $rootId = (int) $this->db->insertID();
        $this->db->table('organizations')->insert([
            'parent_id' => $rootId, 'type' => 'COMPANY',
            'code' => 'SQL050-C-' . uniqid(), 'name' => 'Company',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'username' => 'sql050-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $code = 'SQL050-AUTH-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $code, 'name' => 'Product viewer', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($code),
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $action = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', 'product')->where('ra.code', 'view')->get()->getRowArray();
        $this->assertNotNull($action);
        $this->db->table('group_permissions')->insert([
            'group_id' => $groupId, 'resource_action_id' => (int) $action['id'],
            'created_at' => '2026-10-06 00:00:00',
        ]);
        $this->insertProduct('SQL050-AUTH-PRODUCT', 'STANDARD', 'ACTIVE', null, 'Visible');
        $service = new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $userId, 'sql050-list', false),
        );

        try {
            $service->listProducts(['page_size' => '1']);
            $this->fail('Missing Employee and Assignment must deny Product List.');
        } catch (DomainException $exception) {
            $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $exception->getErrorCode());
        }
        EligibleActorFixture::assignAtOrganization($this->db, $userId, $organizationId);
        $response = $service->listProducts(['page_size' => '1', 'filter' => ['status' => 'ACTIVE']]);
        $this->assertSame(1, $response['page']);
        $this->assertSame(1, $response['page_size']);
        $this->assertGreaterThanOrEqual(1, (int) $response['total']);
        $this->assertCount(1, $response['items']);
        $this->assertArrayHasKey('product_code', $response['items'][0]);
        $this->assertArrayNotHasKey('code', $response['items'][0]);

        // Turn on row-state PBAC after the service already exists; the next
        // list must compile the new ABSOLUTE_DENY rather than reuse a stale
        // global MASTER gate or paginate before rejecting inactive rows.
        $this->insertProduct('SQL050-AUTH-INACTIVE', 'STANDARD', 'INACTIVE', null, 'Hidden');
        $state = $this->db->table('workflow_states')->select('id')->where('code', 'INACTIVE')->get()->getRowArray();
        $resource = $this->db->table('resources')->select('id')->where('type', 'product')->get()->getRowArray();
        $this->assertNotNull($state);
        $this->assertNotNull($resource);
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $rootId,
            'resource_id' => (int) $resource['id'],
            'state_id' => (int) $state['id'],
            'participant_type' => 'USER', 'participant_ref_id' => $userId,
        ], [['resource_action_id' => (int) $action['id'], 'effect' => 'ABSOLUTE_DENY']]);
        $filtered = $service->listProducts(['page_size' => '1']);
        $this->assertSame('1', (string) $filtered['total']);
        $this->assertCount(1, $filtered['items']);
        $this->assertSame('ACTIVE', $filtered['items'][0]['status']);
    }

    public function testStateBucketPredicateMatchesLegacyBeforeCountAndPagination(): void
    {
        $this->insertProducts(87);
        $repository = new ProductRepository($this->db);
        $roles = new ProductRoleService(repository: $repository, db: $this->db);
        $legacyAll = array_map(static function (array $row): array {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            return $row;
        }, $roles->describeList($repository->listAll()));
        $authorizedRows = array_values(array_filter($legacyAll, static fn (array $row): bool => $row['status'] === 'ACTIVE'));
        foreach ([
            ['page_size' => '7'],
            ['page_size' => '7', 'page' => '3', 'sort' => '-id'],
            ['page_size' => '5', 'q' => 'product 2', 'sort' => 'product_code'],
            ['filter' => ['status' => ['ACTIVE', 'INACTIVE']]],
            ['filter' => ['product_type' => 'STANDARD'], 'sort' => '-product_name'],
        ] as $input) {
            $query = $this->query($input);
            $legacy = new AuthorizedArrayListSource($authorizedRows);
            $sql = new SqlProductListSource($repository, $roles, ['ACTIVE']);
            $this->assertSame($legacy->countAuthorized($query), $sql->countAuthorized($query), json_encode($input));
            $actual = array_map(static function (array $row): array {
                $row['product_code'] = $row['code'];
                unset($row['code']);
                return $row;
            }, iterator_to_array($sql->fetchAuthorized($query)));
            $this->assertSame($legacy->fetchAuthorized($query), $actual, json_encode($input));
        }
    }

    public function testZeroAllowedStateStillChecksGlobalProductIntegrity(): void
    {
        $id = $this->insertProduct('SQL050-PBAC-INVALID', 'STANDARD', 'ACTIVE', null, '  ');
        $repository = new ProductRepository($this->db);
        $sql = new SqlProductListSource($repository, new ProductRoleService(repository: $repository, db: $this->db), []);
        try {
            $sql->countAuthorized($this->query(['page_size' => '1']));
            $this->fail('A denied Product must not bypass global catalog consistency.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductRoleService::ERROR_INVALID_ROLE, $exception->getErrorCode());
        }
        $this->assertNotNull($repository->findById($id));
    }

    /** @param array<string,mixed> $input */
    private function assertProjectedMatchesLegacy(array $input): void
    {
        $query = $this->query($input);
        $repo = new ProductRepository($this->db);
        $role = new ProductRoleService(db: $this->db);
        $this->assertTrue($repo->supportsSqlProjection($query));
        $this->assertFalse($repo->supportsSqlList($query));
        $legacyRows = array_map(static function (array $row): array {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            return $row;
        }, $role->describeList($repo->listAll()));
        $legacy = new AuthorizedArrayListSource($legacyRows);
        $projected = new SqlProductListSource($repo, $role);
        $this->assertSame($legacy->countAuthorized($query), $projected->countAuthorized($query));
        $projectedRows = array_map(static function (array $row): array {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            return $row;
        }, iterator_to_array($projected->fetchAuthorized($query)));
        $this->assertSame($legacy->fetchAuthorized($query), $projectedRows, json_encode($input));
    }

    /** @param array<string,mixed> $input */
    private function assertSqlMatchesLegacy(array $input): void
    {
        $query = $this->query($input);
        $repo = new ProductRepository($this->db);
        $role = new ProductRoleService(db: $this->db);
        $this->assertTrue($repo->supportsSqlList($query));
        $legacyRows = array_map(static function (array $row): array {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            return $row;
        }, $role->describeList($repo->listAll()));
        $legacy = new AuthorizedArrayListSource($legacyRows);
        $sql = new SqlProductListSource($repo, $role);
        $this->assertSame($legacy->countAuthorized($query), $sql->countAuthorized($query));
        $sqlRows = array_map(static function (array $row): array {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            return $row;
        }, iterator_to_array($sql->fetchAuthorized($query)));
        $this->assertSame($legacy->fetchAuthorized($query), $sqlRows, json_encode($input));
    }

    /** @param array<string,mixed> $input */
    private function query(array $input): CommonListQuery
    {
        return (new CommonListQueryAdapter())->parse($input, $this->productAllowlist());
    }

    private function productAllowlist(): CommonListQueryAllowlist
    {
        return new CommonListQueryAllowlist(
            search: ['product_code', 'sku', 'barcode', 'product_name', 'product_child_name'],
            filters: ['base_uom_id', 'parent_product_id', 'product_group_id', 'product_type', 'status'],
            sort: ['id', 'product_code', 'product_name', 'product_type', 'status'],
        );
    }

    private function insertProducts(int $count): void
    {
        foreach (array_chunk(range(1, $count), 100) as $numbers) {
            $rows = [];
            foreach ($numbers as $number) {
                $rows[] = [
                    'code' => sprintf('SQL050-BULK-%05d', $number),
                    'product_name' => 'Product ' . $number,
                    'product_type' => 'STANDARD', 'status' => $number % 2 === 0 ? 'ACTIVE' : 'INACTIVE',
                    'created_at' => '2026-10-06 00:00:00',
                ];
            }
            $this->assertGreaterThan(0, $this->db->table('products')->insertBatch($rows));
        }
    }

    private function insertProduct(string $code, string $type, string $status, ?int $parent, ?string $name, ?string $childName = null): int
    {
        $this->assertTrue($this->db->table('products')->insert([
            'code' => $code, 'product_type' => $type, 'status' => $status,
            'parent_product_id' => $parent, 'product_name' => $name,
            'product_child_name' => $childName, 'created_at' => '2026-10-06 00:00:00',
        ]));
        return (int) $this->db->insertID();
    }
}

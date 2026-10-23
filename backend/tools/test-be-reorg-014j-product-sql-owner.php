<?php

declare(strict_types=1);

/**
 * BE-REORG-014J: Product-owned SQL list source characterization.
 * Loads the production source against in-memory Product collaborator doubles;
 * no CI4/vendor, DB, Shield, API requests or transactional effects.
 */
namespace App\Modules\Product\Product {
    use App\Infrastructure\Api\CommonListQuery;

    final class ProductRepository
    {
        public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_PERSISTENCE_FAILED';
        public bool $invalid = false;
        public bool $omitSelected = false;
        public int $fullReads = 0;
        public int $pageReads = 0;
        public int $keyReads = 0;
        public int $countReads = 0;

        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows, public bool $sql = true) {}
        public function firstInvalidListStructure(): ?array
        {
            return $this->invalid ? ['id' => 91, 'reason' => 'BROKEN_PARENT'] : null;
        }
        public function supportsSqlList(CommonListQuery $q): bool { return $this->sql; }
        public function countListMatching(CommonListQuery $q, ?array $statuses): int
        {
            $this->countReads++;
            return count($this->allowed($q, $statuses));
        }
        public function listPageMatching(CommonListQuery $q, ?array $statuses): array
        {
            $this->pageReads++;
            return array_slice($this->allowed($q, $statuses), $q->offset, $q->pageSize);
        }
        public function listProjectedKeys(CommonListQuery $q, ?array $statuses): array
        {
            $this->keyReads++;
            return array_map(static fn (array $row): array => [
                'id' => $row['id'], 'sku' => $row['sku'],
            ], $this->allowed($q, $statuses));
        }
        public function findByIds(array $ids): array
        {
            $this->fullReads++;
            return array_values(array_filter($this->rows, fn (array $row): bool =>
                in_array($row['id'], $ids, true) && (! $this->omitSelected || $row['id'] !== 2)));
        }
        /** @return list<array<string,mixed>> */
        private function allowed(CommonListQuery $q, ?array $statuses): array
        {
            return array_values(array_filter($this->rows, static function (array $row) use ($q, $statuses): bool {
                if ($statuses !== null && ! in_array($row['status'], $statuses, true)) {
                    return false;
                }
                foreach ($q->filters as $field => $values) {
                    if (! in_array((string) ($row[$field] ?? ''), $values, true)) {
                        return false;
                    }
                }
                return true;
            }));
        }
    }

    final class ProductRoleService
    {
        public const ERROR_INVALID_ROLE = 'PRODUCT_INVALID_ROLE';
        public function describePage(array $rows): array
        {
            return array_map(static function (array $row): array {
                $row['role'] = 'SIMPLE';
                return $row;
            }, $rows);
        }
    }
}

namespace {
    use App\Exceptions\DomainException;
    use App\Exceptions\PersistenceException;
    use App\Infrastructure\Api\CommonListQuery;
    use App\Infrastructure\Api\CommonListSortTerm;
    use App\Modules\Product\Product\Application\SqlProductListSource;
    use App\Modules\Product\Product\ProductRepository;
    use App\Modules\Product\Product\ProductRoleService;

    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $file = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    });
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string { return strtolower($value); }
    }

    function jAssert(bool $ok, string $message): void
    {
        if (! $ok) { throw new \RuntimeException($message); }
    }
    function jQuery(?string $q = null, int $page = 1, int $size = 2, array $filters = []): CommonListQuery
    {
        return new CommonListQuery($q, $page, $size, ($page - 1) * $size,
            [new CommonListSortTerm('id', 'ASC')], $filters, ['sku']);
    }
    function jRows(): array
    {
        return [
            ['id' => 1, 'sku' => 'A', 'status' => 'ACTIVE'],
            ['id' => 2, 'sku' => 'B', 'status' => 'ACTIVE'],
            ['id' => 3, 'sku' => 'C', 'status' => 'INACTIVE'],
        ];
    }

    $passed = 0;
    $check = static function (string $name, \Closure $run) use (&$passed): void {
        $run(); $passed++; echo "  {$name}: PASS\n";
    };
    $check('module owner class autoload and no shared implementation', static function () use ($app): void {
        $canonical = $app . 'Modules/Product/Product/Application/SqlProductListSource.php';
        jAssert(! is_file($app . 'Infrastructure/Api/SqlProductListSource.php'), 'Old shared implementation remains');
        jAssert((new \ReflectionClass(SqlProductListSource::class))->getFileName() === realpath($canonical), 'Source must autoload from Product module');
        jAssert(! class_exists('App\\Infrastructure\\Api\\SqlProductListSource'), 'Internal-only source must not receive a legacy alias');
        $owner = (string) file_get_contents($app . 'Modules/Product/Product/Application/ProductListQueryService.php');
        jAssert(! str_contains($owner, 'App\\Infrastructure\\Api\\SqlProductListSource'), 'Owner still depends on old shared class');
        $test = (string) file_get_contents(dirname(__DIR__) . '/tests/unit/P1Api050ProductSqlPaginationTest.php');
        jAssert(str_contains($test, 'use App\\Modules\\Product\\Product\\Application\\SqlProductListSource;'), 'Real DB test still imports old class');
    });
    $check('authorized status and exact filters before SQL count', static function (): void {
        $repo = new ProductRepository(jRows());
        $source = new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']);
        jAssert($source->countAuthorized(jQuery(filters: ['sku' => ['B']])) === 1, 'SQL count must reflect allowed relation');
        jAssert($repo->countReads === 1 && $repo->pageReads === 0, 'Count must not prefetch page');
    });
    $check('SQL page is hydrated with role only after authorized filter', static function (): void {
        $repo = new ProductRepository(jRows());
        $source = new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']);
        $rows = $source->fetchAuthorized(jQuery(page: 2, size: 1));
        jAssert(array_column($rows, 'id') === [2] && $rows[0]['role'] === 'SIMPLE', 'Wrong authorized SQL page');
        jAssert($repo->pageReads === 1 && $repo->keyReads === 0, 'SQL page must not take projection fallback');
    });
    $check('non-SQL projection hydrates only selected page in key order', static function (): void {
        $repo = new ProductRepository(jRows(), false);
        $source = new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']);
        $query = jQuery(page: 2, size: 1);
        jAssert($source->countAuthorized($query) === 2, 'Projection count lost approved State filter');
        $page = $source->fetchAuthorized($query);
        jAssert(array_column($page, 'id') === [2] && ($page[0]['role'] ?? null) === 'SIMPLE', 'Projection page inconsistent');
        jAssert($repo->keyReads === 1 && $repo->fullReads === 1, 'Projection should cache keys and hydrate page only');
    });
    $check('empty authorized projected page does not hydrate full rows', static function (): void {
        $repo = new ProductRepository(jRows(), false);
        $source = new SqlProductListSource($repo, new ProductRoleService(), []);
        jAssert($source->countAuthorized(jQuery()) === 0, 'Zero approved statuses must never count hidden rows');
        jAssert($source->fetchAuthorized(jQuery()) === [], 'Zero approved statuses must never return hidden rows');
        jAssert($repo->fullReads === 0, 'Empty page must skip relationship hydration');
    });
    $check('catalog-wide invalid structure is fail-closed even outside page', static function (): void {
        $repo = new ProductRepository(jRows()); $repo->invalid = true;
        try { (new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']))->countAuthorized(jQuery(size: 1)); }
        catch (DomainException $error) {
            jAssert($error->getErrorCode() === ProductRoleService::ERROR_INVALID_ROLE, 'Wrong machine error');
            jAssert($repo->countReads === 0, 'Structure must be checked before COUNT');
            return;
        }
        throw new \RuntimeException('Corrupt catalog accepted');
    });
    $check('concurrent missing projected row fails closed instead of shortening page', static function (): void {
        $repo = new ProductRepository(jRows(), false); $repo->omitSelected = true;
        try { (new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']))->fetchAuthorized(jQuery(page: 2, size: 1)); }
        catch (PersistenceException $error) {
            jAssert($error->getErrorCode() === ProductRepository::ERROR_PERSISTENCE_FAILED, 'Wrong persistence error');
            return;
        }
        throw new \RuntimeException('Missing selected Product silently omitted');
    });

    echo "BE-REORG-014J PRODUCT SQL SOURCE OWNERSHIP PASS: {$passed} checks; real source with fake Product collaborators. DB/Shield/HTTP NOT_RUN.\n";
}

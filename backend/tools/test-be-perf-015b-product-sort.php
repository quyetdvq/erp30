<?php

declare(strict_types=1);

/**
 * BE-PERF-015B: Product-only sort contract and source-level behavioral gate.
 * Real Product list source + shared PHP comparator, no CI4/vendor, DB or SQL.
 */
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
}
namespace App\Modules\Product\Product {
    use App\Infrastructure\Api\CommonListQuery;

    final class ProductRepository
    {
        public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_PERSISTENCE_FAILED';
        public int $countCalls = 0;
        public int $pageCalls = 0;
        public int $keyCalls = 0;
        public int $nameCalls = 0;
        public int $fullCalls = 0;

        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows) {}
        public function firstInvalidListStructure(): ?array { return null; }
        public function supportsSqlList(CommonListQuery $q): bool {
            return $q->q === null && (count($q->sort) === 1 && $q->sort[0]->field === 'id'
                || count($q->sort) === 2 && $q->sort[0]->field === 'price' && $q->sort[1]->field === 'id');
        }
        public function countListMatching(CommonListQuery $q, ?array $states): int {
            ++$this->countCalls;
            return count($this->allowed($states));
        }
        public function listPageMatching(CommonListQuery $q, ?array $states): array {
            ++$this->pageCalls;
            $rows = $this->allowed($states);
            usort($rows, static function (array $a, array $b) use ($q): int {
                if ($q->sort[0]->field === 'price') {
                    $comparison = \App\Modules\Product\Product\Application\ProductListSort::compareField('price', $a['price'], $b['price']);
                    if ($comparison !== 0) { return $q->sort[0]->direction === 'DESC' ? -$comparison : $comparison; }
                } else {
                    $comparison = ($a['id'] <=> $b['id']);
                    if ($q->sort[0]->direction === 'DESC') { return -$comparison; }
                }
                return $a['id'] <=> $b['id'];
            });
            return array_slice($rows, $q->offset, $q->pageSize);
        }
        public function listProjectedKeys(CommonListQuery $q, ?array $states): array {
            ++$this->keyCalls;
            return $this->allowed($states);
        }
        public function findNamesByIds(array $ids): array {
            ++$this->nameCalls;
            $names = [];
            foreach ($this->rows as $r) {
                if (in_array((int) $r['id'], $ids, true)) { $names[(int) $r['id']] = (string) $r['product_name']; }
            }
            return $names;
        }
        public function findByIds(array $ids): array {
            ++$this->fullCalls;
            return array_values(array_filter($this->rows, static fn ($row): bool => in_array((int) $row['id'], $ids, true)));
        }
        private function allowed(?array $states): array {
            return array_values(array_filter($this->rows, static fn ($row): bool => $states === null || in_array($row['status'], $states, true)));
        }
    }
    final class ProductRoleService
    {
        public const ERROR_INVALID_ROLE = 'PRODUCT_INVALID_ROLE';
        public function describePage(array $rows): array { return $rows; }
    }
}
namespace {
    use App\Infrastructure\Api\AuthorizedArrayListSource;
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Modules\Product\Product\Application\ProductListQueryService;
    use App\Modules\Product\Product\Application\ProductListSort;
    use App\Modules\Product\Product\Application\SqlProductListSource;
    use App\Modules\Product\Product\ProductRepository;
    use App\Modules\Product\Product\ProductRoleService;

    $root = dirname(__DIR__, 2) . '/';
    $app = $root . 'backend/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $path = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    });
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string { return strtolower($value); }
    }
    function b15Check(string $label, callable $callback, int &$passes): void {
        $callback(); ++$passes; echo "  {$label}: PASS\n";
    }
    function b15Assert(bool $ok, string $why): void {
        if (! $ok) { throw new \RuntimeException('BE-PERF-015B: ' . $why); }
    }
    function b15Query(array $input) {
        return (new CommonListQueryAdapter())->parse($input, new CommonListQueryAllowlist(
            search: ['product_code','sku','barcode','product_name','product_child_name'],
            filters: ['status', 'product_type'],
            sort: ['id','product_code','sku','product_name','full_name','price','product_type','status'],
        ));
    }
    function b15Rows(): array {
        return [
            ['id'=>1,'status'=>'ACTIVE','code'=>'2','product_code'=>'2','sku'=>'ZZ','product_name'=>'Beta','product_child_name'=>null,'parent_product_id'=>null,'price'=>'1.10'],
            ['id'=>2,'status'=>'ACTIVE','code'=>'10','product_code'=>'10','sku'=>'AA','product_name'=>'Alpha','product_child_name'=>null,'parent_product_id'=>null,'price'=>'1.9'],
            ['id'=>3,'status'=>'INACTIVE','code'=>'3','product_code'=>'3','sku'=>'BB','product_name'=>null,'product_child_name'=>'Red','parent_product_id'=>2,'price'=>'0.90'],
            ['id'=>4,'status'=>'ACTIVE','code'=>'01','product_code'=>'01','sku'=>'CC','product_name'=>null,'product_child_name'=>'Blue','parent_product_id'=>2,'price'=>null],
        ];
    }

    $passed = 0;
    b15Check('product_id maps to existing id sort without accepting duplicate aliases', static function (): void {
        $method = new \ReflectionMethod(ProductListQueryService::class, 'canonicalSortQuery');
        $mapped = $method->invoke(null, ['sort' => '-product_id,sku']);
        b15Assert($mapped['sort'] === '-id,sku', 'DESC alias mapping');
        b15Assert($method->invoke(null, []) === ['sort' => '-id'], 'absent Product sort must default to id DESC');
        b15Assert($method->invoke(null, ['sort' => 'xproduct_id'])['sort'] === 'xproduct_id', 'partial token rewritten');
        $default = b15Query($method->invoke(null, []))->sort[0];
        b15Assert($default->field === 'id' && $default->direction === 'DESC', 'Product default field/direction must be id DESC');
        $ascending = b15Query($method->invoke(null, ['sort' => 'product_id']))->sort[0];
        b15Assert($ascending->field === 'id' && $ascending->direction === 'ASC', 'explicit Product ID ASC must be preserved');
        try { b15Query($method->invoke(null, ['sort' => 'product_id,id'])); throw new \LogicException('accepted duplicate'); }
        catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $e) { /* expected */ }
        foreach (['unknown_field', 'price;DROP'] as $unsafe) {
            try { b15Query(['sort' => $unsafe]); throw new \LogicException('accepted unsafe sort'); }
            catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $e) { /* expected */ }
        }
    }, $passed);

    b15Check('DECIMAL prices sort exactly including scale, null, negatives and ties', static function (): void {
        $source = new AuthorizedArrayListSource([
            ['id'=>1,'price'=>'1.90'], ['id'=>2,'price'=>'1.10'], ['id'=>3,'price'=>'1.9'],
            ['id'=>4,'price'=>null], ['id'=>5,'price'=>'-0.50'], ['id'=>6,'price'=>'10.00'],
            ['id'=>7,'price'=>'2.00'],
        ], ProductListSort::compareField(...));
        b15Assert(array_column($source->fetchAuthorized(b15Query(['sort'=>'price'])), 'id') === [4,5,2,1,3,7,6], 'ASC decimal comparison wrong');
        b15Assert(array_column($source->fetchAuthorized(b15Query(['sort'=>'-price'])), 'id') === [6,7,1,3,2,5,4], 'DESC decimal comparison wrong');
        b15Assert(ProductListSort::compareField('price', '0001.20', '1.2') === 0, 'decimal canonical difference');
        b15Assert(ProductListSort::compareField('price', '1.90', '1.10') > 0, 'fraction order wrong');
        b15Assert(ProductListSort::compareField('product_code', '2', '10') === null, 'code comparator changed');
        try { ProductListSort::compareField('price', '1.2x', '1.1'); throw new \LogicException('invalid decimal accepted'); }
        catch (\App\Exceptions\PersistenceException $e) { /* fail closed */ }
    }, $passed);

    b15Check('legacy numeric-aware product codes remain unchanged', static function (): void {
        $source = new AuthorizedArrayListSource([
            ['id'=>1,'product_code'=>'10'], ['id'=>2,'product_code'=>'2'], ['id'=>3,'product_code'=>'01'],
        ], ProductListSort::compareField(...));
        b15Assert(array_column($source->fetchAuthorized(b15Query(['sort'=>'product_code'])), 'id') === [3,2,1], 'legacy code ordering drift');
    }, $passed);

    b15Check('full_name sort uses displayed parent/child names, not nullable child product_name', static function (): void {
        $repo = new ProductRepository(b15Rows());
        $src = new SqlProductListSource($repo, new ProductRoleService());
        $q = b15Query(['sort'=>'full_name']);
        b15Assert($src->countAuthorized($q) === 4, 'total drift');
        b15Assert($repo->nameCalls === 0, 'COUNT must not hydrate parent names');
        b15Assert(array_column(iterator_to_array($src->fetchAuthorized($q)), 'id') === [2,4,3,1], 'display name ordering wrong');
        b15Assert($repo->nameCalls === 1 && $repo->keyCalls === 1 && $repo->fullCalls === 1, 'unbounded name reads or full hydration');
        $q2 = b15Query(['sort'=>'-full_name','page_size'=>'2']);
        $src2 = new SqlProductListSource(new ProductRepository(b15Rows()), new ProductRoleService());
        b15Assert(array_column(iterator_to_array($src2->fetchAuthorized($q2)), 'id') === [1,3], 'descending full name incorrect');
    }, $passed);

    b15Check('missing referenced parent fails closed rather than sorting with a fabricated name', static function (): void {
        try {
            ProductListSort::withFullNames([[
                'id'=>12, 'parent_product_id'=>99, 'product_name'=>null, 'product_child_name'=>'X',
            ]], []);
            throw new \LogicException('orphan was accepted');
        } catch (\App\Exceptions\DomainException $exception) {
            b15Assert($exception->getErrorCode() === ProductRoleService::ERROR_INVALID_ROLE, 'wrong error code');
        }
    }, $passed);

    b15Check('PBAC State set filters before page and does not change COUNT', static function (): void {
        $repo = new ProductRepository(b15Rows());
        $src = new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']);
        $q = b15Query(['sort'=>'sku','page_size'=>'2']);
        b15Assert($src->countAuthorized($q) === 3, 'denied rows were counted');
        b15Assert(array_column(iterator_to_array($src->fetchAuthorized($q)), 'id') === [2,4], 'denied State leaked or wrong paging');
        b15Assert($repo->fullCalls === 1, 'should hydrate only page');
    }, $passed);

    b15Check('numeric price without search uses SQL page; search keeps exact projected comparator', static function (): void {
        $repo = new ProductRepository(b15Rows());
        $src = new SqlProductListSource($repo, new ProductRoleService(), ['ACTIVE']);
        $q = b15Query(['sort'=>'-price', 'page_size'=>'2']);
        b15Assert($src->countAuthorized($q) === 3, 'price SQL count wrong');
        b15Assert(array_column(iterator_to_array($src->fetchAuthorized($q)), 'id') === [2,1], 'numeric price SQL page wrong');
        b15Assert($repo->countCalls === 1 && $repo->pageCalls === 1 && $repo->keyCalls === 0, 'price should use SQL count and limit');
        $repo = new ProductRepository(b15Rows());
        $src = new SqlProductListSource($repo, new ProductRoleService());
        $q = b15Query(['sort'=>'price', 'q'=>'a']);
        b15Assert($src->countAuthorized($q) === 2, 'search count mismatch');
        b15Assert(array_column(iterator_to_array($src->fetchAuthorized($q)), 'id') === [1,2], 'searched price ordering incorrect');
        b15Assert($repo->keyCalls === 1 && $repo->pageCalls === 0, 'searched price should use projection');
    }, $passed);

    b15Check('real Product repository uses narrow projection and only allowlisted SQL sort columns', static function () use ($app): void {
        $repo = (string) file_get_contents($app . 'Modules/Product/Product/ProductRepository.php');
        $service = (string) file_get_contents($app . 'Modules/Product/Product/Application/ProductListQueryService.php');
        b15Assert(str_contains($repo, '->select(array_values(array_unique($columns)))'), 'projection is not narrow');
        b15Assert(str_contains($repo, "'product_code'") && str_contains($repo, "'code'"), 'code mapping removed');
        b15Assert(str_contains($repo, "'price'") && str_contains($repo, 'supportsSqlProjection($query)'), 'safe price fast path removed');
        b15Assert(str_contains($repo, '->limit($query->pageSize, $query->offset)'), 'SQL pagination lost');
        b15Assert(str_contains($service, 'canonicalSortQuery($query)'), 'product_id alias not normalized');
    }, $passed);

    b15Check('frontend exposes all supported sorts and keeps Product ID as default', static function () use ($root): void {
        $settings = (string) file_get_contents($root . 'frontend/src/features/catalog/productSortOptions.ts');
        $view = (string) file_get_contents($root . 'frontend/src/features/catalog/pages/ProductsView.vue');
        b15Assert(str_contains($settings, "DEFAULT_PRODUCT_SORT = '-product_id'"), 'wrong frontend default');
        foreach (['product_id','full_name','price','product_code','sku','product_type','status'] as $field) {
            b15Assert(str_contains($settings, "value: '{$field}'") && str_contains($settings, "value: '-{$field}'"), "missing {$field} directions");
        }
        b15Assert(str_contains($view, ':sort-options="productSortOptions"'), 'sort selector not rendered');
        b15Assert(str_contains($view, 'state.setSort(DEFAULT_PRODUCT_SORT)'), 'reset loses product id sort');
    }, $passed);

    echo "BE-PERF-015B PRODUCT SORT SOURCE GATE PASS: {$passed} checks; real array/product list source, zero DB connections or SQL. MariaDB/Shield/HTTP runtime NOT_RUN.\n";
}

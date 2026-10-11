<?php

declare(strict_types=1);

use App\Modules\Product\Lookup\ProductLookupRepository;
use App\Services\ProductLookupService;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Frontend API lookup/batch contract regression on real MariaDB. */
final class P1Perf007ProductLookupBatchTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testProductOptionsFilterDerivedParentRoleBeforePagination(): void
    {
        $parent = $this->product('LOOKUP-ROOT', 'STANDARD', 'ACTIVE', null);
        $child = $this->product('LOOKUP-CHILD', 'STANDARD', 'ACTIVE', $parent);
        $simple = $this->product('LOOKUP-SIMPLE', 'STANDARD', 'ACTIVE', null);
        $combo = $this->product('LOOKUP-COMBO', 'COMBO', 'ACTIVE', null);
        $this->product('LOOKUP-INACTIVE', 'STANDARD', 'INACTIVE', null);

        $lookup = new ProductLookupRepository($this->db);
        $selected = $lookup->options('price_list_item', '', 1, 2);
        self::assertSame(3, $selected['total'], 'PARENT and INACTIVE must be excluded before LIMIT.');
        self::assertSame([$child, $simple], array_map('intval', array_column($selected['rows'], 'id')));
        $lastPage = $lookup->options('price_list_item', '', 2, 2);
        self::assertSame(3, $lastPage['total']);
        self::assertSame([$combo], array_map('intval', array_column($lastPage['rows'], 'id')));

        $parents = $lookup->options('parent_product', '', 1, 50);
        self::assertSame(2, $parents['total']);
        self::assertSame([$parent, $simple], array_map('intval', array_column($parents['rows'], 'id')));
        $search = $lookup->options('combo_component', 'LOOKUP-COMBO', 1, 20);
        self::assertSame(1, $search['total']);
        self::assertSame([$combo], array_map('intval', array_column($search['rows'], 'id')));
    }

    public function testRelationPreviewUsesVisibleStateBeforeWindowCountAndLimit(): void
    {
        $parent = $this->product('LOOKUP-PREVIEW-ROOT', 'STANDARD', 'ACTIVE', null);
        $first = $this->product('LOOKUP-PREVIEW-A', 'STANDARD', 'ACTIVE', $parent);
        $this->product('LOOKUP-PREVIEW-HIDDEN', 'STANDARD', 'INACTIVE', $parent);
        $second = $this->product('LOOKUP-PREVIEW-B', 'STANDARD', 'ACTIVE', $parent);
        $combo = $this->product('LOOKUP-PREVIEW-COMBO', 'COMBO', 'ACTIVE', null);
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $combo, 'component_product_id' => $second,
            'quantity' => '1.000000', 'created_at' => '2026-10-11 00:00:00',
        ]);

        $lookup = new ProductLookupRepository($this->db);
        $rows = $lookup->relationPreview([$parent, $combo], [strtoupper(bin2hex('ACTIVE'))], 1);
        self::assertCount(2, $rows);
        self::assertSame($parent, (int) $rows[0]['owner_id']);
        self::assertSame($first, (int) $rows[0]['product_id']);
        self::assertSame(2, (int) $rows[0]['total'], 'INACTIVE rows must not count toward visible total.');
        self::assertSame($combo, (int) $rows[1]['owner_id']);
        self::assertSame($second, (int) $rows[1]['product_id']);
        self::assertSame(1, (int) $rows[1]['total']);
        self::assertSame([], $lookup->relationPreview([$parent], [], 2));
    }

    public function testNewEndpointsKeepOriginalProductCatalogOperationsAndBounds(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        self::assertSame(26, substr_count($routes, 'Api\\\\ProductCatalog::'));
        foreach (['products/options', 'products/options/resolve', 'products/relations-preview',
            'products/sales-uom-options/resolve', 'products/details/resolve', 'product-uoms/labels/resolve', 'organizations/labels/resolve'] as $path) {
            self::assertStringContainsString("'{$path}'", $routes);
        }

        $service = new ProductLookupService();
        foreach ([
            static fn (): mixed => $service->options(['purpose' => 'invalid']),
            static fn (): mixed => $service->options(['page_size' => 1000]),
            static fn (): mixed => $service->resolve(['0']),
            static fn (): mixed => $service->relationPreviews(['1'], 6),
            static fn (): mixed => $service->salesUomOptions(array_fill(0, 51, '1')),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid request should fail before entering a DB transaction.');
            } catch (BadRequestException $_) {
                self::assertTrue(true);
            }
        }
    }

    private function product(string $code, string $type, string $status, ?int $parent): int
    {
        self::assertTrue($this->db->table('products')->insert([
            'code' => $code, 'product_type' => $type, 'status' => $status,
            'parent_product_id' => $parent,
            'product_name' => $parent === null ? $code : null,
            'product_child_name' => $parent !== null ? $code : null,
            'created_at' => '2026-10-11 00:00:00',
        ]));
        return (int) $this->db->insertID();
    }
}

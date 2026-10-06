<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Product\Combo\ComboExpansionService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Cmb002ComboExpansionServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testBasicSimpleAndChildExpansionAndRootRoleBoundary(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $root = $this->product('ROOT-BASIC', $uomId, $groupId, 'COMBO');
        $simple = $this->product('SIMPLE-BASIC', $uomId, $groupId);
        $parent = $this->product('PARENT-BASIC', $uomId, $groupId);
        $child = $this->product('CHILD-BASIC', $uomId, $groupId, 'STANDARD', $parent);
        $this->edge($root, $simple, '3.000000');
        $this->edge($root, $child, '4.000000');

        $service = new ComboExpansionService(db: $this->db);
        $this->assertSame([
            ['product_id' => $simple, 'base_uom_id' => $uomId, 'quantity' => '6'],
            ['product_id' => $child, 'base_uom_id' => $uomId, 'quantity' => '8'],
        ], $service->expand($root, '2'));
        $this->assertDomainCode(fn () => $service->expand($simple, '1'), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $service->expand($child, '1'), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $service->expand($parent, '1'), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $service->expand(987654321, '1'), ProductRoleService::ERROR_NOT_FOUND);
    }

    public function testCanonicalNestedExampleAggregatesOnlySimpleAndChildLeaves(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $a = $this->product('NESTED-A', $uomId, $groupId, 'COMBO', null, 'ACTIVE', '900.00');
        $b = $this->product('NESTED-B', $uomId, $groupId, 'COMBO', null, 'ACTIVE', '700.00');
        $x = $this->product('NESTED-X', $uomId, $groupId, 'STANDARD', null, 'ACTIVE', '10.00');
        $parent = $this->product('NESTED-Y-PARENT', $uomId, $groupId);
        $y = $this->product('NESTED-Y', $uomId, $groupId, 'STANDARD', $parent);
        $this->edge($a, $x, '1');
        $this->edge($a, $b, '3');
        $this->edge($b, $x, '2');
        $this->edge($b, $y, '4');

        $result = (new ComboExpansionService(db: $this->db))->expand($a, '2');
        $this->assertSame([
            ['product_id' => $x, 'base_uom_id' => $uomId, 'quantity' => '14'],
            ['product_id' => $y, 'base_uom_id' => $uomId, 'quantity' => '24'],
        ], $result);
        $this->assertNotContains($b, array_column($result, 'product_id'));
        $this->assertNotContains($a, array_column($result, 'product_id'));
    }

    public function testSharedSubgraphCountsEveryPathAndPreservesExactPrecision(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $root = $this->product('DIAMOND-ROOT', $uomId, $groupId, 'COMBO');
        $left = $this->product('DIAMOND-LEFT', $uomId, $groupId, 'COMBO');
        $right = $this->product('DIAMOND-RIGHT', $uomId, $groupId, 'COMBO');
        $shared = $this->product('DIAMOND-SHARED', $uomId, $groupId, 'COMBO');
        $leaf = $this->product('DIAMOND-LEAF', $uomId, $groupId);
        $this->edge($root, $left, '1');
        $this->edge($root, $right, '2');
        $this->edge($left, $shared, '3');
        $this->edge($right, $shared, '4');
        $this->edge($shared, $leaf, '5');

        $service = new ComboExpansionService(db: $this->db);
        $this->assertSame([
            ['product_id' => $leaf, 'base_uom_id' => $uomId, 'quantity' => '55'],
        ], $service->expand($root, '1'));

        $precisionRoot = $this->product('PRECISION-ROOT', $uomId, $groupId, 'COMBO');
        $precisionNested = $this->product('PRECISION-NESTED', $uomId, $groupId, 'COMBO');
        $precisionLeaf = $this->product('PRECISION-LEAF', $uomId, $groupId);
        $this->edge($precisionRoot, $precisionNested, '0.123456');
        $this->edge($precisionNested, $precisionLeaf, '0.123456');
        $this->assertSame([
            ['product_id' => $precisionLeaf, 'base_uom_id' => $uomId, 'quantity' => '0.0015241383936'],
        ], $service->expand($precisionRoot, '0.1'));
    }

    public function testEmptyCompositionIsEmptyAndOutputOrderingIsCanonical(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $empty = $this->product('EMPTY-ROOT', $uomId, $groupId, 'COMBO');
        $this->assertSame([], (new ComboExpansionService(db: $this->db))->expand($empty, '1'));

        $root = $this->product('ORDER-ROOT', $uomId, $groupId, 'COMBO');
        $high = $this->product('ORDER-HIGH', $uomId, $groupId);
        $low = $this->product('ORDER-LOW', $uomId, $groupId);
        $this->edge($root, $high, '1');
        $this->edge($root, $low, '1');
        $result = (new ComboExpansionService(db: $this->db))->expand($root, '1');
        $this->assertSame([$high, $low], array_column($result, 'product_id'));
        $this->assertSame(['product_id', 'base_uom_id', 'quantity'], array_keys($result[0]));
    }

    public function testInactiveAndCorruptReferencesFailClosedWithoutPartialOutput(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $root = $this->product('CORRUPT-ROOT', $uomId, $groupId, 'COMBO');
        $inactive = $this->product('CORRUPT-INACTIVE', $uomId, $groupId, 'STANDARD', null, 'INACTIVE');
        $this->edge($root, $inactive, '1');
        $service = new ComboExpansionService(db: $this->db);
        $this->assertDomainCode(fn () => $service->expand($root, '1'), ComboExpansionService::ERROR_INACTIVE_REFERENCE);

        $missingUom = $this->product('CORRUPT-UOM', $uomId, $groupId, 'STANDARD', null, 'ACTIVE', '2.00', null);
        $this->db->table('products')->where('id', $missingUom)->update(['base_uom_id' => null]);
        $this->edge($root, $missingUom, '1');
        $this->db->table('product_combo_items')->where('combo_product_id', $root)->where('component_product_id', $inactive)->delete();
        $this->assertDomainCode(fn () => $service->expand($root, '1'), ProductUomService::ERROR_INVALID_REFERENCE);

        $parent = $this->product('CORRUPT-PARENT', $uomId, $groupId);
        $this->product('CORRUPT-CHILD', $uomId, $groupId, 'STANDARD', $parent);
        $this->db->table('product_combo_items')->where('combo_product_id', $root)->delete();
        $this->edge($root, $parent, '1');
        $this->assertDomainCode(fn () => $service->expand($root, '1'), ProductRoleService::ERROR_INVALID_ROLE);

        $this->db->table('product_combo_items')->where('combo_product_id', $root)->delete();
        $this->edge($root, $missingUom, '0.000000');
        $this->assertDomainCode(fn () => $service->expand($root, '1'), ComboExpansionService::ERROR_INVALID_QUANTITY);

    }

    public function testSelfAndDeepPersistedCyclesTerminateWithNoPartialResult(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $self = $this->product('CYCLE-SELF', $uomId, $groupId, 'COMBO');
        $service = new ComboExpansionService(db: $this->db);
        $this->edge($self, $self, '1');
        $this->assertDomainCode(fn () => $service->expand($self, '1'), ComboExpansionService::ERROR_CYCLE);

        $a = $this->product('CYCLE-A', $uomId, $groupId, 'COMBO');
        $b = $this->product('CYCLE-B', $uomId, $groupId, 'COMBO');
        $c = $this->product('CYCLE-C', $uomId, $groupId, 'COMBO');
        $leaf = $this->product('CYCLE-LEAF', $uomId, $groupId);
        $this->edge($a, $leaf, '1');
        $this->edge($a, $b, '1');
        $this->edge($b, $c, '1');
        $this->edge($c, $a, '1');
        $this->assertDomainCode(fn () => $service->expand($a, '1'), ComboExpansionService::ERROR_CYCLE);
    }

    public function testRootQuantityBoundaryAndInactiveRootAreRejected(): void
    {
        [$uomId, $groupId] = $this->fixture();
        $root = $this->product('BOUNDARY-ROOT', $uomId, $groupId, 'COMBO');
        $leaf = $this->product('BOUNDARY-LEAF', $uomId, $groupId);
        $this->edge($root, $leaf, '1');
        $service = new ComboExpansionService(db: $this->db);
        foreach (['0', '0.000', '-1', '01', 1.2, null] as $quantity) {
            $this->assertDomainCode(fn () => $service->expand($root, $quantity), ComboExpansionService::ERROR_INVALID_QUANTITY);
        }

        $inactiveRoot = $this->product('BOUNDARY-INACTIVE-ROOT', $uomId, $groupId, 'COMBO', null, 'INACTIVE');
        $this->assertDomainCode(fn () => $service->expand($inactiveRoot, '1'), ComboExpansionService::ERROR_INACTIVE_REFERENCE);
    }

    /** @return array{int,int} */
    private function fixture(): array
    {
        $this->db->table('product_groups')->insert([
            'code' => 'CMB2-G-' . uniqid(), 'name' => 'Combo Expansion', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $groupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'CMB2-U-' . uniqid(), 'name' => 'Each', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return [(int) $this->db->insertID(), $groupId];
    }

    private function product(
        string $label,
        int $uomId,
        int $groupId,
        string $type = 'STANDARD',
        ?int $parentId = null,
        string $status = 'ACTIVE',
        string $price = '10.00',
        ?int $baseUomId = null,
    ): int {
        $suffix = bin2hex(random_bytes(3));
        $this->db->table('products')->insert([
            'code' => 'CMB2-' . $label . '-' . $suffix,
            'sku' => 'SKU-CMB2-' . $label . '-' . $suffix,
            'product_type' => $type,
            'status' => $status,
            'base_uom_id' => $baseUomId ?? $uomId,
            'product_group_id' => $groupId,
            'parent_product_id' => $parentId,
            'product_name' => $parentId === null ? $label : null,
            'product_child_name' => $parentId === null ? null : $label,
            'price' => $parentId === null ? $price : '2.00',
            'serial_management_enabled' => 0,
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function edge(int $ownerId, int $componentId, string $quantity): void
    {
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $ownerId,
            'component_product_id' => $componentId,
            'quantity' => $quantity,
            'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected combo expansion error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

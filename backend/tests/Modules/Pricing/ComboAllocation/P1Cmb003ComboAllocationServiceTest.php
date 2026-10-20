<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\ComboAllocation\ComboAllocationService;
use App\Modules\Pricing\PriceList\PriceListRepository;
use App\Modules\Pricing\Resolution\PricingService;
use App\Modules\Product\Combo\ComboExpansionService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use App\Services\OrganizationService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Cmb003ComboAllocationServiceTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testRootCompanyGroupAndDefaultPricingAndLeafPriceListsAreScopedCorrectly(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('PRICE');
        $companyRoot = $this->product('COMPANY-ROOT', $uomId, $groupId, 'COMBO', '50.00');
        $groupRoot = $this->product('GROUP-ROOT', $uomId, $groupId, 'COMBO', '60.00');
        $defaultRoot = $this->product('DEFAULT-ROOT', $uomId, $groupId, 'COMBO', '70.00');
        $leaf = $this->product('PRICE-LEAF', $uomId, $groupId, 'STANDARD', '10.00');
        $this->edge($companyRoot, $leaf, '1');
        $this->edge($groupRoot, $leaf, '1');
        $this->edge($defaultRoot, $leaf, '1');
        $companyList = $this->priceList($companyId, 'CMB3-COMPANY');
        $groupList = $this->priceList($groupId, 'CMB3-GROUP');
        $this->item($companyList, $companyRoot, $uomId, '11.00');
        $this->item($groupList, $groupRoot, $uomId, '22.00');
        $leafList = $this->priceList($companyId, 'CMB3-LEAF');
        $this->item($leafList, $leaf, $uomId, '999.00');

        $result = $this->service()->allocate([
            ['product_id' => $companyRoot, 'quantity' => '1'],
            ['product_id' => $groupRoot, 'quantity' => '1'],
            ['product_id' => $defaultRoot, 'quantity' => '1'],
        ], $companyId, $this->time());

        $this->assertSame(1, count($result));
        $this->assertSame('103.00', $result[0]['allocated_amount']);
        $this->assertSame('34.3333333333333333333333333333333333333333', $result[0]['unit_price']);
    }

    public function testNestedComboPriceIsIgnoredAndOnlyLeafDefaultBasePriceSuppliesWeight(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('NEST');
        $root = $this->product('NEST-ROOT', $uomId, $groupId, 'COMBO', '100.00');
        $nested = $this->product('NEST-NESTED', $uomId, $groupId, 'COMBO', '9000.00');
        $first = $this->product('NEST-FIRST', $uomId, $groupId, 'STANDARD', '10.00');
        $second = $this->product('NEST-SECOND', $uomId, $groupId, 'STANDARD', '20.00');
        $this->edge($root, $nested, '1');
        $this->edge($nested, $first, '1');
        $this->edge($nested, $second, '1');
        $leafList = $this->priceList($companyId, 'CMB3-NEST-LEAF');
        $this->item($leafList, $first, $uomId, '1000.00');
        $this->item($leafList, $second, $uomId, '1.00');

        $result = $this->service()->allocate([
            ['product_id' => $root, 'quantity' => '1'],
        ], $companyId, $this->time());

        $this->assertSame([$first, $second], array_column($result, 'product_id'));
        $this->assertSame(['33.33', '66.67'], array_column($result, 'allocated_amount'));
        $this->assertNotContains($nested, array_column($result, 'product_id'));
    }

    public function testZeroWeightLeafIsAllowedButAllZeroWeightsReject(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('ZERO');
        $root = $this->product('ZERO-ROOT', $uomId, $groupId, 'COMBO', '100.00');
        $zero = $this->product('ZERO-LEAF', $uomId, $groupId, 'STANDARD', '0.00');
        $nonzero = $this->product('NONZERO-LEAF', $uomId, $groupId, 'STANDARD', '10.00');
        $this->edge($root, $zero, '1');
        $this->edge($root, $nonzero, '1');
        $result = $this->service()->allocate([['product_id' => $root, 'quantity' => '1']], $companyId, $this->time());
        $this->assertSame('0.00', $result[0]['allocated_amount']);
        $this->assertSame('100.00', $result[1]['allocated_amount']);

        $empty = $this->product('ZERO-ROOT-ONLY', $uomId, $groupId, 'COMBO', '100.00');
        $this->edge($empty, $zero, '1');
        $this->assertDomainCode(
            fn () => $this->service()->allocate([['product_id' => $empty, 'quantity' => '1']], $companyId, $this->time()),
            ComboAllocationService::ERROR_TOTAL_WEIGHT,
        );
    }

    public function testThreeWayResidualIsDeterministicAndConservedAtTwoDecimals(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('ROUND');
        $root = $this->product('ROUND-ROOT', $uomId, $groupId, 'COMBO', '100.00');
        $a = $this->product('ROUND-A', $uomId, $groupId, 'STANDARD', '1.00');
        $b = $this->product('ROUND-B', $uomId, $groupId, 'STANDARD', '1.00');
        $c = $this->product('ROUND-C', $uomId, $groupId, 'STANDARD', '1.00');
        $this->edge($root, $c, '1');
        $this->edge($root, $a, '1');
        $this->edge($root, $b, '1');

        $result = $this->service()->allocate([['product_id' => $root, 'quantity' => '1']], $companyId, $this->time());
        $this->assertSame(['33.33', '33.33', '33.34'], array_column($result, 'allocated_amount'));
        $this->assertSame('100.00', $this->sumMoney(array_column($result, 'allocated_amount')));
    }

    public function testShuffledRootsProduceSameSortedResultAndSameLeafAggregatesByAmount(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('AGG');
        $rootA = $this->product('AGG-A', $uomId, $groupId, 'COMBO', '90000.00');
        $rootB = $this->product('AGG-B', $uomId, $groupId, 'COMBO', '80000.00');
        $leaf = $this->product('AGG-LEAF', $uomId, $groupId, 'STANDARD', '1.00');
        $this->edge($rootA, $leaf, '1');
        $this->edge($rootB, $leaf, '1');

        $first = $this->service()->allocate([
            ['product_id' => $rootA, 'quantity' => '2'],
            ['product_id' => $rootB, 'quantity' => '3'],
        ], $companyId, $this->time());
        $second = $this->service()->allocate([
            ['product_id' => $rootB, 'quantity' => '3'],
            ['product_id' => $rootA, 'quantity' => '2'],
        ], $companyId, $this->time());

        $this->assertSame($first, $second);
        $this->assertSame(5, (int) $first[0]['quantity']);
        $this->assertSame('420000.00', $first[0]['allocated_amount']);
        $this->assertSame('84000', $first[0]['unit_price']);
    }

    public function testHighPrecisionDecimalAllocationDoesNotUseBinaryFloat(): void
    {
        [$uomId, $groupId, $companyId] = $this->fixture('DECIMAL');
        $root = $this->product('DECIMAL-ROOT', $uomId, $groupId, 'COMBO', '0.30');
        $a = $this->product('DECIMAL-A', $uomId, $groupId, 'STANDARD', '0.10');
        $b = $this->product('DECIMAL-B', $uomId, $groupId, 'STANDARD', '0.20');
        $this->edge($root, $a, '3');
        $this->edge($root, $b, '1');

        $result = $this->service()->allocate([['product_id' => $root, 'quantity' => '3']], $companyId, $this->time());
        $this->assertSame('0.90', $this->sumMoney(array_column($result, 'allocated_amount')));
        $this->assertSame(['0.54', '0.36'], array_column($result, 'allocated_amount'));
        $this->assertSame(['9', '3'], array_column($result, 'quantity'));
    }

    private function service(): ComboAllocationService
    {
        $roles = new ProductRoleService(db: $this->db);
        $uoms = new ProductUomService(db: $this->db);
        return new ComboAllocationService(
            pricing: new PricingService(
                priceLists: new PriceListRepository($this->db),
                roles: $roles,
                productUoms: $uoms,
                organizations: new OrganizationService(db: $this->db),
            ),
            expansion: new ComboExpansionService(roles: $roles, productUoms: $uoms, db: $this->db),
            roles: $roles,
        );
    }

    /** @return array{int,int,int} */
    private function fixture(string $label): array
    {
        $query = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get();
        $group = $query === false ? null : $query->getRowArray();
        $groupId = $group === null ? $this->insertOrganization('GROUP', null, 'CMB3-' . $label . '-GROUP') : (int) $group['id'];
        $companyId = $this->insertOrganization('COMPANY', $groupId, 'CMB3-' . $label . '-COMPANY');
        $this->db->table('product_groups')->insert([
            'code' => 'CMB3-' . $label . '-G-' . uniqid(), 'name' => 'Combo Allocation', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $groupProductId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'CMB3-' . $label . '-U-' . uniqid(), 'name' => 'Each', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return [(int) $this->db->insertID(), $groupProductId, $companyId];
    }

    private function insertOrganization(string $type, ?int $parentId, string $name): int
    {
        $this->db->table('organizations')->insert([
            'type' => $type, 'parent_id' => $parentId, 'code' => $name . '-' . uniqid(), 'name' => $name,
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function product(string $code, int $uomId, int $groupId, string $type, string $price): int
    {
        $suffix = bin2hex(random_bytes(3));
        $this->db->table('products')->insert([
            'code' => 'CMB3-' . $code . '-' . $suffix, 'sku' => 'SKU-CMB3-' . $code . '-' . $suffix,
            'product_type' => $type, 'status' => 'ACTIVE', 'base_uom_id' => $uomId,
            'product_group_id' => $groupId, 'parent_product_id' => null, 'product_name' => $code,
            'product_child_name' => null, 'price' => $price, 'serial_management_enabled' => 0,
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function edge(int $rootId, int $componentId, string $quantity): void
    {
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $rootId, 'component_product_id' => $componentId,
            'quantity' => $quantity, 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function priceList(int $organizationId, string $code): int
    {
        $this->db->table('price_lists')->insert([
            'code' => $code . '-' . uniqid(), 'name' => $code, 'organization_id' => $organizationId,
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => '2026-12-31 00:00:00',
            'priority' => 10, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function item(int $listId, int $productId, int $uomId, string $price): void
    {
        $this->db->table('product_price_list_items')->insert([
            'price_list_id' => $listId, 'product_id' => $productId, 'uom_id' => $uomId,
            'price' => $price, 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function time(): EvaluationTime
    {
        return new EvaluationTime(new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC')));
    }

    /** @param list<string> $amounts */
    private function sumMoney(array $amounts): string
    {
        $sum = '0.00';
        foreach ($amounts as $amount) {
            $sum = (new Decimal($sum))->add($amount, 2)->value;
        }

        return $sum;
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected domain error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

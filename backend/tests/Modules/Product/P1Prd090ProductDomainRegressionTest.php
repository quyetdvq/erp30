<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\ComboAllocation\ComboAllocationService;
use App\Modules\Pricing\PriceList\PriceListRepository;
use App\Modules\Pricing\Resolution\PricingService;
use App\Modules\Product\Combo\ComboExpansionService;
use App\Modules\Product\Product\NoProductHistoryProvider;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Prd090ProductDomainRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testSimpleAlternateUomFlowsThroughProductUomPriceListAndPricing(): void
    {
        [$baseUomId, $alternateUomId, $productGroupId, $companyId] = $this->fixture('CHAIN');
        $productId = $this->product('CHAIN-SIMPLE', $baseUomId, $productGroupId, 'STANDARD', '12.00');
        $this->conversion($productId, $alternateUomId, '12.000000', '115.00', 'BOTH');
        $companyList = $this->priceList($companyId, 'CHAIN-COMPANY');
        $this->item($companyList, $productId, $alternateUomId, '125.00');

        $roles = new ProductRoleService(db: $this->db);
        $uoms = new ProductUomService(db: $this->db);
        $product = $roles->findById($productId);
        $this->assertNotNull($product);
        $validated = $uoms->validateSellingUom($product, $alternateUomId);
        $this->assertFalse($validated['is_base']);
        $this->assertSame($alternateUomId, (int) $validated['uom']['id']);

        $result = $this->pricing($roles, $uoms)->resolve(
            $productId,
            $alternateUomId,
            $companyId,
            $this->time(),
        );
        $this->assertSame('125.00', $result['unit_price']);
        $this->assertSame('PRICE_LIST_COMPANY', $result['price_source']);
        $this->assertSame($alternateUomId, $result['uom_id']);
    }

    public function testSimpleToParentIsRejectedAtomicallyWhenUomOrComboReferencesExist(): void
    {
        [$master, $operation, $baseUomId, $alternateUomId, $productGroupId] = $this->mutationFixture();
        $simpleId = $master->create($this->productPayload('GUARD-SIMPLE', $baseUomId, $productGroupId), $operation);
        $comboId = $this->product('GUARD-COMBO', $baseUomId, $productGroupId, 'COMBO', '30.00');
        $this->conversion($simpleId, $alternateUomId, '2.000000', '20.00', 'SALES');
        $this->edge($comboId, $simpleId, '1.000000');

        $this->assertDomainCode(
            fn () => $master->create([
                'code' => 'GUARD-CHILD-UOM', 'sku' => 'SKU-GUARD-CHILD-UOM', 'product_child_name' => 'Blue',
                'parent_product_id' => $simpleId, 'product_group_id' => $productGroupId,
                'base_uom_id' => $baseUomId, 'price' => '2.00',
            ], $operation),
            ProductMasterService::ERROR_CONVERSION_UOM,
        );
        $this->assertSame(0, (int) $this->db->table('products')->where('parent_product_id', $simpleId)->countAllResults());
        $this->assertSame(ProductRoleService::ROLE_SIMPLE, (new ProductRoleService(db: $this->db))->getRole($simpleId));

        $this->db->table('product_uom_conversions')->where('product_id', $simpleId)->delete();
        $this->assertDomainCode(
            fn () => $master->create([
                'code' => 'GUARD-CHILD-COMBO', 'sku' => 'SKU-GUARD-CHILD-COMBO', 'product_child_name' => 'Red',
                'parent_product_id' => $simpleId, 'product_group_id' => $productGroupId,
                'base_uom_id' => $baseUomId, 'price' => '2.00',
            ], $operation),
            ProductMasterService::ERROR_CONVERSION_COMBO,
        );
        $this->assertSame(0, (int) $this->db->table('products')->where('parent_product_id', $simpleId)->countAllResults());
        $this->assertSame(ProductRoleService::ROLE_SIMPLE, (new ProductRoleService(db: $this->db))->getRole($simpleId));
    }

    public function testNestedComboPricingExpansionAndAllocationConserveRootAmountWithoutNestedPrice(): void
    {
        [$baseUomId, $_alternateUomId, $productGroupId, $companyId] = $this->fixture('NESTED');
        $root = $this->product('NESTED-ROOT', $baseUomId, $productGroupId, 'COMBO', '100.00');
        $nested = $this->product('NESTED-COMBO', $baseUomId, $productGroupId, 'COMBO', null);
        $first = $this->product('NESTED-FIRST', $baseUomId, $productGroupId, 'STANDARD', '10.00');
        $second = $this->product('NESTED-SECOND', $baseUomId, $productGroupId, 'STANDARD', '20.00');
        $this->edge($root, $nested, '1.000000');
        $this->edge($nested, $first, '1.000000');
        $this->edge($nested, $second, '1.000000');
        $rootList = $this->priceList($companyId, 'NESTED-ROOT-LIST');
        $this->item($rootList, $root, $baseUomId, '100.00');

        [$roles, $uoms, $pricing, $expansion, $allocation] = $this->domainServices();
        $resolved = $pricing->resolve($root, $baseUomId, $companyId, $this->time());
        $flattened = $expansion->expand($root, '2');
        $allocated = $allocation->allocate([['product_id' => $root, 'quantity' => '2']], $companyId, $this->time());

        $this->assertSame('100.00', $resolved['unit_price']);
        $this->assertSame([$first, $second], array_column($flattened, 'product_id'));
        $this->assertSame([$first, $second], array_column($allocated, 'product_id'));
        $this->assertSame(['66.67', '133.33'], array_column($allocated, 'allocated_amount'));
        $this->assertSame('200.00', $this->sumMoney(array_column($allocated, 'allocated_amount')));
        $this->assertNotContains($nested, array_column($allocated, 'product_id'));
        $this->assertSame(ProductRoleService::ROLE_COMBO, $roles->getRole($nested));
        $this->assertSame($baseUomId, (int) $uoms->getBaseUom($first)['id']);
    }

    public function testPricingFallbackAndMultiRootNestedLeafAggregationRemainAmountFirst(): void
    {
        [$baseUomId, $alternateUomId, $productGroupId, $companyId] = $this->fixture('FALLBACK');
        $leaf = $this->product('FALLBACK-LEAF', $baseUomId, $productGroupId, 'STANDARD', '40.00');
        $this->conversion($leaf, $alternateUomId, '2.000000', '55.00', 'SALES');
        $companyList = $this->priceList($companyId, 'FALLBACK-COMPANY');
        $groupId = (int) $this->row($this->db->table('organizations')->where('id', $companyId))['parent_id'];
        $groupList = $this->priceList($groupId, 'FALLBACK-GROUP');
        $this->item($companyList, $leaf, $baseUomId, '999.00');
        $this->item($groupList, $leaf, $alternateUomId, '77.00');

        $pricing = $this->pricing(new ProductRoleService(db: $this->db), new ProductUomService(db: $this->db));
        $groupResult = $pricing->resolve($leaf, $alternateUomId, $companyId, $this->time());
        $this->assertSame('77.00', $groupResult['unit_price']);
        $this->assertSame('PRICE_LIST_GROUP', $groupResult['price_source']);

        $this->db->table('product_price_list_items')->where('price_list_id', $groupList)->delete();
        $defaultResult = $pricing->resolve($leaf, $alternateUomId, $companyId, $this->time());
        $this->assertSame('55.00', $defaultResult['unit_price']);
        $this->assertSame('PRODUCT_DEFAULT', $defaultResult['price_source']);

        $rootA = $this->product('FALLBACK-ROOT-A', $baseUomId, $productGroupId, 'COMBO', '90000.00');
        $rootB = $this->product('FALLBACK-ROOT-B', $baseUomId, $productGroupId, 'COMBO', '80000.00');
        $nestedA = $this->product('FALLBACK-NEST-A', $baseUomId, $productGroupId, 'COMBO', null);
        $nestedB = $this->product('FALLBACK-NEST-B', $baseUomId, $productGroupId, 'COMBO', null);
        $this->edge($rootA, $nestedA, '1.000000');
        $this->edge($nestedA, $leaf, '1.000000');
        $this->edge($rootB, $nestedB, '1.000000');
        $this->edge($nestedB, $leaf, '1.000000');
        $rootList = $this->priceList($companyId, 'FALLBACK-ROOTS');
        $this->item($rootList, $rootA, $baseUomId, '90000.00');
        $this->item($rootList, $rootB, $baseUomId, '80000.00');
        $allocation = $this->domainServices()[4];
        $result = $allocation->allocate([
            ['product_id' => $rootA, 'quantity' => '2'],
            ['product_id' => $rootB, 'quantity' => '3'],
        ], $companyId, $this->time());
        $this->assertSame(1, count($result));
        $this->assertSame('5', $result[0]['quantity']);
        $this->assertSame('420000.00', $result[0]['allocated_amount']);
        $this->assertSame('84000', $result[0]['unit_price']);
    }

    /** @return array{ProductMasterService,OperationContext,int,int,int} */
    private function mutationFixture(): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $group = $this->rowOrNull($this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null));
        if ($group === null) {
            $this->db->table('organizations')->insert([
                'type' => 'GROUP', 'code' => 'PRD090-GROUP-' . uniqid(), 'name' => 'Regression Group',
                'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
            ]);
            $group = ['id' => $this->db->insertID()];
        }
        $this->db->table('product_groups')->insert([
            'code' => 'PRD090-MG-' . uniqid(), 'name' => 'Regression Products', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $productGroupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'PRD090-MU-' . uniqid(), 'name' => 'Each', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $baseUomId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'PRD090-MA-' . uniqid(), 'name' => 'Box', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $alternateUomId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'username' => 'prd090-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('groups')->insert([
            'code' => 'PRD090-A-' . uniqid(), 'name' => 'Regression Actors', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $authGroupId = (int) $this->db->insertID();
        $groupCode = (string) $this->row($this->db->table('groups')->where('id', $authGroupId))['code'];
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-06 04:05:06',
        ]);
        foreach (['create', 'edit'] as $action) {
            $row = $this->row($this->db->table('resource_actions ra')->select('ra.id')
                ->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)
            );
            $this->db->table('group_permissions')->insert([
                'group_id' => $authGroupId, 'resource_action_id' => (int) $row['id'],
                'created_at' => '2026-10-06 04:05:06',
            ]);
        }

        return [
            new ProductMasterService(db: $this->db, history: new NoProductHistoryProvider()),
            new OperationContext($userId, $this->time(), 'prd090-' . uniqid(), false),
            $baseUomId,
            $alternateUomId,
            $productGroupId,
        ];
    }

    /** @return array{int,int,int,int} */
    private function fixture(string $label): array
    {
        $shortLabel = substr($label, 0, 12);
        $group = $this->rowOrNull($this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null));
        if ($group === null) {
            $this->db->table('organizations')->insert([
                'type' => 'GROUP', 'code' => 'PRD090-G-' . uniqid(), 'name' => 'Regression Group',
                'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
            ]);
            $group = ['id' => $this->db->insertID()];
        }
        $groupId = (int) $group['id'];
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'parent_id' => $groupId, 'code' => 'P90-C-' . $shortLabel . '-' . uniqid(),
            'name' => 'Regression Company', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $companyId = (int) $this->db->insertID();
        $this->db->table('product_groups')->insert([
            'code' => 'P90-PG-' . $shortLabel . '-' . uniqid(), 'name' => 'Regression Products',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $productGroupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'P90-PCS-' . $shortLabel . '-' . uniqid(), 'name' => 'Each',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $baseUomId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert([
            'code' => 'P90-BOX-' . $shortLabel . '-' . uniqid(), 'name' => 'Box',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return [$baseUomId, (int) $this->db->insertID(), $productGroupId, $companyId];
    }

    /** @return array{ProductRoleService,ProductUomService,PricingService,ComboExpansionService,ComboAllocationService} */
    private function domainServices(): array
    {
        $roles = new ProductRoleService(db: $this->db);
        $uoms = new ProductUomService(db: $this->db);
        $pricing = $this->pricing($roles, $uoms);
        $expansion = new ComboExpansionService(roles: $roles, productUoms: $uoms, db: $this->db);
        $allocation = new ComboAllocationService(pricing: $pricing, expansion: $expansion, roles: $roles);

        return [$roles, $uoms, $pricing, $expansion, $allocation];
    }

    private function pricing(ProductRoleService $roles, ProductUomService $uoms): PricingService
    {
        return new PricingService(
            priceLists: new PriceListRepository($this->db),
            roles: $roles,
            productUoms: $uoms,
            organizations: new OrganizationService(db: $this->db),
        );
    }

    private function product(string $label, int $baseUomId, int $productGroupId, string $type, ?string $price): int
    {
        $suffix = bin2hex(random_bytes(3));
        $codeLabel = substr($label, 0, 20);
        $this->db->table('products')->insert([
            'code' => 'PRD090-' . $codeLabel . '-' . $suffix, 'sku' => 'SKU-PRD090-' . $codeLabel . '-' . $suffix,
            'product_type' => $type, 'status' => 'ACTIVE', 'base_uom_id' => $baseUomId,
            'product_group_id' => $productGroupId, 'parent_product_id' => null,
            'product_name' => $label, 'product_child_name' => null, 'price' => $price,
            'serial_management_enabled' => 0, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @return array<string,mixed> */
    private function productPayload(string $label, int $baseUomId, int $productGroupId): array
    {
        return [
            'code' => 'PRD090-' . $label, 'sku' => 'SKU-PRD090-' . $label,
            'product_name' => $label, 'product_group_id' => $productGroupId,
            'base_uom_id' => $baseUomId, 'price' => '12.00',
        ];
    }

    private function conversion(int $productId, int $uomId, string $factor, ?string $price, string $usageType): void
    {
        $this->db->table('product_uom_conversions')->insert([
            'product_id' => $productId, 'uom_id' => $uomId, 'conversion_factor' => $factor,
            'price' => $price, 'usage_type' => $usageType, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function edge(int $comboId, int $componentId, string $quantity): void
    {
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $comboId, 'component_product_id' => $componentId,
            'quantity' => $quantity, 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function priceList(int $organizationId, string $label): int
    {
        $this->db->table('price_lists')->insert([
            'code' => 'PRD090-' . $label . '-' . uniqid(), 'name' => $label,
            'organization_id' => $organizationId, 'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00', 'priority' => 10, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function item(int $priceListId, int $productId, int $uomId, string $price): void
    {
        $this->db->table('product_price_list_items')->insert([
            'price_list_id' => $priceListId, 'product_id' => $productId,
            'uom_id' => $uomId, 'price' => $price, 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function time(): EvaluationTime
    {
        return new EvaluationTime(new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC')));
    }

    /** @param BaseBuilder $builder
     *  @return array<string,mixed>
     */
    private function row(BaseBuilder $builder): array
    {
        $result = $builder->get();
        if ($result === false) {
            throw new RuntimeException('Expected a database result.');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Expected a database row.');
        }

        return $row;
    }

    /** @param BaseBuilder $builder
     *  @return array<string,mixed>|null
     */
    private function rowOrNull(BaseBuilder $builder): ?array
    {
        $result = $builder->get();
        if ($result === false) {
            throw new RuntimeException('Expected a database result.');
        }

        return $result->getRowArray();
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

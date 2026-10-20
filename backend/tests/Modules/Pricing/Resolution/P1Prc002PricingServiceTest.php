<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\PriceList\PriceListRepository;
use App\Modules\Pricing\Resolution\PricingService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use App\Services\OrganizationService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Prc002PricingServiceTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testCompanyMustBeCompanyAndGroupRootIsAuthoritative(): void
    {
        $groupId = $this->groupRoot();
        $companyId = $this->organization('COMPANY', $groupId);
        $branchId = $this->organization('BRANCH', $companyId);
        $service = $this->service();
        $uomId = $this->uom('SCOPE-PCS');
        $productId = $this->product($uomId, 'SCOPE-PRODUCT', ['price' => '10.00']);
        $listId = $this->priceList($groupId, 'SCOPE-GROUP', ['priority' => 1]);
        $this->item($listId, $productId, $uomId, '11.00');

        $result = $service->resolve($productId, $uomId, $companyId, $this->time('2026-06-01 00:00:00'));
        $this->assertSame('PRICE_LIST_GROUP', $result['price_source']);
        $this->assertSame($listId, $result['price_list_id']);

        foreach ([$groupId, $branchId, 999999999] as $invalidCompany) {
            $this->assertDomainCode(
                fn () => $service->resolve($productId, $uomId, $invalidCompany, $this->time('2026-06-01 00:00:00')),
                'INVALID_REFERENCE',
            );
        }
    }

    public function testProductRoleStatusAndUomBoundariesAreEnforced(): void
    {
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $baseId = $this->uom('BOUNDARY-PCS');
        $salesId = $this->uom('BOUNDARY-BOX');
        $purchaseId = $this->uom('BOUNDARY-BAG');
        $inactiveUomId = $this->uom('BOUNDARY-INACTIVE', 'INACTIVE');
        $simpleId = $this->product($baseId, 'BOUNDARY-SIMPLE');
        $childRootId = $this->product($baseId, 'BOUNDARY-ROOT');
        $childId = $this->product($baseId, 'BOUNDARY-CHILD', [
            'parent_product_id' => $childRootId,
            'product_name' => null,
            'product_child_name' => 'Blue',
        ]);
        $parentId = $this->product($baseId, 'BOUNDARY-PARENT');
        $this->product($baseId, 'BOUNDARY-PARENT-CHILD', [
            'parent_product_id' => $parentId,
            'product_name' => null,
            'product_child_name' => 'Red',
        ]);
        $inactiveId = $this->product($baseId, 'BOUNDARY-INACTIVE-PRODUCT', ['status' => 'INACTIVE']);
        $comboId = $this->product($baseId, 'BOUNDARY-COMBO', ['product_type' => 'COMBO']);
        $this->conversion($simpleId, $salesId, 'SALES', 'ACTIVE', '7.25');
        $this->conversion($simpleId, $purchaseId, 'PURCHASE', 'ACTIVE', null);
        $this->conversion($simpleId, $inactiveUomId, 'BOTH', 'INACTIVE', '8.00');

        $service = $this->service();
        $time = $this->time('2026-06-01 00:00:00');
        $this->assertSame('10.00', $service->resolve($simpleId, $baseId, $companyId, $time)['unit_price']);
        $this->assertSame('7.25', $service->resolve($simpleId, $salesId, $companyId, $time)['unit_price']);
        $this->assertSame('10.00', $service->resolve($childId, $baseId, $companyId, $time)['unit_price']);
        $this->assertSame('10.00', $service->resolve($comboId, $baseId, $companyId, $time)['unit_price']);

        $this->assertDomainCode(fn () => $service->resolve($parentId, $baseId, $companyId, $time), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $service->resolve($inactiveId, $baseId, $companyId, $time), 'INACTIVE_REFERENCE');
        $this->assertDomainCode(fn () => $service->resolve($simpleId, $purchaseId, $companyId, $time), ProductUomService::ERROR_INVALID_CONVERSION);
        $this->assertDomainCode(fn () => $service->resolve($simpleId, $inactiveUomId, $companyId, $time), 'INACTIVE_REFERENCE');
        $this->assertDomainCode(fn () => $service->resolve($comboId, $salesId, $companyId, $time), ProductUomService::ERROR_INVALID_CONVERSION);
    }

    public function testCompanyPrecedesGroupAndMissingCompanyItemFallsBack(): void
    {
        $groupId = $this->groupRoot();
        $companyId = $this->organization('COMPANY', $groupId);
        $baseId = $this->uom('PRECEDENCE-PCS');
        $otherUomId = $this->uom('PRECEDENCE-BOX');
        $productId = $this->product($baseId, 'PRECEDENCE-PRODUCT', ['price' => '5.00']);
        $service = $this->service();
        $time = $this->time('2026-06-01 00:00:00');

        $groupList = $this->priceList($groupId, 'PRECEDENCE-GROUP', ['priority' => 999]);
        $this->item($groupList, $productId, $baseId, '20.00');
        $companyWrongUom = $this->priceList($companyId, 'PRECEDENCE-COMPANY-WRONG-UOM', ['priority' => 100]);
        $this->item($companyWrongUom, $productId, $otherUomId, '99.00');
        $this->assertSame('20.00', $service->resolve($productId, $baseId, $companyId, $time)['unit_price']);

        $companyExact = $this->priceList($companyId, 'PRECEDENCE-COMPANY-EXACT', ['priority' => 1]);
        $this->item($companyExact, $productId, $baseId, '10.00');
        $result = $service->resolve($productId, $baseId, $companyId, $time);
        $this->assertSame('10.00', $result['unit_price']);
        $this->assertSame('PRICE_LIST_COMPANY', $result['price_source']);
        $this->assertSame($companyExact, $result['price_list_id']);
        $this->assertSame($baseId, $result['uom_id']);
    }

    public function testIntervalBoundariesInactiveListsAndDeterministicOverlap(): void
    {
        $groupId = $this->groupRoot();
        $companyId = $this->organization('COMPANY', $groupId);
        $baseId = $this->uom('INTERVAL-PCS');
        $productId = $this->product($baseId, 'INTERVAL-PRODUCT');
        $service = $this->service();

        $early = $this->priceList($companyId, 'INTERVAL-EARLY', [
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => '2026-12-31 00:00:00', 'priority' => 5,
        ]);
        $late = $this->priceList($companyId, 'INTERVAL-LATE', [
            'effective_from' => '2026-06-01 00:00:00', 'effective_to' => null, 'priority' => 5,
        ]);
        $inactive = $this->priceList($companyId, 'INTERVAL-INACTIVE', [
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => null, 'priority' => 999, 'status' => 'INACTIVE',
        ]);
        $tieFirst = $this->priceList($companyId, 'INTERVAL-TIE-FIRST', [
            'effective_from' => '2026-04-01 00:00:00', 'effective_to' => '2026-05-01 00:00:00', 'priority' => 7,
        ]);
        $tieSecond = $this->priceList($companyId, 'INTERVAL-TIE-SECOND', [
            'effective_from' => '2026-04-01 00:00:00', 'effective_to' => '2026-05-01 00:00:00', 'priority' => 7,
        ]);
        $this->item($early, $productId, $baseId, '11.00');
        $this->item($late, $productId, $baseId, '12.00');
        $this->item($inactive, $productId, $baseId, '99.00');
        $this->item($tieFirst, $productId, $baseId, '14.00');
        $this->item($tieSecond, $productId, $baseId, '15.00');

        $atStart = $service->resolve($productId, $baseId, $companyId, $this->time('2026-01-01 00:00:00'));
        $this->assertSame($early, $atStart['price_list_id']);
        $this->assertSame('11.00', $atStart['unit_price']);
        $atTie = $service->resolve($productId, $baseId, $companyId, $this->time('2026-04-15 00:00:00'));
        $this->assertSame($tieFirst, $atTie['price_list_id']);
        $this->assertSame('14.00', $atTie['unit_price']);
        $atLateStart = $service->resolve($productId, $baseId, $companyId, $this->time('2026-06-01 00:00:00'));
        $this->assertSame($late, $atLateStart['price_list_id']);
        $this->assertSame('12.00', $atLateStart['unit_price']);
        $atEnd = $service->resolve($productId, $baseId, $companyId, $this->time('2026-12-31 00:00:00'));
        $this->assertSame($late, $atEnd['price_list_id']);
    }

    public function testDefaultPricesZeroAndMissingPricesFailClosed(): void
    {
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $baseId = $this->uom('DEFAULT-PCS');
        $altId = $this->uom('DEFAULT-BOX');
        $simpleId = $this->product($baseId, 'DEFAULT-SIMPLE', ['price' => '0.00']);
        $missingBaseId = $this->product($baseId, 'DEFAULT-MISSING-BASE', ['price' => null]);
        $missingAltId = $this->product($baseId, 'DEFAULT-MISSING-ALT', ['price' => '9.00']);
        $this->conversion($simpleId, $altId, 'SALES', 'ACTIVE', '3.25');
        $this->conversion($missingAltId, $altId, 'SALES', 'ACTIVE', null);
        $service = $this->service();
        $time = $this->time('2026-06-01 00:00:00');

        $zero = $service->resolve($simpleId, $baseId, $companyId, $time);
        $this->assertSame('0.00', $zero['unit_price']);
        $this->assertSame('PRODUCT_DEFAULT', $zero['price_source']);
        $this->assertNull($zero['price_list_id']);
        $this->assertSame('3.25', $service->resolve($simpleId, $altId, $companyId, $time)['unit_price']);
        $this->assertDomainCode(fn () => $service->resolve($missingBaseId, $baseId, $companyId, $time), PricingService::ERROR_NO_VALID_PRICE);
        $this->assertDomainCode(fn () => $service->resolve($missingAltId, $altId, $companyId, $time), PricingService::ERROR_NO_VALID_PRICE);
    }

    public function testOmittedEvaluationTimeCapturesClockOnce(): void
    {
        $groupId = $this->groupRoot();
        $companyId = $this->organization('COMPANY', $groupId);
        $baseId = $this->uom('CLOCK-PCS');
        $productId = $this->product($baseId, 'CLOCK-PRODUCT');
        $listId = $this->priceList($groupId, 'CLOCK-GROUP', [
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => null,
        ]);
        $this->item($listId, $productId, $baseId, '13.00');
        $clock = new CountingClock(new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC')));
        $service = $this->service($clock);

        $result = $service->resolve($productId, $baseId, $companyId);
        $this->assertSame(1, $clock->calls);
        $this->assertSame('2026-06-01 00:00:00', $clock->last->format('Y-m-d H:i:s'));
        $this->assertSame('13.00', $result['unit_price']);
    }

    private function service(?Clock $clock = null): PricingService
    {
        return new PricingService(
            priceLists: new PriceListRepository($this->db),
            roles: new ProductRoleService(db: $this->db),
            productUoms: new ProductUomService(db: $this->db),
            organizations: new OrganizationService(db: $this->db),
            clock: $clock,
        );
    }

    private function groupRoot(): int
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'PRC002-GROUP-' . uniqid(), 'name' => 'Pricing Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function organization(string $type, int $parentId): int
    {
        $this->db->table('organizations')->insert([
            'type' => $type, 'parent_id' => $parentId, 'code' => 'PRC002-' . $type . '-' . uniqid(),
            'name' => $type . ' Pricing', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function uom(string $code, string $status = 'ACTIVE'): int
    {
        $this->db->table('product_uoms')->insert([
            'code' => $code . '-' . substr(uniqid(), -5), 'name' => $code,
            'status' => $status, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @param array<string,mixed> $overrides */
    private function product(int $baseUomId, string $code, array $overrides = []): int
    {
        $this->db->table('products')->insert(array_merge([
            'code' => $code . '-' . substr(uniqid(), -6), 'sku' => 'SKU-' . $code . '-' . substr(uniqid(), -5),
            'product_name' => $code, 'product_child_name' => null, 'parent_product_id' => null,
            'product_type' => 'STANDARD', 'base_uom_id' => $baseUomId, 'price' => '10.00',
            'serial_management_enabled' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function conversion(int $productId, int $uomId, string $usageType, string $status, ?string $price): int
    {
        $this->db->table('product_uom_conversions')->insert([
            'product_id' => $productId, 'uom_id' => $uomId, 'conversion_factor' => '2.000000',
            'price' => $price, 'usage_type' => $usageType, 'status' => $status,
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @param array<string,mixed> $overrides */
    private function priceList(int $organizationId, string $code, array $overrides = []): int
    {
        $this->db->table('price_lists')->insert(array_merge([
            'code' => $code . '-' . substr(uniqid(), -6), 'name' => $code,
            'organization_id' => $organizationId, 'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00', 'priority' => 0, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function item(int $priceListId, int $productId, int $uomId, string $price): void
    {
        $this->db->table('product_price_list_items')->insert([
            'price_list_id' => $priceListId, 'product_id' => $productId,
            'uom_id' => $uomId, 'price' => $price, 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function time(string $value): EvaluationTime
    {
        return new EvaluationTime(new DateTimeImmutable($value, new DateTimeZone('UTC')));
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

final class CountingClock implements Clock
{
    public int $calls = 0;
    public DateTimeImmutable $last;

    public function __construct(private readonly DateTimeImmutable $time)
    {
        $this->last = $time;
    }

    public function now(): DateTimeImmutable
    {
        $this->calls++;
        $this->last = $this->time;

        return $this->time;
    }
}

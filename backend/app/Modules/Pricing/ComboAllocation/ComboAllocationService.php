<?php

declare(strict_types=1);

namespace App\Modules\Pricing\ComboAllocation;

use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\Resolution\PricingService;
use App\Modules\Product\Combo\ComboExpansionService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;

/** Read-only allocation of directly selected COMBO selling amounts to leaves. */
final class ComboAllocationService
{
    public const ERROR_INVALID_ROOT = 'COMBO_ALLOCATION_INVALID_ROOT';
    public const ERROR_INVALID_QUANTITY = 'COMBO_ALLOCATION_INVALID_QUANTITY';
    public const ERROR_INVALID_LEAF_PRICE = 'COMBO_ALLOCATION_INVALID_LEAF_PRICE';
    public const ERROR_TOTAL_WEIGHT = 'COMBO_ALLOCATION_TOTAL_WEIGHT_INVALID';

    private const MONEY_SCALE = 2;
    private const DIVISION_SCALE = 40;

    public function __construct(
        private readonly ?PricingService $pricing = null,
        private readonly ?ComboExpansionService $expansion = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?Clock $clock = null,
    ) {
    }

    /**
     * Allocate each directly selected COMBO and aggregate equal leaves.
     *
     * @param list<mixed> $selectedRoots
     * @return list<array{product_id:int,base_uom_id:int,quantity:string,allocated_amount:string,unit_price:string}>
     */
    public function allocate(
        array $selectedRoots,
        mixed $companyOrganizationId,
        ?EvaluationTime $evaluationTime = null,
    ): array {
        $companyId = PositiveId::normalize($companyOrganizationId, 'company_organization_id', 'INVALID_REFERENCE');
        $evaluationTime ??= EvaluationTime::capture($this->clock ?? service('clock'));
        $aggregated = [];

        foreach ($selectedRoots as $index => $selectedRoot) {
            if (! is_array($selectedRoot)) {
                throw new DomainException(self::ERROR_INVALID_ROOT, ['index' => $index]);
            }

            $rootId = PositiveId::normalize(
                $selectedRoot['product_id'] ?? null,
                'product_id',
                self::ERROR_INVALID_ROOT,
            );
            $rootQuantity = $this->positiveQuantity($selectedRoot['quantity'] ?? null, 'quantity');
            $root = $this->roles()->findById($rootId);
            if ($root === null) {
                throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $rootId]);
            }
            if ($this->roles()->getRole($root) !== ProductRoleService::ROLE_COMBO) {
                throw new DomainException(self::ERROR_INVALID_ROOT, ['product_id' => $rootId]);
            }
            if (! $this->roles()->isSelectable($root)) {
                throw new DomainException(ComboExpansionService::ERROR_INACTIVE_REFERENCE, ['product_id' => $rootId]);
            }
            $baseUomId = PositiveId::normalize(
                $root['base_uom_id'] ?? null,
                'base_uom_id',
                ProductUomService::ERROR_INVALID_REFERENCE,
            );

            // A root is priced once. Nested COMBOs are seen only by expansion.
            $rootPrice = $this->pricing()->resolve($rootId, $baseUomId, $companyId, $evaluationTime);
            $leaves = $this->expansion()->expand($rootId, $rootQuantity);
            $rootAllocations = $this->allocateRoot($leaves, $rootPrice['unit_price'], $rootQuantity);

            foreach ($rootAllocations as $allocation) {
                $key = $allocation['product_id'] . ':' . $allocation['base_uom_id'];
                if (! isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'product_id' => $allocation['product_id'],
                        'base_uom_id' => $allocation['base_uom_id'],
                        'quantity' => $allocation['quantity'],
                        'allocated_amount' => $allocation['allocated_amount'],
                    ];
                    continue;
                }
                $aggregated[$key]['quantity'] = $this->addExact(
                    $aggregated[$key]['quantity'],
                    $allocation['quantity'],
                );
                $aggregated[$key]['allocated_amount'] = $this->addMoney(
                    $aggregated[$key]['allocated_amount'],
                    $allocation['allocated_amount'],
                );
            }
        }

        $result = array_values($aggregated);
        usort($result, static function (array $left, array $right): int {
            return [$left['product_id'], $left['base_uom_id']] <=> [$right['product_id'], $right['base_uom_id']];
        });

        foreach ($result as &$row) {
            $row['quantity'] = $this->canonicalDecimal($row['quantity']);
            $row['allocated_amount'] = $this->money($row['allocated_amount']);
            $row['unit_price'] = $this->canonicalDecimal(
                (new Decimal($row['allocated_amount']))->divide($row['quantity'], self::DIVISION_SCALE)->value,
            );
        }
        unset($row);

        return $result;
    }

    /**
     * @param list<array{product_id:int,base_uom_id:int,quantity:string}> $leaves
     * @return list<array{product_id:int,base_uom_id:int,quantity:string,allocated_amount:string}>
     */
    private function allocateRoot(array $leaves, string $unitPrice, string $rootQuantity): array
    {
        $weighted = [];
        $totalWeight = '0';
        foreach ($leaves as $leaf) {
            $product = $this->roles()->findById($leaf['product_id']);
            if ($product === null) {
                throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $leaf['product_id']]);
            }
            if (! in_array($this->roles()->getRole($product), [
                ProductRoleService::ROLE_SIMPLE,
                ProductRoleService::ROLE_CHILD,
            ], true)) {
                throw new DomainException(self::ERROR_INVALID_ROOT, [
                    'product_id' => $leaf['product_id'],
                    'reason' => 'non_leaf',
                ]);
            }
            $baseUomId = PositiveId::normalize(
                $product['base_uom_id'] ?? null,
                'base_uom_id',
                ProductUomService::ERROR_INVALID_REFERENCE,
            );
            if ($baseUomId !== $leaf['base_uom_id']) {
                throw new DomainException(ProductUomService::ERROR_INVALID_REFERENCE, [
                    'product_id' => $leaf['product_id'],
                    'base_uom_id' => $leaf['base_uom_id'],
                ]);
            }
            $price = $product['price'] ?? null;
            if (! $this->isDecimal($price)) {
                throw new DomainException(self::ERROR_INVALID_LEAF_PRICE, ['product_id' => $leaf['product_id']]);
            }
            $weight = $this->multiplyExact($leaf['quantity'], (string) $price);
            $weighted[] = [
                'product_id' => $leaf['product_id'],
                'base_uom_id' => $leaf['base_uom_id'],
                'quantity' => $leaf['quantity'],
                'weight' => $weight,
            ];
            $totalWeight = $this->addExact($totalWeight, $weight);
        }

        usort($weighted, static function (array $left, array $right): int {
            return [$left['product_id'], $left['base_uom_id']] <=> [$right['product_id'], $right['base_uom_id']];
        });

        if ($weighted === [] || (new Decimal($totalWeight))->compare('0', $this->fractionScale($totalWeight)) <= 0) {
            throw new DomainException(self::ERROR_TOTAL_WEIGHT);
        }

        $comboTotal = $this->multiplyExact($unitPrice, $rootQuantity);
        $roundedTotal = $this->money($comboTotal);
        $allocated = [];
        $roundedSum = '0.00';
        foreach ($weighted as $entry) {
            $raw = (new Decimal($comboTotal))
                ->multiply($entry['weight'], $this->fractionScale($comboTotal) + $this->fractionScale($entry['weight']))
                ->divide($totalWeight, self::DIVISION_SCALE);
            $amount = $raw->round(self::MONEY_SCALE)->value;
            $allocated[] = [
                'product_id' => $entry['product_id'],
                'base_uom_id' => $entry['base_uom_id'],
                'quantity' => $entry['quantity'],
                'allocated_amount' => $amount,
            ];
            $roundedSum = $this->addMoney($roundedSum, $amount);
        }

        $last = count($allocated) - 1;
        $residual = (new Decimal($roundedTotal))->subtract($roundedSum, self::MONEY_SCALE)->value;
        $allocated[$last]['allocated_amount'] = $this->money(
            (new Decimal($allocated[$last]['allocated_amount']))->add($residual, self::MONEY_SCALE)->value,
        );

        $check = '0.00';
        foreach ($allocated as $entry) {
            $check = $this->addMoney($check, $entry['allocated_amount']);
        }
        if ((new Decimal($check))->compare($roundedTotal, self::MONEY_SCALE) !== 0) {
            throw new DomainException(self::ERROR_TOTAL_WEIGHT, ['reason' => 'amount_conservation']);
        }

        return $allocated;
    }

    private function pricing(): PricingService
    {
        return $this->pricing ?? new PricingService(roles: $this->roles());
    }

    private function expansion(): ComboExpansionService
    {
        return $this->expansion ?? new ComboExpansionService(roles: $this->roles());
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService();
    }

    private function positiveQuantity(mixed $value, string $field): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_QUANTITY, ['field' => $field]);
        }
        if ((new Decimal($value))->compare('0', $this->fractionScale($value)) <= 0) {
            throw new DomainException(self::ERROR_INVALID_QUANTITY, ['field' => $field]);
        }

        return $value;
    }

    private function isDecimal(mixed $value): bool
    {
        return (is_int($value) || is_string($value))
            && preg_match('/\A(?:0|[1-9]\d*)(?:\.\d+)?\z/', (string) $value) === 1;
    }

    private function multiplyExact(string $left, string $right): string
    {
        return $this->canonicalDecimal((new Decimal($left))->multiply($right, $this->fractionScale($left) + $this->fractionScale($right))->value);
    }

    private function addExact(string $left, string $right): string
    {
        return $this->canonicalDecimal((new Decimal($left))->add($right, max($this->fractionScale($left), $this->fractionScale($right)))->value);
    }

    private function addMoney(string $left, string $right): string
    {
        return $this->money((new Decimal($left))->add($right, self::MONEY_SCALE)->value);
    }

    private function money(string $value): string
    {
        return (new Decimal($value))->round(self::MONEY_SCALE)->value;
    }

    private function fractionScale(string $value): int
    {
        $position = strpos($value, '.');

        return $position === false ? 0 : strlen($value) - $position - 1;
    }

    private function canonicalDecimal(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $result = $integer . ($fraction === '' ? '' : '.' . $fraction);

        return $negative && $result !== '0' ? '-' . $result : $result;
    }
}

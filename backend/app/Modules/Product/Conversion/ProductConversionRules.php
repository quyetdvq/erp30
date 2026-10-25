<?php

declare(strict_types=1);

namespace App\Modules\Product\Conversion;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Pure API-050 alternate UOM validation. Never converts DECIMAL values to float. */
final class ProductConversionRules
{
    public const MAX_ITEMS = 250;
    private const FIELDS = [
        'uom_id', 'conversion_factor', 'price', 'package_gross_weight_g',
        'package_length_mm', 'package_width_mm', 'package_height_mm', 'usage_type', 'status',
    ];
    private const OPTIONAL_DECIMAL_SCALE = [
        'price' => 2, 'package_gross_weight_g' => 3,
        'package_length_mm' => 3, 'package_width_mm' => 3, 'package_height_mm' => 3,
    ];

    public static function productId(int|string $raw): string
    {
        try { return PositiveId::value($raw); }
        catch (InvalidArgumentException $e) { throw new DomainException('PRODUCT_NOT_FOUND', ['field'=>'id'], $e); }
    }

    /**
     * Accept both frozen JSON forms: a bare array or {"items": [...]}; the
     * legacy evaluation-organization hint is validated separately, never a grant.
     *
     * @param array<mixed> $body
     * @return array{items:list<array<string,mixed>>,hint:mixed}
     */
    public static function request(array $body): array
    {
        $hint = null;
        if (array_is_list($body)) {
            $raw = $body;
        } else {
            foreach (array_keys($body) as $field) {
                if (!in_array($field, ['items', 'evaluation_organization_id'], true)) {
                    throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>(string)$field]);
                }
            }
            if (!array_key_exists('items', $body) || !is_array($body['items']) || !array_is_list($body['items'])) {
                throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>'items']);
            }
            $raw = $body['items'];
            $hint = $body['evaluation_organization_id'] ?? null;
        }
        if (count($raw) > self::MAX_ITEMS) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>'items']);
        }
        $items = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>'items']);
            }
            $normalized = self::item($row);
            $uomId = $normalized['uom_id'];
            if (isset($seen[$uomId])) {
                throw new DomainException('PRODUCT_CONVERSION_DUPLICATE', ['field'=>'uom_id']);
            }
            $seen[$uomId] = true;
            $items[] = $normalized;
        }
        return ['items'=>$items, 'hint'=>$hint];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function item(array $row): array
    {
        foreach (array_keys($row) as $field) {
            if (!in_array($field, self::FIELDS, true)) {
                throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>(string)$field]);
            }
        }
        if (!array_key_exists('uom_id', $row)) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_UOM', ['field'=>'uom_id']);
        }
        try {
            $value = $row['uom_id'];
            if (!is_int($value) && !is_string($value)) throw new InvalidArgumentException('UOM ID type');
            $row['uom_id'] = PositiveId::value($value);
        } catch (InvalidArgumentException $e) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_UOM', ['field'=>'uom_id'], $e);
        }
        if (!array_key_exists('conversion_factor', $row)) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_DECIMAL', ['field'=>'conversion_factor']);
        }
        $row['conversion_factor'] = self::decimal($row['conversion_factor'], 'conversion_factor', 6, false);
        if (preg_match('/\A0+(?:\.0+)?\z/D', $row['conversion_factor']) === 1) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>'conversion_factor']);
        }
        foreach (self::OPTIONAL_DECIMAL_SCALE as $field=>$scale) {
            if (array_key_exists($field, $row)) {
                $row[$field] = self::decimal($row[$field], $field, $scale, true);
            }
        }
        $row['usage_type'] = self::choice($row['usage_type'] ?? 'BOTH', ['PURCHASE','SALES','BOTH'], 'usage_type');
        $row['status'] = self::choice($row['status'] ?? 'ACTIVE', ['ACTIVE','INACTIVE'], 'status');
        return $row;
    }

    /** @param array<string,mixed> $merged */
    public static function assertPrice(array $merged): void
    {
        if (in_array($merged['usage_type'] ?? '', ['SALES','BOTH'], true)
            && ($merged['price'] ?? null) === null) {
            throw new DomainException('PRODUCT_CONVERSION_PRICE_REQUIRED', ['field'=>'price']);
        }
        if (($merged['price'] ?? null) !== null && str_starts_with((string)$merged['price'], '-')) {
            // -0 has the same numeric value as 0; reject negative values only.
            if (preg_match('/\A-0+(?:\.0+)?\z/D', (string)$merged['price']) !== 1) {
                throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>'price']);
            }
        }
    }

    /** @param array<string,mixed> $product */
    public static function assertEligible(array $product, bool $hasChildren): void
    {
        if (strtoupper((string)($product['product_type'] ?? '')) !== 'STANDARD' || $hasChildren) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_ROLE');
        }
        if (($product['base_uom_id'] ?? null) === null) {
            throw new DomainException('PRODUCT_BASE_UOM_REQUIRED');
        }
    }

    private static function choice(mixed $value, array $choices, string $field): string
    {
        if (!is_string($value) || !in_array(strtoupper(trim($value)), $choices, true)) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>$field]);
        }
        return strtoupper(trim($value));
    }

    private static function decimal(mixed $value, string $field, int $scale, bool $nullable): ?string
    {
        if ($nullable && ($value === null || $value === '')) return null;
        if (!is_int($value) && !is_string($value)) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_DECIMAL', ['field'=>$field]);
        }
        $text = (string)$value;
        if (preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.([0-9]+))?\z/D', $text, $match) !== 1
            || strlen(explode('.', ltrim($text, '-'))[0]) > 18-$scale
            || (isset($match[1]) && strlen($match[1]) > $scale)) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID_DECIMAL', ['field'=>$field]);
        }
        if (!$nullable && str_starts_with($text, '-')) {
            throw new DomainException('PRODUCT_CONVERSION_INVALID', ['field'=>$field]);
        }
        return $text;
    }
}

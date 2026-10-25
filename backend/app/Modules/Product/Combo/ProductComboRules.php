<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Pure M7.2 frozen COMBO replacement rules; exact DECIMAL(18,6), no floats. */
final class ProductComboRules
{
    public const MAX_ITEMS = 250;

    public static function productId(int|string $raw): string
    {
        try { return PositiveId::value($raw); }
        catch (InvalidArgumentException $e) { throw new DomainException('PRODUCT_NOT_FOUND', ['field'=>'id'], $e); }
    }

    /** @param array<mixed> $body
     *  @return array{items:list<array{component_product_id:string,quantity:string}>,hint:mixed}
     */
    public static function request(array $body): array
    {
        $hint = null;
        if (array_is_list($body)) {
            $raw = $body;
        } else {
            foreach (array_keys($body) as $key) {
                if (!in_array($key, ['items', 'evaluation_organization_id'], true)) {
                    throw new DomainException('COMBO_INVALID_ITEM', ['field'=>(string)$key]);
                }
            }
            if (!array_key_exists('items', $body) || !is_array($body['items']) || !array_is_list($body['items'])) {
                throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'items']);
            }
            $raw = $body['items'];
            $hint = $body['evaluation_organization_id'] ?? null;
        }
        if (count($raw) > self::MAX_ITEMS) throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'items']);
        $seen = []; $normalized = [];
        foreach ($raw as $row) {
            if (!is_array($row) || array_is_list($row) || count($row) !== 2
                || !array_key_exists('component_product_id', $row) || !array_key_exists('quantity', $row)) {
                throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'items']);
            }
            try {
                $value = $row['component_product_id'];
                if (!is_int($value) && !is_string($value)) throw new InvalidArgumentException('Component ID type');
                $id = PositiveId::value($value);
            } catch (InvalidArgumentException $e) {
                throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'component_product_id'], $e);
            }
            if (isset($seen[$id])) throw new DomainException('COMBO_DUPLICATE', ['field'=>'component_product_id']);
            $seen[$id] = true;
            $quantity = $row['quantity'];
            if (!is_int($quantity) && !is_string($quantity)) {
                throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'quantity']);
            }
            $quantity = (string)$quantity;
            if (preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $quantity) !== 1
                || preg_match('/\A0+(?:\.0+)?\z/D', $quantity) === 1) {
                throw new DomainException('COMBO_INVALID_ITEM', ['field'=>'quantity']);
            }
            $normalized[] = ['component_product_id'=>$id,'quantity'=>$quantity];
        }
        return ['items'=>$normalized,'hint'=>$hint];
    }

    /** Decimal equality without floats. */
    public static function sameQuantity(string $left, string $right): bool
    {
        return self::scaled($left) === self::scaled($right);
    }

    private static function scaled(string $value): string
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $value) !== 1) {
            throw new DomainException('COMBO_MUTATION_FAILED', ['operation'=>'corrupt_decimal']);
        }
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return $integer.'.'.str_pad($fraction, 6, '0');
    }
}

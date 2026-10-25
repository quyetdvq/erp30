<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use InvalidArgumentException;

/** M7.4 immutable batch limits and order-preserving, non-disclosing projections. */
final class ProductRelationshipRules
{
    public const MAX_DETAILS = 100;
    public const MAX_RELATIONS = 50;
    public const MAX_SALES_UOMS = 50;

    /** @param array<string,mixed> $body @return list<string> */
    public static function ids(array $body, int $maximum): array
    {
        if (!in_array($maximum, [self::MAX_DETAILS, self::MAX_RELATIONS], true)) {
            throw new InvalidArgumentException('Unreviewed Product relationship batch size.');
        }
        $ids = ProductLookupRules::ids($body, 'product_ids');
        if (count($body['product_ids'] ?? []) > $maximum) {
            throw new InvalidArgumentException('Product relationship batch is too large.');
        }
        return $ids;
    }

    /** @param array<string,mixed> $body */
    public static function limitPerProduct(array $body): int
    {
        $limit = array_key_exists('limit_per_product', $body) ? $body['limit_per_product'] : 2;
        if ((!is_int($limit) && !is_string($limit))
            || preg_match('/\A[0-9]+\z/D', (string)$limit) !== 1
            || strlen((string)$limit) > 2 || (int)$limit < 1 || (int)$limit > 5) {
            throw new InvalidArgumentException('Invalid relation preview limit.');
        }
        return (int)$limit;
    }

    /** @param list<string> $requested @param array<string,array<string,mixed>> $visible
     * @return list<array<string,mixed>>
     */
    public static function resolvedDetails(array $requested, array $visible): array
    {
        $result = [];
        foreach ($requested as $id) {
            if (isset($visible[$id])) $result[] = $visible[$id];
        }
        return $result;
    }

    /** @param list<string> $requested @param array<string,string> $ownerRoles
     * @param array<string,array{total:int,products:list<array<string,mixed>>}> $children
     * @return list<array{product_id:string,total:int,products:list<array<string,mixed>>}>
     */
    public static function relationPreviews(array $requested, array $ownerRoles, array $children): array
    {
        $out = [];
        foreach ($requested as $id) {
            if (!in_array($ownerRoles[$id] ?? '', ['PARENT', 'COMBO'], true)) continue;
            $preview = $children[$id] ?? ['total' => 0, 'products' => []];
            $out[] = ['product_id' => $id, 'total' => $preview['total'], 'products' => $preview['products']];
        }
        return $out;
    }

    /** @param list<string> $requested @param array<string,bool> $visible
     * @param array<string,list<array{id:string,code:string}>> $options
     * @return list<array{product_id:string,options:list<array{id:string,code:string}>}>
     */
    public static function salesOptions(array $requested, array $visible, array $options): array
    {
        $out = [];
        foreach ($requested as $id) {
            if (!isset($visible[$id])) continue;
            $out[] = ['product_id'=>$id, 'options'=>$options[$id] ?? []];
        }
        return $out;
    }
}

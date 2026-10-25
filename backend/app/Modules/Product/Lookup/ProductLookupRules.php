<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Pure rules for the three frozen M6.4A Product selector/label APIs. */
final class ProductLookupRules
{
    /** @param array<string,mixed> $raw
     *  @return array{purpose:string,q:string,page:int,page_size:int}
     */
    public static function optionsQuery(array $raw): array
    {
        foreach ($raw as $key => $_) {
            if (!in_array($key, ['purpose', 'q', 'page', 'page_size'], true)) {
                throw new InvalidArgumentException('Unsupported Product lookup query key.');
            }
        }
        $purpose = $raw['purpose'] ?? 'price_list_item';
        if (!is_string($purpose) || !in_array($purpose, ['price_list_item', 'combo_component', 'parent_product'], true)) {
            throw new InvalidArgumentException('Invalid Product selector purpose.');
        }
        $q = $raw['q'] ?? '';
        if (!is_string($q)) {
            throw new InvalidArgumentException('Invalid Product selector search.');
        }
        $length = preg_match_all('/./us', trim($q));
        if ($length === false || $length > 100) {
            throw new InvalidArgumentException('Invalid Product selector search.');
        }
        return [
            'purpose' => $purpose,
            'q' => trim($q),
            'page' => self::boundedInteger($raw['page'] ?? 1, 1, 100000),
            'page_size' => self::boundedInteger($raw['page_size'] ?? 20, 1, 50),
        ];
    }

    /**
     * @param array<string,mixed> $body
     * @return list<string> Unique canonical BIGINT UNSIGNED IDs, in request order.
     */
    public static function ids(array $body, string $field): array
    {
        if (!in_array($field, ['product_ids', 'uom_ids'], true)) {
            throw new InvalidArgumentException('Unsupported batch lookup field.');
        }
        $raw = $body[$field] ?? null;
        if (!is_array($raw) || !array_is_list($raw) || count($raw) > 100) {
            throw new InvalidArgumentException('Lookup IDs must be a list of at most 100.');
        }
        $seen = [];
        $ids = [];
        foreach ($raw as $value) {
            if (!is_int($value) && !is_string($value)) {
                throw new InvalidArgumentException('Lookup IDs must be unsigned integer strings.');
            }
            try { $id = PositiveId::value($value); }
            catch (InvalidArgumentException $error) {
                throw new InvalidArgumentException('Invalid lookup ID.', 0, $error);
            }
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $ids[] = $id;
        }
        return $ids;
    }

    /** @param list<array<string,mixed>> $visible Already per-row PBAC-authorized projection.
     *  @param array{purpose:string,q:string,page:int,page_size:int} $query
     *  @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public static function options(array $visible, array $query): array
    {
        $eligible = $query['purpose'] === 'parent_product' ? ['PARENT', 'SIMPLE'] : ['SIMPLE', 'CHILD', 'COMBO'];
        $needle = self::lower($query['q']);
        $selected = [];
        foreach ($visible as $row) {
            if (($row['status'] ?? null) !== 'ACTIVE' || !in_array($row['role'] ?? '', $eligible, true)) continue;
            if ($needle !== '') {
                $fields = ['product_code', 'sku', 'product_name', 'product_child_name', 'full_name'];
                $matched = false;
                foreach ($fields as $field) {
                    if (isset($row[$field]) && str_contains(self::lower((string)$row[$field]), $needle)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) continue;
            }
            $selected[] = $row;
        }
        usort($selected, static function(array $a, array $b): int {
            $one = (string)$a['id'];
            $two = (string)$b['id'];
            return (strlen($one) <=> strlen($two)) ?: strcmp($one, $two);
        });
        $total = count($selected);
        $data = array_map(self::option(...), array_slice($selected, ($query['page'] - 1) * $query['page_size'], $query['page_size']));
        return ['data'=>$data, 'page'=>$query['page'], 'page_size'=>$query['page_size'], 'total'=>$total];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function option(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : null,
            'full_name' => (string)$row['full_name'],
            'role' => (string)$row['role'],
            'status' => (string)$row['status'],
            'base_uom_id' => isset($row['base_uom_id']) ? (string)$row['base_uom_id'] : null,
            'selectable' => (bool)$row['selectable'],
        ];
    }

    /** @param list<string> $ids @param array<string,array<string,mixed>> $authorizedById
     *  @return list<array<string,mixed>>
     */
    public static function resolveProducts(array $ids, array $authorizedById): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($authorizedById[$id])) $out[] = self::option($authorizedById[$id]);
        }
        return $out;
    }

    /** @param list<string> $ids @param array<string,array<string,mixed>> $authorizedById
     *  @return list<array{id:string,code:string,status:string}>
     */
    public static function resolveUoms(array $ids, array $authorizedById): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (!isset($authorizedById[$id])) continue;
            $row = $authorizedById[$id];
            $out[] = ['id'=>(string)$row['id'], 'code'=>(string)$row['code'], 'status'=>(string)$row['status']];
        }
        return $out;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function boundedInteger(mixed $input, int $min, int $max): int
    {
        if ((!is_int($input) && !is_string($input)) || preg_match('/\A[0-9]+\z/D', (string)$input) !== 1
            || strlen((string)$input) > 10 || (float)$input < $min || (float)$input > $max) {
            throw new InvalidArgumentException('Invalid Product selector pagination.');
        }
        return (int)$input;
    }
}

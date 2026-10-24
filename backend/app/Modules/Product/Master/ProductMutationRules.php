<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Validated API-050 Product Master mutation fields; never uses client state/scope for authorization. */
final class ProductMutationRules
{
    private const FIELDS = [
        'product_code', 'code', 'sku', 'barcode', 'parent_product_id', 'product_name',
        'product_child_name', 'product_group_id', 'product_type', 'base_uom_id',
        'price', 'net_weight_g', 'package_gross_weight_g', 'package_length_mm',
        'package_width_mm', 'package_height_mm', 'description', 'manual_standard_cost',
        'serial_management_enabled', 'status', 'numbering_series_code',
        'evaluation_organization_id', 'id', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
    private const DECIMALS = ['price'=>2, 'manual_standard_cost'=>2, 'net_weight_g'=>3,
        'package_gross_weight_g'=>3, 'package_length_mm'=>3, 'package_width_mm'=>3, 'package_height_mm'=>3];

    public static function id(string|int $raw): string
    {
        try { return PositiveId::value($raw); }
        catch (InvalidArgumentException $e) { throw new DomainException('PRODUCT_NOT_FOUND', ['field'=>'id'], $e); }
    }

    /** @param array<string,mixed> $input @return array{fields:array<string,mixed>,series:?string,combo:?array} */
    public static function normalize(array $input, bool $create): array
    {
        $hasCombo = array_key_exists('combo_items', $input);
        $combo = $hasCombo ? $input['combo_items'] : null;
        unset($input['combo_items']);
        if ($hasCombo && (! $create || ! is_array($combo) || ! array_is_list($combo)
            || strtoupper((string) ($input['product_type'] ?? '')) !== 'COMBO')) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'combo_items']);
        }
        foreach (array_keys($input) as $field) {
            if (! in_array($field, self::FIELDS, true) || (! $create && $field === 'numbering_series_code')) {
                throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>(string)$field]);
            }
        }
        if (! $create && array_key_exists('status', $input)) {
            throw new DomainException('PRODUCT_STATUS_REQUIRES_SETSTATE', ['field'=>'status']);
        }
        // Historical API-050 tolerates but never trusts client-supplied infrastructure columns.
        foreach (['id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'evaluation_organization_id'] as $field) {
            unset($input[$field]);
        }
        $series = null;
        if ($create && array_key_exists('numbering_series_code', $input)) {
            $series = self::text($input['numbering_series_code'], 'numbering_series_code', 100);
        }
        unset($input['numbering_series_code']);
        if (array_key_exists('product_code', $input)) {
            if (array_key_exists('code', $input) && $input['code'] !== $input['product_code']) {
                throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>'product_code']);
            }
            $input['code'] = $input['product_code'];
        }
        unset($input['product_code']);
        foreach (['code'=>50, 'sku'=>100, 'barcode'=>100, 'product_name'=>255, 'product_child_name'=>255] as $field=>$max) {
            if (array_key_exists($field, $input)) $input[$field] = self::text($input[$field], $field, $max);
        }
        foreach (['parent_product_id', 'product_group_id', 'base_uom_id'] as $field) {
            if (array_key_exists($field, $input)) {
                if ($input[$field] === null || $input[$field] === '') $input[$field] = null;
                else {
                    try {
                        if (!is_string($input[$field]) && !is_int($input[$field])) throw new InvalidArgumentException('Invalid ID type');
                        $input[$field] = PositiveId::value($input[$field]);
                    }
                    catch (InvalidArgumentException $e) { throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>$field], $e); }
                }
            }
        }
        if (array_key_exists('product_type', $input)) {
            $input['product_type'] = is_string($input['product_type']) ? strtoupper(trim($input['product_type'])) : '';
            if (! in_array($input['product_type'], ['STANDARD','COMBO'], true)) {
                throw new DomainException('PRODUCT_INVALID_TYPE', ['field'=>'product_type']);
            }
        }
        foreach (self::DECIMALS as $field=>$scale) {
            if (array_key_exists($field,$input)) $input[$field] = self::decimal($input[$field], $field, $scale);
        }
        if (array_key_exists('serial_management_enabled', $input)) {
            $input['serial_management_enabled'] = self::bool($input['serial_management_enabled']);
        }
        if (array_key_exists('description', $input) && $input['description'] !== null && ! is_string($input['description'])) {
            throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>'description']);
        }
        if ($create) {
            $input += [
                'code'=>null, 'sku'=>null, 'barcode'=>null, 'parent_product_id'=>null,
                'product_group_id'=>null, 'product_type'=>'STANDARD', 'base_uom_id'=>null,
                'product_name'=>null, 'product_child_name'=>null, 'price'=>null,
                'manual_standard_cost'=>null, 'serial_management_enabled'=>false,
                'description'=>null,
            ];
            foreach (array_keys(self::DECIMALS) as $field) $input[$field] ??= null;
            self::assertStructure($input, false);
        } elseif ($input === []) {
            throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>'update']);
        }
        return ['fields'=>$input, 'series'=>$series, 'combo'=>$hasCombo ? self::combo($combo) : null];
    }

    /** @param array<string,mixed> $row */
    public static function assertStructure(array $row, bool $hasChildren): void
    {
        $type = strtoupper((string) ($row['product_type'] ?? ''));
        if ($type !== 'STANDARD' && $type !== 'COMBO') throw new DomainException('PRODUCT_INVALID_TYPE');
        $parent = $row['parent_product_id'] ?? null;
        if ($type === 'COMBO') {
            if ($parent !== null || $hasChildren) throw new DomainException('PRODUCT_TYPE_CHANGE_INVALID');
            if ((bool) ($row['serial_management_enabled'] ?? false)) throw new DomainException('PRODUCT_COMBO_SERIAL_INVALID');
        }
        if ($parent !== null && $type !== 'STANDARD') throw new DomainException('PRODUCT_COMBO_PARENT_INVALID');
        if ($parent !== null) {
            if (($row['product_name'] ?? null) !== null) throw new DomainException('PRODUCT_CHILD_PRODUCT_NAME_INVALID');
            if (! self::nonEmpty($row['product_child_name'] ?? null)) throw new DomainException('PRODUCT_CHILD_NAME_REQUIRED');
        } else {
            if (($row['product_child_name'] ?? null) !== null) throw new DomainException('PRODUCT_ROOT_CHILD_NAME_INVALID');
            if (! self::nonEmpty($row['product_name'] ?? null)) throw new DomainException('PRODUCT_NAME_REQUIRED');
        }
        if ($hasChildren) {
            if (($row['price'] ?? null) !== null) throw new DomainException('PRODUCT_PARENT_PRICE_INVALID');
            if ((bool) ($row['serial_management_enabled'] ?? false)) throw new DomainException('PRODUCT_PARENT_SERIAL_INVALID');
        } else {
            if (! self::nonEmpty($row['sku'] ?? null)) throw new DomainException('PRODUCT_SKU_REQUIRED');
            if (($row['base_uom_id'] ?? null) === null) throw new DomainException('PRODUCT_BASE_UOM_REQUIRED');
            if (($row['price'] ?? null) === null) throw new DomainException('PRODUCT_PRICE_REQUIRED');
        }
        foreach (['price'=>'PRODUCT_PRICE_NEGATIVE', 'manual_standard_cost'=>'PRODUCT_STANDARD_COST_NEGATIVE'] as $field=>$error) {
            if (isset($row[$field]) && str_starts_with((string) $row[$field], '-')) {
                // -0 is not negative.
                if (preg_match('/\A-0+(?:\.0+)?\z/D', (string)$row[$field]) !== 1) throw new DomainException($error, ['field'=>$field]);
            }
        }
    }

    /** @param array<string,mixed> $old @param array<string,mixed> $new */
    public static function assertEditBoundary(array $old, array $new, bool $history): void
    {
        if ((string) ($old['parent_product_id'] ?? '') !== (string) ($new['parent_product_id'] ?? '')) {
            throw new DomainException($history ? 'PRODUCT_SEMANTIC_HISTORY_LOCKED' : 'PRODUCT_CHILD_REPARENT_UNSUPPORTED');
        }
        foreach (['base_uom_id', 'product_type', 'serial_management_enabled'] as $field) {
            if (($field==='serial_management_enabled'
                ? (bool)($old[$field]??false) !== (bool)($new[$field]??false)
                : (string)($old[$field]??'') !== (string)($new[$field]??'')) && $history) {
                throw new DomainException('PRODUCT_SEMANTIC_HISTORY_LOCKED', ['field'=>$field]);
            }
        }
    }

    /** @param array<mixed> $items @return list<array{component_product_id:string,quantity:string}> */
    public static function combo(array $items): array
    {
        if (! array_is_list($items)) throw new DomainException('VALIDATION_ERROR', ['field'=>'combo_items']);
        $out=[]; $seen=[];
        foreach ($items as $item) {
            if (! is_array($item) || array_keys($item) !== ['component_product_id','quantity']) {
                // Accept either JSON property order; reject extra/client owner fields.
                if (! is_array($item) || count($item)!==2 || ! array_key_exists('component_product_id',$item) || ! array_key_exists('quantity',$item)) {
                    throw new DomainException('VALIDATION_ERROR', ['field'=>'combo_items']);
                }
            }
            try {
                if (!is_string($item['component_product_id']) && !is_int($item['component_product_id'])) throw new InvalidArgumentException('Invalid component ID type');
                $id = PositiveId::value($item['component_product_id']);
            }
            catch (InvalidArgumentException $e) { throw new DomainException('VALIDATION_ERROR',['field'=>'component_product_id'],$e); }
            $qty = self::decimal($item['quantity'], 'quantity', 6);
            if ($qty === null || preg_match('/\A0+(?:\.0+)?\z/D', $qty) === 1 || str_starts_with($qty,'-') || isset($seen[$id])) {
                throw new DomainException('VALIDATION_ERROR', ['field'=>'combo_items']);
            }
            $seen[$id] = true;
            $out[]=['component_product_id'=>$id,'quantity'=>$qty];
        }
        return $out;
    }

    public static function requestedState(array $input): string
    {
        if (array_keys($input) !== ['status'] || ! is_string($input['status'])) {
            throw new DomainException('PRODUCT_INVALID_STATUS', ['field'=>'status']);
        }
        $status = strtoupper(trim($input['status']));
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $status)!==1) {
            throw new DomainException('PRODUCT_INVALID_STATUS', ['field'=>'status']);
        }
        return $status;
    }

    private static function nonEmpty(mixed $value): bool { return is_string($value) && trim($value) !== ''; }

    private static function text(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || trim($value)==='' || !preg_match('//u', $value)
            || (preg_match_all('/./us', trim($value)) ?: 0)>$max) {
            throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>$field]);
        }
        return trim($value);
    }

    private static function decimal(mixed $value, string $field, int $scale): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_int($value) && !is_string($value)) throw new DomainException('PRODUCT_INVALID_DECIMAL',['field'=>$field]);
        $text=(string)$value;
        if (preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.([0-9]+))?\z/D',$text,$m)!==1
            || strlen(explode('.', ltrim($text,'-'))[0])>15
            || (isset($m[1]) && strlen($m[1])>$scale)) {
            throw new DomainException('PRODUCT_INVALID_DECIMAL',['field'=>$field]);
        }
        return $text;
    }

    private static function bool(mixed $v): bool
    {
        if (in_array($v,[true,1,'1'],true)) return true;
        if (in_array($v,[false,0,'0',null],true)) return false;
        throw new DomainException('PRODUCT_INVALID_FIELD', ['field'=>'serial_management_enabled']);
    }
}

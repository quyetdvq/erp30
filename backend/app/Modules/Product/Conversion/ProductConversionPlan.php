<?php

declare(strict_types=1);

namespace App\Modules\Product\Conversion;

use App\Exceptions\DomainException;

/** Pure DML plan; reused by runtime and offline fixture, never opens a DB. */
final class ProductConversionPlan
{
    /**
     * @param list<array<string,mixed>> $existing persisted rows, already locked
     * @param list<array<string,mixed>> $items validated replacement request
     * @return array{deletes:list<array<string,mixed>>,updates:list<array{old:array<string,mixed>,new:array<string,mixed>}>,creates:list<array<string,mixed>>}
     */
    public static function build(array $existing, array $items): array
    {
        $byUom = [];
        foreach ($existing as $row) {
            $uom = (string)($row['uom_id'] ?? '');
            if ($uom === '' || isset($byUom[$uom]) || !isset($row['id'])) {
                throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED', ['operation'=>'invalid_existing_set']);
            }
            $byUom[$uom] = $row;
        }
        $requested = [];
        $updates = [];
        $creates = [];
        foreach ($items as $item) {
            $uom = (string)$item['uom_id'];
            if (isset($requested[$uom])) {
                throw new DomainException('PRODUCT_CONVERSION_DUPLICATE', ['field'=>'uom_id']);
            }
            $requested[$uom] = true;
            if (isset($byUom[$uom])) {
                $updates[] = ['old'=>$byUom[$uom], 'new'=>$item];
            } else {
                $creates[] = $item;
            }
        }
        $deletes = [];
        foreach ($existing as $row) {
            if (!isset($requested[(string)$row['uom_id']])) $deletes[] = $row;
        }
        return ['deletes'=>$deletes, 'updates'=>$updates, 'creates'=>$creates];
    }
}

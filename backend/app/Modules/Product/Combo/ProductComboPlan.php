<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;

/** Pure ID-preserving replacement plan, containing only changed rows. */
final class ProductComboPlan
{
    /** @param list<array<string,mixed>> $old
     *  @param list<array{component_product_id:string,quantity:string}> $items
     *  @return array{deletes:list<array<string,mixed>>,updates:list<array{old:array<string,mixed>,new:array{component_product_id:string,quantity:string}}>,creates:list<array{component_product_id:string,quantity:string}>}
     */
    public static function build(array $old, array $items): array
    {
        $existing=[];
        foreach ($old as $row) {
            $key=(string)($row['component_product_id'] ?? '');
            if ($key==='' || isset($existing[$key]) || !isset($row['id'])) {
                throw new DomainException('COMBO_MUTATION_FAILED', ['operation'=>'corrupt_existing_set']);
            }
            $existing[$key]=$row;
        }
        $seen=[]; $updates=[]; $creates=[];
        foreach ($items as $item) {
            $key=$item['component_product_id'];
            if (isset($seen[$key])) throw new DomainException('COMBO_DUPLICATE');
            $seen[$key]=true;
            if (!isset($existing[$key])) $creates[]=$item;
            elseif (!ProductComboRules::sameQuantity((string)$existing[$key]['quantity'], $item['quantity'])) {
                $updates[]=['old'=>$existing[$key],'new'=>$item];
            }
        }
        $deletes=[];
        foreach ($old as $row) if (!isset($seen[(string)$row['component_product_id']])) $deletes[]=$row;
        return ['deletes'=>$deletes,'updates'=>$updates,'creates'=>$creates];
    }
}

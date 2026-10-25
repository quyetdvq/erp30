<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Exceptions\DomainException;

/** ID-preserving, no-op-aware atomic replacement plan; pure PHP fixtureable. */
final class PriceListItemPlan
{
    /** @param list<array<string,mixed>> $existing
     *  @param list<array{product_id:string,uom_id:string,price:string}> $submitted
     *  @return array{deletes:list<array<string,mixed>>,updates:list<array{old:array<string,mixed>,new:array{product_id:string,uom_id:string,price:string}}>,creates:list<array{product_id:string,uom_id:string,price:string}>}
     */
    public static function build(array $existing,array $submitted):array
    {
        $byKey=[];
        foreach ($existing as $row) {
            $product=(string)($row['product_id']??'');
            $uom=(string)($row['uom_id']??'');
            if ($product==='' || $uom==='' || !isset($row['id']) || isset($byKey[$product.':'.$uom])) {
                throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>'corrupt_existing_items']);
            }
            $byKey[$product.':'.$uom]=$row;
        }
        $seen=[];$inserts=[];$changes=[];
        foreach ($submitted as $item) {
            $key=$item['product_id'].':'.$item['uom_id'];
            if (isset($seen[$key])) throw new DomainException('PRICE_LIST_ITEM_DUPLICATE');
            $seen[$key]=true;
            if (!isset($byKey[$key])) $inserts[]=$item;
            elseif (!PriceListRules::samePrice((string)$byKey[$key]['price'],$item['price'])) {
                $changes[]=['old'=>$byKey[$key],'new'=>$item];
            }
        }
        $deletes=[];
        foreach ($existing as $row) if (!isset($seen[(string)$row['product_id'].':'.(string)$row['uom_id']])) {
            $deletes[]=$row;
        }
        return ['deletes'=>$deletes,'updates'=>$changes,'creates'=>$inserts];
    }
}

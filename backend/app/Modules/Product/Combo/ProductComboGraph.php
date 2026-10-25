<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;

/** Fail-closed graph validation of reachable nested COMBOs; never compare BIGINT using float/int. */
final class ProductComboGraph
{
    public const MAX_NODES = 5000;
    public const MAX_EDGES = 10000;

    /** @param list<array{component_product_id:string,quantity:string}> $items
     *  @param list<array{combo_product_id:string,component_product_id:string}> $reachable
     */
    public static function assertAcyclic(string $owner, array $items, array $reachable): void
    {
        $adj = [$owner=>[]]; $ids=[$owner=>true]; $edgeCount=0;
        foreach ($items as $item) {
            $next=$item['component_product_id'];
            if ($next===$owner) throw new DomainException('COMBO_SELF_REFERENCE');
            $adj[$owner][]=$next; $ids[$next]=true;
            $edgeCount++;
        }
        foreach ($reachable as $row) {
            $from=$row['combo_product_id']; $to=$row['component_product_id'];
            if ($from===$owner) throw new DomainException('COMBO_MUTATION_FAILED', ['operation'=>'owner_edges_not_excluded']);
            $adj[$from][]=$to; $ids[$from]=true; $ids[$to]=true;
            $edgeCount++;
        }
        if ($edgeCount>self::MAX_EDGES || count($ids)>self::MAX_NODES) {
            throw new DomainException('COMBO_GRAPH_LIMIT');
        }
        // Iterative DFS, safe for deep and adversarial chains.
        $color=[];
        foreach (array_keys($adj) as $start) {
            if (($color[$start] ?? 0)===2) continue;
            $stack=[[$start,0]];
            while ($stack!==[]) {
                $index=count($stack)-1;
                [$node,$cursor]=$stack[$index];
                if (!isset($color[$node])) $color[$node]=1;
                $neighbors=$adj[$node] ?? [];
                if ($cursor>=count($neighbors)) {
                    $color[$node]=2; array_pop($stack); continue;
                }
                $next=$neighbors[$cursor];
                $stack[$index][1]++;
                if (($color[$next] ?? 0)===1) throw new DomainException('COMBO_CYCLE');
                if (($color[$next] ?? 0)===0) $stack[]=[$next,0];
            }
        }
    }
}

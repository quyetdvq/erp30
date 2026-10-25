<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use App\Exceptions\DomainException;

/** Resolve in-file dependencies without executing SQL or mutating input order. */
final class ImportRowPlanner
{
    /** @param list<array{number:int,cells:array<string,string|null>}> $rows
     *  @param list<string> $types
     *  @return list<array{number:int,cells:array<string,string|null>}>
     */
    public static function order(array $rows, array $types): array
    {
        $sources=[]; $codes=[]; $products=[]; $dependent=[];
        foreach ($rows as $row) {
            $cells=$row['cells'];
            $type=strtoupper((string)($cells['record_type'] ?? ''));
            if (!in_array($type,$types,true)) {
                // Keep invalid row so it is recorded as ERROR, never silently skipped.
                $dependent[]=$row;
                continue;
            }
            if ($type!=='PRODUCT') { $dependent[]=$row; continue; }
            $source=(string)($cells['source_key'] ?? '');
            if ($source==='') throw new DomainException('IMPORT_SOURCE_KEY_REQUIRED',['row'=>$row['number']]);
            if (isset($sources[$source])) throw new DomainException('IMPORT_DUPLICATE_SOURCE_KEY',['row'=>$row['number']]);
            $code=(string)($cells['product_code'] ?? '');
            if ($code!=='' && isset($codes[$code])) throw new DomainException('IMPORT_DUPLICATE_PRODUCT_CODE',['row'=>$row['number']]);
            $sources[$source]=$row;
            if ($code!=='') $codes[$code]=true;
        }
        $mark=[];
        $visit=function (string $key) use (&$visit,&$mark,&$products,$sources):void {
            if (($mark[$key]??null)==='DONE') return;
            if (($mark[$key]??null)==='VISITING') throw new DomainException('IMPORT_DEPENDENCY_CYCLE');
            $mark[$key]='VISITING';
            $parent=(string)($sources[$key]['cells']['parent_source_key']??'');
            if ($parent!=='') {
                if (!isset($sources[$parent])) throw new DomainException('IMPORT_DEPENDENCY_INVALID');
                $visit($parent);
            }
            $mark[$key]='DONE';
            $products[]=$sources[$key];
        };
        foreach (array_keys($sources) as $key) $visit($key);
        $rank=['UOM_CONVERSION'=>0,'COMBO_ITEM'=>1];
        usort($dependent, static function(array $left,array $right)use($rank):int {
            return ($rank[strtoupper((string)($left['cells']['record_type']??''))]??2)
                <=> ($rank[strtoupper((string)($right['cells']['record_type']??''))]??2);
        });
        return array_merge($products,$dependent);
    }
}

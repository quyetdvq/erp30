<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use InvalidArgumentException;

/**
 * Safe exact (organization_id, binary State) disjunction for ORGANIZATION PBAC.
 * Authorization happens BEFORE this SQL is compiled: no policy joins here.
 */
final class PriceListSql
{
    /** @param list<array{organization_id:string,status:string}> $buckets
     * @param array{page:int,page_size:int,q:string,sort:list<array{field:string,direction:string}>,filter:array<string,list<string>>} $query
     * @return array{count_sql:string,count_binds:list<mixed>,rows_sql:string,rows_binds:list<mixed>}
     */
    public static function collection(array $buckets,array $query,string $prefix=''):array
    {
        $table=self::table('price_lists',$prefix);
        $conditions=[]; $binds=[];$pairs=[];
        foreach ($buckets as $bucket) {
            if (!preg_match('/\A[1-9][0-9]*\z/D',$bucket['organization_id']) || $bucket['status']==='') {
                throw new InvalidArgumentException('PBAC bucket is not canonical.');
            }
            $pairs[]='(p.organization_id = ? AND BINARY p.status = BINARY ?)';
            $binds[]=$bucket['organization_id'];$binds[]=$bucket['status'];
        }
        $conditions[]=$pairs===[]?'1 = 0':'('.implode(' OR ',$pairs).')';
        if ($query['q']!=='') {
            $q='%'.self::like($query['q']).'%';
            $conditions[]="(p.code LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!')";
            $binds[]=$q; $binds[]=$q;
        }
        foreach ($query['filter'] as $field=>$values) {
            $columns=['code'=>'p.code','name'=>'p.name','organization_id'=>'p.organization_id','status'=>'p.status'];
            if (!isset($columns[$field]) || $values===[]) throw new InvalidArgumentException('Invalid pricing list filter.');
            $column=$columns[$field];
            $placeholders=implode(', ',array_fill(0,count($values),'?'));
            $conditions[]=($field==='status'?'BINARY '.$column.' IN (':' '.$column.' IN (').$placeholders.')';
            array_push($binds,...$values);
        }
        $where=' WHERE '.implode(' AND ',$conditions);
        $columns=['id'=>'p.id','code'=>'p.code','name'=>'p.name','organization_id'=>'p.organization_id',
            'effective_from'=>'p.effective_from','priority'=>'p.priority','status'=>'p.status'];
        $order=[];
        foreach ($query['sort'] as $item) {
            $col=$columns[$item['field']]??null;
            if ($col===null || !in_array($item['direction'],['ASC','DESC'],true)) {
                throw new InvalidArgumentException('Invalid pricing list sort.');
            }
            $order[]=$col.' '.$item['direction'];
        }
        $from=' FROM '.$table.' p';
        return [
            'count_sql'=>'SELECT COUNT(*) AS total'.$from.$where,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT p.id,p.code,p.name,p.organization_id,p.effective_from,p.effective_to,'
                .'p.priority,p.status,p.description'.$from.$where.' ORDER BY '.implode(', ',$order).' LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds,$query['page_size'],($query['page']-1)*$query['page_size']],
        ];
    }

    public static function table(string $name,string $prefix):string
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$name)
            || ($prefix!=='' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$prefix)!==1)) {
            throw new InvalidArgumentException('Unapproved database table prefix.');
        }
        return '`'.$prefix.$name.'`';
    }

    private static function like(string $text):string
    {
        return str_replace(['!','%','_'],['!!','!%','!_'],$text);
    }
}

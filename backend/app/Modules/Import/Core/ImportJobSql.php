<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use InvalidArgumentException;

/** Authorization scope is applied in SQL before COUNT and LIMIT/OFFSET. */
final class ImportJobSql
{
    /** @param list<string> $statuses @param array<string,mixed> $query
     *  @return array{count_sql:string,rows_sql:string,count_binds:list<mixed>,rows_binds:list<mixed>}
     */
    public static function listJobs(array $statuses,array $query,string $prefix='',string $entityType='PRODUCT'):array
    {
        if ($statuses===[]) throw new InvalidArgumentException('Empty job PBAC scope.');
        if (!preg_match('/\A[A-Z][A-Z0-9_]{0,49}\z/D',$entityType)) {
            throw new InvalidArgumentException('Invalid import entity type.');
        }
        $table=self::table('import_jobs',$prefix);
        $conditions=['entity_type = ?', 'BINARY status IN ('.implode(',',array_fill(0,count($statuses),'?')).')'];
        $binds=[$entityType,...$statuses];
        if ($query['q']!=='') {
            $conditions[]='(source_file_name LIKE ? ESCAPE \'!\' OR status LIKE ? ESCAPE \'!\' OR mode LIKE ? ESCAPE \'!\')';
            $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query['q']).'%';
            array_push($binds,$needle,$needle,$needle);
        }
        foreach ($query['filters'] as $name=>$values) {
            if (!in_array($name,['mode','status','created_by'],true)) throw new InvalidArgumentException('Invalid job filter.');
            $conditions[]=$name.' IN ('.implode(',',array_fill(0,count($values),'?')).')';
            array_push($binds,...$values);
        }
        $sql=' FROM '.$table.' WHERE '.implode(' AND ',$conditions);
        $offset=($query['page']-1)*$query['page_size'];
        return ['count_sql'=>'SELECT COUNT(*) AS total'.$sql,
            'rows_sql'=>'SELECT *'.$sql.' ORDER BY '.$query['sort'].' '.$query['direction']
                .($query['sort']==='id'?'':', id DESC').' LIMIT '.$query['page_size'].' OFFSET '.$offset,
            'count_binds'=>$binds,'rows_binds'=>$binds];
    }

    /** @param array<string,mixed> $query
     *  @return array{count_sql:string,rows_sql:string,count_binds:list<mixed>,rows_binds:list<mixed>}
     */
    public static function listRows(string $jobId,array $query,string $prefix=''):array
    {
        $conditions=['import_job_id = ?'];$binds=[$jobId];
        if ($query['q']!=='') {
            $conditions[]='entity_key LIKE ? ESCAPE \'!\'';
            $binds[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query['q']).'%';
        }
        foreach ($query['filters'] as $name=>$values) {
            if ($name!=='row_status') throw new InvalidArgumentException('Invalid row filter.');
            $conditions[]='row_status IN ('.implode(',',array_fill(0,count($values),'?')).')';
            array_push($binds,...$values);
        }
        $sql=' FROM '.self::table('import_job_rows',$prefix).' WHERE '.implode(' AND ',$conditions);
        $offset=($query['page']-1)*$query['page_size'];
        return ['count_sql'=>'SELECT COUNT(*) AS total'.$sql,
            // ROW_NUMBER became a reserved identifier in MariaDB 10.7+. The
            // sort column is allowlisted by ImportListQuery::parse(). Quote both
            // the selected sort and the stable row-number tie-breaker.
            'rows_sql'=>'SELECT *'.$sql.' ORDER BY `'.$query['sort'].'` '.$query['direction']
                .($query['sort']==='row_number'?'':', `row_number` ASC').' LIMIT '.$query['page_size'].' OFFSET '.$offset,
            'count_binds'=>$binds,'rows_binds'=>$binds];
    }

    private static function table(string $name,string $prefix):string
    {
        if ($prefix!=='' && !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$prefix)) {
            throw new InvalidArgumentException('Invalid SQL table prefix.');
        }
        return '`'.$prefix.$name.'`';
    }
}

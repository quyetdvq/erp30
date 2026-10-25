<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use App\Exceptions\DomainException;

/** Bounded, whitelisted list query used by every Import Job adapter. */
final class ImportListQuery
{
    /** @param array<string,mixed> $raw
     *  @return array{page:int,page_size:int,q:string,sort:string,direction:string,filters:array<string,list<string>>}
     */
    public static function parse(array $raw, bool $rows=false):array
    {
        foreach (array_keys($raw) as $key) {
            if (!in_array($key,['q','page','page_size','sort','filter'],true)) {
                throw new DomainException('IMPORT_INVALID_QUERY',['field'=>(string)$key]);
            }
        }
        $page=self::number($raw['page']??1,1,100000);
        $size=self::number($raw['page_size']??50,1,100);
        $q=$raw['q']??'';
        if (!is_string($q) || strlen($q)>300 || !preg_match('//u',$q)) {
            throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'q']);
        }
        $q=trim($q);
        $sort=(string)($raw['sort']??($rows?'row_number':'-created_at'));
        $direction=str_starts_with($sort,'-')?'DESC':'ASC';
        $sort=ltrim($sort,'-');
        $allowed=$rows?['row_number','row_status','entity_key','created_at']
            :['id','mode','status','created_by','created_at'];
        if (!in_array($sort,$allowed,true)) throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'sort']);
        $filters=$raw['filter']??[];
        if (!is_array($filters) || ($filters!==[] && array_is_list($filters))) {
            throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'filter']);
        }
        $names=$rows?['row_status']:['mode','status','created_by'];
        $clean=[];
        foreach ($filters as $name=>$values) {
            if (!in_array($name,$names,true)) throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'filter']);
            $values=is_array($values)?$values:[$values];
            if ($values===[] || !array_is_list($values) || count($values)>20) {
                throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'filter']);
            }
            $clean[$name]=[];
            foreach ($values as $value) {
                if ((!is_string($value)&&!is_int($value)) || strlen((string)$value)>64) {
                    throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'filter']);
                }
                $str=(string)$value;
                if ($name==='row_status' && !in_array($str,['SUCCESS','ERROR','SKIPPED'],true)
                    || $name==='mode' && !in_array($str,['CREATE_ONLY','UPDATE_ONLY','UPSERT'],true)
                    || $name==='status' && !in_array($str,['PENDING','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED'],true)
                    || $name==='created_by' && !preg_match('/\A[1-9][0-9]*\z/D',$str)) {
                    throw new DomainException('IMPORT_INVALID_QUERY',['field'=>'filter']);
                }
                $clean[$name][]=$str;
            }
            $clean[$name]=array_values(array_unique($clean[$name]));
        }
        return ['page'=>$page,'page_size'=>$size,'q'=>$q,'sort'=>$sort,
            'direction'=>$direction,'filters'=>$clean];
    }

    private static function number(mixed $value,int $low,int $high):int
    {
        if ((!is_int($value)&&!is_string($value)) || !preg_match('/\A[0-9]{1,6}\z/D',(string)$value)
            || (int)$value<$low || (int)$value>$high) {
            throw new DomainException('IMPORT_INVALID_QUERY');
        }
        return (int)$value;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Pure API-050 Pricing input/decimal rules. No SQL or PHP floating-point money. */
final class PriceListRules
{
    public const MAX_ITEMS = 500;
    public const MAX_BUCKETS = 512;
    private const FIELDS = ['code','name','organization_id','effective_from','effective_to',
        'priority','description','evaluation_organization_id','status', 'id',
        'created_by','updated_by','created_at','updated_at'];
    private const SORT = ['id','code','name','organization_id','effective_from','priority','status'];
    private const FILTER = ['code','name','organization_id','status'];

    public static function id(string|int $raw): string
    {
        try { return PositiveId::value($raw); }
        catch (InvalidArgumentException $e) { throw new DomainException('PRICE_LIST_NOT_FOUND', ['field'=>'id'], $e); }
    }

    /** @param array<string,mixed> $raw
     * @return array{page:int,page_size:int,q:string,sort:list<array{field:string,direction:string}>,filter:array<string,list<string>>}
     */
    public static function query(array $raw): array
    {
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, ['q','page','page_size','sort','filter'], true)) {
                throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>(string)$key]);
            }
        }
        $q=$raw['q']??'';
        if (!is_string($q) || strlen($q)>400 || (function_exists('mb_strlen') && mb_strlen($q)>100)) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'q']);
        }
        $q=trim($q);
        $page=self::integerBound($raw['page']??1,1,100000,'page');
        $size=self::integerBound($raw['page_size']??50,1,100,'page_size');
        $sort=[]; $seen=[];
        $source=$raw['sort']??'id';
        if (!is_string($source) || $source==='') throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'sort']);
        foreach (explode(',',$source) as $term) {
            if (!preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D',$term,$m)
                || !in_array($m[2],self::SORT,true) || isset($seen[$m[2]])) {
                throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'sort']);
            }
            $seen[$m[2]]=true;
            $sort[]=['field'=>$m[2],'direction'=>$m[1]==='-'?'DESC':'ASC'];
        }
        if (!isset($seen['id'])) $sort[]=['field'=>'id','direction'=>'ASC'];
        $filter=$raw['filter']??[];
        if (!is_array($filter) || ($filter!==[] && array_is_list($filter))) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'filter']);
        }
        $normalized=[];
        foreach ($filter as $name=>$value) {
            if (!is_string($name) || !in_array($name,self::FILTER,true)) {
                throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'filter']);
            }
            $values=is_array($value)?$value:[$value];
            if ($values===[] || !array_is_list($values) || count($values)>20) {
                throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'filter']);
            }
            $selected=[];
            foreach ($values as $item) {
                if (!is_string($item) && !is_int($item)) {
                    throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'filter']);
                }
                $text=(string)$item;
                if (strlen($text)>255) throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'filter']);
                if ($name==='organization_id') $text=self::positiveId($item,'organization_id');
                $selected[]=$text;
            }
            $normalized[$name]=array_values(array_unique($selected));
        }
        return ['page'=>$page,'page_size'=>$size,'q'=>$q,'sort'=>$sort,'filter'=>$normalized];
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public static function master(array $body, bool $create): array
    {
        foreach (array_keys($body) as $key) {
            if (!in_array($key,self::FIELDS,true)) throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>(string)$key]);
        }
        if (array_key_exists('status',$body)) {
            throw new DomainException('PRICE_LIST_STATUS_REQUIRES_SETSTATE',['field'=>'status']);
        }
        foreach (['id','created_by','updated_by','created_at','updated_at','evaluation_organization_id'] as $field) unset($body[$field]);
        foreach (['code'=>50,'name'=>255] as $field=>$limit) {
            if (array_key_exists($field,$body)) {
                if (!is_string($body[$field]) || trim($body[$field])==='' || strlen($body[$field])>$limit*4) {
                    throw new DomainException('PRICE_LIST_REQUIRED_FIELD',['field'=>$field]);
                }
                $text=trim($body[$field]);
                if ((function_exists('mb_strlen') ? mb_strlen($text):strlen($text))>$limit) {
                    throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>$field]);
                }
                $body[$field]=$field==='code'?strtoupper($text):$text;
            }
        }
        if (array_key_exists('organization_id',$body)) {
            $body['organization_id']=self::positiveId($body['organization_id'],'organization_id');
        }
        foreach (['effective_from','effective_to'] as $field) {
            if (array_key_exists($field,$body)) {
                $body[$field]=self::dateTime($body[$field],$field,$field==='effective_to');
            }
        }
        if (array_key_exists('priority',$body)) {
            $body['priority']=self::priority($body['priority']);
        }
        if (array_key_exists('description',$body) && $body['description']!==null && !is_string($body['description'])) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'description']);
        }
        if ($create) {
            foreach (['code','name','organization_id','effective_from'] as $required) {
                if (!array_key_exists($required,$body)) throw new DomainException('PRICE_LIST_REQUIRED_FIELD',['field'=>$required]);
            }
            $body+=['effective_to'=>null,'priority'=>0,'description'=>null];
        } elseif ($body===[]) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'patch']);
        }
        if ($create || array_key_exists('effective_from',$body)) {
            self::interval($body);
        }
        return $body;
    }

    /** @param array<string,mixed> $values */
    public static function interval(array $values): void
    {
        $from=$values['effective_from']??null;
        $to=$values['effective_to']??null;
        if (!is_string($from) || ($to!==null && (!is_string($to) || $to<=$from))) {
            throw new DomainException('PRICE_LIST_INVALID_EFFECTIVE_INTERVAL',['field'=>'effective_to']);
        }
    }

    /** @param array<mixed> $body @return list<array{product_id:string,uom_id:string,price:string}> */
    public static function items(array $body): array
    {
        if (array_is_list($body)) $source=$body;
        else {
            foreach (array_keys($body) as $key) {
                if ($key!=='items') throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>(string)$key]);
            }
            if (!array_key_exists('items',$body) || !is_array($body['items']) || !array_is_list($body['items'])) {
                throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'items']);
            }
            $source=$body['items'];
        }
        if (count($source)>self::MAX_ITEMS) throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'items']);
        $out=[]; $seen=[];
        foreach ($source as $item) {
            if (!is_array($item) || array_is_list($item) || count($item)!==3
                || !array_key_exists('product_id',$item) || !array_key_exists('uom_id',$item)
                || !array_key_exists('price',$item)) {
                throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'items']);
            }
            $product=self::positiveId($item['product_id'],'product_id');
            $uom=self::positiveId($item['uom_id'],'uom_id');
            $key=$product.':'.$uom;
            if (isset($seen[$key])) throw new DomainException('PRICE_LIST_ITEM_DUPLICATE',['field'=>'items']);
            $seen[$key]=true;
            $out[]=['product_id'=>$product,'uom_id'=>$uom,'price'=>self::price($item['price'])];
        }
        return $out;
    }

    public static function requestedState(array $body):string
    {
        if (array_keys($body)!==['status'] || !is_string($body['status'])) {
            throw new DomainException('PRICE_LIST_INVALID_STATUS',['field'=>'status']);
        }
        $value=strtoupper(trim($body['status']));
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D',$value)!==1) {
            throw new DomainException('PRICE_LIST_INVALID_STATUS',['field'=>'status']);
        }
        return $value;
    }

    /** The Pricing SQL DATETIME columns store UTC seconds; APIs require RFC3339. */
    public static function apiDateTime(?string $utc): ?string
    {
        if ($utc === null) return null;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
        $issues = DateTimeImmutable::getLastErrors();
        if ($parsed === false || $parsed->format('Y-m-d H:i:s') !== $utc
            || ($issues !== false && ($issues['warning_count'] !== 0 || $issues['error_count'] !== 0))) {
            throw new InvalidArgumentException('Persisted Pricing datetime is invalid.');
        }
        return $parsed->format('Y-m-d\TH:i:s\Z');
    }

    public static function price(mixed $raw): string
    {
        if (!is_string($raw) && !is_int($raw)) throw new DomainException('PRICE_LIST_INVALID_PRICE',['field'=>'price']);
        $value=(string)$raw;
        if (preg_match('/\A(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?\z/D',$value)!==1) {
            throw new DomainException('PRICE_LIST_INVALID_PRICE',['field'=>'price']);
        }
        return $value;
    }

    public static function samePrice(string $left,string $right):bool
    {
        return self::priceCanonical($left)===self::priceCanonical($right);
    }

    private static function priceCanonical(string $raw):string
    {
        $value=self::price($raw);
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        return $whole.'.'.str_pad($fraction,2,'0');
    }

    public static function positiveId(mixed $raw,string $field):string
    {
        try {
            if (!is_string($raw) && !is_int($raw)) throw new InvalidArgumentException('Invalid ID type.');
            return PositiveId::value($raw);
        } catch (InvalidArgumentException $e) {
            throw new DomainException('PRICE_LIST_INVALID_REFERENCE',['field'=>$field],$e);
        }
    }

    private static function integerBound(mixed $value,int $min,int $max,string $field):int
    {
        if ((!is_string($value) && !is_int($value)) || preg_match('/\A[0-9]+\z/D',(string)$value)!==1
            || strlen((string)$value)>10 || (int)$value<$min || (int)$value>$max) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>$field]);
        }
        return (int)$value;
    }

    private static function priority(mixed $value):int
    {
        if ((!is_string($value) && !is_int($value)) || preg_match('/\A-?(?:0|[1-9][0-9]*)\z/D',(string)$value)!==1
            || strlen((string)$value)>12) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'priority']);
        }
        $integer=filter_var((string)$value,FILTER_VALIDATE_INT);
        if ($integer===false || $integer< -2147483648 || $integer>2147483647) {
            throw new DomainException('PRICE_LIST_INVALID_FIELD',['field'=>'priority']);
        }
        return $integer;
    }

    private static function dateTime(mixed $value,string $field,bool $nullable):?string
    {
        if ($nullable && $value===null) return null;
        if (!is_string($value) || $value==='') throw new DomainException('PRICE_LIST_INVALID_EFFECTIVE_INTERVAL',['field'=>$field]);
        // Match baseline: RFC3339 input (including fractional seconds) is UTC
        // normalized to SQL seconds. SQL datetime without timezone is accepted.
        if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D',$value)===1) {
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
            if ($dt!==false && $dt->format('Y-m-d H:i:s')===$value) return $value;
        } else {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D',$value)===1) {
                $normalized=str_ends_with($value,'Z')?substr($value,0,-1).'+00:00':$value;
                try {
                    $dt=new DateTimeImmutable($normalized);
                    $errors=DateTimeImmutable::getLastErrors();
                    if ($errors===false || ($errors['warning_count']===0 && $errors['error_count']===0)) {
                        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    }
                } catch (\Throwable) {}
            }
        }
        throw new DomainException('PRICE_LIST_INVALID_EFFECTIVE_INTERVAL',['field'=>$field]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\People\Structure\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** M5.4 API-020: pure Department/Position field and hierarchy rules. */
final class StructureMutationRules
{
    private const DEPARTMENT = ['parent_id','code','name','description','status'];
    private const POSITION = ['code','name','level','description','status'];
    private const AUDIT = ['created_by','updated_by','created_at','updated_at'];

    public static function id(mixed $raw, string $type, string $field='id'): string
    {
        if (!is_int($raw) && !is_string($raw)) {
            throw new DomainException(self::error($type,'REFERENCE_INVALID'), ['field'=>$field]);
        }
        try { return PositiveId::value($raw); }
        catch (InvalidArgumentException $e) {
            throw new DomainException(self::error($type,'REFERENCE_INVALID'), ['field'=>$field],$e);
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function payload(string $type, array $input, bool $create): array
    {
        $allowed = match($type) {
            'department'=>self::DEPARTMENT, 'position'=>self::POSITION,
            default=>throw new InvalidArgumentException('UNREVIEWED_STRUCTURE_TYPE'),
        };
        $output=[];
        foreach($input as $field=>$value) {
            if(in_array($field,self::AUDIT,true)) continue;
            if (!in_array($field,$allowed,true)) {
                throw new DomainException(self::error($type,'FIELD_UNSUPPORTED'), ['field'=>(string)$field]);
            }
            if ($field==='parent_id') {
                $output[$field]=$value===null ? null : self::id($value,$type,$field);
            } elseif ($field==='level') {
                if ($value===null) { $output[$field]=null; continue; }
                if (!is_int($value) && !(is_string($value) && preg_match('/\A-?[0-9]+\z/D',$value)===1)) {
                    throw new DomainException('POSITION_REFERENCE_INVALID',['field'=>'level']);
                }
                $numeric=(string)$value;
                $negative=str_starts_with($numeric,'-');
                $digits=ltrim($negative ? substr($numeric,1) : $numeric,'0');
                $digits=$digits==='' ? '0' : $digits;
                $normalized=($negative && $digits!=='0' ? '-' : '').$digits;
                $int=filter_var($normalized,FILTER_VALIDATE_INT,['options'=>['min_range'=>-2147483648,'max_range'=>2147483647]]);
                if ($int===false) throw new DomainException('POSITION_REFERENCE_INVALID',['field'=>'level']);
                $output[$field]=$int;
            } elseif($field==='code' || $field==='name') {
                $max=$field==='code' ? 50 : 255;
                // MariaDB VARCHAR limits Unicode code points, not UTF-8
                // byte length. Vietnamese business names must not be rejected
                // simply for using multibyte characters.
                $length=is_string($value) ? preg_match_all('/./us',trim($value)) : false;
                if ($length===false || trim((string)$value)==='' || $length>$max) {
                    throw new DomainException(self::error($type,'REFERENCE_INVALID'), ['field'=>$field]);
                }
                $output[$field]=trim($value);
            } elseif($field==='status') {
                $output[$field]=self::status($type,$value);
            } elseif($field==='description') {
                if($value!==null && !is_string($value)) {
                    throw new DomainException(self::error($type,'FIELD_UNSUPPORTED'),['field'=>$field]);
                }
                $output[$field]=$value;
            }
        }
        if ($create) {
            foreach(['code','name'] as $field) {
                if(!isset($output[$field])) throw new DomainException(self::error($type,'FIELD_REQUIRED'),['field'=>$field]);
            }
            if($type==='department') $output['parent_id'] ??= null;
        } elseif($output===[]) {
            throw new DomainException(self::error($type,'UPDATE_EMPTY'));
        }
        return $output;
    }

    public static function status(string $type, mixed $raw): string
    {
        $code=is_string($raw) ? strtoupper(trim($raw)) : '';
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D',$code)!==1) {
            throw new DomainException(self::error($type,'STATUS_INVALID'),['field'=>'status']);
        }
        return $code;
    }

    /** @param array<string,mixed> $request */
    public static function requestedState(string $type,array $request): string
    {
        if (array_keys($request)!==['status']) {
            throw new DomainException(self::error($type,'STATUS_INVALID'),['field'=>'status']);
        }
        return self::status($type,$request['status']);
    }

    /**
     * Rows are trusted, locked SQL facts. Detect a missing parent, self-link,
     * pre-existing bad graph and any reparent that creates a cycle.
     * @param list<array<string,mixed>> $rows
     */
    public static function assertDepartmentParent(array $rows, ?string $targetId, ?string $parentId): void
    {
        if($parentId===null) return;
        $byId=[];
        foreach($rows as $row) $byId[(string)$row['id']]=$row;
        $visited=[];
        $cursor=$parentId;
        while($cursor!==null) {
            if ($targetId!==null && $cursor===$targetId || isset($visited[$cursor])) {
                throw new DomainException('DEPARTMENT_HIERARCHY_CYCLE',['field'=>'parent_id']);
            }
            $visited[$cursor]=true;
            $row=$byId[$cursor] ?? null;
            if($row===null) throw new DomainException('DEPARTMENT_REFERENCE_INVALID',['field'=>'parent_id']);
            $cursor=$row['parent_id']===null ? null : (string)$row['parent_id'];
        }
    }

    private static function error(string $type,string $suffix): string
    {
        return match($type) {
            'department'=>'DEPARTMENT_'.$suffix, 'position'=>'POSITION_'.$suffix,
            default=>throw new InvalidArgumentException('UNREVIEWED_STRUCTURE_TYPE'),
        };
    }
}

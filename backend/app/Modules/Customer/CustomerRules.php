<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Strict Customer payload validation. Never trust client status, scope, audit or actor. */
final class CustomerRules
{
    public const TYPES=['INDIVIDUAL','ORGANIZATION'];
    public const DEFAULT_TYPE='INDIVIDUAL';
    public const DEFAULT_GROUP='STANDARD';
    public const MAIN_CONTACT_LABEL='Địa chỉ chính';
    public const ACTIVITIES=['NOTE','CALL','EMAIL','MEETING','CORRECTION'];
    private const TEXT=['customer_code'=>50,'name'=>255,'phone'=>50,'email'=>255,'tax_code'=>50,'address'=>65535];

    public static function id(mixed $input, string $field='id'): string
    {
        try {
            if (!is_string($input) && !is_int($input)) throw new InvalidArgumentException('Invalid identifier type.');
            return PositiveId::value($input);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('CUSTOMER_INVALID_REFERENCE',['field'=>$field],$exception);
        }
    }

    /**
     * One canonical Lifecycle State code, within the current customers.status
     * VARCHAR(20) storage limit. The trusted Lifecycle still determines whether
     * this State belongs to the Customer Resource and a transition is allowed.
     */
    public static function stateCode(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $value)!==1)
            throw new DomainException('CUSTOMER_STATE_INVALID', ['field'=>'status']);
        return $value;
    }

    public static function text(mixed $value, string $field, int $limit, bool $nullable=false): ?string
    {
        if ($nullable && $value===null) return null;
        if (!is_string($value) || preg_match_all('/./us',trim($value))===false || preg_match_all('/./us',trim($value))>$limit) {
            throw new DomainException('CUSTOMER_FIELD_INVALID',['field'=>$field]);
        }
        $value=trim($value);
        if ($value==='' && !$nullable) throw new DomainException('CUSTOMER_FIELD_REQUIRED',['field'=>$field]);
        return $value==='' && $nullable ? null : $value;
    }

    /**
     * Mandatory Customer profile. Validates the merged persisted state on PATCH,
     * so a partial edit cannot remove required fields or reuse another phone.
     * @param array<string,mixed> $fields
     */
    public static function requiredProfile(array $fields): string
    {
        foreach (['name', 'phone', 'address', 'province_id', 'ward_id'] as $field) {
            $value = $fields[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new DomainException('CUSTOMER_FIELD_REQUIRED', ['field' => $field]);
            }
        }
        try {
            return CustomerPhone::key((string) $fields['phone']);
        } catch (InvalidArgumentException $cause) {
            throw new DomainException('CUSTOMER_PHONE_INVALID', ['field' => 'phone'], $cause);
        }
    }

    /** @param array<string,mixed> $body @return array{fields:array<string,mixed>,companies:?array<int,string>,series:?string} */
    public static function customer(array $body, bool $create): array
    {
        $output=[]; $companies=null; $series=null;
        foreach ($body as $field=>$raw) {
            if ($field==='company_ids') {
                if (!is_array($raw) || !array_is_list($raw) || count($raw)>512) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
                $companies=[];
                foreach ($raw as $id) $companies[]=self::id($id,'company_ids');
                if (count($companies)!==count(array_unique($companies))) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
                continue;
            }
            if ($field==='numbering_series_code' && $create) {
                $series=self::text($raw,$field,100,true);
                continue;
            }
            if ($field==='customer_group_code') {
                if ($raw===null || $raw==='') { $output[$field]=null; continue; }
                if (!is_string($raw) || !array_key_exists($raw, CUSTOMER_GROUP())) {
                    throw new DomainException('CUSTOMER_GROUP_INVALID',['field'=>$field]);
                }
                $output[$field]=$raw; continue;
            }
            if ($field==='customer_type') {
                if (!is_string($raw) || !in_array($raw,self::TYPES,true)) throw new DomainException('CUSTOMER_TYPE_INVALID');
                $output[$field]=$raw;continue;
            }
            if ($field==='all_companies') {
                if (!is_bool($raw)) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
                $output[$field]=$raw?1:0;continue;
            }
            if (in_array($field,['province_id','ward_id','primary_contact_id'],true)) {
                $output[$field]=$raw===null?null:self::id($raw,$field);continue;
            }
            // POST with a blank code means: use the configured default Numbering
            // series. The issuer still fails closed when no default exists.
            if ($field==='customer_code' && $create
                && is_string($raw) && trim($raw)==='') {
                continue;
            }
            if (array_key_exists($field,self::TEXT)) {
                $output[$field]=self::text($raw,$field,self::TEXT[$field], !in_array($field,['name','customer_code'],true));
                if ($field==='customer_code' && $output[$field]!==null) {
                    $output[$field]=strtoupper($output[$field]);
                    if (preg_match('/\A[A-Z0-9][A-Z0-9_.-]{0,49}\z/D',$output[$field])!==1)
                        throw new DomainException('CUSTOMER_CODE_INVALID');
                }
                if ($field==='email' && $output[$field]!==null && !filter_var($output[$field],FILTER_VALIDATE_EMAIL))
                    throw new DomainException('CUSTOMER_FIELD_INVALID',['field'=>'email']);
                continue;
            }
            // Never silently accept client actor, status or unexpected fields.
            throw new DomainException('CUSTOMER_FIELD_UNSUPPORTED',['field'=>(string)$field]);
        }
        if ($create) {
            // Defaults apply only to absent POST fields; an explicitly null group
            // remains ungrouped, as permitted by the approved CUS contract.
            $output['customer_type'] ??= self::DEFAULT_TYPE;
            if (!array_key_exists('customer_group_code',$output)) $output['customer_group_code']=self::DEFAULT_GROUP;
            foreach (['name','all_companies'] as $field) {
                if (!array_key_exists($field,$output)) throw new DomainException('CUSTOMER_FIELD_REQUIRED',['field'=>$field]);
            }
        }
        if (!$create && !$output && $companies===null) throw new DomainException('CUSTOMER_UPDATE_EMPTY');
        return ['fields'=>$output,'companies'=>$companies,'series'=>$series];
    }


    /**
     * One-time initialization on POST only, never a live synchronization rule.
     * Customer phone is copied into Contact mobile (and phone); addresses and
     * all other shared fields are copied from normalized server-side data.
     * @param array<string,mixed> $fields normalized Customer fields
     * @return array<string,mixed>|null
     */
    public static function initialContact(string $customerId, array $fields): ?array
    {
        if (($fields['customer_type'] ?? null) !== 'INDIVIDUAL') return null;
        return [
            'contact_type'=>'CUSTOMER',
            'contact_ref_id'=>self::id($customerId, 'contact_ref_id'),
            'description'=>self::MAIN_CONTACT_LABEL,
            'name'=>$fields['name'],
            'phone'=>$fields['phone']??null,
            'mobile'=>$fields['phone']??null,
            'email'=>$fields['email']??null,
            'address'=>$fields['address']??null,
            'province_id'=>$fields['province_id']??null,
            'ward_id'=>$fields['ward_id']??null,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function interaction(array $input): array
    {
        $allowed=['company_organization_id','activity_type','content','happened_at','corrects_interaction_id'];
        foreach ($input as $key=>$_) if (!in_array($key,$allowed,true)) throw new DomainException('CUSTOMER_INTERACTION_FIELD_UNSUPPORTED',['field'=>(string)$key]);
        $type=$input['activity_type']??null;
        if (!is_string($type)||!in_array($type,self::ACTIVITIES,true)) throw new DomainException('CUSTOMER_INTERACTION_TYPE_INVALID');
        $id=self::id($input['company_organization_id']??null,'company_organization_id');
        $content=self::text($input['content']??null,'content',65535);
        $correction=$input['corrects_interaction_id']??null;
        if (($type==='CORRECTION')!==($correction!==null)) throw new DomainException('CUSTOMER_INTERACTION_CORRECTION_INVALID');
        if ($correction!==null) $correction=self::id($correction,'corrects_interaction_id');
        $happenedAt=$input['happened_at']??null;
        if ($happenedAt!==null) {
            if (!is_string($happenedAt)||preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D',$happenedAt)!==1
                || strtotime($happenedAt)===false || strtotime($happenedAt)>time()+300) {
                throw new DomainException('CUSTOMER_INTERACTION_TIME_INVALID');
            }
            $happenedAt=gmdate('Y-m-d H:i:s',strtotime($happenedAt));
        } else $happenedAt=gmdate('Y-m-d H:i:s');
        return ['company_organization_id'=>$id,'activity_type'=>$type,'content'=>$content,
            'happened_at'=>$happenedAt,'corrects_interaction_id'=>$correction];
    }
}

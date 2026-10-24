<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Configuration;

use App\Exceptions\DomainException;

/** Strict source-independent P1-API-040 configuration and pattern validator. */
final class NumberingRules
{
    private const ENUMS = [
        'scope_type'=>['GROUP','COMPANY','ORGANIZATION'],
        'reset_policy'=>['NEVER','YEARLY','MONTHLY','DAILY'],
        'generation_mode'=>['AUTO_REQUIRED','AUTO_IF_EMPTY'],
        'status'=>['ACTIVE','INACTIVE'],
    ];
    private const TOKENS = ['YYYY','YY','MM','DD','COMPANY_CODE','ORG_CODE','RESOURCE'];
    private const FIELDS = ['resource_id','series_code','name','scope_type','pattern','reset_policy',
        'generation_mode','start_number','increment_by','is_default','status'];

    public static function id(mixed $value): string
    {
        $text = (string) $value;
        if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]*$/D',$text)!==1 ||
            strlen($text)>19 || (strlen($text)===19 && strcmp($text,'9223372036854775807')>0)) {
            throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>'id']);
        }
        return $text;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function normalize(array $data, bool $create): array
    {
        unset($data['id'],$data['created_by'],$data['updated_by'],$data['created_at'],$data['updated_at']);
        if ($data === []) throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>'update']);
        foreach ($data as $field=>$value) {
            if (!in_array($field,self::FIELDS,true)) throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
            if (isset(self::ENUMS[$field])) {
                $norm = is_string($value) ? strtoupper(trim($value)) : '';
                if (!in_array($norm,self::ENUMS[$field],true)) throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
                $data[$field] = $norm;
            } elseif ($field === 'resource_id') {
                $data[$field] = self::id($value);
            } elseif ($field === 'series_code') {
                $norm = is_string($value) ? strtoupper(trim($value)) : '';
                if (preg_match('/^[A-Z][A-Z0-9_.:-]{0,99}$/D', $norm)!==1) {
                    throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
                }
                $data[$field] = $norm;
            } elseif ($field === 'name' || $field === 'pattern') {
                $max = $field === 'name' ? 255 : 255;
                if (!is_string($value) || trim($value)==='' || strlen(trim($value))>$max) {
                    throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
                }
                $data[$field] = trim($value);
            } elseif ($field === 'is_default') {
                if (!in_array($value, [true,false,1,0,'1','0'],true)) {
                    throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
                }
                $data[$field] = in_array($value,[true,1,'1'],true) ? 1 : 0;
            } elseif (in_array($field,['start_number','increment_by'],true)) {
                $num = is_int($value) ? $value : (is_string($value) && ctype_digit($value)
                    ? filter_var($value,FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]) : false);
                if ($num===false || $num < ($field==='increment_by' ? 1 : 0)) {
                    throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$field]);
                }
                $data[$field] = $num;
            }
        }
        if ($create) {
            foreach (['resource_id','series_code','name','scope_type','pattern','reset_policy','generation_mode','status'] as $required) {
                if (!array_key_exists($required,$data)) throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>$required]);
            }
            $data += ['start_number'=>1,'increment_by'=>1,'is_default'=>0];
        }
        return $data;
    }

    /** @param array<string,mixed> $series */
    public static function validatePattern(array $series): void
    {
        if (($series['status'] ?? '') !== 'ACTIVE') return;
        $pattern = (string) ($series['pattern'] ?? '');
        if ($pattern === '' || strlen($pattern)>255 ||
            preg_match('/<\?(?:php|=)?|\?>|<%|%>|\{\{|\}\}|\beval\s*\(|\bselect\s+/i',$pattern)===1) {
            throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        }
        preg_match_all('/\{([^{}]*)\}/',$pattern,$matches, PREG_SET_ORDER);
        $sequenceCount = 0;
        $tokens = [];
        $minimum = 0;
        $literal = preg_replace('/\{[^{}]*\}/','', $pattern);
        if ($literal === null || str_contains($literal,'{') || str_contains($literal,'}')) {
            throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        }
        $minimum = strlen($literal);
        foreach ($matches as $m) {
            $token = $m[1];
            if (preg_match('/^SEQ:([0-9]+)$/D',$token,$width)===1) {
                if (++$sequenceCount>1 || (int) $width[1]<1 || (int) $width[1]>32) {
                    throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
                }
                $minimum += (int) $width[1];
                continue;
            }
            if (!in_array($token,self::TOKENS,true)) throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
            $tokens[$token] = true;
            $minimum += ($token==='YYYY' ? 4 : (in_array($token,['YY','MM','DD'],true) ? 2 : 1));
        }
        if ($sequenceCount!==1) throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        $reset = $series['reset_policy'] ?? 'NEVER';
        if ($reset==='YEARLY' && !isset($tokens['YYYY']) && !isset($tokens['YY'])) {
            throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        }
        if ($reset==='MONTHLY' && (!isset($tokens['MM']) || (!isset($tokens['YYYY']) && !isset($tokens['YY'])))) throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        if ($reset==='DAILY' && (!isset($tokens['DD']) || !isset($tokens['MM']) || (!isset($tokens['YYYY']) && !isset($tokens['YY'])))) {
            throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        }
        $scope = $series['scope_type'] ?? 'GROUP';
        if ($scope === 'GROUP' && isset($tokens['COMPANY_CODE'])) {
            throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        }
        if ($minimum>255) throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
    }

    /**
     * Baseline canonical pattern collision fingerprint. Sequence padding width
     * does not make two rendered numbering namespaces independent.
     * Inactive series are excluded from active-pattern collision checks.
     * @param array<string,mixed> $series
     */
    public static function collisionSignature(array $series): ?string
    {
        if (($series['status'] ?? '') !== 'ACTIVE') return null;
        self::validatePattern($series);
        $parts = preg_split('/(\{[^{}]+\})/', (string) $series['pattern'], -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) throw new DomainException('NUM_PATTERN_INVALID', ['field'=>'pattern']);
        $signature = [];
        foreach ($parts as $part) {
            if ($part[0] !== '{') {
                $signature[] = 'L:'.$part;
                continue;
            }
            $token = substr($part, 1, -1);
            $signature[] = 'T:'.(str_starts_with($token, 'SEQ:') ? 'SEQ' : $token);
        }
        return implode('|', $signature);
    }

    /** @param array<string,mixed> $series */
    public static function visible(array $series): array
    {
        if (isset($series['start_number'])) $series['start_number'] = (string) $series['start_number'];
        if (isset($series['is_default'])) $series['is_default'] = (bool) $series['is_default'];
        return $series;
    }
}

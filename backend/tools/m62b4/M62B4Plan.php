<?php

declare(strict_types=1);

/** M6.2B.4 bounded, non-destructive DEV numbering/COMBO test protocol. */
final class M62B4Plan
{
    public static function marker(string $hex): string
    {
        if (preg_match('/\A[a-f0-9]{12}\z/D', $hex) !== 1) throw new InvalidArgumentException('Invalid nonce');
        return 'M62B4_'.strtoupper($hex);
    }

    public static function assertMarker(string $marker): string
    {
        if (preg_match('/\AM62B4_[A-F0-9]{12}\z/D', $marker) !== 1) {
            throw new InvalidArgumentException('Invalid DEV Product marker');
        }
        return $marker;
    }

    public static function id(string $id): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1) throw new InvalidArgumentException('Invalid DEV ID');
        return $id;
    }

    public static function role(string $role): string
    {
        if (!in_array($role, ['a','b'], true)) throw new InvalidArgumentException('Invalid writer role');
        return $role;
    }

    public static function sku(string $marker, string $role): string
    {
        return 'DEV_'.self::assertMarker($marker).'_'.strtoupper(self::role($role));
    }

    /** @return array<string,mixed> */
    public static function product(string $marker, string $role, string $group, string $uom): array
    {
        return [
            'sku'=>self::sku($marker, $role),
            'product_name'=>'[DEV QA] Numbering '.$marker.' '.strtoupper(self::role($role)),
            'product_type'=>'STANDARD', 'product_group_id'=>self::id($group),
            'base_uom_id'=>self::id($uom), 'price'=>'1.00',
            'description'=>'M6.2B.4 two-writer auto issuance '.self::assertMarker($marker),
            // Deliberately no product_code: AUTO series issuance is mandatory.
        ];
    }

    /** @return array<string,mixed> */
    public static function rejectedCombo(string $marker, string $group, string $uom): array
    {
        return [
            'sku'=>'DEV_'.self::assertMarker($marker).'_ROLLBACK',
            'product_name'=>'[DEV QA] COMBO rollback '.self::assertMarker($marker),
            'product_type'=>'COMBO', 'product_group_id'=>self::id($group),
            'base_uom_id'=>self::id($uom), 'price'=>'1.00',
            'description'=>'M6.2B.4 invalid component atomic rollback',
            'combo_items'=>[['component_product_id'=>'999999999999999999','quantity'=>'1.000000']],
        ];
    }

    /** @return array<string,mixed> Parse a single bounded Spark JSON record on LF or Windows CRLF. */
    public static function parseSnapshot(string $output,string $marker): array
    {
        self::assertMarker($marker);
        if (preg_match('/^M62B4_SNAPSHOT_JSON:(\{[^\r\n]*\})\r?$/m',$output,$match)!==1) {
            throw new RuntimeException('Read-only snapshot missing verified protocol');
        }
        $result=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
        if (!is_array($result) || ($result['marker']??null)!==$marker || !is_bool($result['ready']??null)) {
            throw new RuntimeException('Read-only snapshot invalid');
        }
        return $result;
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public static function issued(array $before, array $after, array $a, array $b): bool
    {
        if (!self::sameSeries($before,$after) || ($before['ready']??null)!==true
            || ($after['ready']??null)!==true) return false;
        $rows=$after['products']??null;
        if (!is_array($rows) || !is_array($rows['a']??null) || !is_array($rows['b']??null)
            || ($rows['rollback']??null)!==null) return false;
        foreach (['a'=>$a, 'b'=>$b] as $role=>$worker) {
            if (($worker['status']??null)!==201 || ($worker['get_status']??null)!==200
                || ($worker['matched']??null)!==true) return false;
            if ((string)($worker['id']??'')!==(string)($rows[$role]['id']??'')
                || ($worker['code']??null)!==($rows[$role]['code']??null)
                || ($worker['sku']??null)!==($rows[$role]['sku']??null)) return false;
        }
        if (($rows['a']['code']??null)==='' || ($rows['a']['code']??null)===($rows['b']['code']??null)
            || (string)($rows['a']['id']??'')===(string)($rows['b']['id']??'')
            || (int)($after['simple_combo_components']??-1)!==0) return false;
        $step=(int)($before['increment_by']??0);
        $last=$before['last_value']??null;
        $expected=$last===null ? (int)($before['start_number']??0)+$step : (int)$last+2*$step;
        return $step>0 && $expected>=0 && is_int($after['last_value']??null)
            && $after['last_value'] >= $expected;
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public static function rolledBack(array $before, array $after): bool
    {
        return self::sameSeries($before,$after)
            && ($before['ready']??null)===true && ($after['ready']??null)===true
            && ($before['last_value']??null)===($after['last_value']??null)
            && ($before['counter_id']??null)===($after['counter_id']??null)
            && is_array($after['products']??null)
            && array_key_exists('rollback',$after['products'])
            && $after['products']['rollback']===null
            && ($after['products']['a']??null)===($before['products']['a']??null)
            && ($after['products']['b']??null)===($before['products']['b']??null);
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private static function sameSeries(array $a,array $b): bool
    {
        foreach (['series_id','scope_id','period_key','series_code','increment_by','start_number'] as $key) {
            if (!array_key_exists($key,$a) || !array_key_exists($key,$b) || $a[$key]!==$b[$key]) return false;
        }
        return true;
    }
}

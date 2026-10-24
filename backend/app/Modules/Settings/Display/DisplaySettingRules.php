<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display;

use App\Exceptions\DomainException;
use DateTimeZone;

/** Baseline v1.2.4 Display registry; no executable configuration or user-defined keys. */
final class DisplaySettingRules
{
    /** @var array<string,array{type:string,default:mixed,validation:string,storage:string}> */
    private const DEFINITIONS = [
        'Display.timezone' => ['type'=>'string','default'=>'Asia/Ho_Chi_Minh','validation'=>'timezone','storage'=>'timezone'],
        'Display.dateFormat' => ['type'=>'string','default'=>'DD/MM/YYYY','validation'=>'text','storage'=>'dateFormat'],
        'Display.dateTimeFormat' => ['type'=>'string','default'=>'DD/MM/YYYY HH:mm','validation'=>'text','storage'=>'dateTimeFormat'],
        'Display.timeFormat' => ['type'=>'string','default'=>'HH:mm','validation'=>'text','storage'=>'timeFormat'],
        'Display.firstDayOfWeek' => ['type'=>'string','default'=>'MONDAY','validation'=>'token','storage'=>'firstDayOfWeek'],
        'Display.number.decimalSeparator' => ['type'=>'string','default'=>'.','validation'=>'separator','storage'=>'number_decimalSeparator'],
        'Display.number.groupSeparator' => ['type'=>'string','default'=>',','validation'=>'separator','storage'=>'number_groupSeparator'],
        'Display.quantityDecimals' => ['type'=>'integer','default'=>3,'validation'=>'integer','storage'=>'quantityDecimals'],
        'Display.percentageDecimals' => ['type'=>'integer','default'=>3,'validation'=>'integer','storage'=>'percentageDecimals'],
        'Display.trailingZeros' => ['type'=>'boolean','default'=>false,'validation'=>'boolean','storage'=>'trailingZeros'],
        'Display.money.decimalSeparator' => ['type'=>'string','default'=>'.','validation'=>'separator','storage'=>'money_decimalSeparator'],
        'Display.money.groupSeparator' => ['type'=>'string','default'=>',','validation'=>'separator','storage'=>'money_groupSeparator'],
        'Display.money.symbolMode' => ['type'=>'string','default'=>'SYMBOL','validation'=>'token','storage'=>'money_symbolMode'],
        'Display.money.symbolPosition' => ['type'=>'string','default'=>'AUTO','validation'=>'token','storage'=>'money_symbolPosition'],
        'Display.money.decimalsMode' => ['type'=>'string','default'=>'CURRENCY_DEFAULT','validation'=>'token','storage'=>'money_decimalsMode'],
    ];

    /** @return array<string,array{type:string,default:mixed,validation:string,storage:string}> */
    public static function definitions(): array { return self::DEFINITIONS; }

    public static function storageKey(string $key): string
    {
        if (!isset(self::DEFINITIONS[$key])) throw new DomainException('DISPLAY_SETTING_NOT_FOUND', ['field'=>'key']);
        return self::DEFINITIONS[$key]['storage'];
    }

    public static function validate(string $key, mixed $value): void
    {
        $item = self::DEFINITIONS[$key] ?? null;
        if ($item === null) throw new DomainException('DISPLAY_SETTING_NOT_FOUND', ['field'=>'key']);
        $ok = match ($item['validation']) {
            'timezone' => is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true),
            'text' => is_string($value) && $value !== '',
            'token' => is_string($value) && preg_match('/^[A-Z][A-Z0-9_]*$/D', $value) === 1,
            'separator' => is_string($value) && strlen($value) === 1,
            'integer' => is_int($value) && $value >= 0,
            'boolean' => is_bool($value),
            default => false,
        };
        if (!$ok) throw new DomainException('DISPLAY_SETTING_INVALID', ['field'=>'value']);
    }

    /** @param array<string,mixed> $input @return array{type:string,organization_id:?string,context:string} */
    public static function context(array $input, bool $payload): array
    {
        $typeField = $payload ? 'type' : 'context_type';
        $type = $input[$typeField] ?? null;
        if (!is_string($type)) throw new DomainException('DISPLAY_SETTING_CONTEXT_INVALID', ['field'=>'context']);
        $type = strtoupper(trim($type));
        if ($type === 'SYSTEM' && !array_key_exists('organization_id', $input)) {
            return ['type'=>'SYSTEM','organization_id'=>null,'context'=>'system'];
        }
        $id = $input['organization_id'] ?? null;
        if ($type !== 'ORGANIZATION' || (!is_int($id) && !is_string($id))) {
            throw new DomainException('DISPLAY_SETTING_CONTEXT_INVALID', ['field'=>'context']);
        }
        $text = (string) $id;
        if (preg_match('/^[1-9][0-9]*$/D', $text) !== 1 || strlen($text) > 19 ||
            (strlen($text) === 19 && strcmp($text, '9223372036854775807') > 0)) {
            throw new DomainException('DISPLAY_SETTING_CONTEXT_INVALID', ['field'=>'organization_id']);
        }
        return ['type'=>'ORGANIZATION','organization_id'=>$text,'context'=>'organization:'.$text];
    }

    /** @return array<string,mixed> */
    public static function changes(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input)) throw new DomainException('DISPLAY_SETTING_INVALID', ['field'=>'changes']);
        $out = [];
        foreach ($input as $item) {
            if (!is_array($item) || !is_string($item['key'] ?? null) || !array_key_exists('value',$item)) {
                throw new DomainException('DISPLAY_SETTING_INVALID', ['field'=>'changes']);
            }
            $key = $item['key'];
            if (array_key_exists($key, $out)) throw new DomainException('DISPLAY_SETTING_DUPLICATE_KEY', ['field'=>'key']);
            self::validate($key, $item['value']);
            $out[$key] = $item['value'];
        }
        return $out;
    }

    /** @return list<string> */
    public static function keys(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input)) throw new DomainException('DISPLAY_SETTING_INVALID', ['field'=>'keys']);
        $keys = [];
        foreach ($input as $key) {
            if (!is_string($key) || $key === '') throw new DomainException('DISPLAY_SETTING_INVALID', ['field'=>'keys']);
            self::storageKey($key);
            if (in_array($key, $keys, true)) throw new DomainException('DISPLAY_SETTING_DUPLICATE_KEY', ['field'=>'keys']);
            $keys[] = $key;
        }
        return $keys;
    }

    public static function category(string $key): string
    {
        self::storageKey($key);
        if (str_starts_with($key, 'Display.money.')) return 'MONEY';
        if (str_starts_with($key,'Display.number.') || str_contains($key,'Decimals') || str_contains($key,'trailingZeros')) return 'NUMBER';
        if (str_starts_with($key,'Display.date') || str_starts_with($key,'Display.time') || str_contains($key,'firstDay')) return 'DATE_TIME';
        return 'GENERAL';
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    public static function runtime(array $value): array
    {
        return [
            'timezone'=>$value['Display.timezone'],
            'date'=>['format'=>$value['Display.dateFormat'],'date_time_format'=>$value['Display.dateTimeFormat'],
                'time_format'=>$value['Display.timeFormat'],'first_day_of_week'=>$value['Display.firstDayOfWeek']],
            'number'=>['decimal_separator'=>$value['Display.number.decimalSeparator'],
                'group_separator'=>$value['Display.number.groupSeparator'],
                'quantity_decimals'=>$value['Display.quantityDecimals'],
                'percentage_decimals'=>$value['Display.percentageDecimals'],
                'trailing_zeros'=>$value['Display.trailingZeros']],
            'money'=>['decimal_separator'=>$value['Display.money.decimalSeparator'],
                'group_separator'=>$value['Display.money.groupSeparator'],
                'symbol_mode'=>$value['Display.money.symbolMode'],
                'symbol_position'=>$value['Display.money.symbolPosition'],
                'decimals_mode'=>$value['Display.money.decimalsMode']],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Source-controlled definitions for the server-side Display Settings.
 *
 * This registry deliberately contains presentation settings only. Runtime
 * callers must validate through this class before reaching persistence.
 */
final class DisplaySettingRegistry
{
    /** @var array<string, array{type: string, default: mixed, validation: string, scope: list<string>, user_overridable: bool, storage_key: string}> */
    private const DEFINITIONS = [
        'Display.timezone' => [
            'type'             => 'string',
            'default'          => 'Asia/Ho_Chi_Minh',
            'validation'       => 'iana_timezone',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.timezone',
        ],
        'Display.dateFormat' => [
            'type'             => 'string',
            'default'          => 'DD/MM/YYYY',
            'validation'       => 'non_empty_string',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.dateFormat',
        ],
        'Display.dateTimeFormat' => [
            'type'             => 'string',
            'default'          => 'DD/MM/YYYY HH:mm',
            'validation'       => 'non_empty_string',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.dateTimeFormat',
        ],
        'Display.timeFormat' => [
            'type'             => 'string',
            'default'          => 'HH:mm',
            'validation'       => 'non_empty_string',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.timeFormat',
        ],
        'Display.firstDayOfWeek' => [
            'type'             => 'string',
            'default'          => 'MONDAY',
            'validation'       => 'token',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.firstDayOfWeek',
        ],
        'Display.number.decimalSeparator' => [
            'type'             => 'string',
            'default'          => '.',
            'validation'       => 'separator',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.number_decimalSeparator',
        ],
        'Display.number.groupSeparator' => [
            'type'             => 'string',
            'default'          => ',',
            'validation'       => 'separator',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.number_groupSeparator',
        ],
        'Display.quantityDecimals' => [
            'type'             => 'integer',
            'default'          => 3,
            'validation'       => 'non_negative_integer',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.quantityDecimals',
        ],
        'Display.percentageDecimals' => [
            'type'             => 'integer',
            'default'          => 3,
            'validation'       => 'non_negative_integer',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.percentageDecimals',
        ],
        'Display.trailingZeros' => [
            'type'             => 'boolean',
            'default'          => false,
            'validation'       => 'boolean',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.trailingZeros',
        ],
        'Display.money.decimalSeparator' => [
            'type'             => 'string',
            'default'          => '.',
            'validation'       => 'separator',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.money_decimalSeparator',
        ],
        'Display.money.groupSeparator' => [
            'type'             => 'string',
            'default'          => ',',
            'validation'       => 'separator',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.money_groupSeparator',
        ],
        'Display.money.symbolMode' => [
            'type'             => 'string',
            'default'          => 'SYMBOL',
            'validation'       => 'token',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.money_symbolMode',
        ],
        'Display.money.symbolPosition' => [
            'type'             => 'string',
            'default'          => 'AUTO',
            'validation'       => 'token',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.money_symbolPosition',
        ],
        'Display.money.decimalsMode' => [
            'type'             => 'string',
            'default'          => 'CURRENCY_DEFAULT',
            'validation'       => 'token',
            'scope'            => ['SYSTEM', 'ORGANIZATION'],
            'user_overridable' => true,
            'storage_key'      => 'Display.money_decimalsMode',
        ],
    ];

    /**
     * @return array<string, array{type: string, default: mixed, validation: string, scope: list<string>, user_overridable: bool, storage_key: string}>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return array{type: string, default: mixed, validation: string, scope: list<string>, user_overridable: bool, storage_key: string} */
    public static function definition(string $key): array
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException('Unknown Display Setting key: ' . $key);
        }

        return self::DEFINITIONS[$key];
    }

    public static function default(string $key): mixed
    {
        return self::definition($key)['default'];
    }

    public static function storageKey(string $key): string
    {
        return self::definition($key)['storage_key'];
    }

    public static function canonicalKey(string $storageKey): string
    {
        foreach (self::DEFINITIONS as $canonicalKey => $definition) {
            if ($definition['storage_key'] === $storageKey) {
                return $canonicalKey;
            }
        }

        throw new InvalidArgumentException('Unknown Display Setting storage key: ' . $storageKey);
    }

    public static function validate(string $key, mixed $value): void
    {
        $definition = self::definition($key);

        $validType = match ($definition['type']) {
            'string'  => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default   => false,
        };

        if (! $validType) {
            throw new InvalidArgumentException('Invalid value type for Display Setting: ' . $key);
        }

        $valid = match ($definition['validation']) {
            'iana_timezone'        => in_array($value, DateTimeZone::listIdentifiers(), true),
            'non_empty_string'     => $value !== '',
            'separator'            => strlen($value) === 1,
            'non_negative_integer' => $value >= 0,
            'boolean'              => true,
            'token'                => preg_match('/^[A-Z][A-Z0-9_]*$/', $value) === 1,
            default                => false,
        };

        if (! $valid) {
            throw new InvalidArgumentException('Invalid value for Display Setting: ' . $key);
        }
    }
}

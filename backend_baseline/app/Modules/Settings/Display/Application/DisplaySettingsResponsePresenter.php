<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Application;

/** Stateless public output schema/projection for the Display Settings API. */
final class DisplaySettingsResponsePresenter
{
    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function runtimeSnapshot(array $snapshot): array
    {
        return [
            'timezone' => $snapshot['Display.timezone'],
            'date' => [
                'format' => $snapshot['Display.dateFormat'],
                'date_time_format' => $snapshot['Display.dateTimeFormat'],
                'time_format' => $snapshot['Display.timeFormat'],
                'first_day_of_week' => $snapshot['Display.firstDayOfWeek'],
            ],
            'number' => [
                'decimal_separator' => $snapshot['Display.number.decimalSeparator'],
                'group_separator' => $snapshot['Display.number.groupSeparator'],
                'quantity_decimals' => $snapshot['Display.quantityDecimals'],
                'percentage_decimals' => $snapshot['Display.percentageDecimals'],
                'trailing_zeros' => $snapshot['Display.trailingZeros'],
            ],
            'money' => [
                'decimal_separator' => $snapshot['Display.money.decimalSeparator'],
                'group_separator' => $snapshot['Display.money.groupSeparator'],
                'symbol_mode' => $snapshot['Display.money.symbolMode'],
                'symbol_position' => $snapshot['Display.money.symbolPosition'],
                'decimals_mode' => $snapshot['Display.money.decimalsMode'],
            ],
        ];
    }

    public static function category(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'Display.money.') => 'MONEY',
            str_starts_with($key, 'Display.number.') || str_contains($key, 'Decimals') || str_contains($key, 'trailingZeros') => 'NUMBER',
            str_starts_with($key, 'Display.date') || str_starts_with($key, 'Display.time') || str_contains($key, 'firstDay') => 'DATE_TIME',
            default => 'GENERAL',
        };
    }
}

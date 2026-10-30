<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;
use App\Modules\Numbering\Configuration\NumberingRules;
use DateTimeImmutable;

/**
 * SUP-DEC-05 selection and sequence rules; no database, permissions, or writes.
 * A configured default includes INACTIVE defaults and must fail closed.
 */
final class SupplierNumberingPolicy
{
    private const MAX_TRIES = 1000;

    public static function requestedCode(?string $code): ?string
    {
        if ($code === null) return null;
        $code = strtoupper(trim($code));
        if (preg_match('/\A[A-Z][A-Z0-9_.:-]{0,99}\z/D', $code) !== 1) {
            throw new DomainException('NUM_INVALID_CONFIGURATION', ['field' => 'numbering_series_code']);
        }
        return $code;
    }

    /**
     * @param list<array<string,mixed>> $defaults ALL default rows, regardless of status.
     * @param ?array<string,mixed> $requestedRow From the Supplier resource ONLY.
     * @return array{kind:'MANUAL',code:string}|array{kind:'AUTO',series:array<string,mixed>}
     */
    public static function choose(
        array $defaults,
        ?string $requestedCode,
        ?string $manualCode,
        ?array $requestedRow
    ): array {
        $requested = self::requestedCode($requestedCode);
        $manual = $manualCode === null || trim($manualCode) === ''
            ? null : self::manualCode($manualCode);
        if (count($defaults) > 1) {
            throw new DomainException('NUM_IDENTIFIER_DEFAULT_AMBIGUOUS');
        }
        $default = $defaults[0] ?? null;
        // Validate the configured default BEFORE inspecting requested Series or
        // manual input. Neither is an escape hatch for a bad default.
        if ($default !== null) self::validateSeries($default);

        if ($requested !== null) {
            if ($manual !== null) throw new DomainException('NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
            if ($requestedRow === null || (string)($requestedRow['series_code'] ?? '') !== $requested) {
                throw new DomainException('NUM_IDENTIFIER_SERIES_NOT_FOUND');
            }
            self::validateSeries($requestedRow);
            return ['kind' => 'AUTO', 'series' => $requestedRow];
        }
        if ($default !== null) {
            // Unlike Customer AUTO_IF_EMPTY, Supplier never accepts a manual
            // identifier when any default is configured.
            if ($manual !== null) throw new DomainException('NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
            return ['kind' => 'AUTO', 'series' => $default];
        }
        if ($manual === null) {
            throw new DomainException('SUPPLIER_CODE_REQUIRED', ['field' => 'supplier_code']);
        }
        return ['kind' => 'MANUAL', 'code' => $manual];
    }

    /** @param array<string,mixed> $series */
    public static function validateSeries(array $series): void
    {
        if (($series['status'] ?? null) === 'INACTIVE') {
            throw new DomainException('NUM_IDENTIFIER_SERIES_INACTIVE');
        }
        if (($series['status'] ?? null) !== 'ACTIVE'
            || !in_array($series['generation_mode'] ?? null, ['AUTO_REQUIRED', 'AUTO_IF_EMPTY'], true)
            || !in_array($series['reset_policy'] ?? null, ['NEVER', 'YEARLY', 'MONTHLY', 'DAILY'], true)) {
            throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        }
        // SUP is a Group MASTER. Never infer COMPANY from a client hint and
        // never reinterpret ORGANIZATION as the Group root.
        if (($series['scope_type'] ?? null) !== 'GROUP') {
            throw new DomainException('NUM_SCOPE_CONTEXT_REQUIRED');
        }
        self::number($series['start_number'] ?? null, 0);
        self::number($series['increment_by'] ?? null, 1);
        NumberingRules::validatePattern($series);
    }

    public static function manualCode(string $code): string
    {
        try {
            return SupplierRules::normalizeCode($code);
        } catch (\InvalidArgumentException $e) {
            throw new DomainException('SUPPLIER_CODE_INVALID', ['field' => 'supplier_code'], $e);
        }
    }

    public static function number(mixed $value, int $minimum): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        }
        $number = (string)$value;
        $limit = (string)PHP_INT_MAX;
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $number) !== 1
            || strlen($number) > strlen($limit)
            || (strlen($number) === strlen($limit) && strcmp($number, $limit) > 0)
            || (int)$number < $minimum) {
            throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        }
        return (int)$number;
    }

    public static function next(int $last, int $increment): int
    {
        if ($last < 0 || $increment < 1 || $last > PHP_INT_MAX - $increment) {
            throw new DomainException('NUM_COUNTER_OVERFLOW');
        }
        return $last + $increment;
    }

    /** @param array<string,mixed> $series */
    public static function periodKey(array $series, DateTimeImmutable $now): string
    {
        return match ($series['reset_policy'] ?? null) {
            'NEVER' => 'ALL', 'YEARLY' => $now->format('Y'),
            'MONTHLY' => $now->format('Ym'), 'DAILY' => $now->format('Ymd'),
            default => throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID'),
        };
    }

    public static function render(string $pattern, int $sequence, DateTimeImmutable $now, string $groupCode): string
    {
        $result = preg_replace_callback('/\{([^{}]*)\}/',
            static function (array $part) use ($sequence, $now, $groupCode): string {
                if (preg_match('/\ASEQ:([0-9]+)\z/D', $part[1], $width) === 1) {
                    return str_pad((string)$sequence, (int)$width[1], '0', STR_PAD_LEFT);
                }
                return match ($part[1]) {
                    'YYYY' => $now->format('Y'), 'YY' => $now->format('y'),
                    'MM' => $now->format('m'), 'DD' => $now->format('d'),
                    'ORG_CODE' => $groupCode, 'RESOURCE' => 'supplier',
                    default => throw new DomainException('NUM_COUNTER_RUNTIME_CONTEXT_REQUIRED', ['field' => 'pattern']),
                };
            }, $pattern);
        if (!is_string($result)) throw new DomainException('NUM_PATTERN_INVALID');
        return self::manualCode($result);
    }

    public static function maxAttempts(): int
    {
        return self::MAX_TRIES;
    }
}

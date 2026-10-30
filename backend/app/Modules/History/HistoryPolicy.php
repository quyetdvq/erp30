<?php

declare(strict_types=1);

namespace App\Modules\History;

use InvalidArgumentException;

/**
 * Reviewed per-resource selective audit. Do not infer business operation from
 * the spelling of a Lifecycle state or from user-supplied request fields.
 *
 * `operationalStates` is an explicit server-owned deployment decision, NOT the
 * Lifecycle initial state and NOT PBAC. A custom status must be mapped and
 * regression-tested before it is considered operational for update capture.
 */
final class HistoryPolicy
{
    private const FIELD_MODES = [
        'customer' => [
            'customer_code'=>'plain', 'customer_type'=>'plain',
            'customer_group_code'=>'plain', 'all_companies'=>'flag',
            'name'=>'changed', 'phone'=>'changed', 'email'=>'changed',
            'tax_code'=>'changed', 'address'=>'changed',
            'province_id'=>'changed', 'ward_id'=>'changed',
            'primary_contact_id'=>'changed', 'company_ids'=>'count',
        ],
        'supplier' => [
            'supplier_code'=>'plain', 'supplier_group_code'=>'plain',
            'payment_term_code'=>'plain', 'all_companies'=>'flag',
            'supplier_type'=>'plain', 'name'=>'changed', 'phone'=>'changed',
            'email'=>'changed', 'tax_code'=>'changed', 'address'=>'changed',
            'province_id'=>'changed', 'ward_id'=>'changed',
            'company_ids'=>'count', 'bank_account'=>'changed',
        ],
    ];
    private const OPERATIONAL = [
        'customer' => ['ACTIVE'],
        'supplier' => ['ACTIVE'],
    ];

    public static function allowsResource(string $resource): bool
    {
        return array_key_exists($resource, self::FIELD_MODES);
    }

    public static function isOperational(string $resource, string $status): bool
    {
        self::assertResource($resource);
        return in_array($status, self::OPERATIONAL[$resource], true);
    }

    /** An editor on a draft-like state produces no business UPDATE event. */
    public static function shouldRecordUpdate(string $resource, string $statusBefore, string $statusAfter): bool
    {
        return self::isOperational($resource, $statusBefore)
            || self::isOperational($resource, $statusAfter);
    }

    /**
     * Explicit allowlist, not a generic JSON dump. Sensitive/PII data is never
     * copied into the audit table. Only declared non-sensitive codes use values.
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<string,array<int,string|int|bool|null>|\stdClass>
     */
    public static function diff(string $resource, array $before, array $after): array
    {
        self::assertResource($resource);
        $changes = [];
        foreach (self::FIELD_MODES[$resource] as $field => $mode) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if (self::equivalent($field, $old, $new)) continue;
            // HIS-POL-002: compact, NEW-ONLY schema. A two-item tuple is
            // [before, after]; a JSON object {} is a redacted change marker.
            // stdClass is essential: encoding PHP [] would produce [] not {}.
            $changes[$field] = match ($mode) {
                'plain' => [self::safeCode($old), self::safeCode($new)],
                'count' => [self::safeCount($old), self::safeCount($new)],
                'flag' => [self::flag($old), self::flag($new)],
                'changed' => new \stdClass(),
                default => throw new InvalidArgumentException('Unreviewed History field mode.'),
            };
        }
        return $changes;
    }

    /** Minimal business checkpoint. Never an unreviewed full resource snapshot. */
    public static function checkpoint(string $resource, array $row): array
    {
        self::assertResource($resource);
        $point = [];
        foreach (self::FIELD_MODES[$resource] as $field => $mode) {
            if (!array_key_exists($field, $row)) continue;
            if ($mode === 'plain') {
                $point[$field] = self::safeCode($row[$field]);
            } elseif ($mode === 'flag') {
                $point[$field] = self::flag($row[$field]);
            } elseif ($mode === 'count') {
                $point[$field . '_count'] = self::safeCount($row[$field]);
            } elseif ($mode === 'changed') {
                $point[$field . '_present'] = $row[$field] !== null && $row[$field] !== '';
            }
        }
        return $point;
    }

    private static function assertResource(string $resource): void
    {
        if (!self::allowsResource($resource)) {
            throw new InvalidArgumentException('Unreviewed History resource.');
        }
    }

    private static function equivalent(string $field, mixed $old, mixed $new): bool
    {
        if ($field === 'all_companies') {
            if ($old === null && $new === null) return true;
            return self::flag($old) === self::flag($new);
        }
        if ($field === 'company_ids') {
            // Scope is a set, not an order. Do not expose historic Company IDs.
            if (!is_array($old) || !is_array($new)) {
                throw new InvalidArgumentException('Company scope must be verified array.');
            }
            $a = array_map('strval', $old); $b = array_map('strval', $new);
            sort($a, SORT_STRING); sort($b, SORT_STRING);
            return $a === $b;
        }
        // ID values may be int when read from MySQL, string when normalized.
        if (in_array($field, ['province_id','ward_id','primary_contact_id'], true)) {
            return ($old === null || $new === null)
                ? $old === null && $new === null : (string)$old === (string)$new;
        }
        return $old === $new;
    }

    private static function flag(mixed $value): int
    {
        if (in_array($value, [true, 1, '1'], true)) return 1;
        if (in_array($value, [false, 0, '0'], true)) return 0;
        throw new InvalidArgumentException('Invalid persisted History flag.');
    }

    private static function safeCode(mixed $value): string|int|bool|null
    {
        if ($value === null || is_bool($value) || is_int($value)) return $value;
        if (!is_string($value) || strlen($value) > 100
            || preg_match('/\A[A-Za-z0-9_.:-]*\z/D', $value) !== 1) {
            // Never store arbitrary names/HTML/PII in a so-called code field.
            return '[REDACTED]';
        }
        return $value;
    }

    private static function safeCount(mixed $value): int
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 4096) {
            throw new InvalidArgumentException('Unreviewed Company scope input.');
        }
        return count($value);
    }
}

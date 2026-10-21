<?php

declare(strict_types=1);

namespace Tests\Support\Scratch;

use RuntimeException;

/**
 * Test-only ownership markers. A regular NO-DROP unit run returns the original
 * fixture values unchanged. Scratch workers require a pinned bootstrap and a
 * per-process token before they may alter any fixture field.
 */
final class NumberingMarker
{
    public static function token(): ?string
    {
        if (! defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED') || ERP_SCRATCH_NUMBERING_BOOTSTRAPPED !== true) {
            return null;
        }
        $token = getenv('ERP_SCRATCH_NUMBERING_TOKEN');
        if (! is_string($token) || preg_match('/\A[A-F0-9]{16}\z/D', $token) !== 1) {
            throw new RuntimeException('03C-04B STOP: invalid scratch ownership token.');
        }
        return $token;
    }

    public static function tag(string $value): string
    {
        $token = self::token();
        return $token === null ? $value : $value . '-S04B-' . $token;
    }

    /** Canonical Resource types must be lowercase even on case-sensitive DB collations. */
    public static function resourceType(string $value): string
    {
        return strtolower(self::tag($value));
    }

    public static function sku(string $code): ?string
    {
        $token = self::token();
        return $token === null ? null : 'S04B-' . $token . '-' . $code;
    }
    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public static function product(array $fields, string $code): array
    {
        $sku = self::sku($code);
        if ($sku !== null) {
            $fields['sku'] = $sku;
        }
        return $fields;
    }
}

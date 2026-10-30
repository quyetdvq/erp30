<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use InvalidArgumentException;

/**
 * Key used only for Customer-wide phone uniqueness, never an authorization scope.
 * Ignore common display separators; treat Vietnamese 0 / +84 / 0084 / 84
 * prefixes as the same phone. Do not silently rewrite the user's display phone.
 */
final class CustomerPhone
{
    public static function key(string $phone): string
    {
        $compact = preg_replace('/[\s().-]+/u', '', trim($phone));
        if ($compact === null || preg_match('/\A\+?[0-9]{7,15}\z/D', $compact) !== 1) {
            throw new InvalidArgumentException('Invalid Customer phone format.');
        }
        foreach (['+84', '0084', '84'] as $prefix) {
            if (str_starts_with($compact, $prefix) && strlen($compact) === strlen($prefix) + 9) {
                return '0' . substr($compact, strlen($prefix));
            }
        }
        return $compact;
    }
}

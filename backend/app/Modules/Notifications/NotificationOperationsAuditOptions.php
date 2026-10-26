<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/** Accept only the two safe output/CI flags, across CodeIgniter Spark CLI formats. */
final class NotificationOperationsAuditOptions
{
    /** @param array<array-key,mixed> $params
     * @return array{json:bool,strict:bool} */
    public static function parse(array $params): array
    {
        $options = ['json' => false, 'strict' => false];
        foreach ($params as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $name = $value;
            } elseif (is_string($key) && ($value === null || $value === true)) {
                $name = $key;
            } else {
                throw new RuntimeException('M11_OPERATIONS_AUDIT_INVALID_OPTIONS');
            }
            if (str_starts_with($name, '--')) {
                $name = substr($name, 2);
            }
            if (! array_key_exists($name, $options) || $options[$name]) {
                throw new RuntimeException('M11_OPERATIONS_AUDIT_INVALID_OPTIONS');
            }
            $options[$name] = true;
        }
        return $options;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/**
 * SMTP setup preflight is read-only by default. A one-address network send
 * requires both the recipient and a literal, explicit operator confirmation.
 */
final class NotificationSmtpProbeOptions
{
    private const CONFIRMATION = 'SEND_ONE_TEST_EMAIL';

    /**
     * @param array<array-key,mixed> $params Spark may pass positional or keyed CLI flags.
     * @return array{test_recipient:?string}
     */
    public static function parse(array $params): array
    {
        if ($params === []) {
            return ['test_recipient' => null];
        }

        $options = [];
        foreach ($params as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $raw = $value;
            } elseif (is_string($key) && is_string($value)) {
                $raw = $key . '=' . $value;
            } elseif (is_string($key) && $value === null) {
                $raw = $key;
            } else {
                throw new RuntimeException('M11_SMTP_CHECK_INVALID_OPTIONS');
            }

            if (str_starts_with($raw, '--')) {
                $raw = substr($raw, 2);
            }
            $parts = explode('=', $raw, 2);
            if (count($parts) !== 2 || ! in_array($parts[0], ['to', 'confirm'], true)
                || array_key_exists($parts[0], $options) || $parts[1] === '') {
                throw new RuntimeException('M11_SMTP_CHECK_INVALID_OPTIONS');
            }
            $options[$parts[0]] = $parts[1];
        }

        if (count($options) !== 2
            || ($options['confirm'] ?? null) !== self::CONFIRMATION
            || NotificationEmailRules::address($options['to'] ?? null) === null) {
            throw new RuntimeException('M11_SMTP_CHECK_SEND_REQUIRES_SCOPED_CONFIRMATION');
        }

        return ['test_recipient' => $options['to']];
    }
}

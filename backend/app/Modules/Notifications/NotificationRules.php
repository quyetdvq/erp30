<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Strict public JSON contract. BIGINT revisions are canonical decimal strings. */
final class NotificationRules
{
    /** @param array<string,mixed> $body @return list<array{key:string,default_value:bool,allow_user_override:bool,expected_revision:string}> */
    public static function policyChanges(array $body): array
    {
        return self::parse($body, 'policy');
    }

    /** @param array<string,mixed> $body @return list<array{key:string,value:?bool,expected_override_epoch:string,expected_user_revision:string}> */
    public static function preferenceChanges(array $body): array
    {
        return self::parse($body, 'preference');
    }

    /** @param array<string,mixed> $body @return list<array<string,mixed>> */
    private static function parse(array $body, string $kind): array
    {
        if (array_keys($body) !== ['changes'] || ! is_array($body['changes'])
            || ! array_is_list($body['changes']) || count($body['changes']) < 1
            || count($body['changes']) > count(NotificationCatalog::definitions())) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $fields = $kind === 'policy'
            ? ['allow_user_override', 'default_value', 'expected_revision', 'key']
            : ['expected_override_epoch', 'expected_user_revision', 'key', 'value'];
        sort($fields);
        $seen = [];
        $out = [];
        foreach ($body['changes'] as $index => $change) {
            if (! is_array($change) || array_is_list($change) || count($change) !== 4) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => "changes.{$index}"]);
            }
            $actual = array_keys($change);
            sort($actual);
            if ($actual !== $fields) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => "changes.{$index}"]);
            }
            $key = $change['key'] ?? null;
            $definition = is_string($key) ? (NotificationCatalog::definitions()[$key] ?? null) : null;
            if ($definition === null || $definition['capability'] !== 'AVAILABLE' || isset($seen[$key])) {
                throw new DomainException('NOTIFICATION_INVALID_KEY', ['field' => "changes.{$index}.key"]);
            }
            $seen[$key] = true;
            if ($kind === 'policy') {
                if (! is_bool($change['default_value']) || ! is_bool($change['allow_user_override'])
                    || ! self::version($change['expected_revision'])) {
                    throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => "changes.{$index}"]);
                }
                if (($definition['mandatory'] && (!$change['default_value'] || $change['allow_user_override']))
                    || (in_array($key, ['import.completed.email','import.completed.push'], true) && $change['default_value'])) {
                    throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => "changes.{$index}"]);
                }
            } else {
                if (! (is_bool($change['value']) || $change['value'] === null)
                    || ! self::version($change['expected_override_epoch'])
                    || ! self::version($change['expected_user_revision'], true)) {
                    throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => "changes.{$index}"]);
                }
            }
            $out[] = $change;
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        return $out;
    }

    public static function version(mixed $value, bool $allowZero = false): bool
    {
        if (! is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,19})\z/D', $value) !== 1) {
            return false;
        }
        if (! $allowZero && $value === '0') {
            return false;
        }
        $max = '18446744073709551615';
        return strlen($value) < strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) <= 0);
    }
}

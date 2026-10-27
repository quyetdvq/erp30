<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Public diagnostic DTO: bounded numeric counters and predefined reason codes only. */
final class NotificationOperationsDiagnosticsRules
{
    public const CATEGORIES = ['TRANSPORT', 'LEASE_EXHAUSTED', 'POLICY', 'IDENTITY', 'OTHER', 'UNSPECIFIED'];

    /** @var array<string,list<string>> */
    private const STATUS = [
        'outbox' => ['PENDING', 'LEASED', 'RETRY', 'PROCESSED', 'SUPPRESSED', 'DEAD'],
        'email' => ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
        'push' => ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
    ];

    /** @return list<string> */
    public static function statuses(string $queue): array
    {
        return self::STATUS[$queue] ?? throw new DomainException('NOTIFICATION_STORAGE_FAILED');
    }

    public static function count(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value))
            || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', (string) $value) !== 1
            || strlen((string) $value) > strlen((string) PHP_INT_MAX)
            || (strlen((string) $value) === strlen((string) PHP_INT_MAX)
                && strcmp((string) $value, (string) PHP_INT_MAX) > 0)) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return (int) $value;
    }

    /**
     * A reason is a fixed category, never a raw last_error_code from queue storage.
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    public static function categoryCounts(array $rows): array
    {
        if (count($rows) > count(self::CATEGORIES)) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $result = [];
        foreach ($rows as $row) {
            $category = $row['category'] ?? null;
            if (! is_string($category) || ! in_array($category, self::CATEGORIES, true)
                || array_key_exists($category, $result)) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            $result[$category] = self::count($row['n'] ?? null);
        }
        return $result;
    }

    /** @param array<string,int> $counters @return list<string> */
    public static function findings(bool $available, array $counters): array
    {
        if (! $available) {
            return ['QUEUE_UNAVAILABLE'];
        }
        $codes = [
            'DEAD_PRESENT' => 'dead',
            'EXPIRED_LEASE' => 'expired_leases',
            'OVERDUE_60M' => 'overdue_ready_60m',
            'OVERDUE_15M' => 'overdue_ready_15m',
            'RETRY_READY' => 'retry_ready',
            'RETRY_DELAYED' => 'retry_delayed',
        ];
        $findings = [];
        foreach ($codes as $code => $name) {
            if (! array_key_exists($name, $counters)) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            if (self::count($counters[$name]) > 0) {
                $findings[] = $code;
            }
        }
        return $findings;
    }
}

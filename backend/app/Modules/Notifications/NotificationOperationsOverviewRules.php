<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Pure public DTO allowlist. Unknown or corrupt persisted status fails closed. */
final class NotificationOperationsOverviewRules
{
    private const STATUS = [
        'outbox' => ['PENDING', 'LEASED', 'RETRY', 'PROCESSED', 'SUPPRESSED', 'DEAD'],
        'email' => ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
        'push' => ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
    ];

    /** @param list<array<string,mixed>> $rows @return array<string,int> */
    public static function statusCounts(string $queue, array $rows): array
    {
        if (! isset(self::STATUS[$queue]) || count($rows) > count(self::STATUS[$queue])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? null;
            $value = $row['n'] ?? null;
            if (! is_string($status) || ! in_array($status, self::STATUS[$queue], true)
                || array_key_exists($status, $counts)
                || (! is_string($value) && ! is_int($value))
                || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', (string) $value) !== 1
                || strlen((string) $value) > strlen((string) PHP_INT_MAX)
                || (strlen((string) $value) === strlen((string) PHP_INT_MAX)
                    && strcmp((string) $value, (string) PHP_INT_MAX) > 0)) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            $counts[$status] = (int) $value;
        }
        return $counts;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

/** Shared, byte-compatible field order and UTC formatting for the legacy Inbox DTO. */
final class NotificationInboxProjectionFields
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $params
     * @param array<string,string> $target
     * @return array<string,mixed>
     */
    public static function envelope(array $row, string $type, array $params, array $target): array
    {
        return [
            'id' => (string) $row['id'], 'type' => $type, 'priority' => (string) $row['priority'],
            'title_key' => (string) $row['title_key'], 'body_key' => (string) $row['body_key'],
            'params' => $params, 'target' => $target,
            'read_at' => $row['read_at'] === null ? null : self::iso((string) $row['read_at']),
            'created_at' => self::iso((string) $row['created_at']),
        ];
    }

    private static function iso(string $dbValue): string
    {
        // Preserve legacy DATETIME(6) -> UTC ISO behavior without PHP timezone conversion.
        return str_replace(' ', 'T', $dbValue) . 'Z';
    }
}

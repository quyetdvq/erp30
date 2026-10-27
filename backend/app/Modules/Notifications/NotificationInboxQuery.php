<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use DateTimeImmutable;
use DateTimeZone;

/** Strict, bounded Inbox query. The legacy all/unread request retains cursor v1. */
final class NotificationInboxQuery
{
    /** @param array<string,mixed> $query
     *  @return array{filter:string,page_size:int,cursor:?string,event_group:string,from:?string,to:?string,extended:bool}
     */
    public static function parse(array $query): array
    {
        foreach (array_keys($query) as $key) {
            if (! in_array($key, ['filter', 'page_size', 'cursor', 'event_group', 'from', 'to'], true)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }

        $filter = $query['filter'] ?? 'all';
        $size = $query['page_size'] ?? '20';
        $cursor = $query['cursor'] ?? null;
        $group = array_key_exists('event_group', $query) ? $query['event_group'] : 'all';
        $from = $query['from'] ?? null;
        $to = $query['to'] ?? null;
        $extended = array_key_exists('event_group', $query)
            || array_key_exists('from', $query) || array_key_exists('to', $query);

        if (! is_string($filter) || ! in_array($filter, ['all', 'unread'], true)
            || ! is_string($size) || ! preg_match('/\A(?:[1-9]|[1-4][0-9]|50)\z/D', $size)
            || ($cursor !== null && (! is_string($cursor) || $cursor === ''))
            || ! is_string($group) || ! in_array($group, ['all', 'import', 'workflow'], true)
            || array_key_exists('from', $query) !== array_key_exists('to', $query)
            || (array_key_exists('from', $query) && (! is_string($from) || ! is_string($to)))) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        if ($from !== null) {
            if (! self::validDate($from) || ! self::validDate($to)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
            $first = new DateTimeImmutable($from . 'T00:00:00Z');
            $last = new DateTimeImmutable($to . 'T00:00:00Z');
            // Maximum 366 inclusive UTC calendar days; bounded historical queries.
            if ($first > $last || $first->diff($last)->days > 365) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }

        return [
            'filter' => $filter, 'page_size' => (int) $size, 'cursor' => $cursor,
            'event_group' => $group, 'from' => $from, 'to' => $to, 'extended' => $extended,
        ];
    }

    private static function validDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value && $value >= '1970-01-01' && $value <= '2099-12-31';
    }
}

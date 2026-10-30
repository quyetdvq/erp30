<?php

declare(strict_types=1);

namespace App\Modules\History;

use CodeIgniter\HTTP\Exceptions\BadRequestException;
use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;

/** One parent resource per read, no global scan and no unscoped query. */
final class HistorySql
{
    /** @param array<string,mixed> $query @return array{page:int,page_size:int} */
    public static function params(array $query): array
    {
        foreach (array_keys($query) as $key) {
            if (!in_array($key, ['page','page_size'], true)) {
                throw new BadRequestException('Unreviewed history filter.');
            }
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '25');
        if ($size > 50 || $page > intdiv(1000000, $size)) {
            throw new BadRequestException('Invalid History pagination.');
        }
        return ['page'=>$page, 'page_size'=>$size];
    }

    /**
     * Caller MUST have proven parent VIEW with canonical SQL + PBAC in the
     * same REPEATABLE READ transaction. This query never decides authority.
     * @param array{page:int,page_size:int} $params
     * @return array{count_sql:string,count_binds:array<int,string>,rows_sql:string,rows_binds:array<int,string|int>}
     */
    public static function forVisibleParent(string $resource, string $id, array $params, string $prefix, string $usersLogical = 'users'): array
    {
        if (!in_array($resource, ['customer', 'supplier'], true)
            || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1 || strlen($id) > 20) {
            throw new InvalidArgumentException('Unreviewed History parent.');
        }
        if ($prefix !== '' && preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid History DBPrefix.');
        }
        $table = self::identifier($prefix, 'business_history_events');
        $users = self::identifier($prefix, $usersLogical);
        $where = ' FROM ' . $table . ' h WHERE h.resource_type = ? AND h.resource_id = ?'
            . " AND h.owner_domain = 'MASTER' AND h.owner_organization_id IS NULL";
        return [
            'count_sql'=>'SELECT COUNT(*) AS total' . $where,
            'count_binds'=>[$resource,$id],
            'rows_sql'=>'SELECT ' . self::projection()
                . ' FROM ' . $table . ' h LEFT JOIN ' . $users
                . " u ON u.id = h.actor_user_id AND h.actor_type = 'USER'"
                . ' WHERE h.resource_type = ? AND h.resource_id = ?'
                . " AND h.owner_domain = 'MASTER' AND h.owner_organization_id IS NULL"
                . ' ORDER BY h.id DESC LIMIT ? OFFSET ?',
            'rows_binds'=>[$resource,$id,$params['page_size'],($params['page']-1)*$params['page_size']],
        ];
    }

    /**
     * Privileged monitoring intentionally spans ALL onboarded History resources.
     * A separate SYSTEM PBAC resource is required before any query is executed.
     * UTC date windows are explicit and bounded to 90 days, including older windows.
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function monitorParams(array $query, ?DateTimeImmutable $today = null): array
    {
        foreach (array_keys($query) as $key) {
            if (!in_array($key, ['page','page_size','from','to','resource_type',
                'event_type','actor_user_id','resource_id'], true)) {
                throw new BadRequestException('Unreviewed History monitor filter.');
            }
        }
        $page = self::positive($query['page'] ?? '1');
        $size = self::positive($query['page_size'] ?? '25');
        if ($size > 50 || $page > 1000) {
            throw new BadRequestException('Invalid History monitor pagination.');
        }
        $now = ($today ?? new DateTimeImmutable('today', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $fromRaw = $query['from'] ?? null;
        $toRaw = $query['to'] ?? null;
        if (($fromRaw === null) !== ($toRaw === null)) {
            throw new BadRequestException('History monitor requires both UTC dates.');
        }
        $from = $fromRaw === null ? $now->modify('-29 days') : self::utcDay($fromRaw);
        $to = $toRaw === null ? $now : self::utcDay($toRaw);
        $days = (int) $from->diff($to)->format('%r%a');
        if ($days < 0 || $days > 89) {
            throw new BadRequestException('History monitor UTC window exceeds 90 days.');
        }
        $result = [
            'page'=>$page, 'page_size'=>$size,
            'from'=>$from->format('Y-m-d'), 'to'=>$to->format('Y-m-d'),
            'from_db'=>$from->format('Y-m-d') . ' 00:00:00',
            'to_exclusive_db'=>$to->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
        foreach (['resource_type','event_type','actor_user_id','resource_id'] as $field) {
            $value = $query[$field] ?? '';
            if (!is_string($value) && !is_int($value)) {
                throw new BadRequestException('Invalid History monitor filter.');
            }
            $value = (string) $value;
            if ($field === 'resource_type' && $value !== '' && !in_array($value, ['customer','supplier'], true)) {
                throw new BadRequestException('Unknown History resource.');
            }
            if ($field === 'event_type' && $value !== '' && !in_array($value, [
                'CREATE','UPDATE','STATE_CHANGE','SENSITIVE_CHANGE',
                'SUBMIT','APPROVE','REJECT','DELETE','RESTORE'], true)) {
                throw new BadRequestException('Unknown History event type.');
            }
            if (str_ends_with($field, '_id') && $value !== '' &&
                (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1 || strlen($value) > 20)) {
                throw new BadRequestException('Invalid History monitor ID.');
            }
            $result[$field] = $value;
        }
        if ($result['resource_id'] !== '' && $result['resource_type'] === '') {
            throw new BadRequestException('History resource ID requires resource type.');
        }
        return $result;
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public static function forMonitor(array $params, string $prefix, string $usersLogical = 'users'): array
    {
        $table = self::identifier($prefix, 'business_history_events');
        $users = self::identifier($prefix, $usersLogical);
        $customers = self::identifier($prefix, 'customers');
        $suppliers = self::identifier($prefix, 'suppliers');
        $where = ' WHERE h.created_at >= ? AND h.created_at < ?';
        $binds = [$params['from_db'], $params['to_exclusive_db']];
        foreach (['resource_type','event_type','actor_user_id','resource_id'] as $field) {
            if ($params[$field] === '') continue;
            $where .= ' AND h.' . $field . ' = ?';
            $binds[] = $params[$field];
        }
        return [
            'count_sql'=>'SELECT COUNT(*) AS total FROM ' . $table . ' h' . $where,
            'count_binds'=>$binds,
            'rows_sql'=>'SELECT ' . self::projection()
                . ',COALESCE(c.customer_code,s.supplier_code) AS resource_code'
                . ',COALESCE(c.name,s.name) AS resource_name'
                . ' FROM ' . $table . ' h LEFT JOIN ' . $users
                . " u ON u.id = h.actor_user_id AND h.actor_type = 'USER'"
                // SYSTEM monitor authorization precedes SQL; no Company Usage join.
                // Each one-to-one enrichment is type-gated and cannot fan out COUNT.
                . ' LEFT JOIN ' . $customers
                . " c ON h.resource_type = 'customer' AND c.id = h.resource_id"
                . ' LEFT JOIN ' . $suppliers
                . " s ON h.resource_type = 'supplier' AND s.id = h.resource_id"
                . $where . ' ORDER BY h.created_at DESC,h.id DESC LIMIT ? OFFSET ?',
            'rows_binds'=>[...$binds, $params['page_size'], ($params['page']-1)*$params['page_size']],
        ];
    }

    private static function projection(): string
    {
        return 'h.id,h.resource_type,h.resource_id,h.event_type,h.action_code,'
            . 'h.status_before,h.status_after,h.changes_json,h.checkpoint_json,'
            . 'h.actor_user_id,h.actor_type,h.created_at,h.request_id,'
            . 'u.username AS actor_username,u.full_name AS actor_full_name';
    }

    private static function identifier(string $prefix, string $logical): string
    {
        if (($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1)
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $logical) !== 1) {
            throw new InvalidArgumentException('Invalid History DB identifier.');
        }
        return '`' . $prefix . $logical . '`';
    }

    private static function utcDay(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) !== 1) {
            throw new BadRequestException('Invalid History UTC date.');
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$day || $day->format('Y-m-d') !== $value) {
            throw new BadRequestException('Invalid History UTC date.');
        }
        return $day;
    }

    private static function positive(mixed $raw): int
    {
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/\A[1-9][0-9]*\z/D', (string)$raw)
            || strlen((string)$raw) > 7) {
            throw new BadRequestException('Invalid History pagination.');
        }
        return (int)$raw;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use App\Modules\Notifications\Events\NotificationInboxProjectorRegistry;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/** Every query is scoped to the authenticated Shield User; no global Inbox routes. */
final class NotificationInboxService
{
    private readonly string $userId;
    private readonly BaseConnection $db;
    private readonly string $table;

    public function __construct(User $user, ?BaseConnection $db = null)
    {
        $this->userId = (string) $user->id;
        if (! ctype_digit($this->userId) || $this->userId === '0'
            || ! $user->active || $user->deleted_at !== null) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $this->db = $db ?? db_connect();
        $physical = $this->db->prefixTable('user_notifications');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $this->table = '`' . $physical . '`';
    }

    /** @return array{unread_count:int} */
    public function summary(): array
    {
        $row = $this->select("SELECT COUNT(*) AS total FROM {$this->table}"
            . ' WHERE recipient_user_id=? AND read_at IS NULL'
            . ' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))', [$this->userId])->getRowArray();
        return ['unread_count' => (int) ($row['total'] ?? 0)];
    }

    /** @param array<string,mixed> $query @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function listing(array $query): array
    {
        $selection = NotificationInboxQuery::parse($query);
        $filter = $selection['filter'];
        $pageSize = $selection['page_size'];
        $cursor = $selection['cursor'];
        $group = $selection['event_group'];
        $from = $selection['from'];
        $to = $selection['to'];
        $secret = $this->secret();
        $where = 'recipient_user_id=? AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))';
        $binds = [$this->userId];
        if ($filter === 'unread') {
            $where .= ' AND read_at IS NULL';
        }
        if ($group === 'import') {
            $where .= ' AND type IN (?,?,?)';
            array_push($binds, 'import.completed', 'import.completed_with_errors', 'import.failed');
        } elseif ($group === 'workflow') {
            $where .= ' AND type=?';
            $binds[] = 'workflow.action_required';
        }
        if ($from !== null) {
            // The UI's calendar day is UTC, inclusive of both requested dates.
            $where .= ' AND created_at>=? AND created_at<?';
            $binds[] = $from . ' 00:00:00.000000';
            $binds[] = (new \DateTimeImmutable($to . 'T00:00:00Z'))
                ->modify('+1 day')->format('Y-m-d 00:00:00.000000');
        }
        if ($cursor !== null) {
            $position = $selection['extended']
                ? NotificationCursor::decodeV2($cursor, $this->userId, $filter, $pageSize,
                    $group, $from, $to, $secret)
                : NotificationCursor::decode($cursor, $this->userId, $filter, $pageSize, $secret);
            $where .= ' AND (created_at<? OR (created_at=? AND id<?))';
            array_push($binds, $position['created_at'], $position['created_at'], $position['id']);
        }
        // Numeric pageSize is validated before SQL interpolation; fetch one extra for next cursor.
        $rows = $this->select("SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC,id DESC"
            . ' LIMIT ' . ($pageSize + 1), $binds)->getResultArray();
        $hasMore = count($rows) > $pageSize;
        if ($hasMore) {
            array_pop($rows);
        }
        $last = $hasMore ? end($rows) : null;
        $next = null;
        if (is_array($last)) {
            $next = $selection['extended']
                ? NotificationCursor::encodeV2($this->userId, $filter, $pageSize,
                    $group, $from, $to, (string) $last['created_at'], (string) $last['id'], $secret)
                : NotificationCursor::encode($this->userId, $filter, $pageSize,
                    (string) $last['created_at'], (string) $last['id'], $secret);
        }
        return ['items' => array_map(NotificationInboxProjectorRegistry::project(...), $rows), 'next_cursor' => $next];
    }

    /** @return array{id:string,read_at:?string} */
    public function markRead(string $id): array
    {
        $this->validateId($id);
        $this->db->transBegin();
        try {
            $where = 'id=? AND recipient_user_id=? AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))';
            $row = $this->select("SELECT id,read_at FROM {$this->table} WHERE {$where} FOR UPDATE", [$id, $this->userId])->getRowArray();
            if ($row === null) {
                throw new DomainException('NOTIFICATION_NOT_FOUND');
            }
            if ($row['read_at'] === null) {
                $this->exec("UPDATE {$this->table} SET read_at=UTC_TIMESTAMP(6) WHERE {$where} AND read_at IS NULL", [$id, $this->userId]);
                if ($this->db->affectedRows() !== 1) {
                    throw new DomainException('NOTIFICATION_NOT_FOUND');
                }
                $row = $this->select("SELECT id,read_at FROM {$this->table} WHERE {$where}", [$id, $this->userId])->getRowArray();
                if ($row === null) {
                    throw new DomainException('NOTIFICATION_NOT_FOUND');
                }
            }
            $this->commit();
            return ['id' => (string) $row['id'], 'read_at' => self::iso((string) $row['read_at'])];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return array{updated_count:int,cutoff:string} */
    public function markAllRead(): array
    {
        $this->db->transBegin();
        try {
            $clock = $this->select('SELECT UTC_TIMESTAMP(6) AS cutoff')->getRowArray();
            $cutoff = (string) ($clock['cutoff'] ?? '');
            if ($cutoff === '') {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            $this->exec("UPDATE {$this->table} SET read_at=? WHERE recipient_user_id=?"
                . ' AND read_at IS NULL AND created_at<=?'
                . ' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))',
                [$cutoff, $this->userId, $cutoff]);
            $count = (int) $this->db->affectedRows();
            $this->commit();
            return ['updated_count' => $count, 'cutoff' => self::iso($cutoff)];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private static function iso(string $dbValue): string
    {
        // CI4/MariaDB DATETIME(6) is persisted in UTC; never rely on PHP timezone.
        return str_replace(' ', 'T', $dbValue) . 'Z';
    }

    private static function validateId(string $id): void
    {
        if (! NotificationRules::version($id)) {
            throw new DomainException('NOTIFICATION_NOT_FOUND');
        }
    }

    private static function secret(): string
    {
        $key = (string) env('DINKERP_M11_CURSOR_SECRET', '');
        if (strlen($key) < 32) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        return $key;
    }

    /** @param list<mixed> $binds */
    private function select(string $sql, array $binds = []): object
    {
        $result = $this->db->query($sql, $binds);
        if ($result === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return $result;
    }

    /** @param list<mixed> $binds */
    private function exec(string $sql, array $binds): void
    {
        if ($this->db->query($sql, $binds) === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
    }

    private function commit(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
    }
}

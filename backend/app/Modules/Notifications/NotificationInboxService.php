<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
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
        foreach (array_keys($query) as $key) {
            if (! in_array($key, ['filter','page_size','cursor'], true)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }
        $filter = $query['filter'] ?? 'all';
        $size = $query['page_size'] ?? '20';
        $cursor = $query['cursor'] ?? null;
        if (! is_string($filter) || ! in_array($filter, ['all','unread'], true)
            || ! is_string($size) || ! preg_match('/\A(?:[1-9]|[1-4][0-9]|50)\z/D', $size)
            || ($cursor !== null && (! is_string($cursor) || $cursor === ''))) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $pageSize = (int) $size;
        $secret = $this->secret();
        $where = 'recipient_user_id=? AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6))';
        $binds = [$this->userId];
        if ($filter === 'unread') {
            $where .= ' AND read_at IS NULL';
        }
        if ($cursor !== null) {
            $position = NotificationCursor::decode($cursor, $this->userId, $filter, $pageSize, $secret);
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
        $next = is_array($last)
            ? NotificationCursor::encode($this->userId, $filter, $pageSize,
                (string) $last['created_at'], (string) $last['id'], $secret)
            : null;
        return ['items' => array_map(self::project(...), $rows), 'next_cursor' => $next];
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

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function project(array $row): array
    {
        $type = (string) $row['type'];
        if ($type === 'workflow.action_required') {
            $params = json_decode((string) $row['params_json'], true);
            if (! is_array($params) || array_keys($params) !== ['resource_type','state_code']
                || ! is_string($params['resource_type']) || ! is_string($params['state_code'])
                || $params['state_code'] === '' || strlen($params['state_code']) > 50
                || (string) $row['target_type'] !== 'workflow_target'
                || (string) $row['title_key'] !== 'notifications.workflow.actionRequired.title'
                || (string) $row['body_key'] !== 'notifications.workflow.actionRequired.body'
                || ! NotificationRules::version((string) $row['target_id'])) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            try { BusinessWorkflowRules::table($params['resource_type']); }
            catch (\Throwable) { throw new DomainException('NOTIFICATION_STORAGE_FAILED'); }
            return [
                'id' => (string) $row['id'], 'type' => $type, 'priority' => (string) $row['priority'],
                'title_key' => (string) $row['title_key'], 'body_key' => (string) $row['body_key'],
                'params' => $params,
                'target' => ['type' => 'workflow_target', 'resource_type' => $params['resource_type'],
                    'id' => (string) $row['target_id']],
                'read_at' => $row['read_at'] === null ? null : self::iso((string) $row['read_at']),
                'created_at' => self::iso((string) $row['created_at']),
            ];
        }
        $known = [
            'import.completed' => 'completed',
            'import.completed_with_errors' => 'completedWithErrors',
            'import.failed' => 'failed',
        ];
        $slug = $known[$type] ?? null;
        if ($slug === null || (string) $row['target_type'] !== 'import_job'
            || (string) $row['title_key'] !== "notifications.import.{$slug}.title"
            || (string) $row['body_key'] !== "notifications.import.{$slug}.body"
            || ! NotificationRules::version((string) $row['target_id'])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $params = json_decode((string) $row['params_json'], true);
        if (! is_array($params) || array_keys($params) !== ['success_rows', 'error_rows']
            || ! is_int($params['success_rows']) || ! is_int($params['error_rows'])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return [
            'id' => (string) $row['id'], 'type' => $type, 'priority' => (string) $row['priority'],
            'title_key' => (string) $row['title_key'], 'body_key' => (string) $row['body_key'],
            'params' => $params,
            'target' => ['type' => 'import_job', 'id' => (string) $row['target_id']],
            'read_at' => $row['read_at'] === null ? null : self::iso((string) $row['read_at']),
            'created_at' => self::iso((string) $row['created_at']),
        ];
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

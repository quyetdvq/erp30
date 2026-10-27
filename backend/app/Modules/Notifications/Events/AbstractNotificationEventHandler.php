<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Shared database access preserving the pre-extraction SQL/error contracts.
 * Does not start or end transactions, claim Outbox rows, or send external messages.
 */
abstract class AbstractNotificationEventHandler implements NotificationEventHandler
{
    protected readonly string $jobs;
    protected readonly string $users;
    protected readonly string $settings;
    protected readonly string $preferences;
    protected readonly string $inbox;

    public function __construct(protected readonly BaseConnection $db)
    {
        $this->jobs = $this->table('import_jobs');
        $this->users = $this->table('users');
        $this->settings = $this->table('notification_settings');
        $this->preferences = $this->table('notification_user_preferences');
        $this->inbox = $this->table('user_notifications');
    }

    protected function table(string $name): string
    {
        $physical = $this->db->prefixTable($name);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_INVALID_TABLE_PREFIX');
        }
        return '`' . $physical . '`';
    }

    /** @param list<mixed> $params */
    protected function select(string $sql, array $params = []): object
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new RuntimeException('M11_OUTBOX_DB_READ_FAILED');
        }
        return $result;
    }

    /** @param list<mixed> $params */
    protected function exec(string $sql, array $params): void
    {
        if ($this->db->query($sql, $params) === false) {
            throw new RuntimeException('M11_OUTBOX_DB_WRITE_FAILED');
        }
    }
}

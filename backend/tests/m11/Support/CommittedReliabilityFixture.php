<?php

declare(strict_types=1);

namespace Tests\M11\Support;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Short-lived, committed DML exclusively in the guarded erp_test database.
 * A genuinely independent worker cannot see another connection's rolled-back
 * fixtures; this opt-in reliability suite therefore commits and explicitly
 * removes only IDs it created. Never use this fixture on a live database.
 */
final class CommittedReliabilityFixture
{
    /** @var array<string,string> */
    private array $events = [];
    /** @var list<string> */
    private array $jobs = [];
    /** @var list<string> */
    private array $users = [];
    private bool $createdSetting = false;
    private bool $createdEmailSetting = false;
    /** @var list<string> */
    private array $emailIdentities = [];
    /** @var list<string> */
    private array $emailPreferences = [];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{id:string,key:string} */
    public function poisonEvent(): array
    {
        // The source ID is deliberately unrelated to business data; malformed
        // payload is rejected before any import_job/user lookup.
        $source = (string) random_int(1000000000, 2000000000);
        return $this->event('import-job:' . $source . ':terminal', 'import.completed', $source, '{"schema":2}');
    }

    /** @return array{id:string,key:string,user_id:string,job_id:string} */
    public function validImportEvent(): array
    {
        $this->ensureImportPolicy();
        $tag = bin2hex(random_bytes(8));
        $user = $this->insert('users', [
            'username' => 'm11r_' . $tag,
            'active' => 1,
            'status' => 'ACTIVE',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->users[] = $user;
        $job = $this->insert('import_jobs', [
            'entity_type' => 'PRODUCT', 'mode' => 'INSERT', 'source_format' => 'CSV',
            'status' => 'COMPLETED', 'created_by' => $user,
            'success_rows' => 1, 'error_rows' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->jobs[] = $job;
        return $this->event('import-job:' . $job . ':terminal', 'import.completed', $job, '{"schema":1}')
            + ['user_id' => $user, 'job_id' => $job];
    }

    /** Explicit Email opt-in fixture for the real, non-nested rollback test. */
    public function optInEmail(string $userId): void
    {
        $key = 'import.completed.email';
        $policy = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
        if ($policy === null) {
            if (! $this->db->table('notification_settings')->insert([
                'setting_key' => $key, 'default_value' => 0, 'allow_user_override' => 1,
                'revision' => 1, 'override_epoch' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('M11R email policy fixture failed');
            }
            $this->createdEmailSetting = true;
            $policy = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
        }
        if ((string) $policy['default_value'] !== '0' || (string) $policy['allow_user_override'] !== '1') {
            throw new RuntimeException('M11R needs the default safe, overrideable Email policy in erp_test.');
        }
        $address = 'm11r_' . bin2hex(random_bytes(8)) . '@example.test';
        $identityId = $this->insert('auth_identities', [
            'user_id' => $userId, 'type' => 'email_password',
            'secret' => $address, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->emailIdentities[] = $identityId;
        $preferenceId = $this->insert('notification_user_preferences', [
            'user_id' => $userId, 'setting_key' => $key, 'override_value' => 1,
            'override_epoch' => (string) $policy['override_epoch'], 'user_revision' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->emailPreferences[] = $preferenceId;
    }

    /** @return array{id:string,key:string} */
    private function event(string $key, string $type, string $sourceId, string $payload): array
    {
        $table = $this->name('notification_outbox');
        $sql = "INSERT INTO {$table}"
            . ' (event_key,event_type,source_type,source_id,payload_json,status,attempts,available_at,created_at)'
            . " VALUES (?,?,?,?,?,'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))";
        if ($this->db->query($sql, [$key, $type, 'import_job', $sourceId, $payload]) === false) {
            throw new RuntimeException('M11R fixture Outbox INSERT failed');
        }
        $id = (string) $this->db->insertID();
        $this->events[$id] = $key;
        return ['id' => $id, 'key' => $key];
    }

    private function ensureImportPolicy(): void
    {
        $key = 'import.completed.in_app';
        $policy = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
        if ($policy !== null) {
            if ((string) $policy['default_value'] !== '1') {
                throw new RuntimeException('M11R requires import.completed.in_app default enabled in erp_test; refuses to edit existing policy.');
            }
            return;
        }
        if (! $this->db->table('notification_settings')->insert([
            'setting_key' => $key, 'default_value' => 1, 'allow_user_override' => 0,
            'revision' => 1, 'override_epoch' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('M11R fixture policy INSERT failed');
        }
        $this->createdSetting = true;
    }

    /** Call only after every child worker has terminated. */
    public function cleanup(): void
    {
        foreach ($this->events as $id => $key) {
            // These precise keys/IDs are all generated by this test. Never
            // delete or change any historical/non-fixture Inbox or Outbox row.
            $this->db->table('notification_email_deliveries')->where('event_key', $key)->delete();
            $this->db->table('user_notifications')->where('event_key', $key)->delete();
            $this->db->table('notification_outbox')->where('id', $id)->where('event_key', $key)->delete();
        }
        foreach ($this->emailPreferences as $id) {
            $this->db->table('notification_user_preferences')->where('id', $id)->delete();
        }
        foreach ($this->emailIdentities as $id) {
            $this->db->table('auth_identities')->where('id', $id)->delete();
        }
        foreach ($this->jobs as $id) {
            $this->db->table('import_jobs')->where('id', $id)->delete();
        }
        foreach ($this->users as $id) {
            $this->db->table('users')->where('id', $id)->delete();
        }
        if ($this->createdSetting) {
            $this->db->table('notification_settings')
                ->where('setting_key', 'import.completed.in_app')->delete();
        }
        if ($this->createdEmailSetting) {
            $this->db->table('notification_settings')->where('setting_key', 'import.completed.email')->delete();
        }
        $this->emailIdentities = [];
        $this->emailPreferences = [];
        $this->createdEmailSetting = false;
        $this->events = [];
        $this->jobs = [];
        $this->users = [];
        $this->createdSetting = false;
    }

    /** @param array<string,mixed> $values */
    private function insert(string $table, array $values): string
    {
        if (! $this->db->table($table)->insert($values)) {
            throw new RuntimeException('M11R fixture INSERT failed: ' . $table);
        }
        return (string) $this->db->insertID();
    }

    private function name(string $logical): string
    {
        $name = $this->db->prefixTable($logical);
        if (preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/D', $name) !== 1) {
            throw new RuntimeException('M11R unsafe identifier');
        }
        return '`' . $name . '`';
    }
}

<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Modules\Notifications\NotificationOperationsAuditReader;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

/** Read-only live schema/queue metrics; fixture inserts are rolled back on guarded erp_test. */
final class NotificationOperationsAuditIntegrationTest extends TestCase
{
    private BaseConnection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('M11 Ops Audit fixture transaction failed.');
        }
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        $this->db->close();
        parent::tearDown();
    }

    public function testSchemaFlagsAndConsentGuardsAreReportedWithoutMutation(): void
    {
        // Migration 019 creates the table but does not seed Email consent. A fresh
        // erp_test may have Push from 022 while missing Email altogether. Build an
        // isolated, known-good policy fixture instead of depending on seed history.
        $this->setConsentPolicy('import.completed.email');
        $this->setConsentPolicy('import.completed.push');
        $before = $this->consentRows();
        $auditBefore = $this->db->table('configuration_audit_events')->countAllResults();
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertSame(9, $snapshot['schema']['notification_tables_expected']);
        self::assertSame(9, $snapshot['schema']['notification_tables_present']);
        self::assertSame([], $snapshot['missing_tables']);
        // The schema still has 9 notification tables; M11.6B exposes 12 runtime flags.
        self::assertCount(12, $snapshot['flags']);
        self::assertArrayHasKey('DINKERP_M11_TASKS_INAPP_ENABLED', $snapshot['flags']);
        self::assertArrayHasKey('DINKERP_M11_TASKS_PUSH_ENABLED', $snapshot['flags']);
        self::assertArrayHasKey('DINKERP_M11_TASKS_EMAIL_ENABLED', $snapshot['flags']);
        self::assertTrue($snapshot['policy_guards']['import.completed.email']);
        self::assertTrue($snapshot['policy_guards']['import.completed.push']);
        self::assertTrue($snapshot['queues']['email']['available']);
        self::assertTrue($snapshot['queues']['push']['available']);
        self::assertArrayHasKey('cursor_secret', $snapshot['credentials']);
        self::assertArrayHasKey('push_endpoint_sample', $snapshot['credentials']);
        self::assertSame($before, $this->consentRows(), 'Audit must not mutate consent policies');
        self::assertSame($auditBefore, $this->db->table('configuration_audit_events')->countAllResults(),
            'Read-only audit must not create configuration audit events');
    }

    public function testSystemLockingUserOverrideIsStillValidConsentPolicy(): void
    {
        $this->setConsentPolicy('import.completed.push');
        // Admin lock is a legitimate system policy, not a malformed opt-in rule.
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.push')
            ->update(['allow_user_override' => 0]);
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertTrue($snapshot['policy_guards']['import.completed.push']);
    }

    public function testUnsafeEmailDefaultOrEpochStillFailsClosed(): void
    {
        $this->setConsentPolicy('import.completed.email');
        $this->setConsentPolicy('import.completed.push');
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.email')
            ->update(['default_value' => 1]);
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertFalse($snapshot['policy_guards']['import.completed.email']);
        self::assertTrue($snapshot['policy_guards']['import.completed.push']);

        $this->db->table('notification_settings')->where('setting_key', 'import.completed.email')
            ->update(['default_value' => 0, 'override_epoch' => '0']);
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertFalse($snapshot['policy_guards']['import.completed.email']);
    }

    public function testPendingAndExpiredMetricsSeeOwnFixturesWithoutClaiming(): void
    {
        $reader = new NotificationOperationsAuditReader($this->db);
        $before = $reader->snapshot();
        $eventKey = 'm11-ops-' . bin2hex(random_bytes(12));
        $past = gmdate('Y-m-d H:i:s', time() - 20 * 60);
        $this->db->table('notification_outbox')->insert([
            'event_key' => $eventKey, 'event_type' => 'import.completed',
            'source_type' => 'import_job', 'source_id' => '1', 'payload_json' => '{}',
            'status' => 'PENDING', 'available_at' => $past, 'created_at' => $past,
        ]);
        $after = $reader->snapshot();
        self::assertSame(($before['queues']['outbox']['status']['PENDING'] ?? 0) + 1,
            $after['queues']['outbox']['status']['PENDING']);
        self::assertSame($before['queues']['outbox']['overdue_ready_15m'] + 1,
            $after['queues']['outbox']['overdue_ready_15m']);
        $stored = $this->db->table('notification_outbox')->select('status,attempts')
            ->where('event_key', $eventKey)->get()->getRowArray();
        self::assertSame('PENDING', $stored['status']);
        self::assertSame('0', (string) $stored['attempts']);
    }

    /** Insert/update only inside the outer rollback transaction on guarded erp_test. */
    private function setConsentPolicy(string $key): void
    {
        $values = [
            'default_value' => 0, 'allow_user_override' => 1,
            'revision' => '1', 'override_epoch' => '1',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $existing = $this->db->table('notification_settings')
            ->where('setting_key', $key)->get()->getRowArray();
        $success = $existing === null
            ? $this->db->table('notification_settings')->insert(['setting_key' => $key] + $values)
            : $this->db->table('notification_settings')->where('setting_key', $key)->update($values);
        if (! $success) {
            throw new \RuntimeException('M11 Ops Audit consent fixture could not be prepared: ' . $key);
        }
    }

    /** @return list<array<string,mixed>> */
    private function consentRows(): array
    {
        return $this->db->table('notification_settings')
            ->whereIn('setting_key', ['import.completed.email', 'import.completed.push'])
            ->orderBy('setting_key', 'ASC')->get()->getResultArray();
    }
}

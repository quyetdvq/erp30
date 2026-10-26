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
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertSame(9, $snapshot['schema']['notification_tables_expected']);
        self::assertSame(9, $snapshot['schema']['notification_tables_present']);
        self::assertSame([], $snapshot['missing_tables']);
        self::assertCount(9, $snapshot['flags']);
        self::assertTrue($snapshot['policy_guards']['import.completed.email']);
        self::assertTrue($snapshot['policy_guards']['import.completed.push']);
        self::assertTrue($snapshot['queues']['email']['available']);
        self::assertTrue($snapshot['queues']['push']['available']);
        self::assertArrayHasKey('cursor_secret', $snapshot['credentials']);
        self::assertArrayHasKey('push_endpoint_sample', $snapshot['credentials']);
    }

    public function testSystemLockingUserOverrideIsStillValidConsentPolicy(): void
    {
        // Admin lock is a legitimate system policy, not a malformed opt-in rule.
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.push')
            ->update(['allow_user_override' => 0]);
        $snapshot = (new NotificationOperationsAuditReader($this->db))->snapshot();
        self::assertTrue($snapshot['policy_guards']['import.completed.push']);
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
}

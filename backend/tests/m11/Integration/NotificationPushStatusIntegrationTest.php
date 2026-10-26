<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Modules\Notifications\NotificationPushStatusReader;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

/** Verify read-only, user-scoped diagnostics on guarded erp_test with rollback fixtures. */
final class NotificationPushStatusIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('M11 diagnostic fixture transaction failed');
        }
        $this->db->table('users')->insert([
            'username' => 'm11status_' . bin2hex(random_bytes(6)),
            'active' => 1, 'status' => 'ACTIVE', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->userId = (string) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        $this->db->close();
        parent::tearDown();
    }

    public function testLatestFailedJobAndOutboxAreReportedWithoutChangingRows(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('import_jobs')->insert([
            'entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV',
            'status' => 'COMPLETED_WITH_ERRORS', 'created_by' => $this->userId,
            'total_rows' => 1, 'success_rows' => 0, 'error_rows' => 1,
            'created_at' => $now, 'completed_at' => $now,
        ]);
        $jobId = (string) $this->db->insertID();
        $this->db->table('import_job_rows')->insert([
            'import_job_id' => $jobId, 'row_number' => 1, 'row_status' => 'ERROR',
            'error_code' => 'M11_TEST_BAD_ITEM', 'error_message' => 'sensitive product payload',
            'created_at' => $now,
        ]);
        $this->db->table('notification_outbox')->insert([
            'event_key' => 'import-job:' . $jobId . ':terminal',
            'event_type' => 'import.completed_with_errors',
            'source_type' => 'import_job', 'source_id' => $jobId,
            'payload_json' => '{}', 'status' => 'PENDING',
            'available_at' => $now, 'created_at' => $now,
        ]);
        $before = $this->db->table('notification_outbox')->where('event_key',
            'import-job:' . $jobId . ':terminal')->get()->getRowArray();

        $snapshot = (new NotificationPushStatusReader($this->db))->snapshot($this->userId);

        self::assertSame(0, $snapshot['active_devices']);
        self::assertSame('1', (string) $snapshot['unfinished_outbox']);
        self::assertCount(1, $snapshot['jobs']);
        self::assertSame($jobId, $snapshot['jobs'][0]['id']);
        self::assertSame('COMPLETED_WITH_ERRORS', $snapshot['jobs'][0]['status']);
        self::assertSame('PENDING', $snapshot['jobs'][0]['outbox_status']);
        self::assertSame('import.completed_with_errors', $snapshot['jobs'][0]['event_type']);
        self::assertSame([], $snapshot['jobs'][0]['receipts']);
        self::assertSame([['row_number' => 1, 'error_code' => 'M11_TEST_BAD_ITEM']],
            $snapshot['latest_error_codes']);
        $after = $this->db->table('notification_outbox')->where('event_key',
            'import-job:' . $jobId . ':terminal')->get()->getRowArray();
        self::assertSame($before, $after);
    }

    public function testMissingJobsRemainScopedAndCauseNoMutation(): void
    {
        $snapshot = (new NotificationPushStatusReader($this->db))->snapshot($this->userId);
        self::assertSame([], $snapshot['jobs']);
        self::assertSame([], $snapshot['latest_error_codes']);
    }
}

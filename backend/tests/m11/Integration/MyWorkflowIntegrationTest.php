<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Modules\Notifications\MyWorkflowService;
use App\Modules\Notifications\WorkflowNotificationAssigneesService;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;
use Tests\M11\Support\WorkflowFixture;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';
require_once __DIR__ . '/../Support/WorkflowFixture.php';

/** NFX-B uses only guarded erp_test transactions. Never accesses ERP production data. */
final class MyWorkflowIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private WorkflowFixture $fixture;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        if (! $this->db->transBegin()) throw new \RuntimeException('Cannot begin NFX-B test transaction');
        $this->inTransaction = true;
        try {
            $this->fixture = new WorkflowFixture($this->db);
            $this->fixture->install();
            // Force the real runtime gate ON within the rollback-only test transaction.
            $key = ['class' => 'NotificationRuntime', 'context' => 'system',
                'key' => 'DINKERP_M11_WORKFLOW_WRITE_MODE'];
            $this->db->table('settings')->where($key)->delete();
            $this->db->table('settings')->insert($key + ['value' => '1', 'type' => 'boolean',
                'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            $this->tearDownFixture();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
    }

    private function tearDownFixture(): void
    {
        if ($this->inTransaction) {
            $this->db->transRollback();
            $this->inTransaction = false;
        }
        if (isset($this->db)) $this->db->close();
    }

    public function testSelectedRecipientsWithLiveActionsButNoTaskOwnershipOrReadFlag(): void
    {
        self::assertSame([], $this->reader($this->fixture->firstRecipientId)->listing([])['items']);
        $this->fixture->assign();
        $result = $this->reader($this->fixture->firstRecipientId)->listing([]);
        self::assertTrue($result['execution_enabled']);
        self::assertCount(1, $result['items']);
        self::assertNull($result['next_cursor']);
        $item = $result['items'][0];
        self::assertSame('province', $item['resource_type']);
        self::assertSame($this->fixture->targetId, $item['resource_id']);
        self::assertSame('M11_A', $item['current_state']);
        self::assertSame('M11 notification fixture', $item['record_name']);
        self::assertStringStartsWith('M11', $item['record_code']);
        self::assertSame([['action_code' => 'm11_submit', 'status' => 'M11_B']], $item['available_actions']);
        self::assertArrayNotHasKey('read_at', $item);
        self::assertArrayNotHasKey('completed_at', $item);
        self::assertArrayNotHasKey('owner_user_id', $item);
        // Assignment is independent of Inbox receipts and their read_at field.
        self::assertSame(0, $this->db->table('user_notifications')
            ->where('recipient_user_id', $this->fixture->firstRecipientId)->countAllResults());
        self::assertCount(1, $this->reader($this->fixture->secondRecipientId)->listing([])['items']);
        self::assertSame([], $this->reader($this->fixture->actorId)->listing([])['items']);
    }

    public function testStaleStateRevocationAndRecipientChangeDisappearOnNextRead(): void
    {
        $this->fixture->assign();
        // Deliberately reuse the service across reads: its PBAC evaluator must
        // refresh its persisted permission snapshot after revocation/grant.
        $viewer = $this->reader($this->fixture->firstRecipientId);
        self::assertCount(1, $viewer->listing([])['items']);
        $this->fixture->revoke($this->fixture->firstRecipientId, 'province.view');
        self::assertSame([], $viewer->listing([])['items'], 'Revoked viewer still saw record');
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $this->fixture->firstRecipientId, 'permission' => 'province.view',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->table('provinces')->where('id', $this->fixture->targetId)
            ->update(['status' => 'M11_C']); // Terminal fixture state, no active transitions.
        self::assertSame([], $viewer->listing([])['items'], 'Finished Workflow was still actionable');
        $this->db->table('provinces')->where('id', $this->fixture->targetId)
            ->update(['status' => 'M11_A']);
        self::assertCount(1, $viewer->listing([])['items']);
        $this->fixture->revoke($this->fixture->firstRecipientId, 'province.setstate');
        self::assertSame([], $viewer->listing([])['items'], 'Revoked setstate user was actionable');
        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $this->fixture->firstRecipientId, 'permission' => 'province.setstate',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        (new WorkflowNotificationAssigneesService($this->fixture->actor(), $this->db))
            ->replace('province', $this->fixture->targetId,
                ['expected_revision' => '1', 'user_ids' => [$this->fixture->secondRecipientId]]);
        self::assertSame([], $viewer->listing([])['items'], 'Removed recipient still saw record');
        self::assertCount(1, $this->reader($this->fixture->secondRecipientId)->listing([])['items']);
    }

    public function testKeysetPaginationSkipsOrphanRowsAndIsActorBound(): void
    {
        $this->fixture->assign([$this->fixture->firstRecipientId]);
        $now = gmdate('Y-m-d H:i:s');
        // A second real record, and an orphan assignment that must never be disclosed.
        $this->db->table('provinces')->insert([
            'code' => 'NFXB' . substr(bin2hex(random_bytes(7)), 0, 11),
            'name' => 'NFX-B second target', 'status' => 'M11_A', 'created_at' => $now,
        ]);
        $secondId = (string) $this->db->insertID();
        $this->select($secondId, $now);
        $this->select('9223372036854775807', '2090-01-01 00:00:00.000000');
        $reader = $this->reader($this->fixture->firstRecipientId);
        $firstPage = $reader->listing(['page_size' => '1']);
        self::assertCount(1, $firstPage['items']);
        self::assertNotNull($firstPage['next_cursor']);
        $secondPage = $reader->listing(['page_size' => '1', 'cursor' => $firstPage['next_cursor']]);
        self::assertCount(1, $secondPage['items']);
        self::assertNotSame($firstPage['items'][0]['resource_id'], $secondPage['items'][0]['resource_id']);
        self::assertNull($secondPage['next_cursor']);
        self::assertSame([], $this->reader($this->fixture->secondRecipientId)->listing([])['items']);
        try {
            $this->reader($this->fixture->secondRecipientId)->listing([
                'page_size' => '1', 'cursor' => $firstPage['next_cursor'],
            ]);
            self::fail('Cursor belonging to another user accepted');
        } catch (\App\Exceptions\DomainException $e) {
            self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode());
        }
        try {
            $reader->listing(['page_size' => '1', 'resource_type' => 'product',
                'cursor' => $firstPage['next_cursor']]);
            self::fail('Cursor belonging to another filter accepted');
        } catch (\App\Exceptions\DomainException $e) {
            self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode());
        }
    }

    public function testBoundedScanCanReturnEmptyPageWithContinuableCursor(): void
    {
        // No fabricated "no work" claim when all first 80 candidates are stale.
        for ($i = 1; $i <= MyWorkflowService::SCAN_BUDGET + 2; ++$i) {
            $this->select((string) (4611686018427387904 + $i),
                sprintf('2090-01-01 00:00:00.%06d', $i));
        }
        $reader = $this->reader($this->fixture->firstRecipientId);
        $first = $reader->listing(['page_size' => '10']);
        self::assertSame([], $first['items']);
        self::assertNotNull($first['next_cursor']);
        $second = $reader->listing(['page_size' => '10', 'cursor' => $first['next_cursor']]);
        self::assertSame([], $second['items']);
        self::assertNull($second['next_cursor']);
    }

    public function testInactiveShieldUserIsRejectedBeforeReadingAssignments(): void
    {
        try {
            $this->reader($this->fixture->inactiveRecipientId);
            self::fail('Inactive Shield User accepted');
        } catch (\App\Exceptions\DomainException $e) {
            self::assertSame('UNAUTHENTICATED', $e->getErrorCode());
        }
    }

    public function testMyWorkActionabilityDependsOnRecordPbacNotNotificationWriteSwitch(): void
    {
        $this->fixture->assign();
        // The notification assignment write setting does not control the
        // independent authenticated Workflow execution and row PBAC checks.
        $this->db->table('settings')
            ->where(['class' => 'NotificationRuntime', 'context' => 'system',
                'key' => 'DINKERP_M11_WORKFLOW_WRITE_MODE'])
            ->update(['value' => '0']);
        $result = $this->reader($this->fixture->firstRecipientId)->listing([]);
        self::assertTrue($result['execution_enabled']);
        self::assertIsArray($result['items']);
    }

    private function reader(string $userId): MyWorkflowService
    {
        return new MyWorkflowService($this->fixture->actor($userId), $this->db);
    }

    private function select(string $id, string $createdAt): void
    {
        $this->db->table('workflow_notification_assignment_sets')->insert([
            'resource_type' => 'province', 'resource_id' => $id, 'revision' => '1',
            'updated_by' => $this->fixture->actorId, 'updated_at' => $createdAt,
        ]);
        $this->db->table('workflow_notification_assignees')->insert([
            'resource_type' => 'province', 'resource_id' => $id,
            'recipient_user_id' => $this->fixture->firstRecipientId, 'created_at' => $createdAt,
        ]);
    }
}

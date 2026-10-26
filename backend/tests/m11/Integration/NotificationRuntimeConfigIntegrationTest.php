<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationRuntimeConfigService;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;
use Tests\M11\Support\WorkflowFixture;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';
require_once __DIR__ . '/../Support/WorkflowFixture.php';

/** Real Settings, PBAC, revision and audit; always rollback erp_test fixture rows. */
final class NotificationRuntimeConfigIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private WorkflowFixture $fixture;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('M11 runtime fixture transaction failed');
        }
        $this->inTransaction = true;
        try {
            $this->fixture = new WorkflowFixture($this->db);
            $this->fixture->install();
            $this->setupPbac();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->inTransaction = false;
            $this->db->close();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            $this->db->transRollback();
            $this->inTransaction = false;
        }
        $this->db->close();
        parent::tearDown();
    }

    private function setupPbac(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
        if ($resource === null) {
            $this->db->table('resources')->insert([
                'type' => 'notification_setting', 'name' => 'notification_setting',
                'domain' => 'SYSTEM', 'status' => 'ACTIVE', 'lifecycle_id' => null, 'created_at' => $now,
            ]);
            $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
        }
        $resourceId = (string) $resource['id'];
        $this->db->table('resources')->where('id', $resourceId)->update(['status' => 'ACTIVE']);
        foreach (['view', 'edit'] as $action) {
            if ($this->db->table('resource_actions')->where('resource_id', $resourceId)
                ->where('code', $action)->countAllResults() === 0) {
                $this->db->table('resource_actions')->insert([
                    'resource_id' => $resourceId, 'code' => $action, 'created_at' => $now,
                ]);
            }
            $this->db->table('auth_permissions_users')->insert([
                'user_id' => $this->fixture->actorId,
                'permission' => 'notification_setting.' . $action,
                'created_at' => $now,
            ]);
        }
        if ($this->db->table('notification_settings')->where('setting_key', 'import.completed.in_app')->countAllResults() === 0) {
            $this->db->table('notification_settings')->insert([
                'setting_key' => 'import.completed.in_app',
                'default_value' => 1, 'allow_user_override' => 1,
                'revision' => 1, 'override_epoch' => 1, 'updated_at' => $now,
            ]);
        }
    }

    public function testPersistsAllNineFlagsWithRevisionAndTransactionalAudit(): void
    {
        $service = new NotificationRuntimeConfigService($this->fixture->actor(), $this->db);
        $initial = $service->listing();
        self::assertCount(9, $initial['items']);
        $input = array_map(static fn (array $item): array => [
            'key' => $item['key'], 'enabled' => $item['enabled'],
        ], $initial['items']);
        $updated = $service->update(['expected_revision' => $initial['revision'], 'changes' => $input]);
        self::assertTrue((int) $updated['revision'] > (int) $initial['revision']);
        foreach ($updated['items'] as $item) {
            self::assertSame('database', $item['source']);
        }
        $before = $this->db->table('configuration_audit_events')->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults();
        $same = $service->update(['expected_revision' => $updated['revision'], 'changes' => $input]);
        self::assertSame($updated['revision'], $same['revision']);
        self::assertSame($before, $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults());
        $key = 'DINKERP_M11_EMAIL_QUEUE_ENABLED';
        $next = $service->update(['expected_revision' => $updated['revision'], 'changes' => [
            ['key' => $key, 'enabled' => ! $this->enabled($updated, $key)],
        ]]);
        self::assertSame(! $this->enabled($updated, $key), NotificationFlags::emailQueueEnabled($this->db));
        self::assertSame((string) ((int) $updated['revision'] + 1), $next['revision']);
        try {
            $service->update(['expected_revision' => $updated['revision'], 'changes' => $input]);
            self::fail('Stale config revision accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_REVISION_CONFLICT', $e->getErrorCode());
        }
    }

    public function testReadAndMutationBothRequireRealPbac(): void
    {
        // The recipient has no notification_setting permission.
        $service = new NotificationRuntimeConfigService($this->fixture->actor($this->fixture->firstRecipientId), $this->db);
        try {
            $service->listing();
            self::fail('Unauthorized config read accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
        try {
            $service->update(['expected_revision' => '0', 'changes' => [
                ['key' => 'DINKERP_M11_EMAIL_SEND_ENABLED', 'enabled' => true],
            ]]);
            self::fail('Unauthorized config mutation accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    /** @param array<string,mixed> $snapshot */
    private function enabled(array $snapshot, string $key): bool
    {
        foreach ($snapshot['items'] as $row) {
            if ($row['key'] === $key) return $row['enabled'];
        }
        throw new \RuntimeException('Missing runtime config switch');
    }
}

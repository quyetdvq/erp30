<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationRuntimeConfigService;
use App\Modules\Notifications\NotificationTasksPlan;
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

    public function testPersistsAllTwelveFlagsWithRevisionAndTransactionalAudit(): void
    {
        $service = new NotificationRuntimeConfigService($this->fixture->actor(), $this->db);
        $initial = $service->listing();
        self::assertCount(12, $initial['items']);
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


    public function testSchedulerFlagsPersistWithPbacAuditRevisionAndControlJobPlan(): void
    {
        $service = new NotificationRuntimeConfigService($this->fixture->actor(), $this->db);
        $inApp = 'DINKERP_M11_TASKS_INAPP_ENABLED';
        $push = 'DINKERP_M11_TASKS_PUSH_ENABLED';
        // Isolate scheduler keys even if the guarded erp_test DB contains
        // prior operator fixtures. This DELETE is inside the outer rollback.
        $this->db->table('settings')->where('class', 'NotificationRuntime')
            ->where('context', 'system')->whereIn('key', [$inApp, $push])->delete();
        $initial = $service->listing();
        $auditBefore = $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults();
        $updated = $service->update(['expected_revision' => $initial['revision'], 'changes' => [
            ['key' => $push, 'enabled' => true],
            ['key' => $inApp, 'enabled' => true],
        ]]);
        self::assertTrue(NotificationFlags::enabled($inApp, $this->db));
        self::assertTrue(NotificationFlags::enabled($push, $this->db));
        self::assertSame(['m11-inapp-dispatch', 'm11-push-dispatch'], array_column(
            NotificationTasksPlan::definitions(
                NotificationFlags::enabled($inApp, $this->db),
                NotificationFlags::enabled($push, $this->db),
            ), 'name',
        ));
        self::assertSame($auditBefore + 2, $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults());
        self::assertSame((string) ((int) $initial['revision'] + 1), $updated['revision']);

        $off = $service->update(['expected_revision' => $updated['revision'], 'changes' => [
            ['key' => $push, 'enabled' => false],
        ]]);
        self::assertTrue(NotificationFlags::enabled($inApp, $this->db));
        self::assertFalse(NotificationFlags::enabled($push, $this->db));
        self::assertSame(['m11-inapp-dispatch'], array_column(NotificationTasksPlan::definitions(
            NotificationFlags::enabled($inApp, $this->db),
            NotificationFlags::enabled($push, $this->db),
        ), 'name'));
        self::assertSame((string) ((int) $updated['revision'] + 1), $off['revision']);
        self::assertSame($auditBefore + 3, $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults());
        $bothOff = $service->update(['expected_revision' => $off['revision'], 'changes' => [
            ['key' => $inApp, 'enabled' => false],
        ]]);
        self::assertFalse(NotificationFlags::enabled($inApp, $this->db));
        self::assertSame([], NotificationTasksPlan::definitions(
            NotificationFlags::enabled($inApp, $this->db),
            NotificationFlags::enabled($push, $this->db),
        ));
        self::assertSame($auditBefore + 4, $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults());
        self::assertSame((string) ((int) $off['revision'] + 1), $bothOff['revision']);
        $this->expectException(DomainException::class);
        $service->update(['expected_revision' => $updated['revision'], 'changes' => [
            ['key' => $inApp, 'enabled' => false],
        ]]);
    }

    public function testEmailTaskFlagIsAuditedAndOffByDefaultUntilExplicitOperatorOptIn(): void
    {
        $key = 'DINKERP_M11_TASKS_EMAIL_ENABLED';
        $this->db->table('settings')->where('class', 'NotificationRuntime')
            ->where('context', 'system')->where('key', $key)->delete();
        $service = new NotificationRuntimeConfigService($this->fixture->actor(), $this->db);
        $before = $service->listing();
        self::assertSame(\App\Modules\Notifications\NotificationRuntimeConfigRules::legacyEnabled($key),
            NotificationFlags::enabled($key, $this->db));
        $auditBefore = $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults();
        $on = $service->update(['expected_revision' => $before['revision'], 'changes' => [
            ['key' => $key, 'enabled' => true],
        ]]);
        self::assertTrue(NotificationFlags::enabled($key, $this->db));
        self::assertSame((string) ((int) $before['revision'] + 1), $on['revision']);
        self::assertSame($auditBefore + 1, $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'NOTIFICATION_RUNTIME')->countAllResults());
        $off = $service->update(['expected_revision' => $on['revision'], 'changes' => [
            ['key' => $key, 'enabled' => false],
        ]]);
        self::assertFalse(NotificationFlags::enabled($key, $this->db));
        self::assertSame((string) ((int) $on['revision'] + 1), $off['revision']);
    }

    public function testCorruptSchedulerSettingFailsClosedWithoutRegisteringJob(): void
    {
        $inApp = 'DINKERP_M11_TASKS_INAPP_ENABLED';
        $this->db->table('settings')->where('class', 'NotificationRuntime')
            ->where('context', 'system')->where('key', $inApp)->delete();
        $this->db->table('settings')->insert([
            'class' => 'NotificationRuntime', 'key' => $inApp,
            'context' => 'system', 'value' => '1', 'type' => 'string',
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        self::assertFalse(NotificationFlags::enabled($inApp, $this->db));
        $service = new NotificationRuntimeConfigService($this->fixture->actor(), $this->db);
        $this->expectException(DomainException::class);
        $service->listing();
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

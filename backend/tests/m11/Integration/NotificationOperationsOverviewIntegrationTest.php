<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationOperationsOverviewService;
use App\Modules\Notifications\NotificationOperationsDiagnosticsService;
use App\Modules\Access\NotificationOperationsAccessProvisioner;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;
use Tests\M11\Support\WorkflowFixture;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';
require_once __DIR__ . '/../Support/WorkflowFixture.php';

/** E1.1 read-only service/PBAC DB regression. All fixtures rollback on guarded erp_test. */
final class NotificationOperationsOverviewIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private WorkflowFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('E1.1 overview fixture transaction failed');
        }
        try {
            $this->fixture = new WorkflowFixture($this->db);
            $this->fixture->install();
            $now = gmdate('Y-m-d H:i:s');
            $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
            if ($resource === null) {
                $this->db->table('resources')->insert([
                    'type' => 'notification_setting', 'name' => 'notification_setting',
                    'domain' => 'SYSTEM', 'status' => 'ACTIVE', 'lifecycle_id' => null,
                    'created_at' => $now,
                ]);
                $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
            }
            $this->db->table('resources')->where('id', $resource['id'])->update(['status' => 'ACTIVE']);
            $action = $this->db->table('resource_actions')
                ->where('resource_id', $resource['id'])->where('code', 'view')->get()->getRowArray();
            if ($action === null) {
                $this->db->table('resource_actions')->insert([
                    'resource_id' => $resource['id'], 'code' => 'view', 'created_at' => $now,
                ]);
            }
            $this->db->table('auth_permissions_users')->insert([
                'user_id' => $this->fixture->actorId,
                'permission' => 'notification_setting.view', 'created_at' => $now,
            ]);
            // E1.2A catalog setup is isolated in erp_test and rolled back.
            $ops = $this->db->table('resources')
                ->where('type', 'notification_operations')->get()->getRowArray();
            if ($ops !== null) {
                $this->db->table('resources')->where('id', $ops['id'])
                    ->update(['status' => 'ACTIVE', 'domain' => 'SYSTEM', 'lifecycle_id' => null]);
            }
            (new NotificationOperationsAccessProvisioner($this->db))->provision();
            $this->db->table('auth_permissions_users')->insert([
                'user_id' => $this->fixture->actorId,
                'permission' => 'notification_operations.view', 'created_at' => $now,
            ]);
            if ($this->db->table('notification_settings')->where('setting_key', 'import.completed.in_app')->countAllResults() === 0) {
                $this->db->table('notification_settings')->insert([
                    'setting_key' => 'import.completed.in_app', 'default_value' => 1,
                    'allow_user_override' => 1, 'revision' => 1, 'override_epoch' => 1,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            $this->db->close();
            throw $error;
        }
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        $this->db->close();
        parent::tearDown();
    }

    public function testAuthorizedDiagnosticsIsAggregateOnlyAndNeverMutatesQueue(): void
    {
        $before = $this->db->table('notification_outbox')->countAllResults();
        $audit = $this->db->table('configuration_audit_events')->countAllResults();
        $data = (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
        self::assertSame(['outbox', 'email', 'push'], array_keys($data['queues']));
        self::assertSame(['health' => 'UNKNOWN', 'reason' => 'TELEMETRY_UNAVAILABLE'], $data['cron']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $data['as_of_utc']);
        foreach ($data['queues'] as $queue) {
            self::assertArrayHasKey('retry_ready', $queue);
            self::assertArrayHasKey('retry_delayed', $queue);
            self::assertArrayHasKey('oldest_ready_minutes', $queue);
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        foreach (['recipient_user_id', 'payload_json', 'last_error_code', 'endpoint_cipher',
            'email_address', 'lease_token', 'smtp_password', 'source_id', 'event_key'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
        self::assertSame($before, $this->db->table('notification_outbox')->countAllResults());
        self::assertSame($audit, $this->db->table('configuration_audit_events')->countAllResults());
    }

    public function testDiagnosticsRejectsSettingsOnlyActor(): void
    {
        $this->fixture->revoke($this->fixture->actorId, 'notification_operations.view');
        try {
            (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
            self::fail('Settings-only actor must not read diagnostics');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testDiagnosticsAllowsOperationsOnlyActor(): void
    {
        $this->fixture->revoke($this->fixture->actorId, 'notification_setting.view');
        $data = (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
        self::assertTrue($data['queues']['email']['available']);
        self::assertSame('UNKNOWN', $data['cron']['health']);
    }

    public function testDiagnosticsRejectsDeactivatedOperationsResource(): void
    {
        $this->db->table('resources')->where('type', 'notification_operations')->update(['status' => 'INACTIVE']);
        try {
            (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
            self::fail('Inactive Operations catalog must fail closed');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testDiagnosticsAggregatesRetryLeaseDeadWithoutRawErrors(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $past = gmdate('Y-m-d H:i:s', time() - 5400);
        $future = gmdate('Y-m-d H:i:s', time() + 600);
        $records = [
            ['status' => 'RETRY', 'available_at' => $past, 'last_error_code' => 'M11_EMAIL_TRANSPORT_FAILED'],
            ['status' => 'RETRY', 'available_at' => $future, 'last_error_code' => null],
            ['status' => 'DEAD', 'available_at' => $now, 'last_error_code' => 'private-sender@example.test'],
            ['status' => 'LEASED', 'available_at' => $past, 'leased_until' => $past],
        ];
        foreach ($records as $i => $record) {
            $this->db->table('notification_outbox')->insert(array_merge([
                'event_key' => 'nfx-e12b-' . uniqid('', true) . '-' . $i,
                'event_type' => 'import.completed', 'source_type' => 'import_job', 'source_id' => 1,
                'payload_json' => '{}', 'attempts' => 1, 'created_at' => $now,
            ], $record));
        }
        $data = (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
        $outbox = $data['queues']['outbox'];
        self::assertGreaterThanOrEqual(1, $outbox['retry_ready']);
        self::assertGreaterThanOrEqual(1, $outbox['retry_delayed']);
        self::assertGreaterThanOrEqual(1, $outbox['dead']);
        self::assertGreaterThanOrEqual(1, $outbox['expired_leases']);
        self::assertGreaterThanOrEqual(1, $outbox['overdue_ready_60m']);
        self::assertGreaterThanOrEqual(90, $outbox['oldest_ready_minutes']);
        self::assertContains('DEAD_PRESENT', $outbox['findings']);
        self::assertContains('OVERDUE_60M', $outbox['findings']);
        self::assertGreaterThanOrEqual(1, $outbox['error_categories']['TRANSPORT']);
        self::assertGreaterThanOrEqual(1, $outbox['error_categories']['OTHER']);
        self::assertStringNotContainsString('private-sender@example.test', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** E1.3: avoid stale per-service permission caching after a grant revocation. */
    public function testDiagnosticsRechecksPermissionOnEveryRequest(): void
    {
        $service = new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db);
        self::assertTrue($service->diagnostics()['queues']['outbox']['available']);
        $this->fixture->revoke($this->fixture->actorId, 'notification_operations.view');
        try {
            $service->diagnostics();
            self::fail('Revoked grant must deny a reused service instance');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    /** E1.3: the Diagnostics endpoint cannot be reached through a retired action. */
    public function testDiagnosticsFailsClosedWhenOperationsActionIsRemoved(): void
    {
        $resource = $this->db->table('resources')->where('type', 'notification_operations')->get()->getRowArray();
        $action = $this->db->table('resource_actions')->where('resource_id', $resource['id'])
            ->where('code', 'view')->get()->getRowArray();
        $this->db->table('resource_actions')->where('id', $action['id'])->update(['code' => 'view_disabled']);
        try {
            (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
            self::fail('Missing Operations action must deny Diagnostics');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    /** E1.3: an explicitly denied user must not see Diagnostics via their base grant. */
    public function testDiagnosticsAbsoluteDenyOverridesDirectGrant(): void
    {
        $resource = $this->db->table('resources')->where('type', 'notification_operations')->get()->getRowArray();
        $action = $this->db->table('resource_actions')->where('resource_id', $resource['id'])
            ->where('code', 'view')->get()->getRowArray();
        $root = $this->db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        $this->db->table('policies')->insert([
            'organization_id' => $root['id'], 'resource_id' => $resource['id'], 'state_id' => null,
            'participant_type' => 'USER', 'participant_ref_id' => $this->fixture->actorId,
            'status' => 'ACTIVE', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $policyId = (string) $this->db->insertID();
        $this->db->table('policy_actions')->insert([
            'policy_id' => $policyId, 'resource_action_id' => $action['id'],
            'effect' => 'ABSOLUTE_DENY', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        try {
            (new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db))->diagnostics();
            self::fail('ABSOLUTE_DENY must block Diagnostics');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    /** E1.3: all categories and status counts share one statement per queue. */
    public function testDiagnosticsErrorGroupsRemainConsistentAndNeverExposeUnknownCodes(): void
    {
        $service = new NotificationOperationsDiagnosticsService($this->fixture->actor(), $this->db);
        $before = $service->diagnostics()['queues']['outbox'];
        $past = gmdate('Y-m-d H:i:s', time() - 5400);
        $future = gmdate('Y-m-d H:i:s', time() + 1800);
        $rows = [
            ['RETRY', $past, 'M11_EMAIL_TRANSPORT_FAILED', 'TRANSPORT'],
            ['RETRY', $future, 'M11_EMAIL_POLICY_MISSING', 'POLICY'],
            ['DEAD', $past, 'M11_PUSH_LEASE_EXHAUSTED', 'LEASE_EXHAUSTED'],
            ['DEAD', $past, 'M11_EMAIL_USER_INACTIVE', 'IDENTITY'],
            ['DEAD', $past, 'secret-operator@example.test', 'OTHER'],
            ['DEAD', $past, null, 'UNSPECIFIED'],
            // A PENDING row carrying an obsolete error MUST NOT count as an error.
            ['PENDING', $past, 'M11_EMAIL_TRANSPORT_FAILED', null],
        ];
        foreach ($rows as $i => [$status, $availableAt, $code]) {
            $this->db->table('notification_outbox')->insert([
                'event_key' => 'nfx-e13-' . bin2hex(random_bytes(12)) . '-' . $i,
                'event_type' => 'import.completed', 'source_type' => 'import_job', 'source_id' => 1,
                'payload_json' => '{}', 'attempts' => 1, 'status' => $status,
                'last_error_code' => $code, 'available_at' => $availableAt,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
        $after = $service->diagnostics()['queues']['outbox'];
        foreach (['TRANSPORT', 'POLICY', 'LEASE_EXHAUSTED', 'IDENTITY', 'OTHER', 'UNSPECIFIED'] as $category) {
            self::assertSame($before['error_categories'][$category] + 1, $after['error_categories'][$category], $category);
        }
        self::assertSame($before['retry_ready'] + 1, $after['retry_ready']);
        self::assertSame($before['retry_delayed'] + 1, $after['retry_delayed']);
        self::assertSame($before['dead'] + 4, $after['dead']);
        self::assertSame(
            $after['dead'] + $after['retry_ready'] + $after['retry_delayed'],
            array_sum($after['error_categories'])
        );
        self::assertStringNotContainsString('secret-operator@example.test', json_encode($after, JSON_THROW_ON_ERROR));
    }

    public function testAuthorizedOverviewIsAggregateOnlyReadOnlyAndCronUnknown(): void
    {
        $auditBefore = $this->db->table('configuration_audit_events')->countAllResults();
        $outboxBefore = $this->db->table('notification_outbox')->countAllResults();
        $response = (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
        self::assertSame(['outbox', 'email', 'push'], array_keys($response['queues']));
        self::assertCount(12, $response['runtime_switches']);
        self::assertSame(['health' => 'UNKNOWN', 'reason' => 'TELEMETRY_UNAVAILABLE',
            'last_tick_at' => null], $response['cron']);
        self::assertFalse($response['telemetry_available']);
        self::assertTrue($response['queues']['outbox']['available']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $response['as_of_utc']);
        $json = json_encode($response, JSON_THROW_ON_ERROR);
        foreach (['recipient_user_id', 'payload_json', 'endpoint_cipher', 'smtp_password',
            'last_error_code', 'secret', 'auth_identities', 'email_address', 'event_key'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
        self::assertSame($auditBefore, $this->db->table('configuration_audit_events')->countAllResults());
        self::assertSame($outboxBefore, $this->db->table('notification_outbox')->countAllResults());
    }

    public function testMissingViewPermissionFailsBeforeAnyQueueMetrics(): void
    {
        $this->expectException(DomainException::class);
        try {
            (new NotificationOperationsOverviewService(
                $this->fixture->actor($this->fixture->firstRecipientId), $this->db
            ))->overview();
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
            throw $e;
        }
    }

    public function testInactiveUserDeniedEvenWhenAValidActorExists(): void
    {
        $this->expectException(DomainException::class);
        try {
            (new NotificationOperationsOverviewService(
                $this->fixture->actor($this->fixture->inactiveRecipientId), $this->db
            ))->overview();
        } catch (DomainException $e) {
            self::assertSame('UNAUTHENTICATED', $e->getErrorCode());
            throw $e;
        }
    }

    public function testLegacySettingsViewAloneCannotReadOperations(): void
    {
        // Old permission must NOT be treated as an alias or migration grant.
        $this->fixture->revoke($this->fixture->actorId, 'notification_operations.view');
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Settings VIEW must not authorize Operations');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testDedicatedOperationsViewWorksWithoutSettingsPermission(): void
    {
        $this->fixture->revoke($this->fixture->actorId, 'notification_setting.view');
        $result = (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
        self::assertSame(['outbox', 'email', 'push'], array_keys($result['queues']));
        self::assertSame('UNKNOWN', $result['cron']['health']);
    }

    public function testInactiveOperationsResourceDeniesEvenWithBasePermission(): void
    {
        $this->db->table('resources')->where('type', 'notification_operations')
            ->update(['status' => 'INACTIVE']);
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Inactive operations Resource must fail closed');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testMissingOperationsCatalogResourceFailsClosed(): void
    {
        // A reversible rename is safe even if the catalog row has FK references.
        $this->db->table('resources')->where('type', 'notification_operations')
            ->update(['type' => 'notification_operations_fixture_missing']);
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Missing Operations resource must not fall back to Settings');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testWrongOperationsResourceDomainFailsClosed(): void
    {
        $this->db->table('resources')->where('type', 'notification_operations')
            ->update(['domain' => 'MASTER']);
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Cross-domain resource must be denied');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testExplicitAbsoluteDenyOverridesOperationsBaseGrant(): void
    {
        $resource = $this->db->table('resources')->where('type', 'notification_operations')->get()->getRowArray();
        $action = $this->db->table('resource_actions')->where('resource_id', $resource['id'])
            ->where('code', 'view')->get()->getRowArray();
        $root = $this->db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        $this->db->table('policies')->insert([
            'organization_id' => $root['id'], 'resource_id' => $resource['id'], 'state_id' => null,
            'participant_type' => 'USER', 'participant_ref_id' => $this->fixture->actorId,
            'status' => 'ACTIVE', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $policyId = (string) $this->db->insertID();
        $this->db->table('policy_actions')->insert([
            'policy_id' => $policyId, 'resource_action_id' => $action['id'],
            'effect' => 'ABSOLUTE_DENY', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Absolute PBAC deny must veto base permission');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testProvisioningIsIdempotentAndDoesNotGrantOrReactivate(): void
    {
        $provisioner = new NotificationOperationsAccessProvisioner($this->db);
        $resourceBefore = $this->db->table('resources')->where('type', 'notification_operations')->countAllResults();
        $resource = $this->db->table('resources')->where('type', 'notification_operations')->get()->getRowArray();
        $actionBefore = $this->db->table('resource_actions')->where('resource_id', $resource['id'])->countAllResults();
        $directBefore = $this->db->table('auth_permissions_users')->countAllResults();
        $groupBefore = $this->db->table('group_permissions')->countAllResults();
        $policyBefore = $this->db->table('policies')->countAllResults();
        $provisioner->provision();
        $provisioner->provision();
        self::assertSame($resourceBefore, $this->db->table('resources')->where('type', 'notification_operations')->countAllResults());
        self::assertSame($actionBefore, $this->db->table('resource_actions')->where('resource_id', $resource['id'])->countAllResults());
        self::assertSame($directBefore, $this->db->table('auth_permissions_users')->countAllResults());
        self::assertSame($groupBefore, $this->db->table('group_permissions')->countAllResults());
        self::assertSame($policyBefore, $this->db->table('policies')->countAllResults());
        $this->db->table('resources')->where('id', $resource['id'])->update(['status' => 'INACTIVE']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NFX_E12A_PROVISION_RESOURCE_CONFLICT');
        $provisioner->provision();
    }

    public function testProvisionRejectsPreexistingShieldGrantBeforeRegisteringMissingAction(): void
    {
        // Temporarily hide the fixture's Resource type, preserving every FK,
        // while its direct Shield string remains persisted in this transaction.
        // A new Action would activate that string without an explicit grant.
        $this->db->table('resources')->where('type', 'notification_operations')
            ->update(['type' => 'notification_operations_fixture_hidden']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NFX_E12A_PROVISION_PREEXISTING_GRANTS');
        (new NotificationOperationsAccessProvisioner($this->db))->provision();
    }

    public function testNoActionRegisteredMustDenyBeforeMetrics(): void
    {
        $resource = $this->db->table('resources')->where('type', 'notification_operations')->get()->getRowArray();
        $action = $this->db->table('resource_actions')->where('resource_id', $resource['id'])
            ->where('code', 'view')->get()->getRowArray();
        // Rename inside rollback fixture (rather than delete, which may hit FKs).
        $this->db->table('resource_actions')->where('id', $action['id'])->update(['code' => 'view_disabled']);
        try {
            (new NotificationOperationsOverviewService($this->fixture->actor(), $this->db))->overview();
            self::fail('Absent operations VIEW action must fail closed');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_ACCESS_DENIED', $e->getErrorCode());
        }
    }

    public function testOverdueOutboxCountersReadFixtureWithoutMutatingIt(): void
    {
        $service = new NotificationOperationsOverviewService($this->fixture->actor(), $this->db);
        $before = $service->overview();
        $key = 'e1-test:' . bin2hex(random_bytes(12));
        $past = gmdate('Y-m-d H:i:s', time() - 3600);
        $this->db->table('notification_outbox')->insert([
            'event_key' => $key, 'event_type' => 'import.completed',
            'source_type' => 'import_job', 'source_id' => '1', 'payload_json' => '{}',
            'status' => 'PENDING', 'available_at' => $past, 'created_at' => $past,
        ]);
        $result = $service->overview();
        self::assertSame(($before['queues']['outbox']['status_counts']['PENDING'] ?? 0) + 1,
            $result['queues']['outbox']['status_counts']['PENDING']);
        self::assertSame($before['queues']['outbox']['overdue_ready_15m'] + 1,
            $result['queues']['outbox']['overdue_ready_15m']);
        $stored = $this->db->table('notification_outbox')->where('event_key', $key)->get()->getRowArray();
        self::assertSame('PENDING', $stored['status']);
        self::assertSame('0', (string) $stored['attempts']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationDispatcher;
use App\Modules\Notifications\WorkflowNotificationAssigneesService;
use App\Modules\Notifications\WorkflowNotificationRules;
use App\Modules\Workflow\Execution\BusinessWorkflowService;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;
use Tests\M11\Support\WorkflowFixture;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';
require_once __DIR__ . '/../Support/WorkflowFixture.php';

/**
 * Real MariaDB + real M11 services; all fixture writes and worker ACKs are
 * nested inside ONE outer transaction. The outer transaction always rolls back.
 * Run serially. Does NOT test two separate concurrent database connections.
 */
final class WorkflowNotificationsIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private WorkflowFixture $fixture;
    /** @var array<string,array{getenv:string|false,env:mixed,server:mixed}> */
    private array $savedFlags = [];
    private bool $transactionOpened = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            if (! $this->db->transBegin()) {
                throw new \RuntimeException('Could not start outer M11 fixture transaction');
            }
            $this->transactionOpened = true;
            $this->flag('DINKERP_M11_WORKFLOW_PRODUCER_ENABLED', 'enabled');
            $this->flag('DINKERP_M11_WORKFLOW_WRITE_MODE', 'enabled');
            $this->flag('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', 'enabled');
            $this->fixture = new WorkflowFixture($this->db);
            $this->fixture->install();
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
        // Never commit even if the test failed or was interrupted by an assertion.
        if ($this->transactionOpened) {
            $this->db->transRollback();
            $this->transactionOpened = false;
        }
        foreach ($this->savedFlags as $name => $saved) {
            if ($saved['getenv'] === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $saved['getenv']);
            }
            if ($saved['env'] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $saved['env'];
            }
            if ($saved['server'] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $saved['server'];
            }
        }
        $this->savedFlags = [];
        if (isset($this->db)) {
            $this->db->close();
        }
    }

    private function flag(string $key, string $value): void
    {
        if (! isset($this->savedFlags[$key])) {
            $this->savedFlags[$key] = [
                'getenv' => getenv($key), 'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
            ];
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        self::assertSame($value, (string) env($key, 'off'), 'CI env flag was not overridden: ' . $key);
    }

    public function testFixtureKeepsOneCanonicalGroupRootForPbac(): void
    {
        $roots = $this->db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)->get()->getResultArray();
        self::assertCount(1, $roots, 'M11 fixture must not add a second canonical GROUP root.');

        $assignments = $this->db->table('employee_assignments ea')
            ->select('ea.organization_id')
            ->join('employees e', 'e.id = ea.employee_id')
            ->where('e.user_id', $this->fixture->actorId)->get()->getResultArray();
        self::assertCount(1, $assignments);
        self::assertSame((string) $roots[0]['id'], (string) $assignments[0]['organization_id']);
    }

    public function testAssignmentCasPersistsUsersAndWritesAuditExactlyOnce(): void
    {
        $response = $this->fixture->assign();
        self::assertSame('1', $response['revision']);
        self::assertSame($this->sortedRecipients(), $response['user_ids']);
        $service = new WorkflowNotificationAssigneesService($this->fixture->actor(), $this->db);
        self::assertSame($response, $service->listing('province', $this->fixture->targetId));
        // Idempotent identical write does not advance revision or create another audit.
        self::assertSame($response, $service->replace('province', $this->fixture->targetId, [
            'expected_revision' => '1', 'user_ids' => $response['user_ids'],
        ]));
        self::assertSame(1, $this->auditCount());
        try {
            $service->replace('province', $this->fixture->targetId, [
                'expected_revision' => '0', 'user_ids' => [$this->fixture->firstRecipientId],
            ]);
            self::fail('Stale revision unexpectedly accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_REVISION_CONFLICT', $e->getErrorCode());
        }
        self::assertSame($response, $service->listing('province', $this->fixture->targetId));
        self::assertSame(1, $this->auditCount());
    }

    public function testFullWorkflowEnqueuesSnapshotAndDeliversToTwoUsersExactlyOnce(): void
    {
        $this->fixture->assign();
        $result = $this->transition('m11_submit');
        self::assertSame('M11_B', $result['status']);
        $event = $this->onlyWorkflowEvent();
        self::assertSame('PENDING', $event['status']);
        self::assertSame('workflow.action_required', $event['event_type']);
        self::assertSame($this->sortedRecipients(), WorkflowNotificationRules::decode($event['payload_json'])['recipient_user_ids']);
        $worker = new NotificationDispatcher($this->db);
        self::assertSame(['claimed' => 1, 'processed' => 1], $this->summary($worker->runOnce()));
        self::assertSame('PROCESSED', $this->onlyWorkflowEvent()['status']);
        self::assertSame($this->sortedRecipients(), $this->inboxRecipients());
        self::assertSame(2, $this->inboxCount());
        self::assertSame(['claimed' => 0, 'processed' => 0], $this->summary($worker->runOnce()));
        self::assertSame(2, $this->inboxCount(), 'Repeated dispatch must not duplicate notifications');
    }

    public function testUnassignedWorkflowDoesNotCreateOutbox(): void
    {
        $this->transition('m11_submit');
        self::assertSame('M11_B', $this->targetStatus());
        self::assertSame(0, $this->workflowEventCount());
    }

    public function testProducerOffDoesNotCreateOutboxButTransitionWorks(): void
    {
        $this->fixture->assign();
        $this->flag('DINKERP_M11_WORKFLOW_PRODUCER_ENABLED', 'off');
        $this->transition('m11_submit');
        self::assertSame('M11_B', $this->targetStatus());
        self::assertSame(0, $this->workflowEventCount());
    }

    public function testInvalidRecipientDoesNotChangeAssignmentOrAudit(): void
    {
        $service = new WorkflowNotificationAssigneesService($this->fixture->actor(), $this->db);
        try {
            $service->replace('province', $this->fixture->targetId, [
                'expected_revision' => '0',
                'user_ids' => [$this->fixture->firstRecipientId, $this->fixture->inactiveRecipientId],
            ]);
            self::fail('Inactive recipient accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_RECIPIENT_INVALID', $e->getErrorCode());
        }
        self::assertSame(['revision' => '0', 'user_ids' => []], $service->listing('province', $this->fixture->targetId));
        self::assertSame(0, $this->auditCount());
    }

    public function testActorWithoutMutationPermissionCannotAssignOrTransition(): void
    {
        $this->fixture->revoke($this->fixture->actorId, 'province.setstate');
        $service = new WorkflowNotificationAssigneesService($this->fixture->actor(), $this->db);
        try {
            $service->listing('province', $this->fixture->targetId);
            self::fail('Unauthorized assignee list accepted');
        } catch (DomainException $e) {
            self::assertSame('AUTH_POLICY_DENIED', $e->getErrorCode());
        }
        try {
            $this->transition('m11_submit');
            self::fail('Unauthorized workflow transition accepted');
        } catch (DomainException $e) {
            self::assertSame('AUTH_POLICY_DENIED', $e->getErrorCode());
        }
        self::assertSame('M11_A', $this->targetStatus());
        self::assertSame(0, $this->workflowEventCount());
    }

    public function testInvalidWorkflowActionRollsBackWithoutAnyOutboxWrite(): void
    {
        $this->fixture->assign();
        try {
            $this->transition('m11_approve'); // cannot approve directly from M11_A
            self::fail('Invalid Workflow transition was accepted');
        } catch (DomainException $e) {
            self::assertSame('WF_INVALID_TRANSITION', $e->getErrorCode());
        }
        self::assertSame('M11_A', $this->targetStatus());
        self::assertSame(0, $this->workflowEventCount());
        self::assertSame(0, $this->inboxCount());
    }

    public function testViewRevocationAtDeliveryPreventsNotification(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        $this->fixture->revoke($this->fixture->secondRecipientId, 'province.view');
        $result = (new NotificationDispatcher($this->db))->runOnce();
        self::assertSame(1, $result['processed']);
        self::assertSame([$this->fixture->firstRecipientId], $this->inboxRecipients());
    }

    public function testPermissionRevokedAfterSnapshotBlocksThatRecipientAtDelivery(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        // There is still an immutable two-user snapshot, but no approval ability.
        $this->fixture->revoke($this->fixture->secondRecipientId, 'province.m11_approve');
        $worker = new NotificationDispatcher($this->db);
        self::assertSame(1, $worker->runOnce()['processed']);
        self::assertSame([$this->fixture->firstRecipientId], $this->inboxRecipients());
    }

    public function testDeactivatedUserAfterSnapshotIsNotNotified(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        $this->db->table('users')->where('id', $this->fixture->secondRecipientId)->update(['active' => 0]);
        self::assertSame(1, (new NotificationDispatcher($this->db))->runOnce()['processed']);
        self::assertSame([$this->fixture->firstRecipientId], $this->inboxRecipients());
    }

    public function testIfAllRecipientsLosePermissionEventIsSuppressed(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        foreach ($this->sortedRecipients() as $id) {
            $this->fixture->revoke($id, 'province.setstate');
        }
        $result = (new NotificationDispatcher($this->db))->runOnce();
        self::assertSame(1, $result['suppressed']);
        self::assertSame('SUPPRESSED', $this->onlyWorkflowEvent()['status']);
        self::assertSame(0, $this->inboxCount());
    }

    public function testStaleActionRequiredIsSuppressedWhenTargetAlreadyAdvanced(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        $this->transition('m11_approve'); // M11_C is terminal; no second event.
        self::assertSame('M11_C', $this->targetStatus());
        self::assertSame(1, $this->workflowEventCount());
        $result = (new NotificationDispatcher($this->db))->runOnce();
        self::assertSame(1, $result['suppressed']);
        self::assertSame('NOTIFICATION_WORKFLOW_STATE_CHANGED', $this->onlyWorkflowEvent()['last_error_code']);
        self::assertSame(0, $this->inboxCount());
    }

    public function testSystemPolicyDisabledSuppressesWorkflowDelivery(): void
    {
        $this->fixture->assign();
        $this->transition('m11_submit');
        $this->db->table('notification_settings')->where('setting_key', 'workflow.action_required.in_app')
            ->update(['default_value' => 0]);
        $result = (new NotificationDispatcher($this->db))->runOnce();
        self::assertSame(1, $result['suppressed']);
        self::assertSame(0, $this->inboxCount());
    }

    /** @return array{status:string,action_code:string} */
    private function transition(string $action): array
    {
        return (new BusinessWorkflowService($this->fixture->actor(), $this->db))
            ->execute('province', $this->fixture->targetId, $action);
    }

    /** @return array<string,mixed> */
    private function onlyWorkflowEvent(): array
    {
        $rows = $this->db->table('notification_outbox')->where('source_type', 'workflow_transition')
            ->where('source_id', $this->fixture->targetId)->get()->getResultArray();
        self::assertCount(1, $rows, 'Expected exactly one workflow outbox event');
        return $rows[0];
    }

    private function workflowEventCount(): int
    {
        return $this->db->table('notification_outbox')->where('source_type', 'workflow_transition')
            ->where('source_id', $this->fixture->targetId)->countAllResults();
    }

    private function inboxCount(): int
    {
        return $this->db->table('user_notifications')->like('event_key', $this->fixtureEventPrefix(), 'after')
            ->countAllResults();
    }

    /** @return list<string> */
    private function inboxRecipients(): array
    {
        $rows = $this->db->table('user_notifications')->select('recipient_user_id')
            ->like('event_key', $this->fixtureEventPrefix(), 'after')->get()->getResultArray();
        $ids = array_map(static fn (array $r): string => (string) $r['recipient_user_id'], $rows);
        usort($ids, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        return $ids;
    }

    /** @return list<string> */
    private function sortedRecipients(): array
    {
        $ids = [$this->fixture->firstRecipientId, $this->fixture->secondRecipientId];
        usort($ids, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        return $ids;
    }

    private function targetStatus(): string
    {
        $row = $this->db->table('provinces')->select('status')->where('id', $this->fixture->targetId)
            ->get()->getRowArray();
        return (string) ($row['status'] ?? '');
    }

    private function auditCount(): int
    {
        return $this->db->table('configuration_audit_events')
            ->where('configuration_type', 'WORKFLOW_NOTIFICATION_ASSIGNMENT')
            ->where('setting_key', 'workflow:province:' . $this->fixture->targetId)->countAllResults();
    }

    private function fixtureEventPrefix(): string
    {
        return 'workflow:province:' . $this->fixture->targetId . ':';
    }

    /** @param array<string,int> $stats @return array{claimed:int,processed:int} */
    private function summary(array $stats): array
    {
        return ['claimed' => $stats['claimed'], 'processed' => $stats['processed']];
    }
}

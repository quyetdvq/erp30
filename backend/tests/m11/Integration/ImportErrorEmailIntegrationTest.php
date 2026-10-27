<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Modules\Notifications\NotificationDispatcher;
use App\Modules\Notifications\NotificationEmailSender;
use App\Modules\Notifications\NotificationEmailWorker;
use App\Modules\Notifications\NotificationOutboxProducer;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

/** NFX-C1: guarded, rolled-back MariaDB integration; never contacts SMTP. */
final class ImportErrorEmailIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private bool $opened = false;
    private string $userId;
    /** @var array<string,array{getenv:string|false,env:mixed,server:mixed}> */
    private array $savedFlags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            if (! $this->db->transBegin()) {
                throw new RuntimeException('NFX_C1_FIXTURE_TRANSACTION_FAILED');
            }
            $this->opened = true;
            foreach ([
                'DINKERP_M11_IMPORT_PRODUCER_ENABLED' => 'enabled',
                'DINKERP_NOTIFICATIONS_DELIVERY_ENABLED' => 'enabled',
                'DINKERP_M11_EMAIL_QUEUE_ENABLED' => 'enabled',
                'DINKERP_M11_EMAIL_SEND_ENABLED' => 'enabled',
            ] as $name => $value) {
                $this->flag($name, $value);
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->userId = $this->insert('users', [
                'username' => 'nfxc1_' . bin2hex(random_bytes(7)),
                'status' => 'ACTIVE', 'active' => 1, 'created_at' => $now,
            ]);
            $this->insert('auth_identities', [
                'user_id' => $this->userId, 'type' => 'email_password',
                'secret' => 'nfxc1_' . bin2hex(random_bytes(7)) . '@example.test',
                'created_at' => $now,
            ]);
            foreach (['import.completed.in_app', 'import.completed_with_errors.in_app',
                'import.failed.in_app', 'import.completed.email',
                'import.completed_with_errors.email', 'import.failed.email'] as $key) {
                $this->policy($key, str_ends_with($key, '.in_app') ? 1 : 0);
            }
        } catch (\Throwable $e) {
            $this->cleanup();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        if ($this->opened) {
            $this->db->transRollback();
            $this->opened = false;
        }
        foreach ($this->savedFlags as $key => $old) {
            if ($old['getenv'] === false) { putenv($key); } else { putenv($key . '=' . $old['getenv']); }
            if ($old['env'] === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $old['env']; }
            if ($old['server'] === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $old['server']; }
        }
        $this->savedFlags = [];
        if (isset($this->db)) { $this->db->close(); }
    }

    private function flag(string $key, string $value): void
    {
        if (! array_key_exists($key, $this->savedFlags)) {
            $this->savedFlags[$key] = [
                'getenv' => getenv($key), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null,
            ];
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        self::assertSame($value, (string) env($key, 'off'));
    }

    private function insert(string $table, array $row): string
    {
        if (! $this->db->table($table)->insert($row)) {
            throw new RuntimeException('NFX_C1_FIXTURE_INSERT_FAILED:' . $table);
        }
        return (string) $this->db->insertID();
    }

    private function policy(string $key, int $default): void
    {
        $row = ['default_value' => $default, 'allow_user_override' => 1,
            'revision' => '1', 'override_epoch' => '1', 'updated_at' => gmdate('Y-m-d H:i:s')];
        $exists = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
        $ok = $exists === null
            ? $this->db->table('notification_settings')->insert(['setting_key' => $key] + $row)
            : $this->db->table('notification_settings')->where('setting_key', $key)->update($row);
        if (! $ok) { throw new RuntimeException('NFX_C1_FIXTURE_POLICY_FAILED:' . $key); }
    }

    private function optIn(string $key): void
    {
        $row = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
        if ($row === null) { throw new RuntimeException('NFX_C1_MISSING_POLICY'); }
        $this->insert('notification_user_preferences', [
            'user_id' => $this->userId, 'setting_key' => $key,
            'override_value' => 1, 'override_epoch' => (string) $row['override_epoch'],
            'user_revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{job_id:string,event_key:string,event_type:string} */
    private function dispatch(string $status): array
    {
        $jobId = $this->insert('import_jobs', [
            'entity_type' => 'PRODUCT', 'mode' => 'INSERT', 'source_format' => 'CSV',
            'status' => $status, 'created_by' => $this->userId,
            'success_rows' => $status === 'FAILED' ? 0 : 4,
            'error_rows' => $status === 'COMPLETED' ? 0 : 2,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        NotificationOutboxProducer::enqueueImportTerminal($this->db, $jobId, $status);
        $stats = (new NotificationDispatcher($this->db))->runOnce(5);
        self::assertSame(1, $stats['processed']);
        return ['job_id' => $jobId, 'event_key' => 'import-job:' . $jobId . ':terminal',
            'event_type' => match ($status) {
                'COMPLETED' => 'import.completed',
                'COMPLETED_WITH_ERRORS' => 'import.completed_with_errors',
                'FAILED' => 'import.failed',
            }];
    }

    /** @return array<string,mixed>|null */
    private function receipt(string $eventKey): ?array
    {
        return $this->db->table('notification_email_deliveries')->where('event_key', $eventKey)->get()->getRowArray();
    }

    private function fake(): NotificationEmailSender
    {
        return new class implements NotificationEmailSender {
            /** @var list<array{address:string,subject:string,body:string}> */
            public array $calls = [];
            public function send(string $recipientAddress, string $subject, string $body): void
            {
                $this->calls[] = ['address' => $recipientAddress, 'subject' => $subject, 'body' => $body];
            }
        };
    }

    public function testNoOptInLeavesInAppButDoesNotQueueFailureEmail(): void
    {
        $event = $this->dispatch('FAILED');
        self::assertNull($this->receipt($event['event_key']));
        self::assertSame(1, $this->db->table('user_notifications')
            ->where('event_key', $event['event_key'])->countAllResults());
    }

    public function testExistingCompletedOptInDoesNotAuthorizeFailureEmail(): void
    {
        $this->optIn('import.completed.email');
        $event = $this->dispatch('FAILED');
        self::assertNull($this->receipt($event['event_key']));
    }

    public function testFailureOptInQueuesAndSendsOnlyItsOwnEvent(): void
    {
        $this->optIn('import.failed.email');
        $event = $this->dispatch('FAILED');
        $receipt = $this->receipt($event['event_key']);
        self::assertSame('import.failed', $receipt['event_type'] ?? null);
        self::assertSame('PENDING', $receipt['status'] ?? null);
        $fake = $this->fake();
        $worker = new NotificationEmailWorker($this->db, $fake);
        self::assertSame(1, $worker->runOnce(5)['sent']);
        self::assertCount(1, $fake->calls);
        self::assertStringContainsString('thất bại', $fake->calls[0]['subject']);
        self::assertSame(0, $worker->runOnce(5)['claimed']);
    }

    public function testPartialImportNeedsDifferentOptInAndHasSafeCounts(): void
    {
        $this->optIn('import.failed.email');
        $notAuthorized = $this->dispatch('COMPLETED_WITH_ERRORS');
        self::assertNull($this->receipt($notAuthorized['event_key']));
        $this->optIn('import.completed_with_errors.email');
        $event = $this->dispatch('COMPLETED_WITH_ERRORS');
        $receipt = $this->receipt($event['event_key']);
        self::assertSame('import.completed_with_errors', $receipt['event_type'] ?? null);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['sent']);
        self::assertCount(1, $fake->calls);
        self::assertStringContainsString('4', $fake->calls[0]['body']);
        self::assertStringContainsString('2', $fake->calls[0]['body']);
    }

    public function testOptOutAfterQueueCancelsNewEmailBeforeSmtp(): void
    {
        $this->optIn('import.failed.email');
        $event = $this->dispatch('FAILED');
        $this->db->table('notification_user_preferences')
            ->where('user_id', $this->userId)->where('setting_key', 'import.failed.email')
            ->update(['override_value' => 0]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
        self::assertSame('CANCELLED', $this->receipt($event['event_key'])['status']);
    }

    public function testPolicyEpochChangeCancelsQueuedEmail(): void
    {
        $this->optIn('import.completed_with_errors.email');
        $event = $this->dispatch('COMPLETED_WITH_ERRORS');
        $this->db->table('notification_settings')->where('setting_key', 'import.completed_with_errors.email')
            ->update(['override_epoch' => 2]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
        self::assertSame('CANCELLED', $this->receipt($event['event_key'])['status']);
    }

    public function testUserInactiveAfterQueueCancelsNewEmail(): void
    {
        $this->optIn('import.failed.email');
        $event = $this->dispatch('FAILED');
        $this->db->table('users')->where('id', $this->userId)->update(['active' => 0]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
        self::assertSame('CANCELLED', $this->receipt($event['event_key'])['status']);
    }

    public function testMissingNewPolicyBlocksEmailButDoesNotBreakInApp(): void
    {
        $this->db->table('notification_settings')->where('setting_key', 'import.failed.email')->delete();
        $event = $this->dispatch('FAILED');
        self::assertNull($this->receipt($event['event_key']));
        self::assertSame(1, $this->db->table('user_notifications')
            ->where('event_key', $event['event_key'])->countAllResults());
    }

    public function testUnsafeSystemDefaultDoesNotQueueNewEmail(): void
    {
        $this->optIn('import.failed.email');
        $this->db->table('notification_settings')->where('setting_key', 'import.failed.email')
            ->update(['default_value' => 1]);
        $event = $this->dispatch('FAILED');
        self::assertNull($this->receipt($event['event_key']));
        self::assertSame(1, $this->db->table('user_notifications')
            ->where('event_key', $event['event_key'])->countAllResults());
    }

    public function testEmailSendOffPausesQueuedNewErrorReceipt(): void
    {
        $this->optIn('import.failed.email');
        $event = $this->dispatch('FAILED');
        $this->flag('DINKERP_M11_EMAIL_SEND_ENABLED', 'off');
        $fake = $this->fake();
        $stats = (new NotificationEmailWorker($this->db, $fake))->runOnce(5);
        self::assertSame(0, $stats['sent']);
        self::assertCount(0, $fake->calls);
        self::assertSame('PENDING', $this->receipt($event['event_key'])['status']);
    }
    public function testEmailQueueOffSuppressesNewReceiptsButPreservesInApp(): void
    {
        $this->optIn('import.failed.email');
        $this->flag('DINKERP_M11_EMAIL_QUEUE_ENABLED', 'off');
        $event = $this->dispatch('FAILED');
        self::assertNull($this->receipt($event['event_key']));
        self::assertSame(1, $this->db->table('user_notifications')
            ->where('event_key', $event['event_key'])->countAllResults());
    }

    public function testEmailPolicyAdminLockCancelsPendingErrorReceipt(): void
    {
        $this->optIn('import.completed_with_errors.email');
        $event = $this->dispatch('COMPLETED_WITH_ERRORS');
        $this->db->table('notification_settings')->where('setting_key', 'import.completed_with_errors.email')
            ->update(['allow_user_override' => 0]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
        self::assertSame('CANCELLED', $this->receipt($event['event_key'])['status']);
    }

    public function testEmailDeliveryCanProceedWhenInAppPolicyDisabled(): void
    {
        $this->optIn('import.failed.email');
        $this->db->table('notification_settings')->where('setting_key', 'import.failed.in_app')
            ->update(['default_value' => 0, 'allow_user_override' => 0]);
        $event = $this->dispatch('FAILED');
        self::assertSame(0, $this->db->table('user_notifications')
            ->where('event_key', $event['event_key'])->countAllResults());
        self::assertSame('PENDING', $this->receipt($event['event_key'])['status']);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['sent']);
        self::assertCount(1, $fake->calls);
    }

    public function testRepeatedDispatcherTickDoesNotDuplicateEmailReceipt(): void
    {
        $this->optIn('import.failed.email');
        $event = $this->dispatch('FAILED');
        // Re-run the Dispatcher against the already ACKed outbox.
        // A repeated worker tick must not create another SMTP receipt.
        self::assertSame(0, (new NotificationDispatcher($this->db))->runOnce(5)['claimed']);
        self::assertSame(1, $this->db->table('notification_email_deliveries')
            ->where('event_key', $event['event_key'])->countAllResults());
    }

    public function testUnsupportedEmailReceiptIsDeadWithoutSending(): void
    {
        $eventKey = 'nfx-c1-unsupported-' . bin2hex(random_bytes(9));
        $this->insert('notification_email_deliveries', [
            'event_key' => $eventKey, 'recipient_user_id' => $this->userId,
            'event_type' => 'workflow.action_required',
            'params_json' => '{"success_rows":2,"error_rows":1}', 'status' => 'PENDING',
            'attempts' => 0, 'available_at' => gmdate('Y-m-d H:i:s'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['dead']);
        self::assertCount(0, $fake->calls);
        self::assertSame('DEAD', $this->receipt($eventKey)['status']);
    }

}

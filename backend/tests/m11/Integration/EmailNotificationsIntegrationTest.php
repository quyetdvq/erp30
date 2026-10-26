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

/** All writes to database.tests=erp_test are inside an outer rollback transaction. */
final class EmailNotificationsIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private bool $transactionOpened = false;
    private string $userId;
    private string $jobId;
    private string $emailAddress;
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
                throw new RuntimeException('Email fixture transaction could not begin');
            }
            $this->transactionOpened = true;
            foreach ([
                'DINKERP_M11_IMPORT_PRODUCER_ENABLED' => 'enabled',
                'DINKERP_NOTIFICATIONS_DELIVERY_ENABLED' => 'enabled',
                'DINKERP_M11_EMAIL_QUEUE_ENABLED' => 'enabled',
                'DINKERP_M11_EMAIL_SEND_ENABLED' => 'enabled',
            ] as $key => $value) {
                $this->flag($key, $value);
            }
            $this->fixture();
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
        if ($this->transactionOpened) {
            $this->db->transRollback();
            $this->transactionOpened = false;
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
        if (! isset($this->savedFlags[$key])) {
            $this->savedFlags[$key] = [
                'getenv' => getenv($key), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null,
            ];
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        self::assertSame($value, (string) env($key, 'off'));
    }

    private function fixture(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->userId = $this->insert('users', [
            'username' => 'm11em_' . bin2hex(random_bytes(7)),
            'status' => 'ACTIVE', 'active' => 1, 'created_at' => $now,
        ]);
        $this->emailAddress = 'm11email_' . bin2hex(random_bytes(6)) . '@example.test';
        $this->insert('auth_identities', [
            'user_id' => $this->userId, 'type' => 'email_password',
            'secret' => $this->emailAddress, 'created_at' => $now,
        ]);
        $this->jobId = $this->insert('import_jobs', [
            'entity_type' => 'PRODUCT', 'mode' => 'INSERT', 'source_format' => 'CSV',
            'status' => 'COMPLETED', 'created_by' => $this->userId,
            'success_rows' => 2, 'error_rows' => 0, 'created_at' => $now,
        ]);
        foreach (['import.completed.in_app' => [1, 1], 'import.completed.email' => [0, 1]] as $key => [$default, $override]) {
            $found = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
            if ($found === null) {
                $this->db->table('notification_settings')->insert([
                    'setting_key' => $key, 'default_value' => $default,
                    'allow_user_override' => $override, 'revision' => '1',
                    'override_epoch' => '1', 'updated_at' => $now,
                ]);
            } else {
                $this->db->table('notification_settings')->where('setting_key', $key)->update([
                    'default_value' => $default, 'allow_user_override' => $override,
                ]);
            }
        }
    }

    private function optIn(): void
    {
        $policy = $this->db->table('notification_settings')->where('setting_key', 'import.completed.email')
            ->get()->getRowArray();
        $this->db->table('notification_user_preferences')->insert([
            'user_id' => $this->userId, 'setting_key' => 'import.completed.email',
            'override_value' => 1, 'override_epoch' => (string) $policy['override_epoch'],
            'user_revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string,mixed> */
    private function enqueueAndDispatch(): array
    {
        NotificationOutboxProducer::enqueueImportTerminal($this->db, $this->jobId, 'COMPLETED');
        $result = (new NotificationDispatcher($this->db))->runOnce(25);
        self::assertSame(1, $result['claimed']);
        self::assertSame(1, $result['processed']);
        return $this->db->table('notification_email_deliveries')->where('event_key', 'import-job:' . $this->jobId . ':terminal')
            ->get()->getRowArray() ?? [];
    }

    private function fake(bool $shouldFail = false): NotificationEmailSender
    {
        return new class($shouldFail) implements NotificationEmailSender {
            /** @var list<array{address:string,subject:string,body:string}> */
            public array $calls = [];
            public bool $fail;
            public function __construct(bool $fail) { $this->fail = $fail; }
            public function send(string $recipientAddress, string $subject, string $body): void
            {
                if ($this->fail) { throw new RuntimeException('TEST_SMTP_FAILURE'); }
                $this->calls[] = ['address' => $recipientAddress, 'subject' => $subject, 'body' => $body];
            }
        };
    }

    public function testFailedInboxAckReturnsRetryInNestedFixture(): void
    {
        // This suite wraps every test in an OUTER transaction. CodeIgniter does
        // not physically roll back nested transactions, so asserting side-effect
        // rows here would be a false test. The committed reliability suite tests
        // real rollback of Inbox + Email receipt + Outbox ACK.
        $this->optIn();
        NotificationOutboxProducer::enqueueImportTerminal($this->db, $this->jobId, 'COMPLETED');
        $worker = new NotificationDispatcher($this->db, static function (): void {
            throw new RuntimeException('TEST_ABORT_BEFORE_ACK');
        });
        $stats = $worker->runOnce(5);
        self::assertSame(1, $stats['retry']);
    }

    public function testNoExplicitOptInNeverQueuesEmail(): void
    {
        self::assertSame([], $this->enqueueAndDispatch());
        self::assertSame(1, $this->db->table('user_notifications')->where('recipient_user_id', $this->userId)->countAllResults());
    }

    public function testExplicitOptInDeliversEmailOnceUsingFakeSender(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        self::assertSame('PENDING', $row['status'] ?? null);
        $fake = $this->fake();
        $worker = new NotificationEmailWorker($this->db, $fake);
        $result = $worker->runOnce(5);
        self::assertSame(1, $result['sent']);
        self::assertCount(1, $fake->calls);
        self::assertSame($this->emailAddress, $fake->calls[0]['address']);
        self::assertSame(0, $worker->runOnce(5)['claimed']);
        self::assertSame('SENT', $this->db->table('notification_email_deliveries')->where('id', $row['id'])->get()->getRowArray()['status']);
    }

    public function testEmailCanDeliverWhenInAppIsDisabled(): void
    {
        $this->optIn();
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.in_app')
            ->update(['default_value' => 0, 'allow_user_override' => 0]);
        $row = $this->enqueueAndDispatch();
        self::assertSame('PENDING', $row['status'] ?? null);
        self::assertSame(0, $this->db->table('user_notifications')->where('recipient_user_id', $this->userId)->countAllResults());
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['sent']);
    }

    public function testOptOutAfterEnqueueCancelsWithoutSending(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        $this->db->table('notification_user_preferences')->where('user_id', $this->userId)
            ->where('setting_key', 'import.completed.email')->update(['override_value' => 0]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
        self::assertSame('CANCELLED', $this->db->table('notification_email_deliveries')->where('id', $row['id'])->get()->getRowArray()['status']);
    }

    public function testTransportFailureRetriesThenSendsWithoutDuplicate(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        $fake = $this->fake(true);
        $worker = new NotificationEmailWorker($this->db, $fake);
        self::assertSame(1, $worker->runOnce(5)['retry']);
        $saved = $this->db->table('notification_email_deliveries')->where('id', $row['id'])->get()->getRowArray();
        self::assertSame('RETRY', $saved['status']);
        self::assertSame('M11_EMAIL_TRANSPORT_FAILED', $saved['last_error_code']);
        $fake->fail = false;
        $this->db->table('notification_email_deliveries')->where('id', $row['id'])
            ->set('available_at', 'UTC_TIMESTAMP(6)', false)->update();
        self::assertSame(1, $worker->runOnce(5)['sent']);
        self::assertCount(1, $fake->calls);
    }

    public function testSendingDisabledPausesWithoutConsumingAttempt(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        $this->flag('DINKERP_M11_EMAIL_SEND_ENABLED', 'off');
        $fake = $this->fake();
        self::assertSame(0, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['claimed']);
        $status = $this->db->table('notification_email_deliveries')->where('id', $row['id'])->get()->getRowArray();
        self::assertSame('PENDING', $status['status']);
        self::assertSame('0', (string) $status['attempts']);
        self::assertCount(0, $fake->calls);
    }

    public function testLockedUserAfterQueueCannotReceiveEmail(): void
    {
        $this->optIn();
        $this->enqueueAndDispatch();
        $this->db->table('users')->where('id', $this->userId)->update(['status' => 'locked']);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
    }

    public function testExpiredSixthLeaseIsDeadWithoutSeventhAttempt(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        $this->db->table('notification_email_deliveries')->where('id', $row['id'])
            ->set('status', 'LEASED')->set('attempts', 6)->set('lease_token', 'old-token')
            ->set('leased_until', 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)', false)->update();
        $fake = $this->fake();
        $stats = (new NotificationEmailWorker($this->db, $fake))->runOnce(5);
        self::assertSame(1, $stats['dead']);
        self::assertSame(0, $stats['claimed']);
        self::assertCount(0, $fake->calls);
        $after = $this->db->table('notification_email_deliveries')->where('id', $row['id'])->get()->getRowArray();
        self::assertSame('DEAD', $after['status']);
        self::assertSame('6', (string) $after['attempts']);
    }

    public function testLockedAdminPolicyCancelsPendingEmail(): void
    {
        $this->optIn();
        $row = $this->enqueueAndDispatch();
        $this->db->table('notification_settings')->where('setting_key', 'import.completed.email')
            ->set('allow_user_override', 0)->set('override_epoch', 'override_epoch+1', false)->update();
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertSame('CANCELLED', $this->db->table('notification_email_deliveries')
            ->where('id', $row['id'])->get()->getRowArray()['status']);
        self::assertCount(0, $fake->calls);
    }

    public function testMissingCurrentEmailIdentityCancelsWithoutSending(): void
    {
        $this->optIn();
        $this->enqueueAndDispatch();
        $this->db->table('auth_identities')->where('user_id', $this->userId)->delete();
        $fake = $this->fake();
        self::assertSame(1, (new NotificationEmailWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
    }

    /** @param array<string,mixed> $values */
    private function insert(string $table, array $values): string
    {
        if (! $this->db->table($table)->insert($values)) {
            throw new RuntimeException('M11 Email fixture insert failed: ' . $table);
        }
        return (string) $this->db->insertID();
    }
}

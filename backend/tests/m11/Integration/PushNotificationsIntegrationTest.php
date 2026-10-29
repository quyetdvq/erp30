<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationDispatcher;
use App\Modules\Notifications\NotificationOutboxProducer;
use App\Modules\Notifications\NotificationPushRules;
use App\Modules\Notifications\NotificationPushPayloadSender;
use App\Modules\Notifications\NotificationPushService;
use App\Modules\Notifications\NotificationPushWorker;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

/** Real MariaDB transaction and real Outbox services; external provider replaced by fake. */
final class PushNotificationsIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private bool $transactionOpened = false;
    private string $userId;
    private string $jobId;
    /** @var array<string,array{getenv:string|false,env:mixed,server:mixed}> */
    private array $savedFlags = [];
    /** @var array<string,mixed> */
    private array $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            if (! $this->db->transBegin()) {
                throw new RuntimeException('M11 Push fixture transaction failed');
            }
            $this->transactionOpened = true;
            foreach ([
                'DINKERP_M11_IMPORT_PRODUCER_ENABLED' => 'enabled',
                'DINKERP_NOTIFICATIONS_DELIVERY_ENABLED' => 'enabled',
                'DINKERP_M11_EMAIL_QUEUE_ENABLED' => 'off',
                'DINKERP_M11_PUSH_QUEUE_ENABLED' => 'enabled',
                'DINKERP_M11_PUSH_SEND_ENABLED' => 'enabled',
                'DINKERP_M11_PUSH_ENDPOINT_KEY_B64' => base64_encode(random_bytes(32)),
                'DINKERP_M11_PUSH_VAPID_SUBJECT' => 'mailto:push@example.test',
            ] as $name => $value) {
                $this->flag($name, $value);
            }
            // Share the TEST-ONLY P-256 key used by NotificationPushRulesTest.
            // Runtime key generation requires openssl.cnf on some Windows/XAMPP builds;
            // the fixture exercises the same real VAPID parsing without that dependency.
            // Never use this key outside PHPUnit.
            $pem = file_get_contents(__DIR__ . '/../Fixtures/vapid-p256-test-only.pem');
            if (! is_string($pem) || ! openssl_pkey_get_private($pem) instanceof \OpenSSLAsymmetricKey) {
                throw new RuntimeException('M11 Push test-only VAPID PEM missing or invalid');
            }
            $this->flag('DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64', base64_encode($pem));
            $this->fixture();
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

    private function flag(string $name, string $value): void
    {
        if (! isset($this->savedFlags[$name])) {
            $this->savedFlags[$name] = [
                'getenv' => getenv($name), 'env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null,
            ];
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function fixture(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->userId = $this->insert('users', [
            'username' => 'm11push_' . bin2hex(random_bytes(7)),
            'status' => 'ACTIVE', 'active' => 1, 'created_at' => $now,
        ]);
        $this->jobId = $this->insert('import_jobs', [
            'entity_type' => 'PRODUCT', 'mode' => 'INSERT', 'source_format' => 'CSV',
            'status' => 'COMPLETED', 'created_by' => $this->userId,
            'success_rows' => 2, 'error_rows' => 0, 'created_at' => $now,
        ]);
        foreach (['import.completed.in_app' => [1, 1], 'import.completed.push' => [0, 1]] as $key => [$default, $override]) {
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
        $this->subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/' . bin2hex(random_bytes(10)),
            'keys' => [
                'p256dh' => NotificationPushRules::encode("\x04" . random_bytes(64)),
                'auth' => NotificationPushRules::encode(random_bytes(16)),
            ],
        ];
    }

    private function user(?string $userId = null): User
    {
        $row = $this->db->table('users')->where('id', $userId ?? $this->userId)->get()->getRowArray();
        if ($row === null) { throw new RuntimeException('Push fixture user absent'); }
        return new User($row);
    }

    private function optIn(?string $userId = null): void
    {
        $userId ??= $this->userId;
        $policy = $this->db->table('notification_settings')->where('setting_key', 'import.completed.push')
            ->get()->getRowArray();
        $this->db->table('notification_user_preferences')->insert([
            'user_id' => $userId, 'setting_key' => 'import.completed.push',
            'override_value' => 1, 'override_epoch' => (string) $policy['override_epoch'],
            'user_revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function registered(): string
    {
        $this->optIn();
        return (new NotificationPushService($this->user(), $this->db))
            ->subscribe($this->subscription)['id'];
    }

    private function enqueue(): array
    {
        NotificationOutboxProducer::enqueueImportTerminal($this->db, $this->jobId, 'COMPLETED');
        $stats = (new NotificationDispatcher($this->db))->runOnce(25);
        self::assertSame(1, $stats['processed']);
        return $this->db->table('notification_push_deliveries')->where('event_key',
            'import-job:' . $this->jobId . ':terminal')->get()->getRowArray() ?? [];
    }

    private function fake(string $result = 'sent'): NotificationPushPayloadSender
    {
        return new class($result) implements NotificationPushPayloadSender {
            public array $calls = [];
            public function __construct(public string $result) {}
            public function send(string $endpoint): string
            {
                throw new RuntimeException('M11_TEST_ENCRYPTED_PAYLOAD_REQUIRED');
            }
            public function sendEncrypted(string $endpoint, string $payload, string $p256dh, string $auth): string
            {
                if ($payload === '' || $p256dh === '' || $auth === '') {
                    throw new RuntimeException('M11_TEST_MISSING_EVENT_CONTENT');
                }
                $this->calls[] = $endpoint;
                return $this->result;
            }
        };
    }

    public function testNoOptInRejectsDeviceRegistration(): void
    {
        $service = new NotificationPushService($this->user(), $this->db);
        $this->expectException(DomainException::class);
        $service->subscribe($this->subscription);
    }

    public function testOptedInDeviceGetsOneOpaquePushAfterInboxAck(): void
    {
        $id = $this->registered();
        $service = new NotificationPushService($this->user(), $this->db);
        self::assertSame($id, $service->subscribe($this->subscription)['id']);
        self::assertCount(1, $service->listing());
        $stored = $this->db->table('notification_push_subscriptions')->where('id', $id)->get()->getRowArray();
        self::assertStringNotContainsString('fcm.googleapis', $stored['endpoint_cipher']);
        self::assertArrayNotHasKey('endpoint', $service->listing()[0]);
        $receipt = $this->enqueue();
        self::assertSame('PENDING', $receipt['status'] ?? null);
        $fake = $this->fake();
        $worker = new NotificationPushWorker($this->db, $fake);
        self::assertSame(1, $worker->runOnce(5)['sent']);
        self::assertCount(1, $fake->calls);
        self::assertSame($this->subscription['endpoint'], $fake->calls[0]);
        self::assertSame(0, $worker->runOnce(5)['claimed']);
    }

    public function testLegacyDeviceIsCancelledUntilItsKeysAreRenewed(): void
    {
        $id = $this->registered();
        $receipt = $this->enqueue();
        $this->db->table('notification_push_subscriptions')->where('id', $id)
            ->update(['key_material_cipher' => null]);
        $devices = (new NotificationPushService($this->user(), $this->db))->listing();
        self::assertFalse($devices[0]['detail_ready']);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertSame([], $fake->calls);
        $saved = $this->db->table('notification_push_deliveries')
            ->where('id', $receipt['id'])->get()->getRowArray();
        self::assertSame('M11_PUSH_DEVICE_UPDATE_REQUIRED', $saved['last_error_code']);
    }

    public function testSameDeviceReregistrationPreservesExistingPendingReceipt(): void
    {
        $id = $this->registered();
        $receipt = $this->enqueue();
        self::assertSame('PENDING', $receipt['status']);
        $service = new NotificationPushService($this->user(), $this->db);
        self::assertSame($id, $service->subscribe($this->subscription)['id']);
        self::assertSame('PENDING', (string) $this->db->table('notification_push_deliveries')
            ->where('id', $receipt['id'])->get()->getRowArray()['status']);
        self::assertSame(1, (new NotificationPushWorker($this->db, $this->fake()))->runOnce(5)['sent']);
    }

    public function testOptOutAfterEnqueueCancelsWithoutSending(): void
    {
        $this->registered();
        $this->enqueue();
        $this->db->table('notification_user_preferences')->where('user_id', $this->userId)
            ->where('setting_key', 'import.completed.push')->update(['override_value' => 0]);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
    }

    public function testRevocationCancelsPendingReceipt(): void
    {
        $id = $this->registered();
        $receipt = $this->enqueue();
        self::assertTrue((new NotificationPushService($this->user(), $this->db))
            ->unsubscribe($id)['removed']);
        self::assertSame('CANCELLED', $this->db->table('notification_push_deliveries')
            ->where('id', $receipt['id'])->get()->getRowArray()['status']);
        $fake = $this->fake();
        self::assertSame(0, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['claimed']);
    }

    public function testLockedUserCannotReceiveQueuedPush(): void
    {
        $this->registered();
        $this->enqueue();
        $this->db->table('users')->where('id', $this->userId)->update(['status' => 'locked']);
        $fake = $this->fake();
        self::assertSame(1, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertCount(0, $fake->calls);
    }

    public function testNetworkRetryThenSuccessfulDelivery(): void
    {
        $this->registered();
        $receipt = $this->enqueue();
        $fake = $this->fake('retry');
        $worker = new NotificationPushWorker($this->db, $fake);
        self::assertSame(1, $worker->runOnce(5)['retry']);
        self::assertSame('RETRY', $this->db->table('notification_push_deliveries')
            ->where('id', $receipt['id'])->get()->getRowArray()['status']);
        $fake->result = 'sent';
        $this->db->table('notification_push_deliveries')->where('id', $receipt['id'])
            ->set('available_at', 'UTC_TIMESTAMP(6)', false)->update();
        self::assertSame(1, $worker->runOnce(5)['sent']);
        self::assertCount(2, $fake->calls);
    }

    public function testSendToggleOffPausesWithoutAttempts(): void
    {
        $this->registered();
        $receipt = $this->enqueue();
        $this->flag('DINKERP_M11_PUSH_SEND_ENABLED', 'off');
        $fake = $this->fake();
        self::assertSame(0, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['claimed']);
        $row = $this->db->table('notification_push_deliveries')->where('id', $receipt['id'])->get()->getRowArray();
        self::assertSame('PENDING', $row['status']);
        self::assertSame('0', (string) $row['attempts']);
    }

    public function testGoneEndpointDisablesSubscription(): void
    {
        $id = $this->registered();
        $this->enqueue();
        $fake = $this->fake('gone');
        self::assertSame(1, (new NotificationPushWorker($this->db, $fake))->runOnce(5)['cancelled']);
        self::assertSame('0', (string) $this->db->table('notification_push_subscriptions')
            ->where('id', $id)->get()->getRowArray()['enabled']);
    }

    public function testDeviceTransferredBetweenUsersCancelsOldReceipts(): void
    {
        $id = $this->registered();
        $receipt = $this->enqueue();
        $newId = $this->insert('users', [
            'username' => 'm11push_new_' . bin2hex(random_bytes(6)),
            'status' => 'ACTIVE', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->optIn($newId);
        $transferred = (new NotificationPushService($this->user($newId), $this->db))
            ->subscribe($this->subscription);
        self::assertSame($id, $transferred['id']);
        self::assertSame('CANCELLED', $this->db->table('notification_push_deliveries')
            ->where('id', $receipt['id'])->get()->getRowArray()['status']);
        self::assertSame($newId, (string) $this->db->table('notification_push_subscriptions')
            ->where('id', $id)->get()->getRowArray()['user_id']);
    }

    public function testQueueDisabledDoesNotCreatePushReceipt(): void
    {
        $this->registered();
        $this->flag('DINKERP_M11_PUSH_QUEUE_ENABLED', 'off');
        self::assertSame([], $this->enqueue());
    }

    private function insert(string $table, array $values): string
    {
        if (! $this->db->table($table)->insert($values)) {
            throw new RuntimeException('M11 Push fixture INSERT failed: ' . $table);
        }
        return (string) $this->db->insertID();
    }
}

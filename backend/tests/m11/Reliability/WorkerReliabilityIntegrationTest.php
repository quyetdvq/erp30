<?php

declare(strict_types=1);

namespace Tests\M11\Reliability;

use App\Modules\Notifications\NotificationDispatcher;
use App\Modules\Notifications\NotificationPermanentFailure;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\CommittedReliabilityFixture;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';
require_once __DIR__ . '/../Support/CommittedReliabilityFixture.php';

/**
 * M11.3A gated reliability tests. Unlike M11.2's nested rollback fixtures,
 * these tests COMMIT short-lived rows so two OS processes can see them.
 * Cleanup is precise by generated ID/event_key; no DDL, TRUNCATE or refresh.
 * Only execute serially against database.tests=erp_test.
 */
final class WorkerReliabilityIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private CommittedReliabilityFixture $fixture;
    private bool $lockTransaction = false;
    private ?string $probeDir = null;
    /** @var list<resource> */
    private array $processes = [];
    /** @var array<string,array{getenv:string|false,env:mixed,server:mixed}> */
    private array $savedFlags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->db->query('SET SESSION innodb_lock_wait_timeout=4');
        $lock = $this->db->query("SELECT GET_LOCK('dinkerp_m11_reliability',0) AS acquired")->getRowArray();
        if ((string) ($lock['acquired'] ?? '0') !== '1') {
            $this->db->close();
            throw new RuntimeException('M11R already running. Run the reliability suite serially.');
        }
        $this->fixture = new CommittedReliabilityFixture($this->db);
        $this->flag('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', 'enabled');
        // M11.3B is independent: this legacy In-App worker test must not enqueue SMTP receipts.
        $this->flag('DINKERP_M11_EMAIL_QUEUE_ENABLED', 'off');
    }

    protected function tearDown(): void
    {
        try {
            // Stop workers before deleting committed rows they may have locked.
            $this->stopWorkers();
            if ($this->lockTransaction) {
                $this->db->transRollback();
                $this->lockTransaction = false;
            }
            $this->fixture->cleanup();
        } finally {
            if (isset($this->db)) {
                $this->db->query("SELECT RELEASE_LOCK('dinkerp_m11_reliability')");
                $this->db->close();
            }
            foreach ($this->savedFlags as $key => $previous) {
                if ($previous['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $previous['getenv']);
                }
                if ($previous['env'] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $previous['env'];
                }
                if ($previous['server'] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $previous['server'];
                }
            }
            $this->savedFlags = [];
            if ($this->probeDir !== null && is_dir($this->probeDir)) {
                foreach (glob($this->probeDir . '/*') ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($this->probeDir);
                $this->probeDir = null;
            }
            parent::tearDown();
        }
    }

    public function testEmailReceiptAndInboxPhysicallyRollBackBeforeAck(): void
    {
        // The fixture is committed; the worker owns a REAL top-level InnoDB
        // transaction. Verify inserts happened before fault, then vanished.
        $event = $this->fixture->validImportEvent();
        $this->fixture->optInEmail($event['user_id']);
        $this->flag('DINKERP_M11_IMPORT_PRODUCER_ENABLED', 'enabled');
        $this->flag('DINKERP_M11_EMAIL_QUEUE_ENABLED', 'enabled');
        $db = $this->db;
        $key = $event['key'];
        $observedBothInTransaction = false;
        $worker = new NotificationDispatcher($db, static function () use ($db, $key, &$observedBothInTransaction): void {
            self::assertSame(1, $db->table('notification_email_deliveries')
                ->where('event_key', $key)->countAllResults(), 'Email receipt must be inserted before the fault');
            self::assertSame(1, $db->table('user_notifications')
                ->where('event_key', $key)->countAllResults(), 'Inbox must be inserted before the fault');
            $observedBothInTransaction = true;
            throw new RuntimeException('TEST_ABORT_AFTER_EMAIL_AND_INBOX_INSERT');
        });
        $stats = $worker->runOnce(1);
        self::assertTrue($observedBothInTransaction, 'Fault hook must run after BOTH inserts');
        self::assertSame(1, $stats['claimed']);
        self::assertSame(1, $stats['retry']);
        self::assertSame('RETRY', $this->state($event['id'])['status']);
        self::assertSame(0, $this->inboxCount($event['key']));
        self::assertSame(0, $this->db->table('notification_email_deliveries')
            ->where('event_key', $event['key'])->countAllResults());
    }

    public function testTwoIndependentProcessesClaimOneEventExactlyOnce(): void
    {
        if (! function_exists('proc_open')) {
            self::fail('M11R requires proc_open to test two independent workers; no mock fallback.');
        }
        $event = $this->fixture->poisonEvent();
        $this->probeDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'm11r-' . bin2hex(random_bytes(8));
        if (! mkdir($this->probeDir, 0700) && ! is_dir($this->probeDir)) {
            throw new RuntimeException('Cannot create M11R private test barrier directory');
        }
        // Lock the real InnoDB event before releasing both child processes.
        // The workers compete for exactly one PENDING row and must serialize.
        self::assertTrue($this->db->transBegin());
        $this->lockTransaction = true;
        $prefix = $this->outboxName();
        $locked = $this->db->query("SELECT id FROM {$prefix} WHERE id=? FOR UPDATE", [$event['id']])->getRowArray();
        self::assertSame($event['id'], (string) ($locked['id'] ?? ''));
        $start = $this->probeDir . DIRECTORY_SEPARATOR . 'start';
        $reports = [];
        $entered = [];
        $errorPaths = [];
        for ($i = 1; $i <= 2; ++$i) {
            $ready = $this->probeDir . DIRECTORY_SEPARATOR . "ready{$i}";
            $report = $this->probeDir . DIRECTORY_SEPARATOR . "report{$i}.json";
            $output = $this->probeDir . DIRECTORY_SEPARATOR . "out{$i}.txt";
            $error = $this->probeDir . DIRECTORY_SEPARATOR . "err{$i}.txt";
            $cmd = [PHP_BINARY, __DIR__ . '/../Support/worker_reliability_process.php', $ready, $start, $report];
            $process = proc_open($cmd, [
                0 => ['pipe', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $error, 'w'],
            ], $pipes, dirname(__DIR__, 3));
            if (! is_resource($process)) {
                throw new RuntimeException('Cannot start separate M11R PHP worker');
            }
            fclose($pipes[0]);
            $this->processes[] = $process;
            $reports[] = $report;
            $entered[] = $ready . '.entered';
            $errorPaths[] = $error;
            $this->waitFor($ready, 'worker did not reach READY barrier', $error);
        }
        if (file_put_contents($start, 'go') === false) {
            throw new RuntimeException('Cannot release M11R worker barrier');
        }
        foreach ($entered as $i => $path) {
            $this->waitFor($path, 'worker did not enter the claim phase', $errorPaths[$i]);
        }
        usleep(100000); // both entered SELECT ... FOR UPDATE before unlocking
        self::assertTrue($this->db->transCommit());
        $this->lockTransaction = false;

        $observed = [];
        foreach ($reports as $i => $path) {
            $this->waitFor($path, 'worker did not produce result', $errorPaths[$i]);
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($value['ok'] ?? false, 'Child worker failure: ' . ($value['error'] ?? 'unknown'));
            $observed[] = $value['stats'];
        }
        $this->stopWorkers();
        self::assertSame(1, array_sum(array_column($observed, 'claimed')), 'Only one worker may claim the event');
        self::assertSame(1, array_sum(array_column($observed, 'dead')), 'Only one worker may terminally process the poisoned event');
        self::assertSame(0, array_sum(array_column($observed, 'lost_lease')));
        self::assertSame('DEAD', $this->state($event['id'])['status']);
        self::assertSame('1', (string) $this->state($event['id'])['attempts']);
        self::assertSame('NOTIFICATION_INVALID_EVENT', $this->state($event['id'])['last_error_code']);
        self::assertSame(0, $this->inboxCount($event['key']));
        self::assertSame(0, (new NotificationDispatcher($this->db))->runOnce(1)['claimed']);
    }

    public function testExpiredLeaseIsRecoveredAndOldTokenIsFenced(): void
    {
        $event = $this->fixture->poisonEvent();
        $oldToken = '11111111-1111-4111-8111-111111111111';
        $this->markExpired($event['id'], $oldToken, 1);
        $worker = new NotificationDispatcher($this->db);
        $stats = $worker->runOnce(1);
        self::assertSame(1, $stats['claimed']);
        self::assertSame(1, $stats['dead']);
        $row = $this->state($event['id']);
        self::assertSame('DEAD', $row['status']);
        self::assertSame('2', (string) $row['attempts']);
        self::assertNull($row['lease_token']);
        self::assertNotNull($row['processed_at']);
        // Simulates a stale worker's conditional ACK: old token cannot write.
        $this->db->query("UPDATE {$this->outboxName()} SET status='PROCESSED'"
            . " WHERE id=? AND status='LEASED' AND lease_token=?", [$event['id'], $oldToken]);
        self::assertSame(0, $this->db->affectedRows());
        self::assertSame('DEAD', $this->state($event['id'])['status']);
        self::assertSame(0, $this->inboxCount($event['key']));
    }

    public function testExpiredSixthAttemptIsReapedWithoutSeventhDelivery(): void
    {
        $event = $this->fixture->poisonEvent();
        $this->markExpired($event['id'], '22222222-2222-4222-8222-222222222222', 6);
        $stats = (new NotificationDispatcher($this->db))->runOnce(1);
        self::assertSame(0, $stats['claimed']);
        self::assertSame(1, $stats['dead']);
        $row = $this->state($event['id']);
        self::assertSame('DEAD', $row['status']);
        self::assertSame('6', (string) $row['attempts']);
        self::assertSame('NOTIFICATION_LEASE_EXHAUSTED', $row['last_error_code']);
        self::assertSame(0, $this->inboxCount($event['key']));
    }

    public function testDeliveryOffPausesWithoutConsumingAnAttempt(): void
    {
        $event = $this->fixture->poisonEvent();
        $this->flag('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', 'off');
        $stats = (new NotificationDispatcher($this->db))->runOnce(1);
        self::assertSame(0, $stats['claimed']);
        self::assertSame('PENDING', $this->state($event['id'])['status']);
        self::assertSame('0', (string) $this->state($event['id'])['attempts']);
        $this->flag('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', 'enabled');
        self::assertSame(1, (new NotificationDispatcher($this->db))->runOnce(1)['dead']);
    }

    public function testFaultAfterInboxInsertRollsBackThenRetriesWithoutDuplicate(): void
    {
        $event = $this->fixture->validImportEvent();
        $calls = 0;
        $faulty = new NotificationDispatcher($this->db, static function (string $id) use (&$calls, $event): void {
            if ($id !== $event['id']) {
                throw new RuntimeException('Injected failure targeted another event');
            }
            ++$calls;
            throw new RuntimeException('M11R_FAULT_AFTER_INBOX');
        });
        $first = $faulty->runOnce(1);
        self::assertSame(1, $calls, 'Failure must be injected AFTER successful Inbox write');
        self::assertSame(1, $first['retry']);
        self::assertSame(0, $this->inboxCount($event['key']), 'Inbox insert must roll back on failure before ACK');
        $row = $this->state($event['id']);
        self::assertSame('RETRY', $row['status']);
        self::assertSame('1', (string) $row['attempts']);
        self::assertSame('NOTIFICATION_DISPATCH_FAILED', $row['last_error_code']);
        $future = $this->db->query("SELECT (available_at > UTC_TIMESTAMP(6)) AS future"
            . " FROM {$this->outboxName()} WHERE id=?", [$event['id']])->getRowArray();
        self::assertSame('1', (string) ($future['future'] ?? '0'), 'Retry must use database-clock backoff');
        $this->db->query("UPDATE {$this->outboxName()} SET available_at=UTC_TIMESTAMP(6)"
            . " WHERE id=? AND status='RETRY'", [$event['id']]);
        $normal = new NotificationDispatcher($this->db);
        self::assertSame(1, $normal->runOnce(1)['processed']);
        self::assertSame('PROCESSED', $this->state($event['id'])['status']);
        self::assertSame('2', (string) $this->state($event['id'])['attempts']);
        self::assertSame(1, $this->inboxCount($event['key']));
        self::assertSame(0, $normal->runOnce(1)['claimed']);
        self::assertSame(1, $this->inboxCount($event['key']));
    }

    public function testPermanentFailureAfterInboxInsertRollsBackAndDeadLetters(): void
    {
        $event = $this->fixture->validImportEvent();
        $faulty = new NotificationDispatcher($this->db, static function (string $_id): void {
            throw new NotificationPermanentFailure('M11R_INJECTED_PERMANENT');
        });
        $stats = $faulty->runOnce(1);
        self::assertSame(1, $stats['dead']);
        self::assertSame('DEAD', $this->state($event['id'])['status']);
        self::assertSame('M11R_INJECTED_PERMANENT', $this->state($event['id'])['last_error_code']);
        self::assertSame(0, $this->inboxCount($event['key']));
    }

    private function markExpired(string $id, string $token, int $attempts): void
    {
        $this->db->query("UPDATE {$this->outboxName()} SET status='LEASED',attempts=?,lease_token=?,"
            . 'leased_until=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 60 SECOND)'
            . " WHERE id=? AND status='PENDING'", [$attempts, $token, $id]);
        self::assertSame(1, $this->db->affectedRows());
    }

    /** @return array<string,mixed> */
    private function state(string $id): array
    {
        $row = $this->db->table('notification_outbox')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Reliability fixture event disappeared');
        }
        return $row;
    }

    private function inboxCount(string $key): int
    {
        return $this->db->table('user_notifications')->where('event_key', $key)->countAllResults();
    }

    private function outboxName(): string
    {
        return '`' . $this->db->prefixTable('notification_outbox') . '`';
    }

    private function flag(string $name, string $value): void
    {
        if (! array_key_exists($name, $this->savedFlags)) {
            $this->savedFlags[$name] = [
                'getenv' => getenv($name), 'env' => $_ENV[$name] ?? null,
                'server' => $_SERVER[$name] ?? null,
            ];
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        self::assertSame($value, (string) env($name, 'off'));
    }

    private function waitFor(string $path, string $message, ?string $errorPath = null): void
    {
        $limit = microtime(true) + 15.0;
        while (! is_file($path) && microtime(true) < $limit) {
            usleep(20000);
        }
        if (! is_file($path)) {
            $stderr = $errorPath !== null && is_file($errorPath)
                ? substr((string) file_get_contents($errorPath), 0, 1500) : '';
            self::fail($message . ($stderr === '' ? '' : ' [stderr=' . $stderr . ']'));
        }
    }

    private function stopWorkers(): void
    {
        foreach ($this->processes as $process) {
            if (! is_resource($process)) {
                continue;
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        $this->processes = [];
    }
}

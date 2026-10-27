<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use App\Modules\Notifications\Events\NotificationEventHandlerFactory;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use Closure;
use RuntimeException;
use Throwable;

/** M11.1B database-only worker. MariaDB 10.4: SELECT FOR UPDATE; never SKIP LOCKED. */
final class NotificationDispatcher
{
    private const LEASE_SECONDS = 120;
    private readonly string $outbox;

    /** Fault injection is strictly test-only; never expose it via flags or HTTP. */
    public function __construct(private readonly BaseConnection $db, private readonly ?Closure $testingBeforeAck = null)
    {
        if ($testingBeforeAck !== null && (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing')) {
            throw new RuntimeException('M11_TEST_HOOK_FORBIDDEN');
        }
        $this->outbox = $this->table('notification_outbox');
    }

    /** @return array{claimed:int,processed:int,suppressed:int,retry:int,dead:int,paused:int,lost_lease:int} */
    public function runOnce(int $batch = 25): array
    {
        if ($batch < 1 || $batch > 100) {
            throw new RuntimeException('M11_INVALID_BATCH');
        }
        $stats = array_fill_keys(['claimed','processed','suppressed','retry','dead','paused','lost_lease'], 0);
        if (! NotificationFlags::deliveryEnabled($this->db)) {
            return $stats;
        }
        $token = self::token();
        // Claim and commit BEFORE resolving recipients or delivering any event.
        $stats['dead'] += $this->reapExpiredExhausted($batch);
        $ids = $this->claim($batch, $token);
        $stats['claimed'] = count($ids);
        foreach ($ids as $id) {
            $outcome = $this->deliver($id, $token);
            $stats[$outcome]++;
        }
        return $stats;
    }

    /** A crashed sixth attempt must not remain permanently LEASED/reclaim forever. */
    private function reapExpiredExhausted(int $batch): int
    {
        $this->db->transBegin();
        try {
            $ids = $this->selectClaimIds(
                "status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) AND attempts>=6", $batch,
            );
            if ($ids !== []) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $this->exec("UPDATE {$this->outbox} SET status='DEAD',lease_token=NULL,leased_until=NULL,"
                    . "last_error_code='NOTIFICATION_LEASE_EXHAUSTED',processed_at=UTC_TIMESTAMP(6)"
                    . " WHERE id IN ({$in}) AND status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) AND attempts>=6", $ids);
                if ($this->db->affectedRows() !== count($ids)) {
                    throw new RuntimeException('M11_OUTBOX_REAP_RACE');
                }
            }
            $this->commit();
            return count($ids);
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return list<string> */
    private function claim(int $batch, string $token): array
    {
        $this->db->transBegin();
        try {
            $where = "status IN ('PENDING','RETRY') AND available_at <= UTC_TIMESTAMP(6)";
            $ids = $this->selectClaimIds($where, $batch);
            $expired = false;
            if ($ids === []) {
                $where = "status='LEASED' AND leased_until <= UTC_TIMESTAMP(6) AND attempts<6";
                $ids = $this->selectClaimIds($where, $batch);
                $expired = true;
            }
            if ($ids !== []) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $guard = $expired
                    ? "status='LEASED' AND leased_until <= UTC_TIMESTAMP(6) AND attempts<6"
                    : "status IN ('PENDING','RETRY') AND available_at <= UTC_TIMESTAMP(6)";
                $this->exec("UPDATE {$this->outbox} SET status='LEASED',lease_token=?,"
                    . 'leased_until=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL ' . self::LEASE_SECONDS . ' SECOND),'
                    . "attempts=attempts+1,last_error_code=NULL WHERE id IN ({$in}) AND {$guard}",
                    [$token, ...$ids]);
                if ($this->db->affectedRows() !== count($ids)) {
                    throw new RuntimeException('M11_OUTBOX_CLAIM_RACE');
                }
            }
            $this->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return list<string> */
    private function selectClaimIds(string $where, int $batch): array
    {
        // $batch is range checked, not user supplied SQL. Lock the exact selected IDs.
        $rows = $this->select("SELECT id FROM {$this->outbox} WHERE {$where} "
            . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
        return array_map(static fn (array $r): string => (string) $r['id'], $rows);
    }

    private function deliver(string $id, string $token): string
    {
        $this->db->transBegin();
        try {
            $rows = $this->select("SELECT * FROM {$this->outbox} WHERE id=? FOR UPDATE", [$id])->getRowArray();
            if ($rows === null || $rows['status'] !== 'LEASED' || $rows['lease_token'] !== $token
                || ! $this->ownsLease($id, $token)) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            // Toggle OFF is a PAUSE, never a terminal suppression. Return it to the
            // queue without consuming an attempt, even if the flag changed post-claim.
            if (! NotificationFlags::deliveryEnabled($this->db)) {
                $this->releasePaused($id, $token);
                $this->commit();
                return 'paused';
            }
            // A source alone is never sufficient. Unknown/mismatched pairs are
            // permanent invalid events, not an implicit fallback to Import.
            $definition = NotificationEventRegistry::find(
                (string) $rows['event_type'], (string) $rows['source_type'],
            );
            if ($definition === null) {
                throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
            }
            // Handler receives this exact connection; its writes and the ACK
            // remain inside this transaction and the existing lease fence.
            $handler = NotificationEventHandlerFactory::resolve($definition, $this->db);
            $result = $handler->deliver($rows);
            // Multi-recipient fanout and ACK share a transaction. Do not commit
            // inserts from a worker whose lease expired during PBAC evaluation.
            if (! $this->ownsLease($id, $token)) {
                throw new RuntimeException('M11_OUTBOX_LEASE_EXPIRED_AT_ACK');
            }
            // Test-only seam verifies rollback of the Inbox insert + ACK when
            // a failure occurs *after* materializing a notification.
            if ($this->testingBeforeAck !== null) {
                ($this->testingBeforeAck)($id);
            }
            $this->ack($id, $token, $result->status, $result->reason);
            $this->commit();
            return strtolower($result->status);
        } catch (NotificationPermanentFailure $e) {
            $this->db->transRollback();
            return $this->fail($id, $token, $e->reason, true);
        } catch (Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'M11 dispatcher event={id} failure: {error}', [
                'id' => $id, 'error' => get_class($e),
            ]);
            return $this->fail($id, $token, 'NOTIFICATION_DISPATCH_FAILED', false);
        }
    }

    private function ownsLease(string $id, string $token): bool
    {
        $row = $this->select("SELECT (leased_until>UTC_TIMESTAMP(6)) AS valid FROM {$this->outbox} WHERE id=? AND lease_token=?", [$id, $token])->getRowArray();
        return (string) ($row['valid'] ?? '0') === '1';
    }

    private function releasePaused(string $id, string $token): void
    {
        $this->exec("UPDATE {$this->outbox} SET status='PENDING',attempts=GREATEST(attempts-1,0),"
            . 'lease_token=NULL,leased_until=NULL WHERE id=? AND lease_token=? AND status=\'LEASED\'', [$id, $token]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('M11_PAUSE_LOST_LEASE');
        }
    }

    private function ack(string $id, string $token, string $status, ?string $error): void
    {
        $this->exec("UPDATE {$this->outbox} SET status=?,lease_token=NULL,leased_until=NULL,"
            . 'last_error_code=?,processed_at=UTC_TIMESTAMP(6) WHERE id=? AND status=\'LEASED\' AND lease_token=?',
            [$status, $error, $id, $token]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('M11_OUTBOX_ACK_LOST_LEASE');
        }
    }

    private function fail(string $id, string $token, string $code, bool $permanent): string
    {
        $this->db->transBegin();
        try {
            $row = $this->select("SELECT attempts FROM {$this->outbox} WHERE id=? AND status='LEASED' "
                . 'AND lease_token=? AND leased_until>UTC_TIMESTAMP(6) FOR UPDATE', [$id, $token])->getRowArray();
            if ($row === null) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            $retry = NotificationDeliveryRules::afterFailure((int) $row['attempts']);
            $status = $permanent ? 'DEAD' : $retry['status'];
            $minutes = $permanent ? 0 : $retry['minutes'];
            // Delay is determined by the previously validated numeric retry table.
            $this->exec("UPDATE {$this->outbox} SET status=?,lease_token=NULL,leased_until=NULL,"
                . "available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL {$minutes} MINUTE),"
                . "processed_at=IF(?='DEAD',UTC_TIMESTAMP(6),NULL),last_error_code=?"
                . " WHERE id=? AND status='LEASED' AND lease_token=?",
                [$status, $status, $code, $id, $token]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('M11_OUTBOX_RETRY_LOST_LEASE');
            }
            $this->commit();
            return strtolower($status);
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function table(string $name): string
    {
        $physical = $this->db->prefixTable($name);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_INVALID_TABLE_PREFIX');
        }
        return '`' . $physical . '`';
    }

    /** @param list<mixed> $params */
    private function select(string $sql, array $params = []): object
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new RuntimeException('M11_OUTBOX_DB_READ_FAILED');
        }
        return $result;
    }

    /** @param list<mixed> $params */
    private function exec(string $sql, array $params): void
    {
        if ($this->db->query($sql, $params) === false) {
            throw new RuntimeException('M11_OUTBOX_DB_WRITE_FAILED');
        }
    }

    private function commit(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new RuntimeException('M11_OUTBOX_COMMIT_FAILED');
        }
    }

    private static function token(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Dedicated SMTP channel, after the Inbox/Outbox transaction commits.
 * MariaDB 10.4: bounded SELECT FOR UPDATE claims. SMTP is always outside DB transactions.
 * SMTP accepted + ACK lost is at-least-once, NOT exactly-once external delivery.
 */
final class NotificationEmailWorker
{
    private const LEASE_SECONDS = 120;
    private readonly string $queue;
    private readonly string $users;
    private readonly string $settings;
    private readonly string $preferences;
    private readonly string $identities;
    private ?NotificationEmailSender $sender;

    public function __construct(private readonly BaseConnection $db, ?NotificationEmailSender $sender = null)
    {
        if ($sender !== null && (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing')) {
            throw new RuntimeException('M11_EMAIL_FAKE_SENDER_FORBIDDEN');
        }
        $this->sender = $sender;
        $this->queue = $this->name('notification_email_deliveries');
        $this->users = $this->name('users');
        $this->settings = $this->name('notification_settings');
        $this->preferences = $this->name('notification_user_preferences');
        $this->identities = $this->name('auth_identities');
    }

    /** @return array{claimed:int,sent:int,retry:int,dead:int,cancelled:int,paused:int,lost_lease:int} */
    public function runOnce(int $batch = 25): array
    {
        if ($batch < 1 || $batch > 100) {
            throw new RuntimeException('M11_EMAIL_INVALID_BATCH');
        }
        $stats = array_fill_keys(['claimed','sent','retry','dead','cancelled','paused','lost_lease'], 0);
        if (! NotificationFlags::emailSendEnabled($this->db)) {
            return $stats; // OFF = pause, never consume attempt or cancel queued consent.
        }
        // Preflight BEFORE claiming: absent/misconfigured SMTP must not exhaust retries.
        $this->sender ??= new NotificationSmtpSender();
        $token = bin2hex(random_bytes(16));
        $stats['dead'] += $this->reap($batch);
        $ids = $this->claim($batch, $token);
        $stats['claimed'] = count($ids);
        foreach ($ids as $id) {
            $stats[$this->deliver($id, $token)]++;
        }
        return $stats;
    }

    private function reap(int $batch): int
    {
        $this->db->transBegin();
        try {
            $rows = $this->query("SELECT id FROM {$this->queue} WHERE ((status='LEASED' "
                . "AND leased_until<=UTC_TIMESTAMP(6)) OR status IN ('PENDING','RETRY')) "
                . 'AND attempts>=6 '
                . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            foreach ($rows as $row) {
                $this->execute("UPDATE {$this->queue} SET status='DEAD',lease_token=NULL,leased_until=NULL,"
                    . "last_error_code='M11_EMAIL_LEASE_EXHAUSTED' WHERE id=?", [(string) $row['id']]);
            }
            $this->commit();
            return count($rows);
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
            $condition = "status IN ('PENDING','RETRY') AND attempts<6 AND available_at<=UTC_TIMESTAMP(6)";
            $rows = $this->query("SELECT id FROM {$this->queue} WHERE {$condition} "
                . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            if ($rows === []) {
                $condition = "status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) AND attempts<6";
                $rows = $this->query("SELECT id FROM {$this->queue} WHERE {$condition} "
                    . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            }
            $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
            if ($ids !== []) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $this->execute("UPDATE {$this->queue} SET status='LEASED',lease_token=?,"
                    . 'leased_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ' . self::LEASE_SECONDS . ' SECOND),'
                    . "attempts=attempts+1,last_error_code=NULL WHERE id IN ({$in}) AND {$condition}", [$token, ...$ids]);
                if ($this->db->affectedRows() !== count($ids)) {
                    throw new RuntimeException('M11_EMAIL_CLAIM_RACE');
                }
            }
            $this->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function deliver(string $id, string $token): string
    {
        $this->db->transBegin();
        try {
            $row = $this->query("SELECT * FROM {$this->queue} WHERE id=? FOR UPDATE", [$id])->getRowArray();
            if ($row === null || $row['status'] !== 'LEASED' || $row['lease_token'] !== $token
                || ! $this->ownsLease($id, $token)) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            if (! NotificationFlags::emailSendEnabled($this->db)) {
                $this->execute("UPDATE {$this->queue} SET status='PENDING',attempts=GREATEST(attempts-1,0),"
                    . "lease_token=NULL,leased_until=NULL WHERE id=? AND lease_token=?", [$id, $token]);
                $this->commit();
                return 'paused';
            }
            $userId = (string) $row['recipient_user_id'];
            $user = $this->query("SELECT id,active,status,deleted_at FROM {$this->users} WHERE id=?", [$userId])->getRowArray();
            if ($user === null || ! WorkflowNotificationRules::activeUser($user)) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_EMAIL_USER_INACTIVE');
            }
            $emailKey = NotificationEmailRules::policyKeyForEvent((string) $row['event_type']);
            if ($emailKey === null) {
                return $this->terminal($id, $token, 'DEAD', 'M11_EMAIL_UNSUPPORTED_EVENT');
            }
            $policy = $this->query("SELECT s.*,p.override_value AS preference_value,"
                . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision '
                . "FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
                . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
                [$userId, $emailKey])->getRowArray();
            if ($policy === null) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_EMAIL_POLICY_MISSING');
            }
            $pref = $policy['preference_revision'] === null ? null : [
                'override_value' => $policy['preference_value'],
                'override_epoch' => $policy['preference_epoch'],
                'user_revision' => $policy['preference_revision'],
            ];
            // Queue toggle stops NEW receipts, not previously committed deliveries.
            if (! NotificationEmailRules::consentedForPolicyKey($emailKey, $policy, $pref, false)) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_EMAIL_CONSENT_REVOKED');
            }
            $identities = $this->query("SELECT secret FROM {$this->identities} WHERE user_id=? "
                . "AND type='email_password' LIMIT 2", [$userId])->getResultArray();
            $address = count($identities) === 1
                ? NotificationEmailRules::address($identities[0]['secret'] ?? null) : null;
            if ($address === null) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_EMAIL_IDENTITY_UNAVAILABLE');
            }
            try {
                $content = NotificationEmailRules::content((string) $row['event_type'], (string) $row['params_json']);
            } catch (\InvalidArgumentException $e) {
                return $this->terminal($id, $token, 'DEAD', 'M11_EMAIL_PAYLOAD_INVALID');
            }
            // Release all DB locks BEFORE calling external SMTP.
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            return $this->fail($id, $token);
        }
        if (! NotificationFlags::emailSendEnabled($this->db)) {
            return $this->pauseAfterClaim($id, $token);
        }
        try {
            $this->sender->send($address, $content['subject'], $content['body']);
        } catch (Throwable $e) {
            // No transport exception details/recipient address are persisted or logged.
            return $this->fail($id, $token);
        }
        $this->db->transBegin();
        try {
            $this->execute("UPDATE {$this->queue} SET status='SENT',sent_at=UTC_TIMESTAMP(6),"
                . "lease_token=NULL,leased_until=NULL,last_error_code=NULL"
                . " WHERE id=? AND status='LEASED' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6)", [$id, $token]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();
                return 'lost_lease'; // SMTP accepted but DB ACK lost: at-least-once, possible duplicate.
            }
            $this->commit();
            return 'sent';
        } catch (Throwable $e) {
            $this->db->transRollback();
            return 'lost_lease'; // Never automatically resend inside this call after SMTP succeeded.
        }
    }

    /** Called with an open transaction; terminal cancellations never send SMTP. */
    private function terminal(string $id, string $token, string $status, string $reason): string
    {
        $this->execute("UPDATE {$this->queue} SET status=?,lease_token=NULL,leased_until=NULL,"
            . 'last_error_code=? WHERE id=? AND status=\'LEASED\' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6)',
            [$status, $reason, $id, $token]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('M11_EMAIL_TERMINAL_LOST_LEASE');
        }
        $this->commit();
        return strtolower($status);
    }

    private function pauseAfterClaim(string $id, string $token): string
    {
        $this->db->transBegin();
        try {
            $this->execute("UPDATE {$this->queue} SET status='PENDING',attempts=GREATEST(attempts-1,0),"
                . "lease_token=NULL,leased_until=NULL WHERE id=? AND status='LEASED'"
                . ' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6)', [$id, $token]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            $this->commit();
            return 'paused';
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function fail(string $id, string $token): string
    {
        $this->db->transBegin();
        try {
            $row = $this->query("SELECT attempts FROM {$this->queue} WHERE id=? AND status='LEASED'"
                . ' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6) FOR UPDATE', [$id, $token])->getRowArray();
            if ($row === null) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            $decision = NotificationDeliveryRules::afterFailure((int) $row['attempts']);
            $minutes = $decision['minutes'];
            $status = $decision['status'];
            $this->execute("UPDATE {$this->queue} SET status=?,lease_token=NULL,leased_until=NULL,"
                . "available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL {$minutes} MINUTE),"
                . "last_error_code='M11_EMAIL_TRANSPORT_FAILED' WHERE id=? AND status='LEASED' AND lease_token=?",
                [$status, $id, $token]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('M11_EMAIL_RETRY_LOST_LEASE');
            }
            $this->commit();
            return strtolower($status);
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function ownsLease(string $id, string $token): bool
    {
        $row = $this->query("SELECT (leased_until>UTC_TIMESTAMP(6)) AS valid FROM {$this->queue} "
            . 'WHERE id=? AND lease_token=?', [$id, $token])->getRowArray();
        return (string) ($row['valid'] ?? '0') === '1';
    }

    private function name(string $logical): string
    {
        $name = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
            throw new RuntimeException('M11_EMAIL_INVALID_TABLE');
        }
        return '`' . $name . '`';
    }

    /** @param list<mixed> $params */
    private function query(string $sql, array $params = []): object
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new RuntimeException('M11_EMAIL_DB_READ_FAILED');
        }
        return $result;
    }

    /** @param list<mixed> $params */
    private function execute(string $sql, array $params): void
    {
        if ($this->db->query($sql, $params) === false) {
            throw new RuntimeException('M11_EMAIL_DB_WRITE_FAILED');
        }
    }

    private function commit(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new RuntimeException('M11_EMAIL_DB_COMMIT_FAILED');
        }
    }
}

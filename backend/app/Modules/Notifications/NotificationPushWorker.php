<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Separate at-least-once Web Push sender: never makes an outbound call under DB locks. */
final class NotificationPushWorker
{
    private const LEASE_SECONDS = 120;
    private readonly string $queue;
    private readonly string $subscriptions;
    private readonly string $users;
    private readonly string $settings;
    private readonly string $preferences;
    private readonly string $outbox;
    private readonly string $jobs;
    private ?NotificationPushSender $sender;

    public function __construct(private readonly BaseConnection $db, ?NotificationPushSender $sender = null)
    {
        if ($sender !== null && (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing')) {
            throw new RuntimeException('M11_PUSH_FAKE_SENDER_FORBIDDEN');
        }
        $this->sender = $sender;
        $this->queue = $this->table('notification_push_deliveries');
        $this->subscriptions = $this->table('notification_push_subscriptions');
        $this->users = $this->table('users');
        $this->settings = $this->table('notification_settings');
        $this->preferences = $this->table('notification_user_preferences');
        $this->outbox = $this->table('notification_outbox');
        $this->jobs = $this->table('import_jobs');
    }

    /** @return array{claimed:int,sent:int,retry:int,dead:int,cancelled:int,paused:int,lost_lease:int} */
    public function runOnce(int $batch = 25): array
    {
        if ($batch < 1 || $batch > 100) {
            throw new RuntimeException('M11_PUSH_INVALID_BATCH');
        }
        $stats = array_fill_keys(['claimed','sent','retry','dead','cancelled','paused','lost_lease'], 0);
        if (! NotificationFlags::pushSendEnabled($this->db)) {
            return $stats;
        }
        $key = NotificationPushCrypto::key(); // Fail BEFORE claiming when secrets are absent.
        if ($this->sender === null && ! function_exists('curl_init')) {
            throw new RuntimeException('M11_PUSH_CURL_EXTENSION_REQUIRED');
        }
        $this->sender ??= new NotificationVapidSender();
        $token = bin2hex(random_bytes(16));
        $stats['dead'] += $this->reap($batch);
        $ids = $this->claim($batch, $token);
        $stats['claimed'] = count($ids);
        foreach ($ids as $id) {
            ++$stats[$this->deliver($id, $token, $key)];
        }
        return $stats;
    }

    private function reap(int $batch): int
    {
        $this->db->transBegin();
        try {
            $rows = $this->select("SELECT id FROM {$this->queue} WHERE ((status='LEASED' "
                . "AND leased_until<=UTC_TIMESTAMP(6)) OR status IN ('PENDING','RETRY')) "
                . "AND attempts>=6 ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            foreach ($rows as $row) {
                $this->exec("UPDATE {$this->queue} SET status='DEAD',lease_token=NULL,leased_until=NULL,"
                    . "last_error_code='M11_PUSH_LEASE_EXHAUSTED' WHERE id=?", [(string) $row['id']]);
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
            $rows = $this->select("SELECT id FROM {$this->queue} WHERE {$condition} "
                . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            if ($rows === []) {
                $condition = "status='LEASED' AND leased_until<=UTC_TIMESTAMP(6) AND attempts<6";
                $rows = $this->select("SELECT id FROM {$this->queue} WHERE {$condition} "
                    . "ORDER BY id LIMIT {$batch} FOR UPDATE")->getResultArray();
            }
            $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
            if ($ids !== []) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $this->exec("UPDATE {$this->queue} SET status='LEASED',lease_token=?,"
                    . 'leased_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ' . self::LEASE_SECONDS . ' SECOND),'
                    . "attempts=attempts+1,last_error_code=NULL WHERE id IN ({$in}) AND {$condition}", [$token, ...$ids]);
                if ($this->db->affectedRows() !== count($ids)) {
                    throw new RuntimeException('M11_PUSH_CLAIM_RACE');
                }
            }
            $this->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function deliver(string $id, string $token, string $key): string
    {
        $this->db->transBegin();
        try {
            $row = $this->select("SELECT * FROM {$this->queue} WHERE id=? FOR UPDATE", [$id])->getRowArray();
            if ($row === null || $row['status'] !== 'LEASED' || $row['lease_token'] !== $token
                || ! $this->ownsLease($id, $token)) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            if (! NotificationFlags::pushSendEnabled($this->db)) {
                return $this->pauseLocked($id, $token);
            }
            $subscription = $this->select("SELECT * FROM {$this->subscriptions} WHERE id=?",
                [(string) $row['subscription_id']])->getRowArray();
            if ($subscription === null || (string) $subscription['enabled'] !== '1'
                || (string) $subscription['user_id'] !== (string) $row['recipient_user_id']) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_SUBSCRIPTION_REVOKED');
            }
            $userId = (string) $row['recipient_user_id'];
            $user = $this->select("SELECT * FROM {$this->users} WHERE id=?",
                [$userId])->getRowArray();
            if ($user === null || ! WorkflowNotificationRules::activeUser($user)) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_USER_INACTIVE');
            }
            $event = $this->select("SELECT event_type,source_type,source_id,payload_json FROM {$this->outbox} "
                . "WHERE event_key=? AND status='PROCESSED'", [(string) $row['event_key']])->getRowArray();
            if ($event === null || ! isset(NotificationCatalog::definitions()[(string) $event['event_type'] . '.push'])) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_EVENT_UNAVAILABLE');
            }
            $policyKey = (string) $event['event_type'] . '.push';
            if ((string) $event['event_type'] === 'workflow.action_required') {
                try {
                    $payload = WorkflowNotificationRules::decode((string) $event['payload_json']);
                    $live = (new BusinessWorkflowService(new User($user), $this->db))
                        ->options($payload['resource_type'], (string) $event['source_id']);
                    if ($live['current'] !== $payload['to_state'] || $live['options'] === []) {
                        return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_WORKFLOW_STALE');
                    }
                } catch (DomainException|\InvalidArgumentException $e) {
                    return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_WORKFLOW_UNAVAILABLE');
                }
            }
            $policy = $this->select("SELECT s.*,p.override_value AS preference_value,"
                . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision '
                . "FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
                . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
                [$userId, $policyKey])->getRowArray();
            if ($policy === null) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_POLICY_MISSING');
            }
            $pref = $policy['preference_revision'] === null ? null : [
                'override_value' => $policy['preference_value'],
                'override_epoch' => $policy['preference_epoch'],
                'user_revision' => $policy['preference_revision'],
            ];
            if (! NotificationPushRules::consented($policy, $pref, $policyKey)) {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_CONSENT_REVOKED');
            }
            // A subscription from before encrypted Push support cannot display
            // an event topic. Do not silently send a generic empty Push: the
            // user must explicitly refresh this browser subscription to opt in.
            if (! is_string($subscription['key_material_cipher'] ?? null)
                || $subscription['key_material_cipher'] === '') {
                return $this->terminal($id, $token, 'CANCELLED', 'M11_PUSH_DEVICE_UPDATE_REQUIRED');
            }
            $endpoint = NotificationPushCrypto::decrypt((string) $subscription['endpoint_cipher'], $key);
            $keys = NotificationPushCrypto::decryptKeys($subscription['key_material_cipher'], $key);
            $job = null;
            if ((string) $event['source_type'] === 'import_job') {
                $job = $this->select("SELECT success_rows,error_rows FROM {$this->jobs} WHERE id=?",
                    [(string) $event['source_id']])->getRowArray();
            }
            $content = NotificationPushPresentation::payload($event, $job);
            // COMMIT before HTTP; no DB row or recipient identity is sent to the provider.
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            return $this->fail($id, $token);
        }
        if (! NotificationFlags::pushSendEnabled($this->db)) {
            return $this->pauseAfterClaim($id, $token);
        }
        try {
            if (! $this->sender instanceof NotificationPushPayloadSender) {
                throw new \RuntimeException('M11_PUSH_ENCRYPTED_SENDER_REQUIRED');
            }
            $status = $this->sender->sendEncrypted($endpoint, $content, $keys['p256dh'], $keys['auth']);
        } catch (Throwable) {
            return $this->fail($id, $token);
        }
        if ($status === 'retry') {
            return $this->fail($id, $token);
        }
        if (! in_array($status, ['sent','gone','dead'], true)) {
            return $this->fail($id, $token);
        }
        $this->db->transBegin();
        try {
            $row = $this->select("SELECT id FROM {$this->queue} WHERE id=? AND status='LEASED' "
                . 'AND lease_token=? AND leased_until>UTC_TIMESTAMP(6) FOR UPDATE', [$id, $token])->getRowArray();
            if ($row === null) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            $terminal = $status === 'sent' ? 'SENT' : ($status === 'gone' ? 'CANCELLED' : 'DEAD');
            $this->exec("UPDATE {$this->queue} SET status=?,lease_token=NULL,leased_until=NULL,"
                . 'sent_at=IF(?=\'SENT\',UTC_TIMESTAMP(6),NULL),last_error_code=? '
                . "WHERE id=? AND status='LEASED' AND lease_token=?", [
                    $terminal, $terminal, $status === 'sent' ? null : 'M11_PUSH_PROVIDER_REJECTED', $id, $token,
                ]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('M11_PUSH_ACK_LOST_LEASE');
            }
            if ($status === 'gone') {
                $this->db->table('notification_push_subscriptions')->where('id', $subscription['id'])
                    ->where('user_id', $row['recipient_user_id'] ?? $userId)
                    ->update(['enabled' => 0, 'revoked_at' => gmdate('Y-m-d H:i:s'),
                        'updated_at' => gmdate('Y-m-d H:i:s')]);
                $this->db->table('notification_push_deliveries')->where('subscription_id', $subscription['id'])
                    ->whereIn('status', ['PENDING','RETRY'])->update([
                        'status' => 'CANCELLED', 'last_error_code' => 'M11_PUSH_PROVIDER_GONE',
                    ]);
            }
            $this->commit();
            return $status === 'sent' ? 'sent' : ($status === 'gone' ? 'cancelled' : 'dead');
        } catch (Throwable) {
            $this->db->transRollback();
            return 'lost_lease';
        }
    }

    private function terminal(string $id, string $token, string $status, string $reason): string
    {
        $this->exec("UPDATE {$this->queue} SET status=?,lease_token=NULL,leased_until=NULL,"
            . "last_error_code=? WHERE id=? AND status='LEASED' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6)",
            [$status, $reason, $id, $token]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('M11_PUSH_TERMINAL_LOST_LEASE');
        }
        $this->commit();
        return strtolower($status);
    }

    private function pauseLocked(string $id, string $token): string
    {
        $this->exec("UPDATE {$this->queue} SET status='PENDING',attempts=GREATEST(attempts-1,0),"
            . "lease_token=NULL,leased_until=NULL WHERE id=? AND status='LEASED' AND lease_token=?", [$id, $token]);
        $this->commit();
        return 'paused';
    }

    private function pauseAfterClaim(string $id, string $token): string
    {
        $this->db->transBegin();
        try {
            $this->exec("UPDATE {$this->queue} SET status='PENDING',attempts=GREATEST(attempts-1,0),"
                . "lease_token=NULL,leased_until=NULL WHERE id=? AND status='LEASED' AND lease_token=? "
                . 'AND leased_until>UTC_TIMESTAMP(6)', [$id, $token]);
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
            $row = $this->select("SELECT attempts FROM {$this->queue} WHERE id=? AND status='LEASED'"
                . ' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6) FOR UPDATE', [$id, $token])->getRowArray();
            if ($row === null) {
                $this->db->transRollback();
                return 'lost_lease';
            }
            $decision = NotificationDeliveryRules::afterFailure((int) $row['attempts']);
            $minutes = $decision['minutes'];
            $status = $decision['status'];
            $this->exec("UPDATE {$this->queue} SET status=?,lease_token=NULL,leased_until=NULL,"
                . "available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL {$minutes} MINUTE),"
                . "last_error_code='M11_PUSH_TRANSPORT_FAILED' WHERE id=? AND status='LEASED' AND lease_token=?",
                [$status, $id, $token]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('M11_PUSH_RETRY_LOST_LEASE');
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
        $row = $this->select("SELECT (leased_until>UTC_TIMESTAMP(6)) AS valid FROM {$this->queue}"
            . ' WHERE id=? AND lease_token=?', [$id, $token])->getRowArray();
        return (string) ($row['valid'] ?? '0') === '1';
    }

    private function table(string $logical): string
    {
        $physical = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $physical) !== 1) {
            throw new RuntimeException('M11_PUSH_INVALID_TABLE');
        }
        return '`' . $physical . '`';
    }

    /** @param list<mixed> $params */
    private function select(string $sql, array $params = []): object
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new RuntimeException('M11_PUSH_READ_FAILED');
        }
        return $result;
    }

    /** @param list<mixed> $params */
    private function exec(string $sql, array $params): void
    {
        if ($this->db->query($sql, $params) === false) {
            throw new RuntimeException('M11_PUSH_WRITE_FAILED');
        }
    }

    private function commit(): void
    {
        if (! $this->db->transStatus() || ! $this->db->transCommit()) {
            throw new RuntimeException('M11_PUSH_COMMIT_FAILED');
        }
    }
}

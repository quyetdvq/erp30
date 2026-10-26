<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use App\Modules\Workflow\Execution\BusinessWorkflowService;
use CodeIgniter\Shield\Entities\User;
use Closure;
use RuntimeException;
use Throwable;

/** M11.1B database-only worker. MariaDB 10.4: SELECT FOR UPDATE; never SKIP LOCKED. */
final class NotificationDispatcher
{
    private const LEASE_SECONDS = 120;
    private readonly string $outbox;
    private readonly string $jobs;
    private readonly string $users;
    private readonly string $settings;
    private readonly string $preferences;
    private readonly string $inbox;

    /** Fault injection is strictly test-only; never expose it via flags or HTTP. */
    public function __construct(private readonly BaseConnection $db, private readonly ?Closure $testingBeforeAck = null)
    {
        if ($testingBeforeAck !== null && (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing')) {
            throw new RuntimeException('M11_TEST_HOOK_FORBIDDEN');
        }
        $this->outbox = $this->table('notification_outbox');
        $this->jobs = $this->table('import_jobs');
        $this->users = $this->table('users');
        $this->settings = $this->table('notification_settings');
        $this->preferences = $this->table('notification_user_preferences');
        $this->inbox = $this->table('user_notifications');
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
            $result = (string) $rows['source_type'] === 'workflow_transition'
                ? $this->deliverWorkflow($rows) : $this->deliverImport($rows);
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
            $this->ack($id, $token, $result['status'], $result['reason']);
            $this->commit();
            return strtolower($result['status']);
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

    /** @param array<string,mixed> $event @return array{status:string,reason:?string} */
    private function deliverImport(array $event): array
    {
        $id = (string) $event['source_id'];
        if ((string) $event['source_type'] !== 'import_job'
            || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
            || (string) $event['event_key'] !== "import-job:{$id}:terminal"
            || ! in_array((string) $event['event_type'], ['import.completed','import.completed_with_errors','import.failed'], true)
            || (string) $event['payload_json'] !== '{"schema":1}') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
        }
        $job = $this->select("SELECT id,status,created_by,success_rows,error_rows FROM {$this->jobs} WHERE id=?", [$id])->getRowArray();
        if ($job === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_NOT_FOUND');
        }
        try {
            $type = NotificationDeliveryRules::importEventType((string) $job['status']);
            $recipientId = NotificationDeliveryRules::shieldUserId($job['created_by']);
        } catch (\InvalidArgumentException $e) {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_SOURCE', 0, $e);
        }
        if ($type !== (string) $event['event_type']) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_CHANGED');
        }
        if ($recipientId === null) {
            return ['status' => 'SUPPRESSED', 'reason' => 'NOTIFICATION_NO_CREATOR']; // Explicit system job.
        }
        $user = $this->select("SELECT id,active,deleted_at FROM {$this->users} WHERE id=? FOR UPDATE", [$recipientId])->getRowArray();
        if ($user === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
        }
        if ((string) $user['active'] !== '1' || $user['deleted_at'] !== null) {
            return ['status' => 'SUPPRESSED', 'reason' => 'NOTIFICATION_RECIPIENT_INACTIVE'];
        }
        $key = $type . '.in_app';
        $definition = NotificationCatalog::definitions()[$key] ?? null;
        if ($definition === null || $definition['capability'] !== 'AVAILABLE') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT_TYPE');
        }
        // One JOIN / one statement: SYSTEM and USER version data are seen together
        // under MariaDB READ-COMMITTED. The producer toggle is NOT delivery policy.
        $policy = $this->select("SELECT s.*,p.override_value AS preference_value,"
            . "p.override_epoch AS preference_epoch,p.user_revision AS preference_revision"
            . " FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
            . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
            [$recipientId, $key])->getRowArray();
        if ($policy === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_POLICY_MISSING');
        }
        $pref = $policy['preference_revision'] === null ? null : [
            'override_value' => $policy['preference_value'],
            'override_epoch' => $policy['preference_epoch'],
            'user_revision' => $policy['preference_revision'],
        ];
        try {
            $effective = NotificationPolicyResolver::preferenceView($key, $policy, $definition, $pref);
        } catch (Throwable $e) {
            throw new NotificationPermanentFailure('NOTIFICATION_POLICY_INVALID', 0, $e);
        }
        // M11.3B: a separate durable SMTP receipt is created within the SAME
        // Outbox ACK transaction. It is sent by another worker only after COMMIT.
        // In-App disabled does not silently disable an explicitly opted-in Email.
        $sendInApp = (bool) $effective['effective_value'];
        $queueEmail = false;
        $success = max(0, (int) $job['success_rows']);
        $errors = max(0, (int) $job['error_rows']);
        $params = json_encode(['success_rows' => $success, 'error_rows' => $errors], JSON_THROW_ON_ERROR);
        if ($type === 'import.completed' && NotificationFlags::emailQueueEnabled($this->db)) {
            $emailKey = 'import.completed.email';
            $emailPolicy = $this->select("SELECT s.*,p.override_value AS preference_value,"
                . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision'
                . " FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
                . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
                [$recipientId, $emailKey])->getRowArray();
            if ($emailPolicy === null) {
                throw new NotificationPermanentFailure('NOTIFICATION_EMAIL_POLICY_MISSING');
            }
            $emailPref = $emailPolicy['preference_revision'] === null ? null : [
                'override_value' => $emailPolicy['preference_value'],
                'override_epoch' => $emailPolicy['preference_epoch'],
                'user_revision' => $emailPolicy['preference_revision'],
            ];
            try {
                $queueEmail = NotificationEmailRules::consented($emailPolicy, $emailPref);
            } catch (Throwable $e) {
                throw new NotificationPermanentFailure('NOTIFICATION_EMAIL_POLICY_INVALID', 0, $e);
            }
            if ($queueEmail) {
                $receipts = $this->table('notification_email_deliveries');
                $this->exec("INSERT INTO {$receipts}"
                    . ' (event_key,recipient_user_id,event_type,params_json,status,attempts,available_at,created_at)'
                    . " VALUES (?,?,?,?,'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"
                    . ' ON DUPLICATE KEY UPDATE id=id',
                    [(string) $event['event_key'], $recipientId, $type, $params]);
            }
        }
        // M11.3C: one opaque payload-free Push signal per active device, in the SAME
        // Outbox ACK transaction. No external HTTP calls or endpoint plaintext here.
        $queuePush = false;
        if ($type === 'import.completed' && NotificationFlags::pushQueueEnabled($this->db)) {
            $pushKey = 'import.completed.push';
            $pushPolicy = $this->select("SELECT s.*,p.override_value AS preference_value,"
                . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision'
                . " FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
                . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
                [$recipientId, $pushKey])->getRowArray();
            if ($pushPolicy === null) {
                throw new NotificationPermanentFailure('NOTIFICATION_PUSH_POLICY_MISSING');
            }
            $pushPref = $pushPolicy['preference_revision'] === null ? null : [
                'override_value' => $pushPolicy['preference_value'],
                'override_epoch' => $pushPolicy['preference_epoch'],
                'user_revision' => $pushPolicy['preference_revision'],
            ];
            try {
                $pushConsent = NotificationPushRules::consented($pushPolicy, $pushPref);
            } catch (Throwable $e) {
                throw new NotificationPermanentFailure('NOTIFICATION_PUSH_POLICY_INVALID', 0, $e);
            }
            if ($pushConsent) {
                $subs = $this->table('notification_push_subscriptions');
                $receipts = $this->table('notification_push_deliveries');
                $ids = $this->select("SELECT id FROM {$subs} WHERE user_id=? AND enabled=1 LIMIT 5", [$recipientId])
                    ->getResultArray();
                foreach ($ids as $device) {
                    $this->exec("INSERT INTO {$receipts}"
                        . ' (subscription_id,recipient_user_id,event_key,status,attempts,available_at,created_at)'
                        . " VALUES (?,?,?,'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"
                        . ' ON DUPLICATE KEY UPDATE id=id',
                        [(string) $device['id'], $recipientId, (string) $event['event_key']]);
                    $queuePush = true;
                }
            }
        }
        if (! $sendInApp && ! $queueEmail && ! $queuePush) {
            return ['status' => 'SUPPRESSED', 'reason' => 'NOTIFICATION_PREFERENCE_DISABLED'];
        }
        if ($sendInApp) {
            $slug = match ($type) {
                'import.completed' => 'completed',
                'import.completed_with_errors' => 'completedWithErrors',
                'import.failed' => 'failed',
            };
            $sql = "INSERT INTO {$this->inbox}"
                . ' (recipient_user_id,event_key,type,priority,title_key,body_key,params_json,target_type,target_id,created_at)'
                . ' VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=id';
            $this->exec($sql, [$recipientId,(string) $event['event_key'],$type,'NORMAL',
                "notifications.import.{$slug}.title", "notifications.import.{$slug}.body",
                $params, 'import_job', $id]);
        }
        return ['status' => 'PROCESSED', 'reason' => null];
    }

    /** @param array<string,mixed> $event @return array{status:string,reason:?string} */
    private function deliverWorkflow(array $event): array
    {
        $id = (string) $event['source_id'];
        if ((string) $event['source_type'] !== 'workflow_transition'
            || (string) $event['event_type'] !== 'workflow.action_required') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
        }
        try {
            BusinessWorkflowRules::id($id);
            $payload = WorkflowNotificationRules::decode((string) $event['payload_json']);
            $resource = $payload['resource_type'];
            $table = $this->table(BusinessWorkflowRules::table($resource));
        } catch (Throwable $e) {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT', 0, $e);
        }
        if (preg_match('/\Aworkflow:' . preg_quote($resource, '/') . ':'
                . preg_quote($id, '/') . ':[a-f0-9]{32}\z/D', (string) $event['event_key']) !== 1) {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
        }
        // Lock target once for the entire delivery, so status and PBAC decisions
        // cannot race a subsequent Workflow transition.
        $target = $this->select("SELECT * FROM {$table} WHERE id=? FOR UPDATE", [$id])->getRowArray();
        if ($target === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_NOT_FOUND');
        }
        if (str_ends_with($resource, '_contact') && strtoupper((string) ($target['contact_type'] ?? ''))
            !== ($resource === 'organization_contact' ? 'ORGANIZATION' : 'EMPLOYEE')) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_NOT_FOUND');
        }
        if (strtoupper((string) $target['status']) !== $payload['to_state']) {
            return ['status' => 'SUPPRESSED', 'reason' => 'NOTIFICATION_WORKFLOW_STATE_CHANGED'];
        }
        $key = 'workflow.action_required.in_app';
        $definition = NotificationCatalog::definitions()[$key] ?? null;
        if ($definition === null || $definition['capability'] !== 'AVAILABLE') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT_TYPE');
        }
        $ids = $payload['recipient_user_ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // One SELECT yields the SAME SYSTEM+USER policy snapshot for the entire
        // fanout at READ-COMMITTED, even when an admin edits policy concurrently.
        $rows = $this->select("SELECT u.id AS recipient_user_id,s.*,"
            . 'p.override_value AS preference_value,p.override_epoch AS preference_epoch,'
            . 'p.user_revision AS preference_revision'
            . " FROM {$this->settings} s JOIN {$this->users} u ON u.id IN ({$placeholders})"
            . " LEFT JOIN {$this->preferences} p ON p.setting_key=s.setting_key AND p.user_id=u.id"
            . ' WHERE s.setting_key=?', [...$ids, $key])->getResultArray();
        if ($rows === []) {
            throw new NotificationPermanentFailure('NOTIFICATION_POLICY_MISSING');
        }
        $policies = [];
        foreach ($rows as $row) {
            $policies[(string) $row['recipient_user_id']] = $row;
        }
        $inserted = 0;
        foreach ($ids as $userId) {
            if (! isset($policies[$userId])) {
                throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
            }
            $policy = $policies[$userId];
            $userRow = $this->select("SELECT * FROM {$this->users} WHERE id=? FOR UPDATE", [$userId])->getRowArray();
            if ($userRow === null) {
                throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
            }
            if (! WorkflowNotificationRules::activeUser($userRow)) {
                continue;
            }
            $pref = $policy['preference_revision'] === null ? null : [
                'override_value' => $policy['preference_value'],
                'override_epoch' => $policy['preference_epoch'],
                'user_revision' => $policy['preference_revision'],
            ];
            try {
                $effective = NotificationPolicyResolver::preferenceView($key, $policy, $definition, $pref);
            } catch (Throwable $e) {
                throw new NotificationPermanentFailure('NOTIFICATION_POLICY_INVALID', 0, $e);
            }
            if (! $effective['effective_value']) {
                continue;
            }
            // Explicit assignment is necessary, but never sufficient: recheck
            // the target's CURRENT per-user VIEW and executable next actions.
            try {
                $options = (new BusinessWorkflowService(new User($userRow), $this->db))
                    ->options($resource, $id);
            } catch (DomainException $e) {
                if ($e->getErrorCode() === 'AUTH_POLICY_DENIED') {
                    continue;
                }
                throw $e;
            }
            if ($options['options'] === []) {
                continue;
            }
            $params = json_encode(['resource_type' => $resource, 'state_code' => $payload['to_state']], JSON_THROW_ON_ERROR);
            $this->exec("INSERT INTO {$this->inbox}"
                . ' (recipient_user_id,event_key,type,priority,title_key,body_key,params_json,target_type,target_id,created_at)'
                . ' VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=id',
                [$userId, (string) $event['event_key'], 'workflow.action_required', 'NORMAL',
                    'notifications.workflow.actionRequired.title', 'notifications.workflow.actionRequired.body',
                    $params, 'workflow_target', $id]);
            ++$inserted;
        }
        return $inserted > 0
            ? ['status' => 'PROCESSED', 'reason' => null]
            : ['status' => 'SUPPRESSED', 'reason' => 'NOTIFICATION_NO_ELIGIBLE_ASSIGNEE'];
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

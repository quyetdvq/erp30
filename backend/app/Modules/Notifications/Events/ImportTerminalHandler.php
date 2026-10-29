<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationDeliveryRules;
use App\Modules\Notifications\NotificationEmailRules;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationPermanentFailure;
use App\Modules\Notifications\NotificationPolicyResolver;
use App\Modules\Notifications\NotificationPushEnqueuer;
use Throwable;

/** Existing Import terminal delivery (In-App, eligible Email and Push receipts).
 * Called only inside the Dispatcher-owned Outbox ACK transaction.
 */
final class ImportTerminalHandler extends AbstractNotificationEventHandler
{
    /** @param array<string,mixed> $event */
    public function deliver(array $event): NotificationDeliveryResult
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
            return NotificationDeliveryResult::suppressed('NOTIFICATION_NO_CREATOR'); // Explicit system job.
        }
        $user = $this->select("SELECT id,active,deleted_at FROM {$this->users} WHERE id=? FOR UPDATE", [$recipientId])->getRowArray();
        if ($user === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
        }
        if ((string) $user['active'] !== '1' || $user['deleted_at'] !== null) {
            return NotificationDeliveryResult::suppressed('NOTIFICATION_RECIPIENT_INACTIVE');
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
        $emailKey = NotificationEmailRules::policyKeyForEvent($type);
        if ($emailKey !== null && NotificationFlags::emailQueueEnabled($this->db)) {
            $emailPolicy = $this->select("SELECT s.*,p.override_value AS preference_value,"
                . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision'
                . " FROM {$this->settings} s LEFT JOIN {$this->preferences} p"
                . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
                [$recipientId, $emailKey])->getRowArray();
            if ($emailPolicy === null && $type === 'import.completed') {
                throw new NotificationPermanentFailure('NOTIFICATION_EMAIL_POLICY_MISSING');
            }
            if ($emailPolicy !== null) {
                $emailPref = $emailPolicy['preference_revision'] === null ? null : [
                    'override_value' => $emailPolicy['preference_value'],
                    'override_epoch' => $emailPolicy['preference_epoch'],
                    'user_revision' => $emailPolicy['preference_revision'],
                ];
                try {
                    $queueEmail = NotificationEmailRules::consentedForPolicyKey($emailKey, $emailPolicy, $emailPref);
                } catch (Throwable $e) {
                    if ($type === 'import.completed') {
                        throw new NotificationPermanentFailure('NOTIFICATION_EMAIL_POLICY_INVALID', 0, $e);
                    }
                    // A corrupt/missing *new* optional Email policy disables Email only;
                    // it must not discard the existing In-App Import notification.
                    $queueEmail = false;
                }
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
        // All supported Import outcomes may enqueue Push when THIS event has explicit opt-in.
        // The dispatcher owns this transaction; network calls remain in the Push worker.
        $queuePush = NotificationPushEnqueuer::enqueue($this->db,
            $recipientId, $type, (string) $event['event_key']);
        if (! $sendInApp && ! $queueEmail && ! $queuePush) {
            return NotificationDeliveryResult::suppressed('NOTIFICATION_PREFERENCE_DISABLED');
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
        return NotificationDeliveryResult::processed();
    }

}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Transactional, event-specific opted-in delivery fanout; no HTTP under locks. */
final class NotificationPushEnqueuer
{
    public static function enqueue(BaseConnection $db, string $recipient, string $eventType, string $eventKey): bool
    {
        if (! NotificationFlags::pushQueueEnabled($db)) {
            return false;
        }
        $policyKey = $eventType . '.push';
        if (! isset(NotificationCatalog::definitions()[$policyKey])) {
            return false;
        }
        $table = static function (string $logical) use ($db): string {
            $name = $db->prefixTable($logical);
            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
                throw new RuntimeException('M11_PUSH_INVALID_TABLE');
            }
            return '`' . $name . '`';
        };
        $settings = $table('notification_settings');
        $preferences = $table('notification_user_preferences');
        $policy = $db->query("SELECT s.*,p.override_value AS preference_value,"
            . 'p.override_epoch AS preference_epoch,p.user_revision AS preference_revision'
            . " FROM {$settings} s LEFT JOIN {$preferences} p"
            . ' ON p.setting_key=s.setting_key AND p.user_id=? WHERE s.setting_key=?',
            [$recipient, $policyKey])->getRowArray();
        if ($policy === null) {
            throw new RuntimeException('M11_PUSH_POLICY_NOT_PROVISIONED');
        }
        $pref = $policy['preference_revision'] === null ? null : [
            'override_value' => $policy['preference_value'],
            'override_epoch' => $policy['preference_epoch'],
            'user_revision' => $policy['preference_revision'],
        ];
        if (! NotificationPushRules::consented($policy, $pref, $policyKey)) {
            return false;
        }
        $subs = $table('notification_push_subscriptions');
        $queue = $table('notification_push_deliveries');
        $devices = $db->query("SELECT id FROM {$subs} WHERE user_id=? AND enabled=1 ORDER BY id LIMIT 5",
            [$recipient])->getResultArray();
        foreach ($devices as $device) {
            $result = $db->query("INSERT INTO {$queue}"
                . ' (subscription_id,recipient_user_id,event_key,status,attempts,available_at,created_at)'
                . " VALUES (?,?,?,'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"
                . ' ON DUPLICATE KEY UPDATE id=id',
                [(string) $device['id'], $recipient, $eventKey]);
            if ($result === false) {
                throw new RuntimeException('M11_PUSH_QUEUE_WRITE_FAILED');
            }
        }
        return $devices !== [];
    }
}

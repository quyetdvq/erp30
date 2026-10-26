<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationPushAcceptanceOptions;
use App\Modules\Notifications\NotificationPushCrypto;
use App\Modules\Notifications\NotificationPushRules;
use App\Modules\Notifications\NotificationVapidSender;
use App\Modules\Notifications\NotificationRuntimeConfigService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Read-only Browser Push acceptance gates. Does not claim queue rows, send HTTP,
 * generate keys, change preferences, or modify database/configuration.
 */
final class NotificationsPushAcceptance extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:push-acceptance';
    protected $description = 'READ ONLY Push/VAPID readiness and scoped Import receipt evidence.';

    private bool $failed = false;

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $options = NotificationPushAcceptanceOptions::parse($params);
        $db = db_connect();
        CLI::write('M11.3C Browser Push acceptance (READ ONLY; no network send or database writes).');
        $this->check('openssl', extension_loaded('openssl'));
        $this->check('curl', extension_loaded('curl'));

        // Database schema must be ready before evaluating operational policy.
        $tables = ['notification_settings', 'notification_user_preferences',
            'notification_outbox', 'notification_push_subscriptions', 'notification_push_deliveries',
            'import_jobs', 'settings'];
        $schemaReady = true;
        foreach ($tables as $table) {
            $exists = $db->tableExists($table);
            $this->check('table_' . $table, $exists);
            $schemaReady = $schemaReady && $exists;
        }
        if (! $schemaReady) {
            CLI::write('BLOCKED: required M11 tables missing. No operations performed.', 'red');
            exit(1);
        }
        $queueEnabled = NotificationFlags::pushQueueEnabled($db);
        $sendEnabled = NotificationFlags::pushSendEnabled($db);
        $this->flag($db, 'DINKERP_M11_PUSH_QUEUE_ENABLED', $queueEnabled);
        $this->flag($db, 'DINKERP_M11_PUSH_SEND_ENABLED', $sendEnabled);
        $this->flag($db, 'DINKERP_M11_IMPORT_PRODUCER_ENABLED', NotificationFlags::producerEnabled($db));
        $this->flag($db, 'DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', NotificationFlags::deliveryEnabled($db));

        $key = null;
        try {
            $key = NotificationPushCrypto::key();
            $probe = 'https://fcm.googleapis.com/fcm/send/m11-acceptance-only';
            $roundTrip = NotificationPushCrypto::decrypt(NotificationPushCrypto::encrypt($probe, $key), $key);
            $this->check('endpoint_storage_key_and_aes_gcm', hash_equals($probe, $roundTrip));
        } catch (Throwable) {
            $this->check('endpoint_storage_key_and_aes_gcm', false);
        }
        try {
            $sender = new NotificationVapidSender();
            $public = NotificationVapidSender::publicKey();
            $raw = NotificationPushRules::decode($public);
            $this->check('vapid_private_key_p256_and_subject', $raw !== null && strlen($raw) === 65
                && $raw[0] === "\x04" && ! NotificationVapidSender::isPublicTestFixture($public));
            unset($sender);
        } catch (Throwable) {
            $this->check('vapid_private_key_p256_and_subject', false);
        }
        $cursor = (string) (function_exists('env')
            ? env('DINKERP_M11_CURSOR_SECRET', '') : (getenv('DINKERP_M11_CURSOR_SECRET') ?: ''));
        $this->check('inbox_cursor_secret_present', strlen($cursor) >= 32);

        if ($schemaReady) {
            $policy = $db->table('notification_settings')->where('setting_key', 'import.completed.push')
                ->get()->getRowArray();
            $this->check('import_completed_push_policy_off_by_default', $policy !== null
                && (string) $policy['default_value'] === '0'
                && (string) $policy['allow_user_override'] === '1');
            if ($key !== null) {
                $this->verifyStoredEndpoints($db, $key);
            }
            if ($options['user_id'] !== null) {
                $this->evidence($db, $options);
            }
        }
        if (in_array($options['stage'], ['created', 'queued'], true) && ! $queueEnabled) {
            $this->check('queue_enabled_before_delivery', false);
        }
        if (in_array($options['stage'], ['created', 'queued'], true) && $sendEnabled) {
            $this->check('send_off_until_queue_is_scoped', false);
        }
        if ($options['stage'] === 'preflight') {
            // Preflight is a preparation gate, never an authorization to send.
            // Operators must explicitly enable the queue later for a scoped job.
            $this->check('queue_off_during_preflight', ! $queueEnabled);
            $this->check('send_off_during_preflight', ! $sendEnabled);
            if ($queueEnabled || $sendEnabled) {
                CLI::write('Disable Push Queue and Push Send before provisioning or preflight.', 'yellow');
            }
        }

        CLI::write($this->failed ? 'BLOCKED: correct the listed checks before proceeding.'
            : 'CHECKS PASS: review receipt evidence and confirm the real browser notification separately.',
            $this->failed ? 'red' : 'green');
        CLI::write('No secrets, encrypted endpoints or recipient details were printed. No changes were made.');
        if ($this->failed) {
            exit(1);
        }
    }

    private function verifyStoredEndpoints(BaseConnection $db, string $key): void
    {
        $total = $db->table('notification_push_subscriptions')->where('enabled', 1)->countAllResults();
        $rows = $db->table('notification_push_subscriptions')->select('endpoint_cipher')
            ->where('enabled', 1)->limit(100)->get()->getResultArray();
        $healthy = true;
        foreach ($rows as $row) {
            try {
                NotificationPushCrypto::decrypt((string) $row['endpoint_cipher'], $key);
            } catch (Throwable) {
                $healthy = false;
                break;
            }
        }
        $this->check('enabled_endpoint_decryption_sample', $healthy && $total <= count($rows));
        CLI::write('active_subscriptions=' . (int) $total . '; inspected=' . count($rows)
            . '; ' . ($total > count($rows) ? 'sample_only' : 'all_checked'));
        if ($total === 0) {
            CLI::write('NOTE: key continuity cannot be proven until a real browser is registered.', 'yellow');
        }
    }

    /** @param array{stage:string,user_id:?string,import_job_id:?string} $options */
    private function evidence(BaseConnection $db, array $options): void
    {
        $userId = (string) $options['user_id'];
        $eventKey = 'import-job:' . $options['import_job_id'] . ':terminal';
        // Never return endpoints, business payload, user names, or encrypted columns.
        $active = $db->table('notification_push_subscriptions')->where('user_id', $userId)
            ->where('enabled', 1)->countAllResults();
        $row = $db->table('notification_settings s')
            ->select('s.*,p.override_value AS preference_value,p.override_epoch AS preference_epoch,'
                . 'p.user_revision AS preference_revision')
            ->join('notification_user_preferences p', 'p.setting_key=s.setting_key AND p.user_id=' . (int) $userId, 'left')
            ->where('s.setting_key', 'import.completed.push')->get()->getRowArray();
        $consent = false;
        try {
            if ($row !== null) {
                $pref = $row['preference_revision'] === null ? null : [
                    'override_value' => $row['preference_value'],
                    'override_epoch' => $row['preference_epoch'], 'user_revision' => $row['preference_revision'],
                ];
                $consent = NotificationPushRules::consented($row, $pref);
            }
        } catch (Throwable) {
            $consent = false;
        }
        $job = $db->table('import_jobs')->select('created_by,status')
            ->where('id', $options['import_job_id'])->get()->getRowArray();
        $isOwnCompletedImport = $job !== null && (string) $job['created_by'] === $userId
            && (string) $job['status'] === 'COMPLETED';
        $outbox = $db->table('notification_outbox')->select('status')->where('event_key', $eventKey)
            ->get()->getRowArray();
        $stats = [];
        foreach ($db->table('notification_push_deliveries')->select('status,COUNT(*) AS total')
            ->where('recipient_user_id', $userId)->where('event_key', $eventKey)
            ->groupBy('status')->get()->getResultArray() as $record) {
            $stats[(string) $record['status']] = (int) $record['total'];
        }
        $total = array_sum($stats);
        $unfinishedOutbox = $db->table('notification_outbox')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        CLI::write('globally_unfinished_outbox=' . $unfinishedOutbox);
        // The live worker processes the global queue, not just the test user.
        // Reject test sends if any other uncompleted Push receipt exists.
        $unfinished = $db->table('notification_push_deliveries')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        CLI::write('globally_unfinished_push_receipts=' . $unfinished);
        CLI::write('target_user_id=' . $userId . '; import_job_id=' . $options['import_job_id']);
        CLI::write('target_opt_in=' . ($consent ? 'yes' : 'no') . '; active_devices=' . $active);
        CLI::write('target_job_owner_and_completed=' . ($isOwnCompletedImport ? 'yes' : 'no'));
        CLI::write('target_outbox_status=' . ($outbox['status'] ?? 'NOT_FOUND'));
        CLI::write('target_push_receipts=' . json_encode($stats, JSON_THROW_ON_ERROR));
        if ($options['stage'] === 'created') {
            $this->check('single_import_outbox_safe_to_dispatch', $isOwnCompletedImport && $consent && $active === 1
                && ($outbox['status'] ?? null) === 'PENDING' && $unfinishedOutbox === 1
                && $total === 0);
        } elseif ($options['stage'] === 'queued') {
            $this->check('single_queued_receipt_safe_to_send', $isOwnCompletedImport && $consent && $active === 1
                && ($outbox['status'] ?? null) === 'PROCESSED' && $total === 1
                && ($stats['PENDING'] ?? 0) === 1 && $unfinished === 1);
        } elseif ($options['stage'] === 'sent') {
            $this->check('provider_accepted_receipt', $isOwnCompletedImport && $consent && $active === 1
                && ($outbox['status'] ?? null) === 'PROCESSED' && $total === 1
                && ($stats['SENT'] ?? 0) === 1);
        }
    }

    private function flag(BaseConnection $db, string $name, bool $enabled): void
    {
        $source = NotificationRuntimeConfigService::stored($db, $name) === null
            ? 'legacy_env_or_default' : 'database';
        CLI::write($name . '=' . ($enabled ? 'on' : 'off') . ' (' . $source . ')');
    }

    private function check(string $name, bool $ok): void
    {
        CLI::write(($ok ? 'PASS ' : 'FAIL ') . $name, $ok ? 'green' : 'red');
        $this->failed = $this->failed || ! $ok;
    }
}

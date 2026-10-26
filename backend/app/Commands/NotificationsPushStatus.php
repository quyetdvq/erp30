<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationPushStatusAdvice;
use App\Modules\Notifications\NotificationPushStatusOptions;
use App\Modules\Notifications\NotificationPushStatusReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Read-only DEV diagnostic. Does not return endpoints, secrets, business payload or row messages. */
final class NotificationsPushStatus extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:push-status';
    protected $description = 'READ ONLY latest Import jobs, Outbox, Push receipts and next acceptance gate for one user.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $userId = NotificationPushStatusOptions::userId($params);
        $data = (new NotificationPushStatusReader(db_connect()))->snapshot($userId);

        CLI::write('M11 Push status (READ ONLY; scoped user_id=' . $userId . ')');
        CLI::write('push_queue=' . ($data['queue_enabled'] ? 'on' : 'off')
            . '; push_send=' . ($data['send_enabled'] ? 'on' : 'off')
            . '; active_devices=' . $data['active_devices']);
        CLI::write('global_unfinished_outbox=' . $data['unfinished_outbox']
            . '; global_unfinished_push_receipts=' . $data['unfinished_push']);
        CLI::write('recent_import_jobs=' . count($data['jobs']) . ' (up to 5; newest first)');
        foreach ($data['jobs'] as $job) {
            CLI::write('JOB #' . $job['id']
                . ' status=' . $job['status']
                . ' success=' . $job['success_rows']
                . ' errors=' . $job['error_rows']
                . ' outbox=' . $job['outbox_status']
                . ' event=' . $job['event_type']
                . ' push=' . json_encode($job['receipts'], JSON_THROW_ON_ERROR));
        }

        $latest = $data['jobs'][0] ?? null;
        if ($latest === null) {
            CLI::write('NEXT: No Import jobs for this user. Register a browser, then create a successful DEV Import.');
        } else {
            if ($latest['status'] !== 'COMPLETED') {
                CLI::write('NEXT: Latest Import is not COMPLETED. Fix Import errors before a new Push trial.', 'yellow');
                foreach ($data['latest_error_codes'] as $error) {
                    CLI::write('  failed_row=' . $error['row_number'] . '; error_code=' . $error['error_code']);
                }
            }
            if ($data['active_devices'] !== 1) {
                CLI::write('NEXT: Register exactly one browser for this user before scoped acceptance.', 'yellow');
            }
            if ($data['send_enabled']) {
                CLI::write('STOP: Push Send is ON. Turn it OFF before preparing a scoped trial.', 'red');
            }
            if (! $data['queue_enabled']) {
                CLI::write('NOTE: Push Queue is OFF. Enable only after preflight and browser registration.');
            }
            $candidate = NotificationPushStatusAdvice::candidateStage(
                $latest, $latest['outbox_status'], $latest['receipts'],
                $data['active_devices'], $data['queue_enabled'], $data['send_enabled'],
                $data['unfinished_outbox'], $data['unfinished_push'],
            );
            if ($candidate !== null) {
                CLI::write('NEXT GATE (NOT AUTOMATIC AUTHORIZATION):');
                CLI::write('  php spark notifications:push-acceptance --stage=' . $candidate
                    . ' --user-id=' . $userId . ' --import-job-id=' . $latest['id']);
            } else {
                CLI::write('No scoped dispatch/send gate suggested. Resolve blockers and rerun this report.');
            }
        }
        CLI::write('READ ONLY. No SQL input, queue claims, changes or network sends.');
        CLI::write('A snapshot is NOT permission to run workers: use the exact scoped acceptance gate.', 'yellow');
    }
}

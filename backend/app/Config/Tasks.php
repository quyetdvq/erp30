<?php

declare(strict_types=1);

namespace Config;

use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationTasksPlan;
use App\Modules\Notifications\NotificationSmtpSender;
use Config\Email as EmailConfig;
use Throwable;
use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
use CodeIgniter\Tasks\Scheduler;

/**
 * M11.5 shared-hosting cron adapter for CodeIgniter Tasks v1.0.1.
 *
 * Scheduler registration is controlled by PBAC-audited runtime settings.
 * Stored flags override legacy .env (1/0), with OFF as the default. Do not
 * schedule an Email worker unless separately opted in AND SMTP is configured. M11 workers retain all
 * DB flags, consent, lease, retry and idempotency checks.
 */
class Tasks extends BaseTasks
{
    public bool $logPerformance = false;
    public int $maxLogsPerTask = 10;

    public function init(Scheduler $schedule)
    {
        // Never register live delivery jobs under a PHPUnit/testing bootstrap.
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            return;
        }

        $emailTask = NotificationFlags::enabled('DINKERP_M11_TASKS_EMAIL_ENABLED');
        $emailSend = NotificationFlags::emailSendEnabled();
        $smtpReady = false;
        if ($emailTask && $emailSend) {
            try {
                $smtpReady = NotificationSmtpSender::configurationReady(
                    config(EmailConfig::class), (string) env('DINKERP_M11_EMAIL_FROM', ''),
                );
            } catch (Throwable) {
                // Never register an external sender when settings are unavailable.
            }
        }
        foreach (NotificationTasksPlan::definitions(
            NotificationFlags::enabled('DINKERP_M11_TASKS_INAPP_ENABLED'),
            NotificationFlags::enabled('DINKERP_M11_TASKS_PUSH_ENABLED'),
            $emailTask, $emailSend, $smtpReady,
        ) as $job) {
            // command() runs a Spark command, NOT a shell command; no exec()
            // capability is required from the shared-hosting PHP configuration.
            $schedule->command($job['command'])
                ->everyMinute()
                ->named($job['name'])
                ->singleInstance($job['lock_seconds']);
        }
    }
}

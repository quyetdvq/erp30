<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationDispatchOptions;
use App\Modules\Notifications\NotificationEmailWorker;
use App\Modules\Notifications\NotificationFlags;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Dedicated SMTP worker, never piggybacks on Inbox dispatch transactions. */
final class NotificationsEmailDispatch extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:email-dispatch';
    protected $description = 'Send opted-in, queued M11.3B email receipts with bounded lease/retry.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $batch = NotificationDispatchOptions::batch($params);
        $db = db_connect();
        if (! NotificationFlags::emailSendEnabled($db)) {
            CLI::write('M11 Email send PAUSED; no rows claimed.', 'yellow');
            return;
        }
        $db->query('SET SESSION innodb_lock_wait_timeout = 3');
        $result = (new NotificationEmailWorker($db))->runOnce($batch);
        CLI::write('M11 Email dispatcher: ' . json_encode($result, JSON_THROW_ON_ERROR), 'green');
    }
}

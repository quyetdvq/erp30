<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationDispatchOptions;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationPushWorker;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Independent browser Push worker; only enabled via persisted runtime config. */
final class NotificationsPushDispatch extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:push-dispatch';
    protected $description = 'Send opted-in, payload-free browser Push signals with lease/retry.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $batch = NotificationDispatchOptions::batch($params);
        $db = db_connect();
        if (! NotificationFlags::pushSendEnabled($db)) {
            CLI::write('M11 Push sending PAUSED; no rows claimed.', 'yellow');
            return;
        }
        $db->query('SET SESSION innodb_lock_wait_timeout = 3');
        $result = (new NotificationPushWorker($db))->runOnce($batch);
        CLI::write('M11 Push dispatcher: ' . json_encode($result, JSON_THROW_ON_ERROR), 'green');
    }
}

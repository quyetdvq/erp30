<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationDispatcher;
use App\Modules\Notifications\NotificationDispatchOptions;
use App\Modules\Notifications\NotificationFlags;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Schedule bounded --once runs via CodeIgniter Tasks + CLI cron; never use an HTTP worker. */
final class NotificationsDispatch extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:dispatch';
    protected $description = 'Deliver a bounded batch of M11 Outbox events to Shield-owned Inbox rows.';

    /** @param array<array-key, string|null> $params */
    public function run(array $params): void
    {
        $batch = NotificationDispatchOptions::batch($params);
        $db = db_connect();
        if (! NotificationFlags::deliveryEnabled($db)) {
            CLI::write('M11 delivery PAUSED; no events claimed.', 'yellow');
            return;
        }
        // Bound lock wait for concurrent worker contention in MariaDB 10.4.
        $db->query('SET SESSION innodb_lock_wait_timeout = 3');
        $stats = (new NotificationDispatcher($db))->runOnce($batch);
        CLI::write('M11 dispatcher: ' . json_encode($stats, JSON_THROW_ON_ERROR), 'green');
    }
}

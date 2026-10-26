<?php

declare(strict_types=1);

/** Dedicated subprocess for two *actual* PHP workers with separate MariaDB connections. */
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "M11R worker: internal test harness only.\n");
    exit(2);
}

[$script, $readyPath, $startPath, $reportPath] = $argv;
putenv('CI_ENVIRONMENT=testing');
$_ENV['CI_ENVIRONMENT'] = 'testing';
$_SERVER['CI_ENVIRONMENT'] = 'testing';
putenv('DINKERP_NOTIFICATIONS_DELIVERY_ENABLED=enabled');
$_ENV['DINKERP_NOTIFICATIONS_DELIVERY_ENABLED'] = 'enabled';
$_SERVER['DINKERP_NOTIFICATIONS_DELIVERY_ENABLED'] = 'enabled';

chdir(dirname(__DIR__, 3)); // backend/
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
require_once __DIR__ . '/M11DatabaseGuard.php';

use App\Modules\Notifications\NotificationDispatcher;
use Tests\M11\Support\M11DatabaseGuard;

$report = ['ok' => false, 'error' => 'NOT_STARTED'];
$db = null;
try {
    // Child bypasses the *idle queue* guard because the parent intentionally
    // staged one committed event. Still validate the database/user/host first.
    $db = M11DatabaseGuard::connectTarget();
    if (file_put_contents($readyPath, 'ready') === false) {
        throw new RuntimeException('Cannot signal worker ready');
    }
    $deadline = microtime(true) + 15;
    while (! is_file($startPath) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (! is_file($startPath)) {
        throw new RuntimeException('Worker start barrier timed out');
    }
    if (file_put_contents($readyPath . '.entered', 'claiming') === false) {
        throw new RuntimeException('Cannot signal worker entering claim');
    }
    $stats = (new NotificationDispatcher($db))->runOnce(1);
    $report = ['ok' => true, 'stats' => $stats];
} catch (Throwable $e) {
    $report = ['ok' => false, 'error' => $e::class . ':' . $e->getMessage()];
} finally {
    if ($db !== null) {
        $db->close();
    }
}
$temporary = $reportPath . '.tmp';
if (file_put_contents($temporary, json_encode($report, JSON_THROW_ON_ERROR), LOCK_EX) === false
    || ! rename($temporary, $reportPath)) {
    fwrite(STDERR, "M11R worker: cannot persist atomic result.\n");
    exit(3);
}
exit($report['ok'] ? 0 : 1);

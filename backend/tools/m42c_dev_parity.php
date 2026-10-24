<?php

declare(strict_types=1);

/**
 * M4.2C DEV shortcut for existing api.monmin.net / apibase.monmin.net vhosts.
 * No database clones, SQL, arbitrary URL input or business mutation requests.
 * php tools/m42c_dev_parity.php smoke
 * php tools/m42c_dev_parity.php read [--resource-id=N --series-id=N --organization-id=N]
 */
require_once __DIR__ . '/m42c/M42CDevParity.php';
require_once __DIR__ . '/m34b/M34BSafety.php';

$mode = $argv[1] ?? 'smoke';
$ids = [];
foreach (array_slice($argv, 2) as $arg) {
    if (! preg_match('/^--(resource-id|series-id|organization-id)=([1-9][0-9]{0,17})$/D', $arg, $parts)
        || array_key_exists($parts[1], $ids)) {
        fwrite(STDERR, "Usage: smoke | read [--resource-id=N --series-id=N --organization-id=N]\n");
        exit(2);
    }
    $ids[$parts[1]] = $parts[2];
}
if (! in_array($mode, ['smoke', 'read'], true) || ($mode === 'smoke' && $ids !== [])
    || (isset($ids['series-id']) && ! isset($ids['resource-id']))) {
    fwrite(STDERR, "Usage: smoke | read [--resource-id=N --series-id=N --organization-id=N]\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$report = ['milestone' => 'M4.2C-DEV', 'time_utc' => gmdate('c'), 'mode' => $mode,
    'status' => 'BLOCKED_DEV_ENV', 'production_acceptance' => 'NOT_CLAIMED',
    'baseline_write_parity' => 'NOT_TESTED'];
$exit = 2;
try {
    $runner = new M42CDevParity($root);
    $details = $mode === 'smoke' ? $runner->smoke() : $runner->read(
        $ids, (string) getenv('M42C_DEV_USER'), (string) getenv('M42C_DEV_PASSWORD'));
    $report['integration'] = $details;
    $report['status'] = $details['status'];
    $exit = str_starts_with($report['status'], 'PASS_') ? 0
        : (str_starts_with($report['status'], 'PARTIAL_') ? 3 : 1);
    echo 'M4.2C DEV ' . strtoupper($mode) . ': ' . $report['status'] . PHP_EOL;
    if ($mode === 'read') {
        echo 'M4.2C DEV GET pairs: ' . $details['passed'] . '/'
            . $details['paired_cases'] . ', operations ' . $details['operation_get_tested'] . '/7' . PHP_EOL;
    }
} catch (Throwable $error) {
    $report['reason'] = M34BSafety::sanitizedException($error);
    fwrite(STDERR, 'M4.2C DEV BLOCKED: ' . $report['reason'] . PHP_EOL);
} finally {
    $dir = $root . '/backend/writable/m42/reports';
    if (! is_dir($dir)) { @mkdir($dir, 0700, true); }
    if (is_dir($dir) && is_writable($dir)) {
        $path = $dir . '/m42c_dev_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        echo 'M4.2C DEV report: ' . $path . PHP_EOL;
    }
}
exit($exit);

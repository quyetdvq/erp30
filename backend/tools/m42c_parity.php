<?php

declare(strict_types=1);

/**
 * M4.2C: operator-hosted two-clone HTTP parity for M4 reads and denials.
 * No SQL mutations or authorized write requests are implemented here.
 *
 * Usage:
 *   php backend/tools/m42c_parity.php inventory
 *   php backend/tools/m42c_parity.php preflight --config=FILE --confirm=M34_CLONE_ONLY
 *   php backend/tools/m42c_parity.php negative  --config=FILE --confirm=M34_CLONE_ONLY
 *   php backend/tools/m42c_parity.php read      --config=FILE --cases=FILE --confirm=M34_CLONE_ONLY
 */
require_once __DIR__ . '/m42c/M42CParity.php';
require_once __DIR__ . '/m34b/M34BSafety.php';

$root = dirname(__DIR__, 2);
$mode = $argv[1] ?? 'inventory';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (!preg_match('/^--(config|cases|confirm)=([^\r\n]+)$/D', $argument, $match)
        || array_key_exists($match[1], $options)) {
        fwrite(STDERR, "Usage: inventory | preflight | negative | read, --config=FILE --confirm=M34_CLONE_ONLY [--cases=FILE]\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}
if (!in_array($mode, ['inventory', 'preflight', 'negative', 'read'], true)) {
    fwrite(STDERR, "Unknown M4.2C mode; use inventory, preflight, negative or read\n");
    exit(2);
}
$report = ['milestone' => 'M4.2C', 'timestamp_utc' => gmdate('c'), 'mode' => $mode,
    'status' => 'BLOCKED_ENV_OR_CONFIG', 'baseline_write_parity' => 'NOT_TESTED',
    'production_write' => 'BLOCKED'];
$exit = 2;
try {
    $ops = M42CPlan::operations($root);
    if ($mode === 'inventory') {
        if ($options !== []) throw new M34Failure('inventory accepts no database options');
        $report['status'] = 'PASS_PINNED_SOURCE_ONLY';
        $report['operations'] = $ops;
        $report['route_total'] = count($ops);
        $report['read_only_routes'] = count(M42CPlan::readNames($ops));
        $report['write_routes_negative_only'] = count(M42CPlan::writeNames($ops));
        $exit = 0;
    } else {
        if (!isset($options['config']) || ($options['confirm'] ?? null) !== M34Safety::CONFIRMATION
            || ($mode === 'read' && !isset($options['cases']))
            || ($mode !== 'read' && isset($options['cases']))) {
            throw new M34Failure('Two clone config, explicit M34_CLONE_ONLY and read-only scenarios (read mode) are required');
        }
        $conf = M34Safety::config($options['config'], true);
        $runner = new M42CParity($conf, $ops);
        $report['integration'] = match ($mode) {
            'preflight' => $runner->preflight(),
            'negative' => $runner->negative(),
            'read' => $runner->read($options['cases']),
        };
        $report['status'] = $report['integration']['status'];
        $exit = str_starts_with($report['status'], 'PASS_') ? 0 : 1;
        echo 'M4.2C ' . strtoupper($mode) . ': ' . $report['status'] . PHP_EOL;
        if (isset($report['integration']['passed'])) {
            echo 'M4.2C paired HTTP: ' . $report['integration']['passed'] . '/'
                . $report['integration']['paired_cases'] . ' PASS' . PHP_EOL;
        }
    }
} catch (Throwable $error) {
    $report['status'] = 'BLOCKED_ENV_OR_CONFIG';
    $report['reason'] = M34BSafety::sanitizedException($error);
    fwrite(STDERR, 'M4.2C BLOCKED: ' . $report['reason'] . PHP_EOL);
    $exit = 2;
} finally {
    if ($mode !== 'inventory') {
        $folder = $root . '/backend/writable/m42/reports';
        if (!is_dir($folder)) @mkdir($folder, 0700, true);
        if (is_dir($folder) && is_writable($folder)) {
            $filename = $folder . '/m42c_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
            file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
            @chmod($filename, 0600);
            echo 'M4.2C report: ' . $filename . PHP_EOL;
        }
    } elseif ($exit === 0) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
}
exit($exit);

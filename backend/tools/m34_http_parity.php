<?php

declare(strict_types=1);

require_once __DIR__ . '/m34/M34Runner.php';

$root = dirname(__DIR__, 2);
$mode = $argv[1] ?? 'inventory';
$options = [];
foreach (array_slice($argv, 2) as $value) {
    if (preg_match('/^--([a-z-]+)=(.*)$/D', $value, $found)) { $options[$found[1]] = $found[2]; }
    else { fwrite(STDERR, "Unexpected argument. Use --config=FILE, --cases=FILE, --confirm=M34_CLONE_ONLY, --out=FILE\n"); exit(2); }
}
try {
    $operations = M34Contract::operations($root);
    if ($mode === 'inventory') {
        echo json_encode(['operations' => $operations, 'count' => count($operations)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
        exit(0);
    }
    if (! in_array($mode, ['preflight','negative','positive'], true)) { throw new M34Failure('Unknown mode. Use inventory | preflight | negative | positive'); }
    $conf = M34Safety::config($options['config'] ?? '', ($options['confirm'] ?? '') === M34Safety::CONFIRMATION);
    $runner = new M34Runner($conf, $operations);
    $report = match ($mode) {
        'preflight' => $runner->preflight(),
        'negative' => $runner->negative(),
        'positive' => $runner->positive($options['cases'] ?? ''),
    };
    if (isset($options['out'])) {
        $path = $options['out'];
        if (! preg_match('/\.json$/i', $path)) { throw new M34Failure('Report --out path must end in .json'); }
        $dirname = dirname($path);
        if (! is_dir($dirname) || ! is_writable($dirname)) { throw new M34Failure('Report directory must already exist and be writable'); }
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    $summary = $report;
    unset($summary['results']);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    if (isset($report['results'])) {
        foreach ($report['results'] as $row) {
            echo ($row['pass'] ? 'PASS ' : 'FAIL '), $row['operation'], ' ', $row['case'],
                isset($row['language']) ? (' ' . $row['language']) : '',
                $row['pass'] ? '' : (' ' . $row['reason']), "\n";
        }
    }
    exit(($report['failed'] ?? 0) > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'M3.4 BLOCKED: ' . $error->getMessage() . "\n");
    exit(2);
}

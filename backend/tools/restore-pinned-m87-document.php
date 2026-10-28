<?php

declare(strict_types=1);

/**
 * Explicit, read-only CHECK or missing-only RESTORE of the original P1 M8.7
 * verification document. Neither CHECK nor the normal test harness writes docs.
 * Usage: php backend/tools/restore-pinned-m87-document.php --check|--restore
 */
$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--restore'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php backend/tools/restore-pinned-m87-document.php --check|--restore\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$relative = 'M8_7_DEV_SQL_PBAC_RUNTIME_VERIFICATION.md';
$source = $root . '/backend/tests/fixtures/pinned_m87/' . $relative;
$directory = $root . '/docs/backend-rewrite';
$target = $directory . '/' . $relative;
$sha256 = '73dd036c7c8b503dec44840f88f442a02c7a601ba37061945cdf34e2fe7b4191';

try {
    if (!is_file($source) || is_link($source) || !is_readable($source)
        || !hash_equals($sha256, (string)hash_file('sha256', $source))) {
        throw new RuntimeException('The pinned P1 M8.7 source fixture is missing or corrupt.');
    }
    if (file_exists($target) || is_link($target)) {
        if (!is_file($target) || is_link($target) || !is_readable($target)
            || !hash_equals($sha256, (string)hash_file('sha256', $target))) {
            throw new RuntimeException('Original M8.7 document differs; REFUSE overwrite.');
        }
        echo "PINNED M8.7 CHECK PASS: original document matches SHA-256.\n";
        exit(0);
    }
    if ($mode === '--check') {
        throw new RuntimeException('Original M8.7 document missing; run --restore explicitly.');
    }
    foreach ([$root . '/docs', $directory] as $dir) {
        if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) {
            throw new RuntimeException('Unsafe M8.7 documentation directory.');
        }
        if (!is_dir($dir) && !mkdir($dir, 0755) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create missing M8.7 documentation directory.');
        }
    }
    // Exclusive create prevents clobbering a file written by another process.
    $input = fopen($source, 'rb');
    $output = fopen($target, 'xb');
    if ($input === false || $output === false) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        throw new RuntimeException('Unable to create M8.7 document exclusively.');
    }
    try {
        $bytes = stream_copy_to_stream($input, $output);
    } finally {
        fclose($input);
        fclose($output);
    }
    if ($bytes === false || !hash_equals($sha256, (string)hash_file('sha256', $target))) {
        @unlink($target);
        throw new RuntimeException('Restored M8.7 file failed SHA-256 verification.');
    }
    echo "PINNED M8.7 RESTORE PASS: original P1 document restored and verified.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'PINNED M8.7 STOP: ' . $error->getMessage() . "\n");
    exit(1);
}

<?php

declare(strict_types=1);

/**
 * Restore ONLY missing v1.2.4 reference files from SHA-256 pinned fixtures.
 * Explicit offline maintenance, not part of CHECK and never touches the database.
 * A present-but-modified reference fails closed; it is NEVER overwritten.
 * Usage: php backend/tools/restore-pinned-openapi.php --check|--restore
 */
require_once __DIR__ . '/m34/M34Core.php';

$mode = $argv[1] ?? '--check';
if (! in_array($mode, ['--check', '--restore'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php backend/tools/restore-pinned-openapi.php --check|--restore\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$docs = $root . '/docs/backend-rewrite';
$fixtures = $root . '/backend/tests/fixtures/baseline_v1_2_4/contract';
$files = [
    'openapi-operations-v1.2.4.json' => M34Contract::MANIFEST_SHA256,
    'openapi-frozen-v1.2.4.yaml' => M34Contract::OPENAPI_SHA256,
];

try {
    // Validate EVERY source and EVERY existing destination before writing anything.
    $missing = [];
    foreach ($files as $name => $expected) {
        $source = $fixtures . '/' . $name;
        $target = $docs . '/' . $name;
        if (! is_file($source) || is_link($source) || ! is_readable($source)
            || ! hash_equals($expected, (string) hash_file('sha256', $source))) {
            throw new RuntimeException('Pinned fixture missing/corrupted: ' . $name);
        }
        if (file_exists($target) || is_link($target)) {
            if (! is_file($target) || is_link($target) || ! is_readable($target)
                || ! hash_equals($expected, (string) hash_file('sha256', $target))) {
                throw new RuntimeException('Present repository reference changed; REFUSE overwrite: ' . $name);
            }
            echo "VERIFIED: {$name}\n";
        } else {
            $missing[] = $name;
            echo "MISSING: {$name}\n";
        }
    }
    if ($missing === []) {
        echo "PINNED OPENAPI CHECK PASS: two references match independent SHA-256 anchors.\n";
        exit(0);
    }
    if ($mode === '--check') {
        fwrite(STDERR, "References missing; run --restore only on the intended development checkout.\n");
        exit(3);
    }
    foreach ([$root . '/docs', $docs] as $dir) {
        if (is_link($dir) || (file_exists($dir) && ! is_dir($dir))) {
            throw new RuntimeException('Unsafe repository documentation directory: ' . $dir);
        }
        if (! is_dir($dir) && ! mkdir($dir, 0755) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create documentation directory: ' . $dir);
        }
    }
    foreach ($missing as $name) {
        $source = $fixtures . '/' . $name;
        $target = $docs . '/' . $name;
        // Exclusive creation: do not follow or overwrite any newly appeared path.
        $in = fopen($source, 'rb');
        $out = fopen($target, 'xb');
        if ($in === false || $out === false) {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            throw new RuntimeException('Cannot safely create absent reference: ' . $name);
        }
        try {
            $written = stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
        if ($written === false || ! hash_equals($files[$name], (string) hash_file('sha256', $target))) {
            @unlink($target);
            throw new RuntimeException('New reference failed SHA-256 verification: ' . $name);
        }
        echo "RESTORED + VERIFIED: {$name}\n";
    }
    echo "PINNED OPENAPI RESTORE PASS: originals restored byte-for-byte; no runtime code, test rule or DB changed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "PINNED OPENAPI STOP: " . $error->getMessage() . "\n");
    exit(1);
}

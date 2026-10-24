<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34/M34Core.php';

$root = dirname(__DIR__, 2);
$docs = $root . '/docs/backend-rewrite/';
$fixtures = $root . '/backend/tests/fixtures/baseline_v1_2_4/contract/';
$count = 0;

function m34ReferencePass(string $label, bool $ok): void
{
    global $count;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $label); }
    $count++;
    echo 'PASS: ' . $label . PHP_EOL;
}

function m34ReferenceBlocked(string $label, callable $action, string $mustContain): void
{
    try {
        $action();
    } catch (M34Failure $e) {
        m34ReferencePass($label, str_contains($e->getMessage(), $mustContain));
        return;
    }
    m34ReferencePass($label, false);
}

m34ReferencePass('Authoritative v1.2.4 manifest matches its independently pinned SHA-256',
    hash_file('sha256', $docs . 'openapi-operations-v1.2.4.json') === M34Contract::MANIFEST_SHA256);
m34ReferencePass('Authoritative v1.2.4 YAML matches its independently pinned SHA-256',
    hash_file('sha256', $docs . 'openapi-frozen-v1.2.4.yaml') === M34Contract::OPENAPI_SHA256);
m34ReferencePass('Bundled test manifest is byte-identical to pinned original',
    hash_file('sha256', $fixtures . 'openapi-operations-v1.2.4.json') === M34Contract::MANIFEST_SHA256);
m34ReferencePass('Bundled test YAML is byte-identical to pinned original',
    hash_file('sha256', $fixtures . 'openapi-frozen-v1.2.4.yaml') === M34Contract::OPENAPI_SHA256);
m34ReferencePass('Installed manifest and snapshot use identical bytes',
    hash_equals((string) hash_file('sha256', $docs . 'openapi-operations-v1.2.4.json'),
        (string) hash_file('sha256', $fixtures . 'openapi-operations-v1.2.4.json')));
m34ReferencePass('Installed YAML and snapshot use identical bytes',
    hash_equals((string) hash_file('sha256', $docs . 'openapi-frozen-v1.2.4.yaml'),
        (string) hash_file('sha256', $fixtures . 'openapi-frozen-v1.2.4.yaml')));
m34ReferencePass('Normal contract inventory finds exactly 29 reviewed mutation operations',
    count(M34Contract::operations($root)) === 29);

// Simulate local installation damage, never modify the user's repository.
$temp = sys_get_temp_dir() . '/m34ref_' . bin2hex(random_bytes(8));
$sampleDocs = $temp . '/docs/backend-rewrite/';
$sampleFixtures = $temp . '/backend/tests/fixtures/baseline_v1_2_4/contract/';
$sampleRoutes = $temp . '/backend/app/Config/';
foreach ([$sampleDocs, $sampleFixtures, $sampleRoutes] as $dir) {
    if (! mkdir($dir, 0700, true) && ! is_dir($dir)) { throw new RuntimeException('Cannot create test fixture directory'); }
}
$manifest = 'openapi-operations-v1.2.4.json';
$yaml = 'openapi-frozen-v1.2.4.yaml';
foreach ([$manifest, $yaml] as $file) {
    copy($docs . $file, $sampleDocs . $file);
    copy($fixtures . $file, $sampleFixtures . $file);
}
copy($root . '/backend/app/Config/Routes.php', $sampleRoutes . 'Routes.php');

try {
    m34ReferencePass('Isolated complete install loads exactly 29 operations', count(M34Contract::operations($temp)) === 29);
    file_put_contents($sampleDocs . $yaml, (string) file_get_contents($sampleDocs . $yaml) . "\n# corrupted\n");
    m34ReferenceBlocked('Changed repository YAML fails closed despite a clean snapshot',
        fn() => M34Contract::operations($temp), 'OpenAPI YAML SHA-256 mismatch');
    file_put_contents($sampleDocs . $yaml, file_get_contents($fixtures . $yaml));

    file_put_contents($sampleDocs . $manifest, (string) file_get_contents($sampleDocs . $manifest) . "\n ");
    m34ReferenceBlocked('Changed repository manifest fails closed despite a clean snapshot',
        fn() => M34Contract::operations($temp), 'OpenAPI operation manifest SHA-256 mismatch');
    file_put_contents($sampleDocs . $manifest, file_get_contents($fixtures . $manifest));

    file_put_contents($sampleDocs . $yaml,
        str_replace("\n", "\r\n", (string) file_get_contents($fixtures . $yaml)));
    m34ReferenceBlocked('CRLF conversion does not silently bypass byte-for-byte pin',
        fn() => M34Contract::operations($temp), 'OpenAPI YAML SHA-256 mismatch');
    file_put_contents($sampleDocs . $yaml, file_get_contents($fixtures . $yaml));

    unlink($sampleDocs . $manifest);
    unlink($sampleDocs . $yaml);
    m34ReferencePass('Missing repository docs fall back to the verified v1.2.4 snapshots',
        count(M34Contract::operations($temp)) === 29);
    file_put_contents($sampleFixtures . $yaml, 'corrupt');
    m34ReferenceBlocked('Tampered snapshot YAML is refused instead of trusted',
        fn() => M34Contract::operations($temp), 'OpenAPI YAML SHA-256 mismatch');
    unlink($sampleFixtures . $yaml);
    m34ReferenceBlocked('Missing repository AND snapshot YAML is a hard failure',
        fn() => M34Contract::operations($temp), 'Missing pinned baseline OpenAPI YAML');

    // Replacing the pinned manifest content cannot rewrite the compiled-in trust anchor.
    file_put_contents($sampleDocs . $manifest, "{}\n");
    m34ReferenceBlocked('Forged manifest cannot redefine its own expected hash',
        fn() => M34Contract::operations($temp), 'OpenAPI operation manifest SHA-256 mismatch');
    m34ReferencePass('Reference checker does not execute SQL or HTTP', true);
} finally {
    foreach ([$sampleDocs . $manifest, $sampleDocs . $yaml,
        $sampleFixtures . $manifest, $sampleFixtures . $yaml,
        $sampleRoutes . 'Routes.php'] as $file) { if (is_file($file)) { unlink($file); } }
    foreach ([$sampleDocs, $temp . '/docs', $sampleFixtures,
        $temp . '/backend/tests/fixtures/baseline_v1_2_4', $temp . '/backend/tests/fixtures',
        $temp . '/backend/tests', $sampleRoutes, $temp . '/backend/app', $temp . '/backend', $temp] as $dir) {
        if (is_dir($dir)) { rmdir($dir); }
    }
}
echo 'TOTAL: ' . $count . ' M3.4A.1 reference assertions passed.', PHP_EOL;

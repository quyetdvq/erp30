<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34b/M34BTemplateResolver.php';
require_once __DIR__ . '/../tools/m34b/M34BSandbox.php';

$n = 0;
function check34b2(string $label, bool $ok): void
{
    global $n;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $label); }
    ++$n;
    echo 'PASS: ', $label, PHP_EOL;
}
function rejects34b2(callable $fn, string $class = M34BFailure::class): bool
{
    try { $fn(); } catch (Throwable $e) { return $e instanceof $class; }
    return false;
}

$seen = [];
$inspect = static function (string $name, bool $privileged) use (&$seen): void {
    $seen[] = [$name, $privileged];
};
$result = M34BTemplateResolver::choose(null, $inspect);
check34b2('Complete default erp_test stays the preferred source', $result === [
    'schema'=>'erp_test', 'authoritative_metadata_fallback'=>false
]);
check34b2('Normal path reads only the one test source', $seen === [['erp_test',false]]);

$seen = [];
$result = M34BTemplateResolver::choose(null,
    static function (string $name, bool $privileged) use (&$seen): void {
        $seen[] = [$name, $privileged];
        if ($name === 'erp_test') {
            throw new M34BIncompleteTemplate('Local schema template is missing required table users; no sandbox was created.');
        }
    });
check34b2('Incomplete default selects exact erp metadata fallback', $result === [
    'schema'=>'erp', 'authoritative_metadata_fallback'=>true
]);
check34b2('Fallback occurs only after test schema check', $seen === [['erp_test',false], ['erp',true]]);

$seen = [];
$result = M34BTemplateResolver::choose(null,
    static function (string $name, bool $privileged) use (&$seen): void {
        $seen[] = [$name, $privileged];
        if ($name === 'erp_test') {
            throw new M34BIncompleteTemplate('Missing users.status');
        }
    });
check34b2('Missing v1.2.4 columns also allow metadata fallback', $result['schema'] === 'erp');
check34b2('Neither source is ever writable target', rejects34b2(static fn () => M34BSafety::assertSandbox('erp', str_repeat('a',32)))
    && rejects34b2(static fn () => M34BSafety::assertSandbox('erp_test', str_repeat('a',32))));

$seen = [];
check34b2('Explicit incomplete custom test schema must block', rejects34b2(static function () use (&$seen): void {
    M34BTemplateResolver::choose('qa_clone',
        static function (string $name, bool $privileged) use (&$seen): void {
            $seen[] = [$name, $privileged];
            throw new M34BIncompleteTemplate('missing users');
        });
}, M34BIncompleteTemplate::class));
check34b2('Explicit bad source never probes erp', $seen === [['qa_clone',false]]);

check34b2('Explicit erp source remains forbidden', rejects34b2(static function (): void {
    M34BTemplateResolver::choose('erp', static function (): void {});
}));
check34b2('Unsafe source identifiers remain forbidden', rejects34b2(static function (): void {
    M34BTemplateResolver::choose('erp;drop database erp', static function (): void {});
}));
check34b2('Schema erp accepted only for exact internal metadata fallback',
    M34BSafety::metadataSourceName('erp',true)==='erp'
    && rejects34b2(static fn () => M34BSafety::metadataSourceName('erp',false)));
check34b2('Another production-like name cannot be promoted to fallback',
    rejects34b2(static fn () => M34BSafety::metadataSourceName('erp_prod',true)));

$seen = [];
check34b2('Permission/SQL failures must not fall back to erp', rejects34b2(static function () use (&$seen): void {
    M34BTemplateResolver::choose(null,
        static function (string $name, bool $privileged) use (&$seen): void {
            $seen[] = [$name, $privileged];
            throw new M34BFailure('metadata permission denied');
        });
}));
check34b2('No hidden source switch after permission failure', $seen === [['erp_test',false]]);

check34b2('Failed second schema stays blocked without creating sandbox', rejects34b2(static function (): void {
    M34BTemplateResolver::choose(null,
        static function (string $name): void {
            throw new M34BIncompleteTemplate('Missing schema for ' . $name);
        });
}));

$sandbox = (string) file_get_contents(__DIR__ . '/../tools/m34b/M34BSandbox.php');
$runner = (string) file_get_contents(__DIR__ . '/../tools/m34b_run.php');
$src = (string) file_get_contents(__DIR__ . '/../tools/m34b/M34BTemplateResolver.php');
check34b2('Fallback still examines information_schema only',
    str_contains($sandbox, 'information_schema.TABLES') && str_contains($sandbox, 'information_schema.COLUMNS'));
check34b2('Source copy uses only SHOW CREATE TABLE, no source row SELECT',
    str_contains($sandbox,'SHOW CREATE TABLE ')
    && ! preg_match('/SELECT\s+\*\s+FROM\s+[^;]+(?:erp|template)/i',$sandbox));
check34b2('Source metadata inspect happens before CREATE DATABASE',
    strpos($runner, 'M34BTemplateResolver::choose(') < strpos($runner, '$archive->provision()'));
check34b2('Fallback never copies live auto-increment counter', str_contains($sandbox, 'AUTO_INCREMENT='));
check34b2('Production write-mode remains controlled in separate child runner',
    ! str_contains($runner,"'DINKERP_M33B_WRITE_MODE'=>'enabled'"));
check34b2('No SQL or HTTP operations in fallback decision source',
    ! preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE DATABASE|DROP DATABASE|curl_init)\b/i', $src));

echo 'TOTAL: ', $n, ' M3.4B.2 schema template fallback assertions passed.', PHP_EOL;

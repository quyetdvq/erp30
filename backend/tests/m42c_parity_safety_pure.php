<?php

declare(strict_types=1);

/** M4.2C pure preflight: pinned source, path allowlist and clone-only guardrails. */
require_once dirname(__DIR__) . '/tools/m42c/M42CParity.php';

$root = dirname(__DIR__, 2);
$count = 0;
$check = static function (bool $passed, string $description) use (&$count): void {
    if (!$passed) throw new RuntimeException('M4.2C offline safety FAIL: ' . $description);
    ++$count;
};
$ops = M42CPlan::operations($root);
$routes = file_get_contents($root . '/backend_baseline/app/Config/Routes.php');
if (!is_string($routes)) throw new RuntimeException('Missing pinned baseline route source');
$canonicalLf = str_replace("\r\n", "\n", $routes);
$canonicalCrlf = str_replace("\n", "\r\n", $canonicalLf);
$check(M42CPlan::pinnedBaselineRoutes($canonicalLf), 'Original baseline Routes.php exact LF hash');
$check(M42CPlan::pinnedBaselineRoutes($canonicalCrlf), 'Original baseline Routes.php exact Windows CRLF hash');
$check(!M42CPlan::pinnedBaselineRoutes($canonicalLf . "\n"), 'Extra newline changes baseline hash');
$check(!M42CPlan::pinnedBaselineRoutes($canonicalCrlf . ' '), 'Appended content changes Windows baseline hash');
$changedOnce = preg_replace('/\r\n/', "\n", $canonicalCrlf, 1);
$check(is_string($changedOnce) && !M42CPlan::pinnedBaselineRoutes($changedOnce), 'Mixed LF/CRLF baseline fails closed');
$check(count($ops) === 14, 'Fourteen frozen M4 route names and statuses');
$check(count(M42CPlan::readNames($ops)) === 8, 'Eight read-only/dry-run operations');
$check(count(M42CPlan::writeNames($ops)) === 6, 'Six mutation operations, denial-only in this milestone');
$readNames = M42CPlan::readNames($ops);
$check(in_array('validateNumberingSeriesConfig', $readNames, true), 'Validate-config correctly classified read-only');
$check(!in_array('resetDisplaySettings', $readNames, true), 'Reset classified mutation');
$check(M42CPlan::pathAllowed('listDisplaySettingValues', '/api/v1/display-settings/values?context_type=SYSTEM', $ops), 'Exact SYSTEM-only values query');
$check(!M42CPlan::pathAllowed('listDisplaySettingValues', '/api/v1/display-settings/values?organization_id=9', $ops), 'Unexpected values query refused');
$check(!M42CPlan::pathAllowed('listDisplaySettingValues', '/api/v1/display-settings/values', $ops), 'Missing context query refused for prepared read');
$check(M42CPlan::pathAllowed('getNumberingSeries', '/api/v1/resources/10/numbering-series/25', $ops), 'Exact numeric ID route allowed');
$check(!M42CPlan::pathAllowed('getNumberingSeries', '/api/v1/resources/10/numbering-series/0', $ops), 'Zero/invalid resource ID refused');
$check(!M42CPlan::pathAllowed('getNumberingSeries', '/api/v1/resources/10/numbering-series/20/../settings', $ops), 'Traversal refused');
$check(!M42CPlan::pathAllowed('listNumberingSeries', '/api/v1/resources/25/numbering-series?foo=bar', $ops), 'Arbitrary query refused');
$check(!M42CPlan::pathAllowed('executeBusinessWorkflowAction', '/api/v1/business-workflow/policy/10/actions/archive', $ops), 'Unreviewed Resource type refused');
$check(M42CPlan::pathAllowed('executeBusinessWorkflowAction', '/api/v1/business-workflow/organization/10/actions/archive', $ops), 'Organization transition route approved');
foreach (M42CPlan::writeNames($ops) as $name) {
    $path = M42CPlan::invalidWritePath($name, $ops);
    $check(M42CPlan::pathAllowed($name, $path, $ops), 'Invalid-target mutation route remains anchored: ' . $name);
}
$templates = $root . '/backend/tests/fixtures';
$read = json_decode((string) file_get_contents($templates . '/m42c_read_cases.template.json'), true, 512, JSON_THROW_ON_ERROR);
$check(count($read['scenarios'] ?? []) === 8, 'Eight documented manual read scenarios');
$check(count(array_filter($read['scenarios'], static fn ($row) => ($row['ready'] ?? null) === true)) === 0, 'No prepared case silently marked ready');
try {
    M42CParity::checkedReadCases($templates . '/m42c_read_cases.template.json', $ops);
    throw new RuntimeException('The unready example was accepted');
} catch (M34Failure $expected) {
    $check(str_contains($expected->getMessage(), 'NOT automatically runnable'), 'Unready cases fail closed without SQL');
}
$readReady = $read;
foreach ($readReady['scenarios'] as &$row) {
    $row['ready'] = true;
    $row['path'] = str_replace(['REPLACE_RESOURCE_ID','REPLACE_SERIES_ID','REPLACE_ORGANIZATION_ID'], ['5','8','10'], $row['path']);
}
unset($row);
$tmp = tempnam(sys_get_temp_dir(), 'm42c_');
if ($tmp === false) throw new RuntimeException('Cannot create temporary test fixture');
try {
    file_put_contents($tmp, json_encode($readReady, JSON_THROW_ON_ERROR));
    $checked = M42CParity::checkedReadCases($tmp, $ops);
    $check(count($checked) === 8, 'Exactly 8 validated ready cases allowed');
    $mutated = $readReady;
    $mutated['scenarios'][4]['path'] = '/api/v1/resources/5/numbering-series/actions/validate-config?debug=1';
    file_put_contents($tmp, json_encode($mutated, JSON_THROW_ON_ERROR));
    try {
        M42CParity::checkedReadCases($tmp, $ops);
        throw new RuntimeException('Unsafe dry-run path was accepted');
    } catch (M34Failure) {
        $check(true, 'Unsafe dry-run query fails closed');
    }
    $mutated = $readReady;
    $mutated['scenarios'][4]['body'] = null;
    file_put_contents($tmp, json_encode($mutated, JSON_THROW_ON_ERROR));
    try {
        M42CParity::checkedReadCases($tmp, $ops);
        throw new RuntimeException('Dry-run missing body accepted');
    } catch (M34Failure) {
        $check(true, 'Missing dry-run JSON object fails closed');
    }
} finally {
    unlink($tmp);
}
$config = json_decode((string) file_get_contents($templates . '/m42c_clones.config.example.json'), true, 512, JSON_THROW_ON_ERROR);
$check(($config['purpose'] ?? null) === 'M34_DISPOSABLE_CLONES', 'Reuses mandatory M34 clone guards');
$check(($config['baseline']['dsn'] ?? null) !== ($config['v2']['dsn'] ?? null), 'Distinct required clone names');
$check(!isset($config['baseline']['db_password']) && !isset($config['v2']['db_password']), 'Template stores no DB password');
$entry = (string) file_get_contents($root . '/backend/tools/m42c_parity.php');
$runner = (string) file_get_contents($root . '/backend/tools/m42c/M42CParity.php');
$db = (string) file_get_contents($root . '/backend/tools/m42c/M42CCloneDb.php');
$transport = (string) file_get_contents($root . '/backend/tools/m42c/M42CClient.php');
$check(str_contains($entry, 'M34Safety::config') && str_contains($entry, 'M34_CLONE_ONLY'), 'Explicit clone-only confirmation required');
$check(str_contains($db, 'm34_clone_guard') && str_contains($db, 'SELECT DATABASE()'), 'SQL clone marker and selected DB attested');
$check(str_contains($runner, "'authorized_mutation_replay' => 'NOT_TESTED'"), 'No false positive mutation parity claim');
$check(str_contains($runner, 'M42CCloneDb::changed($before, $after)'), 'Per-probe row fingerprints required');
$check(str_contains($runner, "'production_write' => 'BLOCKED'"), 'No production write release');
$check(str_contains($transport, 'context_type=SYSTEM') && str_contains($transport, 'M34Safety::assertLocalUrl'), 'Loopback transport and restricted read query');
$check(!preg_match('/\b(?:DROP|TRUNCATE|ALTER|CREATE)\s+(?:DATABASE|TABLE)\b/i', $db . $runner . $entry), 'No schema mutation commands in tools');
echo 'PASS: ' . $count . ' M4.2C pinned source, URL allowlist, clone isolation, ready-case and false-claim assertions.' . PHP_EOL;

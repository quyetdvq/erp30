<?php

declare(strict_types=1);

/**
 * M3.4D independent acceptance report. No HTTP/SQL calls and NEVER enables
 * mutations. Optional --integration-report consumes the exact JSON emitted by
 * a successful prior M3.4B sandbox run, without re-running the sandbox.
 */
require_once __DIR__.'/m34d/M34DAcceptance.php';

$root = dirname(__DIR__, 2);
$args = array_slice($argv, 1);
$provided = null;
$exit = 0;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--integration-report=')) {
        if ($provided !== null) { fwrite(STDERR, "Duplicate integration report.\n"); exit(2); }
        $provided = substr($arg, strlen('--integration-report='));
        if ($provided === '') { fwrite(STDERR, "Empty integration report path.\n"); exit(2); }
    } elseif ($arg !== '--offline' || count($args) > 1) {
        fwrite(STDERR, "Usage: php backend/tools/m34d_accept.php [--offline | --integration-report=PATH]\n");
        exit(2);
    }
}
$gates = M34DAcceptance::source($root);
foreach ($gates as $name => $gate) {
    printf("M3.4D %-20s %s\n", $name, $gate['status']);
    if ($gate['status'] !== 'PASS') $exit = 1;
}
$integration = ['status'=>'NOT_SUPPLIED','level'=>'LOCAL_MARIADB_SANDBOX',
    'reason'=>'A M3.4B sandbox report was not supplied; RUN INTEGRATION verifies this gate.'];
$integrationInput = null;
if ($provided !== null) {
    try {
        $reportsDirectory = realpath($root.'/backend/writable/m34b/reports');
        $actual = realpath($provided);
        if ($reportsDirectory === false || $actual === false
            || dirname($actual) !== $reportsDirectory
            || !preg_match('/\Am34b_[0-9]{8}_[0-9]{6}_[0-9a-f]{8}\.json\z/D', basename($actual))
            || !is_file($actual) || filesize($actual) > 5*1024*1024) {
            throw new M34DViolation('Integration evidence must be a regular M3.4B report under backend/writable/m34b/reports/');
        }
        $payload = json_decode((string) file_get_contents($actual), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) throw new M34DViolation('Integration report root must be JSON object');
        $ops = M34Contract::operations($root);
        $integration = M34DAcceptance::integrationEvidence($payload, $ops, M34DSourceFingerprint::hash($root));
        $integrationInput = ['file'=>basename($actual),'sha256'=>hash_file('sha256',$actual),
            'reported_utc'=>$payload['timestamp_utc'] ?? null];
    } catch (Throwable $e) {
        $integration = ['status'=>'FAIL','level'=>'LOCAL_MARIADB_SANDBOX',
            'reason'=>substr($e->getMessage(),0,400)];
        $exit = 1;
    }
}
$gates['LOCAL_SANDBOX_HTTP_DB'] = $integration;
$gates['LIVE_BASELINE_HTTP_PARITY'] = ['status'=>'NOT_TESTED','level'=>'INDEPENDENT_BASELINE',
    'reason'=>'No separately running baseline v1.2.4 compared for HTTP status, error body, validation, side effects'];
$gates['TRANSACTION_ISOLATION_PARITY'] = ['status'=>'NOT_TESTED','level'=>'INDEPENDENT_BASELINE',
    'reason'=>'Local SQL atomicity does not establish baseline concurrency, lock ordering or isolation-level parity'];
$gates['ALL_70_LIVE_RESPONSE_PARITY'] = ['status'=>'NOT_TESTED','level'=>'INDEPENDENT_BASELINE',
    'reason'=>'All 70 routes match frozen contract statically; only 29 mutations have local sandbox HTTP evidence'];
$allPassed = $exit === 0 && array_reduce(array_slice($gates, 0, 6),
    static fn (bool $carry, array $g): bool => $carry && $g['status'] === 'PASS', true);
$verdict = !$allPassed ? 'FAIL' : ($provided === null
    ? 'SOURCE_ACCEPTED_INTEGRATION_NOT_SUPPLIED' : 'LOCAL_INTEGRATION_ACCEPTED_LIVE_PARITY_OPEN');
$summary = [
    'milestone'=>'M3.4D', 'generated_utc'=>gmdate('c'), 'verdict'=>$verdict,
    'production_cutover'=>'BLOCKED',
    'production_write_mode_required'=>'off',
    'baseline_live_parity'=>'NOT_TESTED',
    'module_dependency'=>'PINNED_SOURCE_GRAPH',
    'backend_source_sha256'=>M34DSourceFingerprint::hash($root),
    'source_gates_passed'=>count(array_filter(array_slice($gates,0,6),
        static fn (array $g):bool => $g['status']==='PASS')),
    'source_gates_total'=>6,
    'gates'=>$gates,
    'integration_source'=>$integrationInput,
    'open_gates'=>['LIVE_BASELINE_HTTP_PARITY','TRANSACTION_ISOLATION_PARITY','ALL_70_LIVE_RESPONSE_PARITY'],
    'notes'=>[
        'PASS is limited to source/golden-master gates and, when supplied, separate local sandbox evidence.',
        'This tool cannot prove or grant permission to enable mutations on a live service.',
        'No SQL, HTTP request or local production configuration is executed or edited here.',
    ],
];
$out = $root.'/backend/writable/m34d/reports';
if (!is_dir($out) && !mkdir($out,0700,true) && !is_dir($out)) {
    fwrite(STDERR, "M3.4D: unable to create report directory.\n"); exit(2);
}
$path = $out.'/m34d_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
try {
    $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json."\n", LOCK_EX) === false) throw new RuntimeException('Cannot save report');
    @chmod($path, 0600);
} catch (Throwable) {
    fwrite(STDERR, "M3.4D: unable to save acceptance report.\n"); exit(2);
}
printf("M3.4D: %s; source gates %d/6; local HTTP/DB=%s; LIVE BASELINE=NOT_TESTED; production=BLOCKED.\n",
    $verdict, $summary['source_gates_passed'], $integration['status']);
echo 'M3.4D report: '.$path.PHP_EOL;
exit($exit);

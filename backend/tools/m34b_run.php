<?php

declare(strict_types=1);

/**
 * M3.4B: one-command local MariaDB integration using a synthetic database.
 * Run: php backend/tools/m34b_run.php
 * Offline-only fallback: php backend/tools/m34b_run.php --offline
 * On failure, the sandbox is RETAINED for inspection; on complete PASS it is
 * automatically removed unless --keep is provided.
 *
 * Never pass production credentials via command-line args or JSON files.
 */
require_once __DIR__.'/m34b/M34BOffline.php';
require_once __DIR__.'/m34b/M34BSandbox.php';
require_once __DIR__.'/m34b/M34BTemplateResolver.php';
require_once __DIR__.'/m34b/M34BIntegration.php';
require_once __DIR__.'/m34d/M34DSourceFingerprint.php';

$root = dirname(__DIR__,2);
$options = array_slice($argv,1);
foreach ($options as $option) {
    if (! in_array($option,['--offline','--keep'],true)) {
        fwrite(STDERR, "Usage: php backend/tools/m34b_run.php [--offline] [--keep]\n"); exit(2);
    }
}
$onlyOffline = in_array('--offline',$options,true);
$keep = in_array('--keep',$options,true);
$report = [
    'milestone'=>'M3.4B', 'timestamp_utc'=>gmdate('c'),
    'status'=>'BLOCKED_ENV', 'mode'=>$onlyOffline?'OFFLINE':'AUTO_LOCAL_SANDBOX',
    'production_mutations'=>'NEVER', 'baseline_live_parity'=>'NOT_TESTED',
    'backend_source_sha256'=>M34DSourceFingerprint::hash(dirname(__DIR__, 2)),
];
$exit = 2;
$archive = null;
try {
    $ops = M34Contract::operations($root);
    M34BPlan::assertCoverage($ops);
    fwrite(STDOUT,"M3.4B: pinned 29/29 mutation operations.\n");
    $offline = M34BOffline::run($root);
    $report['offline'] = $offline;
    echo 'M3.4B OFFLINE: ' . $offline['passed'] . '/' . $offline['total'] . " suites PASS.\n";
    // Name failing suites on the console. JSON retains the bounded failure
    // tail for diagnosis; do not echo raw test output or secrets here.
    foreach ($offline['failed'] as $failure) {
        $safeTest = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename((string)$failure['test']));
        fwrite(STDERR, 'M3.4B OFFLINE FAILED SUITE: '.$safeTest
            .' (exit '.(int)$failure['exit']."). See JSON report for details.\n");
    }

    if ($offline['failed'] !== []) {
        $report['status'] = 'FAIL_OFFLINE';
        $exit = 1;
    } elseif ($onlyOffline) {
        $report['status'] = 'PASS_OFFLINE_ONLY';
        $exit = 0;
    } else {
        if (! is_dir($root.'/backend/vendor') || ! is_file($root.'/backend/vendor/autoload.php')) {
            throw new M34BFailure('Composer vendor/ is missing in backend/. Install dependencies before real HTTP tests.');
        }
        $explicitSource = getenv('M34B_SOURCE_SCHEMA') ?: null;
        $user = getenv('M34B_MYSQL_USER');
        if (! is_string($user) || $user === '') { $user = 'root'; }
        $password = getenv('M34B_MYSQL_PASSWORD');
        if (! is_string($password)) { $password = ''; }
        $portRaw = getenv('M34B_MYSQL_PORT') ?: '3306';
        if (! preg_match('/\A[0-9]{1,5}\z/D', (string)$portRaw)) {
            throw new M34BFailure('M34B_MYSQL_PORT must be a valid numeric local port');
        }
        $port = (int)$portRaw;
        M34BSafety::mysqlEndpoint('127.0.0.1',$port);
        $nonce = bin2hex(random_bytes(16));
        $name = M34BSafety::freshName($nonce);
        $server = M34BSandbox::connect('127.0.0.1', $port, $user, $password);
        $choice = M34BTemplateResolver::choose($explicitSource,
            static function (string $name, bool $metadataFallback) use ($server): void {
                M34BSandbox::inspectTemplate($server, $name, $metadataFallback);
            });
        $source = $choice['schema'];
        $fallback = $choice['authoritative_metadata_fallback'];
        $report['template_schema'] = $source;
        $report['authoritative_metadata_only_fallback'] = $fallback;
        $report['expected_sandbox'] = $name;
        if ($fallback) {
            echo "M3.4B: erp_test has incomplete structure; using local erp table DEFINITIONS only.\n";
            echo "M3.4B: source erp is READ-ONLY metadata; NO business rows copied or source writes.\n";
        }
        echo "M3.4B: checking schema-only template {$source}, creating {$name}.\n";
        $archive = new M34BSandbox($server, $source, $name, $nonce, $fallback);
        $archive->provision();
        $report['sandbox_table_prefix'] = $archive->tablePrefix();
        echo 'M3.4B: sandbox physical table prefix: '
            . ($archive->tablePrefix() === '' ? '(none)' : $archive->tablePrefix()) . ".\n";
        $fixture = $archive->seed();
        $runDirectory = $root . '/backend/writable/m34b/run_' . substr($nonce,0,16);
        if (! is_dir($runDirectory) && ! mkdir($runDirectory,0700,true)) {
            throw new M34BFailure('Cannot create local-only report/session directory');
        }
        $runner = new M34BIntegration($root,$archive,$ops,$fixture,$user,$password,$port,$runDirectory);
        $real = $runner->run();
        $report['integration'] = $real;
        $report['status'] = $real['verdict'];
        $exit = $real['verdict']==='PASS' ? 0 : 1;
        if ($exit === 0 && ! $keep) {
            $archive->dropOwnSandbox();
            $report['integration']['sandbox_retained_for_review'] = false;
            $report['integration']['sandbox_cleaned_after_success'] = true;
        }
        echo 'M3.4B REAL: ' . $real['successful_positive_mutations'] . '/29 successful, '
            . $real['unauthenticated_bilingual_probes'] . '/58 unauth, '
            . $real['invalid_csrf_probes'] . '/29 CSRF; rollback=' . $real['real_db_rollback'] . "\n";
    }
} catch (Throwable $e) {
    $report['status'] = 'BLOCKED_ENV';
    $report['reason'] = M34BSafety::sanitizedException($e);
    if ($archive instanceof M34BSandbox && $archive->retained()) {
        $report['retained_sandbox'] = $archive->name;
        $report['note'] = 'This run created a disposable schema which is retained for inspection.';
    }
    fwrite(STDERR,'M3.4B BLOCKED: '.$report['reason']."\n");
    $exit = 2;
} finally {
    $directory = $root.'/backend/writable/m34b/reports';
    if (! is_dir($directory)) { @mkdir($directory,0700,true); }
    if (is_dir($directory) && is_writable($directory)) {
        $filename = $directory.'/m34b_'.gmdate('Ymd_His').'_'.substr(bin2hex(random_bytes(4)),0,8).'.json';
        @file_put_contents($filename,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        @chmod($filename,0600);
        echo 'M3.4B report: '.$filename.PHP_EOL;
    }
}
exit($exit);

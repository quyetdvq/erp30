<?php

declare(strict_types=1);

/**
 * Inert-by-default M6.3B.2 DEV delegated Shield User Avatar QA.
 * php tools/m63b2_dev_admin_avatar.php --target-user-id=N
 * php tools/m63b2_dev_admin_avatar.php --target-user-id=N --preflight
 * php tools/m63b2_dev_admin_avatar.php --target-user-id=N --apply --confirm=DEV_ADMIN_AVATAR_QA
 * Uses two independent logins and no HTTP requests to non-loopback hosts.
 */
require_once __DIR__.'/m63b2/M63B2DevDelegation.php';

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/\A--(target-user-id|confirm)=(.+)\z/D', $arg, $match) === 1) {
        if (isset($options[$match[1]])) {fwrite(STDERR, "Duplicate QA option.\n"); exit(2);}
        $options[$match[1]] = $match[2];
    } elseif (in_array($arg, ['--apply','--preflight'], true) && !isset($options[substr($arg, 2)])) {
        $options[substr($arg, 2)] = true;
    } else {fwrite(STDERR, "Unknown/duplicate QA option.\n"); exit(2);}
}
try {
    $id = M63B1AvatarPlan::userId((string)($options['target-user-id'] ?? ''));
    $apply = isset($options['apply']);
    $preflight = isset($options['preflight']);
    if ($preflight && ($apply || isset($options['confirm']))) {
        throw new InvalidArgumentException('Read-only and write modes cannot overlap.');
    }
    if (!$apply && !$preflight) {
        if (isset($options['confirm'])) throw new InvalidArgumentException('Dry-run confirmation rejected.');
        echo 'M6.3B.2 PLAN ONLY: no HTTP or SQL operations performed.'.PHP_EOL;
        echo 'Target Shield User id='.$id.'; requires a distinct admin and active, avatar-empty disposable owner.'.PHP_EOL;
        echo 'Use --preflight for read-only SELECT/login/GET. --apply --confirm=DEV_ADMIN_AVATAR_QA will mutate target Avatar.'.PHP_EOL;
        exit(0);
    }
    if ($apply && ($options['confirm'] ?? null) !== 'DEV_ADMIN_AVATAR_QA') {
        throw new InvalidArgumentException('Explicit delegated Avatar mutation confirmation required.');
    }
    $ciEnv = strtolower(trim((string)(getenv('CI_ENVIRONMENT') ?: '')));
    $appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: '')));
    if ($ciEnv === 'production' || $appEnv === 'production') {
        throw new InvalidArgumentException('DEV QA is forbidden in production.');
    }
    $environment = $ciEnv !== '' ? $ciEnv : $appEnv;
    if ($apply && ($ciEnv !== '' && $appEnv !== '' && $ciEnv !== $appEnv
        || !in_array($environment, ['development', 'testing'], true)
        || getenv('M63B2_DEV_QA_WRITE') !== 'YES'
        || preg_match('/\A[A-Za-z0-9_]{1,64}\z/D',
            (string)(getenv('M63B2_EXPECTED_DATABASE') ?: '')) !== 1)) {
        throw new InvalidArgumentException('Explicit DEV environment, write opt-in and database pin are required.');
    }
} catch (Throwable) {
    fwrite(STDERR, 'M6.3B.2 BLOCKED: invalid target, mode or environment; no SQL/HTTP performed.'.PHP_EOL);
    exit(2);
}
$report = ['milestone' => 'M6.3B.2', 'timestamp_utc' => gmdate('c'),
    'target_user_id' => $id, 'status' => 'BLOCKED_ENV',
    'mode' => $apply ? 'EXPLICIT_DELEGATED_AVATAR_WRITE' : 'READ_ONLY_PREFLIGHT',
    'production_acceptance' => 'NOT_CLAIMED', 'CAS_live_race' => 'NOT_TESTED',
    'independent_baseline_parity' => 'NOT_TESTED'];
$exit = 2;
try {
    $result = (new M63B2DevDelegation())->run($id,
        (string)(getenv('M63B2_ADMIN_USER') ?: ''),
        (string)(getenv('M63B2_ADMIN_PASSWORD') ?: ''),
        (string)(getenv('M63B2_TARGET_USER') ?: ''),
        (string)(getenv('M63B2_TARGET_PASSWORD') ?: ''), $apply);
    $report['integration'] = $result;
    $report['status'] = $result['verdict'];
    $exit = in_array($result['verdict'],
        ['PASS_DEV_ADMIN_AVATAR_PREFLIGHT','PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL'], true) ? 0 : 1;
    echo 'M6.3B.2 DEV ADMIN AVATAR: '.$result['verdict'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.3B.2 checks: distinct_admin='.(int)$result['admin_is_distinct']
        .' owner='.(int)$result['owner_verified'].' sql='.(int)$result['sql_verified']
        .' gd='.(int)$result['gd_all_codecs'].PHP_EOL;
    if ($apply) {
        echo 'M6.3B.2 PBAC: admin_probe='.$result['admin_probe'].' owner_denied='.$result['owner_denied'].PHP_EOL;
        echo 'M6.3B.2 HTTP: UPLOAD='.($result['upload_http'] ?? 'NOT_SENT')
            .' REPLACE='.($result['replace_http'] ?? 'NOT_SENT')
            .' DELETE='.($result['delete_http'] ?? 'NOT_SENT').PHP_EOL;
        echo 'M6.3B.2 FILES: first='.($result['static_first'] ?? 'NOT_TESTED')
            .' second='.($result['static_second'] ?? 'NOT_TESTED')
            .' old_gone='.($result['old_gone'] ?? 'NOT_TESTED')
            .' removed='.(int)$result['physical_cleaned']
            .' final_sql_empty='.(int)$result['final_sql_empty'].PHP_EOL;
        if ($exit !== 0) {
            echo 'M6.3B.2 WARNING: Partial Avatar mutation may have committed; inspect SQL/FS before any repeat.'.PHP_EOL;
        }
    }
} catch (Throwable $error) {
    $report['failure_class'] = get_class($error);
    fwrite(STDERR, 'M6.3B.2 BLOCKED: local transport or read-only audit unavailable.'.PHP_EOL);
}
$dir = dirname(__DIR__).'/writable/m63b2/reports';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
if (is_dir($dir) && is_writable($dir)) {
    $path = $dir.'/m63b2_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    @chmod($path, 0600);
    echo 'M6.3B.2 report: '.$path.PHP_EOL;
}
exit($exit);

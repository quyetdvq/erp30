<?php

declare(strict_types=1);

/**
 * M6.3C DEV-only Avatar CAS race runner; inert by default.
 * php tools/m63c_dev_avatar_race.php --target-user-id=N
 * php tools/m63c_dev_avatar_race.php --target-user-id=N --preflight
 * php tools/m63c_dev_avatar_race.php --target-user-id=N --apply --confirm=DEV_AVATAR_RACE_QA
 */
require_once __DIR__.'/m63c/M63CAvatarRace.php';
$options = [];
try {
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/\A--(target-user-id|confirm)=(.+)\z/D', $argument, $match) === 1) {
            if (isset($options[$match[1]])) throw new InvalidArgumentException('Duplicate argument.');
            $options[$match[1]] = $match[2];
        } elseif (in_array($argument, ['--preflight','--apply','--mysql-check'], true)) {
            $key = substr($argument, 2);
            if (isset($options[$key])) throw new InvalidArgumentException('Duplicate mode.');
            $options[$key] = true;
        } else {
            throw new InvalidArgumentException('Unknown argument.');
        }
    }
    $target = M63B1AvatarPlan::userId((string)($options['target-user-id'] ?? ''));
    $apply = isset($options['apply']);
    $preflight = isset($options['preflight']);
    $mysqlCheck = isset($options['mysql-check']);
    if ((int)$apply + (int)$preflight + (int)$mysqlCheck > 1) {
        throw new InvalidArgumentException('Overlapping modes.');
    }
    $confirmation = $options['confirm'] ?? null;
    M63CRacePlan::guardEnvironment($apply, $confirmation);
    if (!$apply && !$preflight && !$mysqlCheck) {
        echo 'M6.3C PLAN ONLY: no HTTP, SQL or filesystem operations performed.'.PHP_EOL;
        echo 'Target User id='.$target.'; two independently authenticated local HTTPS writers; MySQL row lock telemetry required.'.PHP_EOL;
        echo 'Use --preflight for SELECT/login/GET only; --apply requires dedicated DEV fixture, backup and opt-in.'.PHP_EOL;
        exit(0);
    }
    $database = M63CRacePlan::database((string)(getenv('M63C_EXPECTED_DATABASE') ?: ''));
    putenv('M63B2_EXPECTED_DATABASE='.$database);
} catch (Throwable) {
    fwrite(STDERR, "M6.3C BLOCKED: invalid mode, target or DEV environment; no SQL/HTTP operations.\n");
    exit(2);
}
$report = ['milestone' => 'M6.3C', 'timestamp_utc' => gmdate('c'),
    'target_user_id' => $target, 'database' => $database,
    'mode' => $apply ? 'EXPLICIT_RACE_WRITE'
        : ($mysqlCheck ? 'READ_ONLY_MYSQL_ATTEST' : 'READ_ONLY_PREFLIGHT'),
    'status' => 'BLOCKED_ENV', 'production_cutover' => 'BLOCKED'];
$exit = 2;
try {
    $race = new M63CAvatarRace();
    $integration = $mysqlCheck
        ? $race->mysqlCheck($target, $database)
        : $race->run($target, $database,
            (string)(getenv('M63C_ADMIN_USER') ?: ''),
            (string)(getenv('M63C_ADMIN_PASSWORD') ?: ''),
            (string)(getenv('M63C_TARGET_USER') ?: ''),
            (string)(getenv('M63C_TARGET_PASSWORD') ?: ''), $apply);
    $report['integration'] = $integration;
    $report['status'] = $integration['verdict'];
    $exit = in_array($integration['verdict'],
        ['PASS_DEV_AVATAR_RACE_PREFLIGHT', 'PASS_DEV_AVATAR_TWO_HTTP_WRITER_CAS',
            'PASS_DEV_AVATAR_MYSQL_ATTEST_READ_ONLY'], true) ? 0 : 1;
    if ($mysqlCheck) {
        echo 'M6.3C MYSQL READ-ONLY: '.$integration['verdict'].'; phase='.$integration['phase'].PHP_EOL;
    } else {
        echo 'M6.3C AVATAR QA: '.$integration['verdict'].'; phase='.$integration['phase'].PHP_EOL;
        echo 'M6.3C checks: preflight='.(int)$integration['preflight']
            .' sessions='.(int)$integration['two_independent_sessions']
            .' lock_waits='.$integration['lock_wait_connections']
            .' winner_http='.($integration['winner_http'] ?? 'NOT_TESTED')
            .' loser_http='.($integration['loser_http'] ?? 'NOT_TESTED').PHP_EOL;
        echo 'M6.3C cleanup: loser_staged_clean='.(int)$integration['loser_staged_file_cleaned']
            .' final_sql_empty='.(int)$integration['final_sql_empty']
            .' winner_file_deleted='.(int)$integration['winning_file_deleted'].PHP_EOL;
    }
    if (isset($integration['failure_code'])) {
        echo 'M6.3C MySQL diagnosis: step='.($integration['mysql_attest_step'] ?? 'unknown')
            .' code='.$integration['failure_code'].PHP_EOL;
    }
    if ($apply && $exit !== 0) {
        if (!empty($integration['partial_state_review_required'])) {
            echo 'M6.3C WARNING: Partial avatar write possible. Inspect SQL/files before any retry.'.PHP_EOL;
        } else {
            echo 'M6.3C NO AVATAR POST DISPATCHED: failure before race HTTP barrier;'
                .' still verify current SQL state.'.PHP_EOL;
        }
    }
} catch (Throwable) {
    $report['status'] = 'BLOCKED_ENV';
    $report['failure_class'] = 'QA_RUNTIME_FAILURE';
    fwrite(STDERR, "M6.3C BLOCKED: local prerequisites or audit unavailable.\n");
}
$folder = __DIR__.'/../writable/m63c/reports';
if (!is_dir($folder)) @mkdir($folder, 0700, true);
if (is_dir($folder) && is_writable($folder)) {
    $path = $folder.'/m63c_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode($report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    @chmod($path, 0600);
    echo 'M6.3C report: '.$path.PHP_EOL;
}
exit($exit);

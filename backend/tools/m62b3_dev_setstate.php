<?php

declare(strict_types=1);

/**
 * M6.2B.3. Existing DEV fixture ONLY, never creates/deletes Products.
 * php tools/m62b3_dev_setstate.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234
 * php tools/m62b3_dev_setstate.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234 --preflight
 * php tools/m62b3_dev_setstate.php --marker=M62B2_XXXXXXXXXXXX --product-id=1234 --target-state=ACTIVE --apply --confirm=DEV_PRODUCT_SETSTATE
 * Run `php spark m62b3:plan MARKER ID` to resolve the actual Lifecycle target first.
 */
require_once __DIR__.'/m62b3/M62B3DevState.php';

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/\A--(marker|product-id|target-state|confirm)=(.+)\z/D', $arg, $match) === 1) {
        if (isset($options[$match[1]])) { fwrite(STDERR, "Duplicate DEV QA option.\n"); exit(2); }
        $options[$match[1]] = $match[2];
    } elseif (($arg === '--apply' || $arg === '--preflight') && !isset($options[substr($arg, 2)])) {
        $options[substr($arg, 2)] = true;
    } else { fwrite(STDERR, "Unknown/duplicate DEV QA option.\n"); exit(2); }
}
try {
    $marker = M62B3Fixture::marker((string)($options['marker'] ?? ''));
    $id = M62B3Fixture::id((string)($options['product-id'] ?? ''));
    $preflight = isset($options['preflight']);
    $apply = isset($options['apply']);
    if ($preflight && ($apply || isset($options['target-state']) || isset($options['confirm']))) {
        throw new InvalidArgumentException('Read-only preflight cannot be combined with writes.');
    }
    if (!$apply && !$preflight) {
        if (isset($options['target-state']) || isset($options['confirm'])) {
            throw new InvalidArgumentException('Dry-run does not accept mutation flags.');
        }
        echo 'M6.2B.3 PLAN ONLY: no HTTP, SQL, or mutations executed.'.PHP_EOL;
        echo 'Fixture marker='.$marker.' id='.$id.PHP_EOL;
        echo 'Run: php spark m62b3:plan '.$marker.' '.$id.PHP_EOL;
        echo 'Then use --preflight to verify the authenticated actor without mutating Product.'.PHP_EOL;
        exit(0);
    }
    $target = null;
    if ($apply) {
        if (($options['confirm'] ?? null) !== 'DEV_PRODUCT_SETSTATE') {
            throw new InvalidArgumentException('Explicit DEV SETSTATE confirmation required.');
        }
        $target = M62B3Fixture::target((string)($options['target-state'] ?? ''));
    }
    // This script does not bootstrap CI4; honor explicit PHP/CI environment flags.
    $environment = strtolower((string)(getenv('CI_ENVIRONMENT') ?: getenv('APP_ENV') ?: 'development'));
    if ($environment === 'production') {
        throw new InvalidArgumentException('Production is never eligible for DEV QA.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, "M6.2B.3 BLOCKED: invalid fixture/options/confirmation; no requests performed.\n");
    exit(2);
}

$report = ['milestone'=>'M6.2B.3', 'timestamp_utc'=>gmdate('c'),
    'production_acceptance'=>'NOT_CLAIMED', 'sql_verification'=>'NOT_TESTED',
    'mode'=>$apply?'EXPLICIT_DEV_SETSTATE':'HTTP_READ_ONLY_PREFLIGHT'];
$exit=1;
try {
    $result=(new M62B3DevState())->run(
        $marker, $id,
        (string)(getenv('M62B_DEV_USER') ?: ''),
        (string)(getenv('M62B_DEV_PASSWORD') ?: ''),
        $target,
    );
    $report['integration']=$result;
    $report['status']=$result['verdict'];
    $exit=in_array($result['verdict'],['PASS_DEV_PRODUCT_STATE_PREFLIGHT','PASS_DEV_PRODUCT_SETSTATE_HTTP'],true)?0:1;
    echo 'M6.2B.3 DEV STATE: '.$report['status'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.2B.3 fixture: marker='.$marker.' id='.$id.' from='.($result['from_status']??'-')
        .' target='.($result['target_status']??'READ_ONLY').PHP_EOL;
    echo 'M6.2B.3 HTTP: GET_BEFORE='.($result['initial_get_status']??'NOT_SENT')
        .' authorized='.(int)$result['allowed_action']
        .' POST='.($result['http_post_status']??'NOT_SENT')
        .' GET_AFTER='.($result['get_after_status']??'NOT_SENT')
        .' code='.($result['http_error_code']??$result['initial_get_error_code']??'-').PHP_EOL;
    echo 'M6.2B.3 note: no SQL writes; inspect Product status with php spark m62b3:plan after success.'.PHP_EOL;
} catch (Throwable $error) {
    $report['status']='BLOCKED_DEV_QA';
    $report['failure_class']=get_class($error);
    fwrite(STDERR,"M6.2B.3 BLOCKED: HTTP fixture verification could not complete.\n");
}
$directory=dirname(__DIR__).'/writable/m62b/reports';
if (!is_dir($directory)) @mkdir($directory, 0700, true);
if (is_dir($directory) && is_writable($directory)) {
    $file=$directory.'/m62b3_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($file,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
    @chmod($file,0600);
    echo 'M6.2B.3 report: '.$file.PHP_EOL;
}
exit($exit);

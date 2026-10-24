<?php

declare(strict_types=1);

/**
 * DEV only; opt-in successful writes against EXISTING local api.monmin.net XAMPP.
 * Does not create a database clone and does NOT clean up the new labelled Product.
 *
 * php tools/m62b2_dev_write.php --group-id=3 --uom-id=1         (dry-run)
 * php tools/m62b2_dev_write.php --group-id=3 --uom-id=1 --preflight (HTTP read-only)
 * php tools/m62b2_dev_write.php --group-id=3 --uom-id=1 --apply --confirm=DEV_PRODUCT_WRITE
 * Optional: --target-state=ACTIVE (only if persisted Lifecycle allows this transition)
 */
require_once __DIR__ . '/m62b/M62B2DevWrite.php';

$root = dirname(__DIR__, 2);
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/\A--(group-id|uom-id|confirm|target-state)=(.+)\z/D', $arg, $m) === 1) {
        if (isset($options[$m[1]])) { fwrite(STDERR, "Duplicate option.\n"); exit(2); }
        $options[$m[1]] = $m[2];
    } elseif ($arg === '--preflight' && !isset($options['preflight'])) {
        $options['preflight'] = true;
    } elseif ($arg === '--apply' && !isset($options['apply'])) {
        $options['apply'] = true;
    } else { fwrite(STDERR, "Unknown DEV QA option.\n"); exit(2); }
}
try {
    if (!isset($options['group-id'], $options['uom-id'])) throw new InvalidArgumentException('Both DEV IDs are required.');
    M62B2DevPlan::id($options['group-id']);
    M62B2DevPlan::id($options['uom-id']);
    if (isset($options['target-state']) && preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $options['target-state']) !== 1) {
        throw new InvalidArgumentException('Invalid requested state.');
    }
    if (isset($options['preflight']) && (isset($options['apply']) || isset($options['confirm']) || isset($options['target-state']))) {
        throw new InvalidArgumentException('Preflight mode is read-only and cannot be combined with mutation options.');
    }
    if (!isset($options['apply']) && !isset($options['preflight'])) {
        if (isset($options['confirm']) || isset($options['target-state'])) throw new InvalidArgumentException('Do not pass write confirmation or target state in dry-run.');
        echo 'M6.2B.2 PLAN ONLY: no HTTP or database operations performed.'.PHP_EOL;
        echo 'DEV group_id='.$options['group-id'].' uom_id='.$options['uom-id'].PHP_EOL;
        echo 'To CREATE a labelled Product in your existing DEV database, add --apply --confirm=DEV_PRODUCT_WRITE.'.PHP_EOL;
        echo 'No DELETE route exists: the labelled Product will remain in DEV for manual review.'.PHP_EOL;
        exit(0);
    }
    if (!isset($options['preflight']) && ($options['confirm'] ?? null) !== 'DEV_PRODUCT_WRITE') {
        throw new InvalidArgumentException('Explicit DEV write confirmation is required.');
    }
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'M6.2B.2 BLOCKED: invalid arguments or confirmation. No requests performed.'.PHP_EOL);
    exit(2);
}
$preflightOnly = isset($options['preflight']);
$report = ['milestone' => 'M6.2B.2.1', 'timestamp_utc' => gmdate('c'),
    'mode' => $preflightOnly ? 'DEV_LOCAL_HTTP_READ_ONLY_PREFLIGHT' : 'DEV_LOCAL_HTTP_EXPLICIT_WRITE',
    'status' => 'BLOCKED_ENV', 'production_acceptance' => 'NOT_CLAIMED',
    'mutates_existing_dev_db' => !$preflightOnly];
$exit = 2;
try {
    $result = (new M62B2DevWrite())->execute(
        $options['group-id'], $options['uom-id'],
        (string)(getenv('M62B_DEV_USER') ?: ''), (string)(getenv('M62B_DEV_PASSWORD') ?: ''),
        $options['target-state'] ?? null, $preflightOnly,
    );
    $report['integration'] = $result;
    $report['status'] = $result['verdict'];
    $exit = in_array($result['verdict'], ['PASS_DEV_PRODUCT_CREATE_PATCH', 'PASS_DEV_PRODUCT_PREFLIGHT'], true) ? 0 : 1;
    if ($preflightOnly) {
        echo 'M6.2B.2.1 DEV PREFLIGHT: '.$report['status'].'; step='.$result['preflight_step'].PHP_EOL;
    } else {
        echo 'M6.2B.2 DEV WRITE: '.$report['status'].'; phase='.$result['phase'].PHP_EOL;
    echo 'M6.2B.2 fixture: marker='.$result['marker'].' id='.($result['created_product_id'] ?? 'NOT_CREATED').PHP_EOL;
    echo 'M6.2B.2 success: CREATE='.$result['create_http'].' PATCH='.$result['patch_http']
        .' duplicate='.(int)$result['duplicate_rejected'].' COMBO negative='.(int)$result['combo_failure_rejected']
        .' visible rows expected='.(int)$result['http_visible_no_orphan']
        .' state='.$result['setstate'].PHP_EOL;
    echo 'M6.2B.2 note: SQL audit and counter/concurrency require independent verification; retained DEV fixture is NOT auto-deleted.'.PHP_EOL;
    }
    if ($preflightOnly || $result['phase'] === 'preflight') {
        echo 'M6.2B.2 PRE-FLIGHT CHECKS (HTTP status and stable codes only):'.PHP_EOL;
        foreach (['csrf', 'login', 'me'] as $stage) {
            $auth = $result['preflight']['authentication'][$stage] ?? null;
            if ($auth !== null) echo '  auth_'.$stage.': HTTP '.($auth['http_status'] ?? 0).' code='.($auth['error_code'] ?? '-').PHP_EOL;
        }
        foreach (['product_group', 'product_uom'] as $key) {
            $ref = $result['preflight'][$key] ?? null;
            if ($ref !== null) echo '  '.$key.': HTTP '.$ref['http_status'].' state='.($ref['record_state'] ?? '-').' code='.($ref['error_code'] ?? '-').' reason='.$ref['reason'].PHP_EOL;
        }
        if (($result['preflight_step'] ?? '') === 'authentication') {
            echo '  auth: FAIL (check CSRF/login/session).'.PHP_EOL;
        }
    }
} catch (Throwable $e) {
    $report['failure_class'] = get_class($e);
    fwrite(STDERR, 'M6.2B.2 BLOCKED before DEV write; check environment and confirmation.\n');
}
$directory = $root.'/backend/writable/m62b/reports';
if (!is_dir($directory)) @mkdir($directory, 0700, true);
if (is_dir($directory) && is_writable($directory)) {
    $file = $directory.'/m62b2_dev_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    @chmod($file, 0600);
    echo 'M6.2B.2 report: '.$file.PHP_EOL;
}
exit($exit);

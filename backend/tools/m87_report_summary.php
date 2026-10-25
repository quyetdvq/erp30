<?php

declare(strict_types=1);

require_once __DIR__.'/m87/M87Preflight.php';
$input=(string)($argv[1]??'');
if ($input==='') {
    fwrite(STDERR,'Usage: php tools/m87_report_summary.php <M87-DB-report.json>'.PHP_EOL);
    exit(2);
}
$review=M87Preflight::report($input,dirname(__DIR__).'/writable/m87/reports',false);
$report=$review['source'];
if (!is_array($report)) {
    echo 'M8.7A REPORT: BLOCKED ('.implode(',',$review['codes']).')'.PHP_EOL;
    exit(2);
}
echo 'M8.7A DB STATUS: '.(string)($report['status']??'UNKNOWN').PHP_EOL;
echo 'M8.7A CASE MANIFEST: '.(($report['fixture_manifest_supplied']??false)?'SUPPLIED':'MISSING').PHP_EOL;
if (empty($report['fixture_manifest_supplied'])) {
    echo 'Minimum case matrix required: allow+deny for organization, employee_assignment and contact for EACH of two DEV actors.'.PHP_EOL;
}
$actors=$report['actor_ids']??[];
foreach ($actors as $actor) {
    if (!is_scalar($actor)) continue;
    $rows=$report['data'][(string)$actor]??[];
    if (!is_array($rows)) continue;
    foreach ($rows as $resource=>$entry) {
        if (!is_array($entry) || !is_string($resource)) continue;
        $status=is_string($entry['status']??null)?$entry['status']:'UNKNOWN';
        $visible=is_numeric($entry['oracle_visible']??null)?(string)$entry['oracle_visible']:'?';
        $hidden=is_numeric($entry['oracle_hidden']??null)?(string)$entry['oracle_hidden']:'?';
        $unicode=($entry['unicode_case_tested']??false)===true?'YES':'NO';
        printf("actor=%s resource=%s status=%s visible=%s hidden=%s unicode=%s\n",
            (string)$actor,$resource,$status,$visible,$hidden,$unicode);
    }
}
foreach (['issues','coverage_gaps'] as $field) {
    $items=$report[$field]??[];
    if (!is_array($items)) continue;
    echo strtoupper($field).': '.count($items).PHP_EOL;
    foreach ($items as $item) if (is_string($item)) echo ' - '.preg_replace('/[^A-Za-z0-9_:\.-]/','?',substr($item,0,140)).PHP_EOL;
}
echo 'This report does not prove policy precedence, concurrency, baseline parity or production readiness.'.PHP_EOL;
exit(0);

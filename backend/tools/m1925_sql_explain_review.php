<?php

declare(strict_types=1);

/** Print only sanitized EXPLAIN metadata from an operator-generated M8.7 report. */
require_once __DIR__.'/m87/M87Preflight.php';

$input = $argv[1] ?? '';
if (!is_string($input) || $input === '' || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php tools/m1925_sql_explain_review.php <fresh-m87-report.json>\n");
    exit(2);
}
$result = M87Preflight::report($input,dirname(__DIR__).'/writable/m87/reports',true);
if ($result['codes'] !== [] || !is_array($result['source'])) {
    fwrite(STDERR, 'M19.25 EXPLAIN BLOCKED: '.implode(',', $result['codes'])."\n");
    exit(2);
}
$report=$result['source'];
echo 'M19.25 DEV SQL/PBAC: '.(string)$report['status']."\n";
$shown=0;
foreach ($report['actor_ids'] as $actor) {
    $rows=$report['data'][(string)$actor]??[];
    if (!is_array($rows)) continue;
    foreach ($rows as $resource=>$entry) {
        if (!is_string($resource) || !preg_match('/\A[a-z_]+\z/D',$resource)
            || !is_array($entry) || !is_array($entry['explain']??null)) continue;
        foreach (['count','page'] as $kind) {
            $plan=$entry['explain'][$kind]??null;
            if (!is_array($plan) || !is_array($plan['tables']??null)) continue;
            foreach ($plan['tables'] as $t) {
                if (!is_array($t)) continue;
                // Whitelist, bounded output; never echo raw SQL, binds, IDs or credentials.
                $table=is_string($t['table']??null) ? preg_replace('/[^a-zA-Z0-9_]/','',substr($t['table'],0,60)) : '?';
                $access=is_string($t['access_type']??null) ? preg_replace('/[^A-Za-z_]/','',substr($t['access_type'],0,30)) : '?';
                $key=is_string($t['key']??null) ? preg_replace('/[^a-zA-Z0-9_]/','',substr($t['key'],0,60)) : 'none';
                $estimate=is_int($t['rows']??null) && $t['rows']>=0 ? $t['rows'] : '?';
                printf("actor=%s resource=%s plan=%s table=%s access=%s index=%s rows=%s\n",
                    (string)$actor,$resource,$kind,$table,$access,$key,(string)$estimate);
                $shown++;
            }
        }
    }
}
if ($shown===0) echo "No usable EXPLAIN table nodes (empty scope, blocked scope, or insufficient fixture data).\n";
echo 'EXPLAIN entries: '.$shown.". Index/full-scan assessment needs actual row counts and a DEV baseline.\n";
echo 'This is NOT an HTTP/DB integration or production performance approval.'."\n";

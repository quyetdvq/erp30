<?php

declare(strict_types=1);

/** Source-only M4 extension gate. Does not perform HTTP or SQL. */
require_once __DIR__.'/m4/M4SourceAcceptance.php';
try {
    $root=dirname(__DIR__,2);
    $routes=M4SourceAcceptance::verify($root);
    $old=M34DAcceptance::source($root);
    $fail=array_keys(array_filter($old,static fn(array $gate):bool=>$gate['status']!=='PASS'));
    if ($fail!==[])throw new RuntimeException('M3.4D static gates changed: '.implode(',',$fail));
    $report=[
        'milestone'=>'M4',
        'verdict'=>'SOURCE_ACCEPTED_M4_HTTP_DB_NOT_TESTED',
        'preexisting_contract_70'=>'PASS_PINNED_SHA256',
        'm4_contract_14'=>'PASS_SOURCE_AND_PINNED_OPENAPI',
        'total_registered_routes'=>84,
        'baseline_operations'=>150,
        'm34d_static_gates'=>count($old),
        'm4_http_integration'=>'NOT_TESTED',
        'm4_real_mariadb_transactions'=>'NOT_TESTED',
        'baseline_live_parity'=>'NOT_TESTED',
        'production_write_enablement'=>'BLOCKED',
        'scope'=>'Source-level extension verification; M3.4B 29-mutation sandbox does not exercise new M4 routes',
        'details'=>$routes,
    ];
    $folder=$root.'/backend/writable/m4/reports';
    if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder))throw new RuntimeException('M4 report directory unavailable');
    $path=$folder.'/m4_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
    if(file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('M4 report cannot be written');
    echo 'M4 SOURCE PASS: 70 existing + 14 new = 84/150 routes; baseline pins and M3.4D gates PASS.'.PHP_EOL;
    echo 'M4 PENDING: new M4 HTTP/MariaDB and independent baseline parity NOT_TESTED; production write BLOCKED.'.PHP_EOL;
    echo 'M4 report: '.$path.PHP_EOL;
} catch(Throwable $e) {
    fwrite(STDERR,'M4 SOURCE FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}

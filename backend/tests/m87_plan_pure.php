<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m87/M87Plan.php';

$checks=0;
$ok=static function (bool $condition,string $name) use (&$checks):void {
    if (!$condition) throw new RuntimeException('M8.7 plan FAIL: '.$name);
    $checks++;
};
$throws=static function(callable $fn,string $name) use ($ok):void {
    try { $fn(); } catch (Throwable) { $ok(true,$name); return; }
    throw new RuntimeException('M8.7 should reject '.$name);
};
$resources=M87Plan::resources();
$ok(count($resources)===9,'nine reviewed collections');
foreach (['product','product_group','product_uom','organization','employee_assignment','employee','department','position','contact'] as $key) {
    $ok(isset($resources[$key]),'reviewed '.$key);
}
$parsed=M87Plan::args(['3','5','erp_dev','DEV_READ_ONLY','1000']);
$ok($parsed['actors']===['3','5'],'two real distinct actors');
$ok($parsed['database']==='erp_dev','attested database');
$ok($parsed['max_rows']===1000,'bounded corpus');
$ok($parsed['case_file']===null,'no implicit fixture file');
foreach ([
    ['3','3','erp_dev','DEV_READ_ONLY'],
    ['3','5','erp_dev','enabled'],
    ['3','5','erp_prod','DEV_READ_ONLY'],
    ['3','5','mysql','DEV_READ_ONLY'],
    ['0','5','erp_dev','DEV_READ_ONLY'],
    ['3','5','erp_dev','DEV_READ_ONLY','5001'],
    ['3','5','erp_dev','DEV_READ_ONLY','0'],
] as $bad) $throws(static fn()=>M87Plan::args($bad),'invalid actor/database/confirmation/bound');
M87Plan::devEnvironment('development');$checks++;
M87Plan::devEnvironment('testing');$checks++;
$throws(static fn()=>M87Plan::devEnvironment('production'),'production safety');
$throws(static fn()=>M87Plan::databaseMatches('real','expected'),'database mismatch');
M87Plan::databaseMatches('dev_erp','dev_erp');$checks++;
$comparison=M87Plan::compareIds(['1','2','3'],['1','2','3']);
$ok($comparison['match'] && $comparison['first_mismatch_position']===null,'sequence equality');
$comparison=M87Plan::compareIds(['1','2','3'],['1','3','2']);
$ok(!$comparison['match'] && $comparison['first_mismatch_position']===2,'sequence mismatch');
$ok(!str_contains(json_encode($comparison,JSON_THROW_ON_ERROR),'"1","2"'),'no raw IDs');
$stats=M87Plan::timings([40,3,2,100,10]);
$ok($stats['samples']===5 && $stats['p50_ms']===10.0 && $stats['p95_ms']===100.0,'timing percentiles');
$plan=M87Plan::explainSummary('{"query_block":{"table":{"table_name":"p","access_type":"range","key":"status_idx","rows":20}}}');
$ok($plan['tables'][0]['key']==='status_idx' && !$plan['optimized_away'],'EXPLAIN key metadata');
$optimized=M87Plan::explainSummary('{"query_block":{"table":null,"message":"Impossible WHERE"}}');
$ok($optimized['optimized_away']===true,'EXPLAIN optimised denied scope');
$throws(static fn()=>M87Plan::explainSummary('invalid JSON'),'invalid EXPLAIN JSON');
$cases=['actors'=>['3'=>['organization'=>['allow'=>['1'],'deny'=>['2']]],
    '5'=>['contact'=>['allow'=>['4'],'deny'=>['5']]]]];
$ok(M87Plan::validateCases($cases,['3','5'])===$cases,'independent DEV oracle manifest');
$ok(!M87Plan::hasMinimumCases($cases,['3','5']),'incomplete scenario fixture is inconclusive');
$ok(!M87Plan::hasMinimumCases(null,['3','5']),'absent fixture is inconclusive');
$throws(static fn()=>M87Plan::validateCases(['actors'=>['3'=>['unknown'=>['allow'=>['1']]],'5'=>[]]],['3','5']),
    'unsupported PBAC resource');
$throws(static fn()=>M87Plan::validateCases(['actors'=>['3'=>['contact'=>['allow'=>['0']]],'5'=>[]]],['3','5']),
    'invalid case row');
echo 'M8.7 AUDIT PLAN / SAFETY: '.$checks.'/'.$checks.' PASS'.PHP_EOL;

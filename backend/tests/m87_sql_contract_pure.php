<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);
$files=[
    'verifier'=>$root.'/backend/tools/m87/M87SqlVerifier.php',
    'command'=>$root.'/backend/app/Commands/M87DevSqlAudit.php',
    'http'=>$root.'/backend/tools/m87/M87Http.php',
    'entry'=>$root.'/backend/tools/m87_http_readonly.php',
    'plan'=>$root.'/backend/tools/m87/M87Plan.php',
    'preflight'=>$root.'/backend/tools/m87/M87Preflight.php',
];
$source=[];
foreach ($files as $key=>$path) {
    if (!is_file($path)||is_link($path)) throw new RuntimeException('M8.7 source missing: '.$key);
    $source[$key]=(string)file_get_contents($path);
}
$checks=0;
$must=static function(bool $b,string $name)use(&$checks):void {
    if (!$b) throw new RuntimeException('M8.7 contract FAIL: '.$name);
    $checks++;
};
foreach ([
    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
    'SET TRANSACTION READ ONLY',
    'transBegin()', 'transRollback()',
    'UnifiedAccessService(', 'canView(',
    'ContactScope(', 'contactOracle(',
    'ContactScopeProvider(\'organization_contact\'',
    'ContactScopeProvider(\'employee_contact\'',
    'ProductReadSql::collection(', 'ProductCatalogSql::collection(',
    'OrganizationPeopleSql::collection(', 'PeopleMasterSql::collection(',
    'ContactSql::collection(',
    'EXPLAIN FORMAT=JSON ', 'AuthorizedListPage::paginate(',
    'queryScenarios(', 'compareIds(',
] as $term) $must(str_contains($source['verifier'],$term),'SQL oracle '. $term);
foreach (['DEV_READ_ONLY','devEnvironment(ENVIRONMENT)','M87SqlVerifier','WRITEPATH','production_acceptance'] as $term) {
    $must(str_contains($source['command'],$term) || $term==='DEV_READ_ONLY'
        && str_contains($source['plan'],$term),'CLI guard '.$term);
}
foreach (['127.0.0.1','verify_peer\'=>false','public static function allowed(','/api/v1/auth/login',
    'follow_location\'=>0','str_contains($path,\'..\')'] as $term) {
    $must(str_contains($source['http'],$term),'local HTTP '.$term);
}
foreach (['M87_ACTOR_',"source['data']",'page_id_digest',
    'INCONCLUSIVE_BASELINE_AND_SCENARIO_GATES','M87Preflight::environment('] as $term) {
    $must(str_contains($source['entry'],$term),'HTTP report '.$term);
}
foreach (['M87_DEV_CONSENT','M87_ACTOR_A_USER','M87_ACTOR_B_PASSWORD',
    'DEV_HTTP_READ_ONLY','DB_REPORT_OUTSIDE_15_MIN_WINDOW'] as $term) {
    $must(str_contains($source['preflight'],$term),'HTTP preflight guard '.$term);
}
foreach (['INSERT INTO ','UPDATE contacts ','DELETE FROM ','DROP TABLE','TRUNCATE TABLE','CREATE TABLE'] as $forbidden) {
    $must(!str_contains($source['verifier'],$forbidden),'no business SQL writes '.$forbidden);
}
echo 'M8.7 SQL / HTTP SOURCE CONTRACT: '.$checks.'/'.$checks.' PASS'.PHP_EOL;

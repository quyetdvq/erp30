<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m8/M8SourceAcceptance.php';
$n=0;
$ok=static function(bool $yes,string $name)use(&$n):void {
    if (!$yes) throw new RuntimeException('M8 CONTRACT FAILED: '.$name);
    $n++;
};
$root=dirname(__DIR__,2);
$ok(M8SourceAcceptance::verify($root)===['prior'=>146,'added'=>4,'total'=>150,'baseline'=>150],
    'exact four frozen operations and legacy 146 unchanged');
$api=(string)file_get_contents($root.'/contracts/openapi/openapi.yaml');
$controller=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Import/ProductImportController.php');
$service=(string)file_get_contents(__DIR__.'/../app/Modules/Import/Core/ImportJobService.php');
$adapter=(string)file_get_contents(__DIR__.'/../app/Modules/Product/Import/ProductImportAdapter.php');
$interface=(string)file_get_contents(__DIR__.'/../app/Modules/Import/Core/ImportAdapter.php');
$access=(string)file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
foreach (M8SourceAcceptance::operations() as $operation) {
    $ok(str_contains($api,'operationId: '.$operation['id']),'frozen op '.$operation['id']);
    $ok(str_contains($controller,'public function '.$operation['id'].'('),'implemented handler '.$operation['id']);
}
foreach (['authorizeStart()', 'execute(', 'transactionalTables()', 'viewableJobStatuses(', 'canViewJob('] as $term) {
    $ok(str_contains($interface,$term),'module adapter contract '.$term);
}
foreach (['ProductMutationService(', 'ProductConversionService(', 'ProductComboService(',
    'canStartProductImport()', 'mergeLocked('] as $term) {
    $ok(str_contains($adapter,$term),'business services, locks and PBAC '.$term);
}
foreach (['assertInnoDb($adapter)', 'processRow(', "'status'=>'PROCESSING'", "finalizeTerminal(\$id,'FAILED'",
    "'row_number'=>\$number", 'projectJob(', 'projectRow(', 'canViewJob(', 'viewableJobStatuses('] as $term) {
    $ok(str_contains($service,$term),'shared pipeline security/journal '.$term);
}
$ok(str_contains($service, 'FOR UPDATE') && str_contains($service, 'transBegin()')
    && str_contains($service, 'NotificationOutboxProducer::enqueueImportTerminal')
    && str_contains($service, 'transRollback()'), 'locked atomic terminal/outbox transaction');
$ok(str_contains($access,"'import_job' => 'ORGANIZATION'"),'organization job domain');
$ok(str_contains($access,'viewableProductImportStatuses()'),'native job state scope');
$ok(str_contains($access,'canStartProductImport()'),'import action distinct from create');
$ok(str_contains($controller,"DINKERP_M33B_WRITE_MODE"),'write disabled by default');
$ok(str_contains($controller,'isValid()'),'multipart upload validation');
$ok(str_contains($controller,"'UNAUTHENTICATED'"),'authentication gate');
$ok(!str_contains($service,"->table('products')"),'shared pipeline has no Product table writes');
$ok(!str_contains($service,"->table('product_combo_items')"),'shared pipeline does not rewrite Combo');
$ok(str_contains((string)file_get_contents(__DIR__.'/../../frontend/src/features/product-import/pages/ProductImportView.vue'),'<ImportWizard'),
    'Product UI reuses shared wizard');
$ok(str_contains((string)file_get_contents(__DIR__.'/../../frontend/src/features/import/shared/ImportWizard.vue'),'templateRoot'),
    'static versioned template download');
echo 'M8 IMPORT CONTRACT: '.$n.'/'.$n." PASS\n";

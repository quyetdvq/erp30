<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m7/M72SourceAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
$ok=static function(bool $condition,string $why)use(&$n):void{
    if (!$condition)throw new RuntimeException('M7.2 contract: '.$why);
    ++$n;
};
$verified=M72SourceAcceptance::verify($root);
$ok($verified===['prior'=>134,'added'=>2,'total'=>136,'baseline'=>150],'132+2+2 route chain exact');
$ops=M72SourceAcceptance::operations();
$ok(count($ops)===2,'exact frozen endpoint count');
$ok($ops['GET products/(:num)/combo-items']['id']==='listComboItems','GET method pinned');
$ok($ops['PUT products/(:num)/combo-items']['id']==='replaceComboItems','PUT method pinned');
$controller=(string)file_get_contents($root.'/backend/app/Modules/Product/Combo/ProductComboController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Product/Combo/ProductComboService.php');
$rules=(string)file_get_contents($root.'/backend/app/Modules/Product/Combo/ProductComboRules.php');
$ok(substr_count($controller,"'UNAUTHENTICATED', 401")===2,'Shield required for GET and PUT');
$ok(str_contains($controller,"'ACCESS_MUTATION_NOT_ENABLED', 403"),'mutation disabled default');
$ok(str_contains($controller, "if (\$raw==='' ||"), 'missing body protected');
$ok(str_contains($controller,"!array_key_exists('items', \$value)"),'{} not interpreted as DELETE ALL');
$ok(str_contains($service,'ProductComboGraph::assertAcyclic('),'graph cycle check');
$ok(str_contains($service,'ProductComboPlan::build('),'shared pure plan');
$ok(str_contains($service,'assertTransactionEngines()'),'transactional table engine gate');
$ok(str_contains($service,'lockedItems('),'previous item rows are locked');
$ok(str_contains($service,'lockedComponents('),'component rows locked');
$ok(str_contains($service,'reachableEdges('),'nested COMBO graph bounded');
$ok(str_contains($service,'transRollback()'),'SQL transaction rollback');
$ok(str_contains($service,'transCommit()'),'SQL commit');
$ok(str_contains($service,'resetTransStatus()'),'transaction error state reset');
$ok(str_contains($service,"decideMutation('product','edit'"),'record-PBAC owner edit');
$ok(str_contains($service,"viewableMasterLifecycleStatuses('product')"),'SQL PBAC state pushdown');
$ok(str_contains($service,'whereIn('),'bounded SQL edge traversal');
$ok(str_contains($service,'getDatabase()'),'shared Product Master advisory lock key');
$ok(str_contains($rules,'MAX_ITEMS = 250'),'request size bound');
$ok(str_contains($rules,'sameQuantity('),'decimal exact comparison');
$ok(str_contains($rules,'PositiveId::value('),'canonical BIGINT, no casts');
$ok(!str_contains($service,'(float)'),'no float in COMBO service');
$ok(!str_contains($rules,'(float)'),'no float in COMBO quantity');
echo 'M7.2 COMBO CONTRACT: '.$n.'/'.$n." PASS\n";

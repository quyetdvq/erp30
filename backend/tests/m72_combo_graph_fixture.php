<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Product/Combo/ProductComboGraph.php';
require_once __DIR__.'/../app/Modules/Product/Combo/ProductComboRules.php';
require_once __DIR__.'/../app/Modules/Product/Combo/ProductComboPlan.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Combo\ProductComboGraph as G;
use App\Modules\Product\Combo\ProductComboPlan as P;

$n=0;
$ok=static function(bool $pass,string $name)use(&$n):void {if(!$pass)throw new RuntimeException('M7.2 graph: '.$name);++$n;};
$reject=static function(callable $action,string $code,string $why)use($ok):void {
    try {$action(); $ok(false,$why);}catch(DomainException $e){$ok($e->getErrorCode()===$code,$why);}
};
$items=[['component_product_id'=>'11','quantity'=>'1'],['component_product_id'=>'12','quantity'=>'0.125000']];
G::assertAcyclic('10',$items,[]);$ok(true,'flat COMBO components');
G::assertAcyclic('10',$items,[['combo_product_id'=>'12','component_product_id'=>'13']]);$ok(true,'valid nested COMBO');
$reject(static fn()=>G::assertAcyclic('10',[['component_product_id'=>'10','quantity'=>'1']],[]),'COMBO_SELF_REFERENCE','direct self cycle');
$reject(static fn()=>G::assertAcyclic('10',$items,[['combo_product_id'=>'12','component_product_id'=>'10']]),'COMBO_CYCLE','indirect cycle to owner');
$reject(static fn()=>G::assertAcyclic('10',$items,[['combo_product_id'=>'12','component_product_id'=>'13'],['combo_product_id'=>'13','component_product_id'=>'12']]),'COMBO_CYCLE','preexisting descendant cycle');
$reject(static fn()=>G::assertAcyclic('10',$items,[['combo_product_id'=>'10','component_product_id'=>'99']]),'COMBO_MUTATION_FAILED','old owner edges must be replaced');
$edges=[];
for($i=0;$i<100;$i++)$edges[]=['combo_product_id'=>(string)($i+100),'component_product_id'=>(string)($i+101)];
G::assertAcyclic('10',[['component_product_id'=>'100','quantity'=>'1']],$edges);$ok(true,'deep nested chain, iterative walk');
$edges[]=['combo_product_id'=>'200','component_product_id'=>'10'];
$reject(static fn()=>G::assertAcyclic('10',[['component_product_id'=>'100','quantity'=>'1']],$edges),'COMBO_CYCLE','deep chain cycle');
$old=[['id'=>'100','component_product_id'=>'11','quantity'=>'1.000000'],['id'=>'101','component_product_id'=>'12','quantity'=>'2.0']];
$next=[['component_product_id'=>'11','quantity'=>'1'],['component_product_id'=>'13','quantity'=>'0.2']];
$p=P::build($old,$next);
$ok(count($p['deletes'])===1 && $p['deletes'][0]['id']==='101','delete omitted');
$ok($p['updates']===[],'unchanged quantity untouched, no audit churn');
$ok(count($p['creates'])===1 && $p['creates'][0]['component_product_id']==='13','create new');
$changed=P::build($old,[['component_product_id'=>'11','quantity'=>'5']]);
$ok(count($changed['updates'])===1 && $changed['updates'][0]['old']['id']==='100','update keeps original ID');
$ok(count(P::build($old,[])['deletes'])===2,'empty replacement removes all');
$ok(P::build([],$next)['updates']===[],'new items no updates');
$reject(static fn()=>P::build([$old[0],$old[0]],$next),'COMBO_MUTATION_FAILED','existing duplicate rows');
$reject(static fn()=>P::build($old,[$next[0],$next[0]]),'COMBO_DUPLICATE','request duplicate plan');
// Copy-on-write transaction simulation, NOT MariaDB rollback evidence.
$snapshot=$old;$data=$old;
try{$data=[];foreach($p['creates'] as $item)$data[]=$item;throw new RuntimeException('simulated failed SQL insert');}
catch(RuntimeException){$data=$snapshot;}
$ok($data===$old,'rollback model restores full snapshot');
echo 'M7.2 COMBO GRAPH/PLAN FIXTURE: '.$n.'/'.$n." PASS (not live SQL)\n";

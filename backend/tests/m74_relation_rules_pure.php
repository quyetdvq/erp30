<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Product/Lookup/ProductLookupRules.php';
require_once __DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipRules.php';

use App\Modules\Product\Lookup\ProductRelationshipRules as R;

$n = 0;
$ok = static function(bool $value, string $case) use (&$n): void {
    if (!$value) throw new RuntimeException('M7.4 RULES FAILED: '.$case);
    ++$n;
};
$invalid = static function(callable $fn, string $case) use ($ok): void {
    try { $fn(); throw new RuntimeException('Expected InvalidArgumentException: '.$case); }
    catch (InvalidArgumentException) { $ok(true, $case); }
};
$ok(R::ids(['product_ids'=>['2','1','2']],100)===['2','1'],'dedup stable request order');
$ok(R::ids(['product_ids'=>[1,2,'0003']],50)===['1','2','3'],'canonical numeric ids');
$ok(R::ids(['product_ids'=>[]],100)===[],'empty batch');
$ok(R::ids(['product_ids'=>[PHP_INT_MAX]],100)===[(string)PHP_INT_MAX],'numeric PHP int allowed');
$ok(R::ids(['product_ids'=>['18446744073709551615']],100)===['18446744073709551615'],'max unsigned bigint exact');
foreach ([null,'2',[-1],[0],['0'],['-1'],['1.1'],['1e2'],['18446744073709551616'],[1.0],[[1]],[true]] as $item) {
    $invalid(static fn()=>R::ids(['product_ids'=>$item],100),'reject invalid batch/ID '.json_encode($item));
}
$invalid(static fn()=>R::ids([],50),'required product_ids');
$invalid(static fn()=>R::ids(['product_ids'=>array_fill(0,51,'1')],50),'raw preview max 50 even duplicates');
$invalid(static fn()=>R::ids(['product_ids'=>array_fill(0,101,'1')],100),'details max 100');
$invalid(static fn()=>R::ids(['product_ids'=>['1']],49),'unreviewed max cap');
$ok(R::limitPerProduct([])===2,'default limit 2');
$ok(R::limitPerProduct(['limit_per_product'=>1])===1,'min limit 1');
$ok(R::limitPerProduct(['limit_per_product'=>'5'])===5,'max limit 5');
foreach ([0,6,-1,'0','6','1e1','2.1','',null,[],1.0,true,'999'] as $bad) {
    $invalid(static fn()=>R::limitPerProduct(['limit_per_product'=>$bad]),'reject invalid limit '.json_encode($bad));
}
$visible = ['2'=>['id'=>'2'], '5'=>['id'=>'5']];
$ok(R::resolvedDetails(['5','9','2'], $visible)===[['id'=>'5'],['id'=>'2']],'resolve skips invisible preserving order');
$ok(R::resolvedDetails(['9'], $visible)===[],'resolve does not reveal inaccessible ids');
$roles = ['5'=>'PARENT','2'=>'COMBO','7'=>'CHILD','8'=>'SIMPLE'];
$children = ['5'=>['total'=>8,'products'=>[['id'=>'13'],['id'=>'14']]],
    '2'=>['total'=>1,'products'=>[['id'=>'10']]]];
$previews=R::relationPreviews(['2','99','5','7','8'],$roles,$children);
$ok(array_column($previews,'product_id')===['2','5'],'only visible PARENT/COMBO owners');
$ok($previews[0]['total']===1 && $previews[1]['total']===8,'total comes from scoped count not page length');
$ok(count($previews[1]['products'])===2,'limit smaller than total');
$ok(R::relationPreviews(['5'],['5'=>'PARENT'],[])===[['product_id'=>'5','total'=>0,'products'=>[]]],'visible empty owner returned');
$ok(R::relationPreviews(['1'],[],[])===[],'hidden owners omitted');
$sales=R::salesOptions(['6','9','2'],['2'=>true,'6'=>true],['6'=>[['id'=>'3','code'=>'PCS']]]);
$ok($sales===[['product_id'=>'6','options'=>[['id'=>'3','code'=>'PCS']]],['product_id'=>'2','options'=>[]]],'sales empty options and ordering');
$ok(R::salesOptions(['99'],[],[])===[],'hidden product omitted from SALES response');
echo 'M7.4 RELATION RULES: '.$n.'/'.$n." PASS\n";

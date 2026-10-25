<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Product/Lookup/ProductLookupRules.php';

use App\Modules\Product\Lookup\ProductLookupRules as R;

$n=0;
$ok=static function(bool $valid,string $message) use (&$n):void {
    if (!$valid) throw new RuntimeException('M6.4A pure rules: '.$message);
    ++$n;
};
$bad=static function(callable $action,string $reason) use ($ok):void {
    try { $action(); } catch (InvalidArgumentException) { $ok(true,'reject '.$reason); return; }
    throw new RuntimeException('Accepted unsafe '.$reason);
};
$q=R::optionsQuery([]);
$ok($q===['purpose'=>'price_list_item','q'=>'','page'=>1,'page_size'=>20],'defaults');
$ok(R::optionsQuery(['purpose'=>'parent_product','q'=>'  Bon  ','page'=>'2','page_size'=>'50'])===
    ['purpose'=>'parent_product','q'=>'Bon','page'=>2,'page_size'=>50],'valid query');
foreach (['bad','',[],9] as $purpose) $bad(static fn()=>R::optionsQuery(['purpose'=>$purpose]),'purpose');
foreach ([0,'0',-1,'101000','',[],true,'999999999999999999999'] as $page) {
    $bad(static fn()=>R::optionsQuery(['page'=>$page]),'page');
}
$bad(static fn()=>R::optionsQuery(['page'=>100001]),'page upper bound');
$bad(static fn()=>R::optionsQuery(['page_size'=>51]),'page size');
$bad(static fn()=>R::optionsQuery(['q'=>str_repeat('a',101)]),'q length');
$bad(static fn()=>R::optionsQuery(['q'=>[]]),'q array');
$bad(static fn()=>R::optionsQuery(['filter'=>['status'=>'ACTIVE']]),'unreviewed query key');
$ok(R::optionsQuery(['page'=>100000])['page']===100000,'max page');
$ids=R::ids(['product_ids'=>['2','0001','2',3,3]], 'product_ids');
$ok($ids===['2','1','3'],'canonicalize & dedupe stable');
$ok(R::ids(['product_ids'=>['18446744073709551615']],'product_ids')===['18446744073709551615'],'BIGINT max exact');
$ok(R::ids(['uom_ids'=>[]],'uom_ids')===[],'empty batch accepted');
foreach ([null,'3',[0],[false],['3.0'],['-1'],['18446744073709551616'],array_fill(0,101,'1'), ['1',[]]] as $invalid) {
    $bad(static fn()=>R::ids(['product_ids'=>$invalid],'product_ids'),'batch ID validation');
}
$bad(static fn()=>R::ids(['product_ids'=>['1']],'not_allowed'),'wrong field');
$rows=[];
foreach ([
    ['1','SIMPLE','ACTIVE','Bread','B01',true],
    ['2','CHILD','ACTIVE','Bread - Small','B02',true],
    ['3','PARENT','ACTIVE','Bread Parent','B03',false],
    ['4','COMBO','ACTIVE','Combo Bread','C04',true],
    ['5','SIMPLE','INACTIVE','Old Bread','B05',false],
    ['10','SIMPLE','ACTIVE','Bread Ten','B10',true],
] as [$id,$role,$status,$name,$code,$selectable]) {
    $rows[]=['id'=>$id,'role'=>$role,'status'=>$status,'full_name'=>$name,'product_code'=>$code,
        'base_uom_id'=>'7','selectable'=>$selectable,'sku'=>'x-'.$id];
}
$first=R::options($rows,R::optionsQuery(['q'=>'bread','page_size'=>2]));
$ok($first['total']===4 && $first['page']===1 && $first['page_size']===2,'filtered total BEFORE page');
$ok(array_column($first['data'],'id')===['1','2'],'numeric order first page');
$second=R::options($rows,R::optionsQuery(['q'=>'bread','page'=>2,'page_size'=>2]));
$ok(array_column($second['data'],'id')===['4','10'],'numeric order stable page');
$parent=R::options($rows,R::optionsQuery(['purpose'=>'parent_product']));
$ok(array_column($parent['data'],'id')===['1','3','10'],'parent includes root standard parent/simple');
$ok($parent['data'][1]['selectable']===false,'PARENT selectable flag not escalated');
$combo=R::options($rows,R::optionsQuery(['purpose'=>'combo_component']));
$ok(array_column($combo['data'],'id')===['1','2','4','10'],'combo purpose baseline role eligibility');
$ok(R::options($rows,R::optionsQuery(['q'=>'ZZZZ']))['total']===0,'no match yields zero');
$ok(R::options($rows,R::optionsQuery(['q'=>'b02']))['data'][0]['id']==='2','search by code');
$ok(array_keys($first['data'][0])===['id','product_code','full_name','role','status','base_uom_id','selectable'],'no private Product fields');
$resolved=R::resolveProducts(['5','4','9','2'], ['2'=>$rows[1],'4'=>$rows[3],'5'=>$rows[4]]);
$ok(array_column($resolved,'id')===['5','4','2'] && $resolved[0]['selectable']===false,'resolve inactive & omit invisible preserving order');
$ok(R::resolveProducts(['99'],[])===[],'absent/hidden indistinguishable');
$uoms=R::resolveUoms(['4','2','9'],['2'=>['id'=>'2','code'=>'BOX','status'=>'INACTIVE'],
    '4'=>['id'=>'4','code'=>'PCS','status'=>'ACTIVE']]);
$ok($uoms===[['id'=>'4','code'=>'PCS','status'=>'ACTIVE'],['id'=>'2','code'=>'BOX','status'=>'INACTIVE']],'UOM ordering, inactive labels & missing omission');
$ok(R::resolveUoms(['9'],[])===[],'no UOM leak');
echo 'M6.4A LOOKUP RULES: '.$n.'/'.$n." PASS\n";

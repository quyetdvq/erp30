<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m62b3/M62B3Fixture.php';

$count=0;
$assert=static function(bool $condition,string $context)use(&$count):void {
    if (!$condition) throw new RuntimeException('M6.2B.3 safety: '.$context);
    $count++;
};
$marker='M62B2_1B6B52993C72';
$id='1466';
$row=['id'=>$id,'sku'=>'DEV_'.$marker,'code'=>'DEV_'.$marker,
    'product_name'=>'[DEV QA] Product '.$marker,
    'description'=>'M6.2B.2 HTTP PATCH confirmed '.$marker,
    'product_type'=>'STANDARD','parent_product_id'=>null,'status'=>'DRAFT'];
M62B3Fixture::assertRow($row,$marker,$id);
$assert(M62B3Fixture::marker($marker)===$marker,'known fixture marker');
$assert(M62B3Fixture::id($id)===$id,'canonical numeric id');
$assert(M62B3Fixture::target('ACTIVE')==='ACTIVE','uppercase Lifecycle code');
$assert(M62B3Fixture::readDto(['status'=>200,'json'=>['data'=>['product_code'=>$row['code'], ...array_diff_key($row,['code'=>true])]]],$marker,$id)['status']==='DRAFT','HTTP DTO accepted by identity');
foreach (['','M62B2_XXX','M62B2_1b6b52993c72','M62B3_1B6B52993C72', 'M62B2_1B6B52993C72/../'] as $bad) {
    try { M62B3Fixture::marker($bad); throw new RuntimeException('Accepted unsafe marker'); }
    catch (InvalidArgumentException) { $assert(true,'marker denied'); }
}
foreach (['0','-1','01','1e2','1 OR 1=1','99999999999999999999'] as $bad) {
    try { M62B3Fixture::id($bad); throw new RuntimeException('Accepted unsafe id'); }
    catch (InvalidArgumentException) { $assert(true,'id denied'); }
}
foreach (['','active','ACTIVE NOW',"ACTIVE\n", 'X'.str_repeat('Y',30)] as $bad) {
    try { M62B3Fixture::target($bad); throw new RuntimeException('Accepted unsafe target'); }
    catch (InvalidArgumentException) { $assert(true,'target denied'); }
}
foreach (['id'=>'1467','sku'=>'REAL_SKU','product_name'=>'Real Product','description'=>'Unrelated',
    'product_type'=>'COMBO','parent_product_id'=>'1','status'=>'??','code'=>''] as $field=>$bad) {
    $modified=$row; $modified[$field]=$bad;
    $refused=false;
    try { M62B3Fixture::assertRow($modified,$marker,$id); }
    catch (RuntimeException|InvalidArgumentException) { $refused=true; }
    $assert($refused,'fixture bound '.$field);
}
$denied=false;
try { M62B3Fixture::readDto(['status'=>403,'json'=>['error'=>['code'=>'AUTH_POLICY_DENIED']]],$marker,$id); }
catch (RuntimeException) { $denied=true; }
$assert($denied,'HTTP denial guarded');
$malformed=false;
try { M62B3Fixture::readDto(['status'=>200,'json'=>['data'=>'unexpected']],$marker,$id); }
catch (RuntimeException) { $malformed=true; }
$assert($malformed,'malformed DTO guarded');
$root=dirname(__DIR__);
$entry=file_get_contents($root.'/tools/m62b3_dev_setstate.php');
$state=file_get_contents($root.'/tools/m62b3/M62B3DevState.php');
$command=file_get_contents($root.'/app/Commands/M62B3DevPlan.php');
$assert(is_string($entry)&&str_contains($entry,'DEV_PRODUCT_SETSTATE'),'opt-in write confirmation');
$assert(str_contains($entry,"'--preflight'")&&str_contains($entry,"'--apply'"),'explicit read vs write modes');
$assert(str_contains($entry,'isset($options[\'target-state\']) || isset($options[\'confirm\'])'),'preflight never carries mutation options');
$assert(str_contains($state, "'POST', '/api/v1/products/'" ) && str_contains($state, "'/actions/set-state'"), 'state-only mutation allowlist');
$assert(str_contains($state,'M62B3Fixture::readDto($initial, $marker, $id)'),'authenticated GET rechecks fixture');
$assert(str_contains($state,'if ($readOnly)')&&str_contains($state,'return $report;'),'preflight stops before mutation');
$assert(!str_contains($state,'db_connect(')&&!str_contains($state,'DELETE FROM')&&!str_contains($state,'UPDATE products'),'HTTP runner never uses SQL mutation');
$assert(str_contains($command,'ResourceStateRepository')&&str_contains($command,"transitionTarget('product'"),'trusted Lifecycle state plan');
$assert(!str_contains($command,'->update(')&&!str_contains($command,'->insert(')&&!str_contains($command,'->delete('),'plan is SELECT-only');
$run=static function(array $options)use($root):array {
    $p=proc_open([PHP_BINARY,$root.'/tools/m62b3_dev_setstate.php',...$options],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if(!is_resource($p))throw new RuntimeException('No subprocess');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
};
$base=['--marker='.$marker,'--product-id='.$id];
[$exit,$stdout]=$run($base);
$assert($exit===0&&str_contains($stdout,'PLAN ONLY'),'dry-run no HTTP');
foreach ([
    ['--apply'], ['--apply','--target-state=ACTIVE'],
    ['--apply','--confirm=DEV_PRODUCT_SETSTATE'],
    ['--preflight','--apply'], ['--preflight','--target-state=ACTIVE'],
    ['--preflight','--confirm=DEV_PRODUCT_SETSTATE'],
    ['--preflight','--preflight'], ['--target-state=ACTIVE'], ['--confirm=DEV_PRODUCT_SETSTATE'],
    ['--marker='.$marker], ['--product-id='.$id],
] as $bad) {
    [$exit,$stdout]=$run([...$base,...$bad]);
    $assert($exit===2&&!str_contains($stdout,'PLAN ONLY'),'unsafe CLI flags blocked');
}
echo 'M6.2B.3 SETSTATE SAFETY: '.$count.'/'.$count.' PASS'.PHP_EOL;

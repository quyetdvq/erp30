<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m62b4/M62B4Plan.php';
$n=0;
$ok=static function(bool $cond,string $label)use(&$n):void {
    if (!$cond) throw new RuntimeException('M6.2B.4 DEV safety: '.$label);
    $n++;
};
$marker=M62B4Plan::marker('012345abcdef');
$ok($marker==='M62B4_012345ABCDEF','nonce and label canonicalized');
$a=M62B4Plan::product($marker,'a','3','1');
$b=M62B4Plan::product($marker,'b','3','1');
$ok(($a['sku']??null)==='DEV_'.$marker.'_A' && ($b['sku']??null)==='DEV_'.$marker.'_B','two distinct SKU values');
$ok(!isset($a['product_code'])&&!isset($b['product_code']),'two writer requests demand AUTO numbering');
$ok($a['product_type']==='STANDARD' && $a['product_group_id']==='3' && $a['base_uom_id']==='1','fixed product refs');
$combo=M62B4Plan::rejectedCombo($marker,'3','1');
$ok($combo['product_type']==='COMBO' && !isset($combo['product_code']),'COMBO rollback also uses AUTO numbering');
$ok($combo['sku']==='DEV_'.$marker.'_ROLLBACK','rollback SKU distinct');
$ok($combo['combo_items']===[['component_product_id'=>'999999999999999999','quantity'=>'1.000000']], 'lookup will fail after insert');
foreach (['', 'M62B4_bad', 'M62B4_aaaaaaaaaaaa', 'M62B3_012345ABCDEF',
    "M62B4_012345ABCDEF\n", 'M62B4_012345ABCDEF/../'] as $invalid) {
    $failed=false;
    try { M62B4Plan::assertMarker($invalid); } catch(InvalidArgumentException) { $failed=true; }
    $ok($failed,'invalid marker refused');
}
foreach (['','0','-1','01','1e2','1/../users','99999999999999999999'] as $invalid) {
    $failed=false;
    try { M62B4Plan::id($invalid); } catch(InvalidArgumentException) { $failed=true; }
    $ok($failed,'invalid reference ID refused');
}
foreach (['c','A','../',''] as $invalid) {
    $failed=false;
    try { M62B4Plan::role($invalid); } catch(InvalidArgumentException) { $failed=true; }
    $ok($failed,'invalid worker role refused');
}
$before=['ready'=>true,'reason'=>'PASS','series_id'=>'9','series_code'=>'P','scope_id'=>'1',
    'period_key'=>'2026','counter_id'=>'4','last_value'=>11,'start_number'=>1,'increment_by'=>1,
    'products'=>['a'=>null,'b'=>null,'rollback'=>null],'simple_combo_components'=>0];
foreach (["\n","\r\n"] as $end) {
    $message="CodeIgniter CLI".$end."M62B4_SNAPSHOT_JSON:".json_encode(array_merge($before,['marker'=>$marker]),JSON_THROW_ON_ERROR).$end;
    $parsed=M62B4Plan::parseSnapshot($message,$marker);
    $ok($parsed['series_id']==='9','Spark snapshot parser accepts Unix/Windows newlines');
}
$blocked=false;
try { M62B4Plan::parseSnapshot('M62B4_SNAPSHOT_JSON:{"ready":true,"marker":"M62B4_BAD"}', $marker); }
catch (RuntimeException) { $blocked=true; }
$ok($blocked,'snapshot parser refuses mismatched marker');
$after=$before;
$after['last_value']=13;
$after['products']['a']=['id'=>'101','code'=>'P0012','sku'=>$a['sku']];
$after['products']['b']=['id'=>'102','code'=>'P0013','sku'=>$b['sku']];
$wa=['status'=>201,'get_status'=>200,'id'=>'101','code'=>'P0012','sku'=>$a['sku'],'matched'=>true];
$wb=['status'=>201,'get_status'=>200,'id'=>'102','code'=>'P0013','sku'=>$b['sku'],'matched'=>true];
$ok(M62B4Plan::issued($before,$after,$wa,$wb),'two distinct committed writers and counter advance');
$ok(M62B4Plan::rolledBack($after,$after),'failed COMBO left products and counter unchanged');
foreach ([
    ['last_value'=>12], ['period_key'=>'2027'],
    ['simple_combo_components'=>1], ['products'=>['rollback'=>['id'=>'103','code'=>'P0014','sku'=>$combo['sku']]]],
    ['ready'=>false],
] as $changed) {
    $bad=array_replace_recursive($after,$changed);
    $ok(!M62B4Plan::issued($before,$bad,$wa,$wb),'issue invariant rejects adverse case');
}
foreach ([
    ['last_value'=>14], ['period_key'=>'2027'],
    ['products'=>['rollback'=>['id'=>'103','code'=>'P0014','sku'=>$combo['sku']]]],
    ['products'=>['a'=>['id'=>'999']]], ['counter_id'=>'999'],
] as $changed) {
    $bad=array_replace_recursive($after,$changed);
    $ok(!M62B4Plan::rolledBack($after,$bad),'rollback invariant rejects drift/orphan');
}
$wrongA=$wa; $wrongA['status']=403;
$ok(!M62B4Plan::issued($before,$after,$wrongA,$wb),'HTTP 403 cannot pass successful issuance');
$wrongB=$wb; $wrongB['id']='999';
$ok(!M62B4Plan::issued($before,$after,$wa,$wrongB),'read-back/SQL ID divergence detected');
$root=dirname(__DIR__);
$entry=file_get_contents($root.'/tools/m62b4_dev_atomicity.php');
$service=file_get_contents($root.'/tools/m62b4/M62B4DevAtomicity.php');
$worker=file_get_contents($root.'/tools/m62b4/worker.php');
$sql=file_get_contents($root.'/app/Commands/M62B4DevSnapshot.php');
$ok(str_contains($entry,'DEV_PRODUCT_ATOMICITY')&&str_contains($entry,"'--preflight'")
    &&str_contains($entry,"'--apply'"),'explicit opt-in is mandatory');
$ok(str_contains($entry,"'production'")&&str_contains($entry,'PLAN ONLY'),'fail production and inert default');
$ok(str_contains($service,"'a.ready','b.ready'") && str_contains($service,"'a.json','b.json'"),
    'two process rendezvous barriers');
$ok(str_contains($service,'M62B4Plan::rolledBack') && str_contains($service,"$".'this->snapshot('),
    'before/after SELECT-only counter snapshots');
$ok(str_contains($service,"'https://")===false,'HTTP caller uses pinned transport, not arbitrary URLs');
$ok(str_contains($worker,'M62B2DevTransport') && str_contains($worker,"'POST','/api/v1/products'"),
    'independently authenticated loopback worker');
$ok(!str_contains($worker,'db_connect(') && !str_contains($service,'db_connect('),
    'HTTP workers do not access SQL directly');
$ok(!str_contains($sql,'->insert(')&&!str_contains($sql,'->update(')&&!str_contains($sql,'->delete(')
    &&!str_contains($sql,'->truncate('),'SQL snapshot never mutates database');
$ok(str_contains($sql,"where('status','ACTIVE')") && str_contains($sql,"where('is_default',1)")
    && str_contains($sql,"!== 'GROUP'")===false && str_contains($sql,"!=='GROUP'"),
    'existing ACTIVE AUTO GROUP default is required');
$ok(str_contains($sql,'M62B4_SNAPSHOT_JSON:'),'SQL read-only snapshot machine protocol');
$run=static function(array $args)use($root):array {
    $p=proc_open([PHP_BINARY,$root.'/tools/m62b4_dev_atomicity.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if(!is_resource($p))throw new RuntimeException('No subprocess');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
};
[$exit,$stdout]=$run(['--group-id=3','--uom-id=1']);
$ok($exit===0 && str_contains($stdout,'PLAN ONLY'),'no HTTP or SQL in default mode');
foreach ([
    ['--group-id=3','--uom-id=1','--apply'],
    ['--group-id=3','--uom-id=1','--confirm=DEV_PRODUCT_ATOMICITY'],
    ['--group-id=3','--uom-id=1','--apply','--confirm=BAD'],
    ['--group-id=3','--uom-id=1','--preflight','--apply'],
    ['--group-id=3','--uom-id=1','--preflight','--confirm=DEV_PRODUCT_ATOMICITY'],
    ['--group-id=3','--uom-id=1','--unknown'],
    ['--group-id=3','--uom-id=1','--apply','--apply'],
    ['--group-id=0','--uom-id=1'],
    ['--group-id=3','--uom-id=0'],
] as $bad) {
    [$exit,$stdout]=$run($bad);
    $ok($exit===2 && !str_contains($stdout,'PLAN ONLY'),'unsafe CLI options rejected before runtime');
}
echo 'M6.2B.4 ATOMICITY SAFETY: '.$n.'/'.$n.' PASS'.PHP_EOL;

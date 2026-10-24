<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/tools/m63a1/M63A1MediaPlan.php';

$n=0;
$ok=static function (bool $cond,string $label) use (&$n):void {
    if (!$cond) throw new RuntimeException('M6.3A.1 safety: '.$label);
    $n++;
};
$marker='M62B2_1B6B52993C72';
$id='1466';
$ok(M63A1MediaPlan::marker($marker)===$marker,'pinned fixture marker');
$ok(M63A1MediaPlan::id($id)===$id,'pinned fixture ID');
$ok(is_bool(M63A1MediaPlan::codecsReady()),'GD codec check is deterministic');
$row=['id'=>$id, 'sku'=>'DEV_'.$marker,'product_name'=>'[DEV QA] Product '.$marker,
    'description'=>'M6.2B.2 HTTP PATCH confirmed '.$marker,'product_type'=>'STANDARD',
    'parent_product_id'=>null, 'product_code'=>'PRD-1234', 'status'=>'ACTIVE',
    'role'=>'SIMPLE','allowed_actions'=>['edit'=>true,'setstate'=>true],
    'image_available'=>false, 'image_url'=>null];
$original=['status'=>200,'json'=>['data'=>$row]];
$ok(M63A1MediaPlan::fixture($original,$marker,$id,true)['sku']==='DEV_'.$marker,'verified empty fixture');
$filled=$row; $filled['image_available']=true; $filled['image_url']='/uploads/2026/20261024product1234567.png';
$ok(M63A1MediaPlan::fixture(['status'=>200,'json'=>['data'=>$filled]],$marker,$id,false)['image_available'],
    'known own attached image allowed in readback');
foreach (['id'=>'1467','sku'=>'REAL', 'product_name'=>'real', 'description'=>'changed',
    'product_type'=>'COMBO', 'parent_product_id'=>'99', 'status'=>'INACTIVE',
    'role'=>'CHILD', 'allowed_actions'=>['edit'=>false],
    'image_available'=>true, 'image_url'=>'/uploads/2026/20261024product1234567.png'] as $field=>$bad) {
    $badDto=$row; $badDto[$field]=$bad;
    $refused=false;
    try { M63A1MediaPlan::fixture(['status'=>200,'json'=>['data'=>$badDto]],$marker,$id,true); }
    catch (RuntimeException|InvalidArgumentException) { $refused=true; }
    $ok($refused,'refuse modified existing fixture '.$field);
}
foreach (['','M62B2_abc','M62B4_1B6B52993C72','M62B2_1b6b52993c72',"M62B2_1B6B52993C72\n"] as $bad) {
    $blocked=false;
    try { M63A1MediaPlan::marker($bad); } catch(InvalidArgumentException) { $blocked=true; }
    $ok($blocked,'bad fixture marker rejected');
}
foreach (['0','-1','01','14x','123456789012345678901','1/../'] as $bad) {
    $blocked=false;
    try { M63A1MediaPlan::id($bad); } catch(InvalidArgumentException) { $blocked=true; }
    $ok($blocked,'bad fixture ID rejected');
}
$url='/uploads/2026/20261024product1234567.png';
$ok(M63A1MediaPlan::filename($url)==='20261024product1234567.png','filename validated');
$ok(str_ends_with(M63A1MediaPlan::physicalPath($url),
    DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'20261024product1234567.png'),'physical public/upload path');
foreach (['','/uploads/2027/20261024product1234567.png', '/uploads/2026/20261024avatar1234567.png',
    '/uploads/2026/../../writable/x.png', '/uploads/2026/20260230product1234567.png',
    'https://api.monmin.net/uploads/2026/20261024product1234567.png',
    '/uploads/2026/20261024product1234567.png?sql=1', '/upload/2026/20261024product1234567.png'] as $bad) {
    $blocked=false;
    try { M63A1MediaPlan::filename($bad); } catch(RuntimeException) { $blocked=true; }
    $ok($blocked,'unsafe URL refused');
}
$withImage=['status'=>200,'json'=>['data'=>array_replace($row,
    ['image_available'=>true,'image_url'=>$url])]];
$ok(M63A1MediaPlan::imageUrl($withImage,$id)===$url,'image URL from authorized Product DTO');
foreach ([['status'=>403,'json'=>['error'=>['code'=>'AUTH_POLICY_DENIED']]],
    ['status'=>200,'json'=>['data'=>array_replace($row,['id'=>'1467','image_available'=>true,'image_url'=>$url])]],
    ['status'=>200,'json'=>['data'=>array_replace($row,['image_available'=>true,'image_url'=>'../../private'])]]
    ] as $badResponse) {
    $blocked=false;
    try { M63A1MediaPlan::imageUrl($badResponse,$id); } catch (RuntimeException) { $blocked=true; }
    $ok($blocked,'invalid upload response refused');
}
M63A1MediaPlan::emptyAfterDelete($original,$id);
$ok(true,'image-free response after DELETE accepted');
$blocked=false;
try { M63A1MediaPlan::emptyAfterDelete($withImage,$id); } catch (RuntimeException) { $blocked=true; }
$ok($blocked,'DELETE response with image blocked');
foreach (['en','vi'] as $locale) {
    $message=M63A1MediaPlan::negative(['status'=>422,'language'=>$locale,
        'json'=>['error'=>['code'=>'PRODUCT_IMAGE_INVALID_TYPE','message'=>$locale.' error']]],
        'PRODUCT_IMAGE_INVALID_TYPE',$locale);
    $ok($message===$locale.' error','bilingual error contract');
}
foreach ([403,200,500] as $status) {
    $blocked=false;
    try { M63A1MediaPlan::negative(['status'=>$status,'language'=>'vi',
        'json'=>['error'=>['code'=>'PRODUCT_IMAGE_INVALID_TYPE','message'=>'test']]],
        'PRODUCT_IMAGE_INVALID_TYPE','vi'); } catch (RuntimeException) { $blocked=true; }
    $ok($blocked,'wrong negative status refused');
}
if (extension_loaded('gd') && function_exists('imagepng')) {
    $a=M63A1MediaPlan::sample(1); $b=M63A1MediaPlan::sample(2);
    $ok($a['bytes']!==$b['bytes'],'test images are different');
    $ok((new finfo(FILEINFO_MIME_TYPE))->buffer($a['bytes'])==='image/png','sample is a real PNG');
    $ok(strlen($a['bytes'])<3*1024*1024,'sample within max upload size');
}
$root=dirname(__DIR__);
$cli=file_get_contents($root.'/tools/m63a1_dev_media.php');
$driver=file_get_contents($root.'/tools/m63a1/M63A1DevMedia.php');
$transport=file_get_contents($root.'/tools/m63a1/M63A1MediaTransport.php');
$audit=file_get_contents($root.'/app/Commands/M63A1DevAudit.php');
$ok(is_string($cli)&&str_contains($cli,'DEV_PRODUCT_IMAGE_QA')&&str_contains($cli,'PLAN ONLY'),
    'inert default and explicit confirmation');
$ok(str_contains($cli,'--preflight') && str_contains($cli,'--apply')
    && str_contains($cli,"'production'"),'read-only/write mode and production guard');
$ok(str_contains($driver,'M63A1MediaPlan::fixture($http->json(\'GET\',$path),$marker,$id,true)'),
    'preflight checks pre-existing image BEFORE mutation');
$ok(str_contains($driver,'M63A1MediaPlan::codecsReady()'),'preflight enforces full image codec support');
$ok(str_contains($driver,'if (!$apply)')&&str_contains($driver,"'PASS_DEV_PRODUCT_MEDIA_PREFLIGHT'"),
    'preflight returns before POST');
$ok(str_contains($driver,"->multipart(") && str_contains($driver,"->json('DELETE'")
    && str_contains($driver,'verifyStatic') && str_contains($driver,'physicalPath'),
    'HTTP/Apache/FS happy-path measured');
$ok(!str_contains($driver,'db_connect(') && !str_contains($driver,'DELETE FROM')
    && !str_contains($driver,'->update('),'HTTP runner never edits SQL directly');
$ok(str_contains($transport,"'https://127.0.0.1'")
    && str_contains($transport,"'api.monmin.net'") && str_contains($transport,'follow_location')
    && !str_contains($transport,'https://api.monmin.net'),
    'pinned loopback origin and redirects disabled');
$ok(str_contains($transport,"'multipart/form-data; boundary='")
    && str_contains($transport,'Content-Disposition: form-data; name='),
    'real multipart image form construction');
$ok(str_contains($audit,"where('sku','DEV_'") && str_contains($audit,'M62B3Fixture::assertRow')
    && !str_contains($audit,'->update(') && !str_contains($audit,'->delete(')
    && !str_contains($audit,'->insert('),'SQL audit read only and fixture-bound');
$run=static function(array $args)use($root):array {
    $process=proc_open([PHP_BINARY,$root.'/tools/m63a1_dev_media.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if(!is_resource($process)) throw new RuntimeException('Unable to spawn media QA subprocess.');
    fclose($pipes[0]); $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process),$stdout,$stderr];
};
$base=['--marker='.$marker,'--product-id='.$id];
[$exit,$out]=$run($base);
$ok($exit===0&&str_contains($out,'PLAN ONLY'),'default is truly no HTTP');
foreach ([
    ['--apply'], ['--confirm=DEV_PRODUCT_IMAGE_QA'],
    ['--apply','--confirm=BAD'], ['--preflight','--apply'],
    ['--preflight','--confirm=DEV_PRODUCT_IMAGE_QA'],
    ['--unknown'], ['--apply','--apply'], ['--preflight','--preflight'],
    ['--marker='.$marker], ['--product-id='.$id],
    ['--apply','--confirm=DEV_PRODUCT_IMAGE_QA','--preflight'],
] as $args) {
    [$exit,$out]=$run([...$base,...$args]);
    $ok($exit===2&&!str_contains($out,'PLAN ONLY'),'invalid flags refused before HTTP');
}
echo 'M6.3A.1 MEDIA DEV SAFETY: '.$n.'/'.$n.' PASS'.PHP_EOL;

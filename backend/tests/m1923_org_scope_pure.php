<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Modules/Access/Query/TrustedOrganizationIdPager.php';
require_once __DIR__ . '/../app/Modules/Access/Query/AuthorizedSqlScope.php';

use App\Modules\Access\Query\TrustedOrganizationIdPager as P;
use App\Modules\Access\Query\AuthorizedSqlScope as S;

$n=0;
$ok=static function(bool $v,string $label)use(&$n):void{if(!$v)throw new RuntimeException('M19.23 ORG: '.$label);++$n;};
$reject=static function(callable $cb,string $label)use($ok):void{
    try{$cb();}catch(UnexpectedValueException|InvalidArgumentException){$ok(true,$label);return;}
    throw new RuntimeException('M19.23 unsafe accepted: '.$label);
};
$requests=0;
$ids=P::collect(static function(?string $last,int $size)use(&$requests):array{
    ++$requests;
    $start=$last === null?1:(int)$last+1;
    $rows=[];
    for($i=$start;$i<=1200 && count($rows)<$size;$i++)$rows[]=['id'=>(string)$i];
    return $rows;
});
$ok(count($ids)===1200 && $ids[0]==='1' && end($ids)==='1200','1200 organizations without 512 ceiling');
$ok($requests>=5 && $requests<=7,'paged metadata not full table query');
$reject(fn()=>P::collect(static fn(?string $last,int $size):array=>[['id'=>'1'],['id'=>'1']]),'duplicate org denied');
$reject(fn()=>P::collect(static fn(?string $last,int $size):array=>[['id'=>'0']]),'invalid persisted org denied');
$reject(fn()=>P::collect(static fn(?string $last,int $size):array=>[['id'=>'2'],['id'=>'1']]),'out-of-order org denied');
$reject(fn()=>P::collect(static fn(?string $last,int $size):array=>[['id'=>"1') OR 1=1"]]),'id injection denied');
$reject(fn()=>P::collect(static fn(?string $last,int $size):array=>[],5000),'unreviewed oversized budget');
$reject(fn()=>P::collect(static function(?string $last,int $size):array{
    $start=$last===null?1:(int)$last+1;$out=[];
    for($i=$start;$i<=$start+$size-1;$i++)$out[]=['id'=>(string)$i];
    return $out;
}),'hard 4096 ceiling fail closed without truncation');
$slots=[];
for($i=1;$i<=1200;$i++){
    $slots[]=['organization_id'=>(string)$i,'status'=>'ACTIVE'];
    if($i % 2===0)$slots[]=['organization_id'=>(string)$i,'status'=>'DRAFT'];
}
foreach(['organization', 'employee_assignment', 'organization_contact', 'employee_contact'] as $resource){
    $scope=S::organization($resource,$slots);
    $pred=str_ends_with($resource,'contact')
        ? $scope->contactOwnerPredicate($resource==='organization_contact'?'o':'a','c')
        : $scope->predicate('p');
    $ok(str_contains($pred['sql'],' IN (') && substr_count($pred['sql'],'= ?)')===2,'one status group per canonical state '.$resource);
    $ok(count($pred['binds'])===1802 && substr_count($pred['sql'],'?')===count($pred['binds']),'compact bounded binds '.$resource);
    $ok($pred['binds'][0]==='1' && $pred['binds'][1199]==='1200' && $pred['binds'][1200]==='ACTIVE','original first state set '.$resource);
    $ok(end($pred['binds'])==='DRAFT','second state retained '.$resource);
}
$bad=S::organization('employee_contact',[]);
$ok($bad->contactOwnerPredicate('a','c')['sql']==='1 = 0','empty scope fail closed');
$source=(string)file_get_contents(__DIR__.'/../app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($source,'if (count($slots) > 16384)'), 'canonical per-state budget still enforced');
echo 'M19.23 ORGANIZATION SCOPE: '.$n.'/'.$n." PASS\n";

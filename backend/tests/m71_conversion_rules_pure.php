<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Product/Conversion/ProductConversionRules.php';
require_once __DIR__.'/../app/Modules/Product/Conversion/ProductConversionSql.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Conversion\ProductConversionRules as R;
use App\Modules\Product\Conversion\ProductConversionSql as S;

$n=0;
$ok=static function(bool $passed,string $name)use(&$n):void {if (!$passed) throw new RuntimeException('M7.1 rules: '.$name); ++$n;};
$reject=static function(callable $work,string $code,string $name)use($ok):void {
    try {$work(); $ok(false,$name);} catch (DomainException $e) {$ok($e->getErrorCode()===$code,$name);}
};
$product=['product_type'=>'STANDARD','base_uom_id'=>'1'];
$ok(R::request([])['items']===[],'empty replacement deletes all');
$ok(R::productId('000123')==='123','canonical product BIGINT');
$ok(R::productId('18446744073709551615')==='18446744073709551615','max BIGINT');
$reject(static fn()=>R::productId('0'),'PRODUCT_NOT_FOUND','zero Product ID rejected');
$reject(static fn()=>R::productId('18446744073709551616'),'PRODUCT_NOT_FOUND','overflow Product ID rejected');
$payload=['uom_id'=>'0002','conversion_factor'=>'12.123456', 'usage_type'=>'PURCHASE'];
$row=R::request(['items'=>[$payload]])['items'][0];
$ok($row['uom_id']==='2','exact UOM ID');
$ok($row['conversion_factor']==='12.123456','exact factor');
$ok($row['status']==='ACTIVE','default status ACTIVE');
$ok($row['usage_type']==='PURCHASE','explicit purchase');
$ok(!isset($row['price']),'unspecified nullable price left out');
$ok(R::request(['items'=>[$payload], 'evaluation_organization_id'=>'5'])['hint']==='5','organization hint is returned only for server validation');
$ok(R::request([['uom_id'=>'2','conversion_factor'=>'1.000000','price'=>'0']])['items'][0]['usage_type']==='BOTH','default BOTH');
$ok(R::request([['uom_id'=>'2','conversion_factor'=>1,'price'=>0]])['items'][0]['price']==='0','integer amounts stringified');
$reject(static fn()=>R::request(['items'=>'invalid']),'PRODUCT_CONVERSION_INVALID','items type');
$reject(static fn()=>R::request(['items'=>[['uom_id'=>'2','conversion_factor'=>'1']], 'extra'=>1]),'PRODUCT_CONVERSION_INVALID','extra root');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','id'=>100]]),'PRODUCT_CONVERSION_INVALID','server controlled id');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','created_at'=>'x']]),'PRODUCT_CONVERSION_INVALID','server controlled audit');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','product_id'=>10]]),'PRODUCT_CONVERSION_INVALID','other product ID');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1'],['uom_id'=>'02','conversion_factor'=>'2']]),'PRODUCT_CONVERSION_DUPLICATE','canonical duplicate IDs');
$reject(static fn()=>R::request([['uom_id'=>0,'conversion_factor'=>'1']]),'PRODUCT_CONVERSION_INVALID_UOM','zero UOM ID');
$reject(static fn()=>R::request([['uom_id'=>true,'conversion_factor'=>'1']]),'PRODUCT_CONVERSION_INVALID_UOM','boolean UOM ID');
$reject(static fn()=>R::request([['uom_id'=>'2']]),'PRODUCT_CONVERSION_INVALID_DECIMAL','factor required');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'0.000000']]),'PRODUCT_CONVERSION_INVALID','factor positive');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'-1']]),'PRODUCT_CONVERSION_INVALID','factor positive nonnegative regex');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>0.1]]),'PRODUCT_CONVERSION_INVALID_DECIMAL','float rejected');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1.0000001']]),'PRODUCT_CONVERSION_INVALID_DECIMAL','factor scale 6');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1234567890123']]),'PRODUCT_CONVERSION_INVALID_DECIMAL','factor integer precision 12');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','price'=>'0.001']]),'PRODUCT_CONVERSION_INVALID_DECIMAL','price scale 2');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','package_length_mm'=>'0.0001']]),'PRODUCT_CONVERSION_INVALID_DECIMAL','package scale 3');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','status'=>'BAD']]),'PRODUCT_CONVERSION_INVALID','bad status');
$reject(static fn()=>R::request([['uom_id'=>'2','conversion_factor'=>'1','usage_type'=>'RETAIL']]),'PRODUCT_CONVERSION_INVALID','bad usage');
$reject(static fn()=>R::request(array_fill(0,R::MAX_ITEMS+1,['uom_id'=>1,'conversion_factor'=>1])),'PRODUCT_CONVERSION_INVALID','bounded item count');
$reject(static fn()=>R::assertPrice(['usage_type'=>'BOTH','price'=>null]),'PRODUCT_CONVERSION_PRICE_REQUIRED','price mandatory for BOTH');
$reject(static fn()=>R::assertPrice(['usage_type'=>'SALES','price'=>null]),'PRODUCT_CONVERSION_PRICE_REQUIRED','price mandatory for SALES');
R::assertPrice(['usage_type'=>'PURCHASE','price'=>null]); $ok(true,'PURCHASE without price');
R::assertPrice(['usage_type'=>'BOTH','price'=>'0']); $ok(true,'zero price allowed');
$reject(static fn()=>R::assertPrice(['usage_type'=>'SALES','price'=>'-0.01']),'PRODUCT_CONVERSION_INVALID','negative price');
R::assertEligible($product,false); $ok(true,'simple STANDARD eligible');
R::assertEligible([...$product,'parent_product_id'=>'5'],false); $ok(true,'child STANDARD eligible');
$reject(static fn()=>R::assertEligible(['product_type'=>'COMBO','base_uom_id'=>'1'],false),'PRODUCT_CONVERSION_INVALID_ROLE','combo ineligible');
$reject(static fn()=>R::assertEligible($product,true),'PRODUCT_CONVERSION_INVALID_ROLE','parent ineligible');
$reject(static fn()=>R::assertEligible(['product_type'=>'STANDARD','base_uom_id'=>null],false),'PRODUCT_BASE_UOM_REQUIRED','base UOM required');
$sql=S::authorizedProduct('123',['ACTIVE','INACTIVE','ACTIVE'],'dinktech_');
$ok(substr_count($sql['sql'],'?')===3,'bound ID plus two statuses');
$ok($sql['binds']===['123','ACTIVE','INACTIVE'],'status values remain bound');
$ok(str_contains($sql['sql'],'dinktech_products'),'trusted DB prefix');
$ok(str_contains($sql['sql'],'UPPER(TRIM(p.status))'),'VIEW pushdown canonical statuses');
$none=S::authorizedProduct('123',[]);
$ok(str_contains($none['sql'],'WHERE 1=0') && $none['binds']===[],'fail-closed no view states');
$badPrefix=false;
try {S::authorizedProduct('1',['ACTIVE'],'abc; DROP');}catch(InvalidArgumentException){$badPrefix=true;}
$ok($badPrefix,'reject SQL prefix injection');
$badState=false;
try {S::authorizedProduct('1',['ACTIVE OR 1=1']);}catch(InvalidArgumentException){$badState=true;}
$ok($badState,'reject untrusted states');
echo 'M7.1 CONVERSION RULES: '.$n.'/'.$n." PASS\n";

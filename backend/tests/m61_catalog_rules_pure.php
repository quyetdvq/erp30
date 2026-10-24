<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../app/Modules/Product/Catalog/ProductCatalogRules.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Catalog\ProductCatalogRules as R;

$n=0;
$ok=static function(bool $result,string $why) use (&$n):void {
    if (!$result) throw new RuntimeException('M6.1 rules: '.$why);
    ++$n;
};
$reject=static function(callable $work,string $code)use($ok):void {
    try { $work(); } catch(DomainException $e) { $ok($e->getErrorCode()===$code, 'expected '.$code);return; }
    throw new RuntimeException('M6.1 should reject '.$code);
};
$ok(R::type('product_group')==='product_group','group type');
$ok(R::type('product_uom')==='product_uom','uom type');
try { R::type('pricing'); throw new RuntimeException('bad resource accepted'); } catch(InvalidArgumentException) { $ok(true,'other resource refused'); }
$ok(R::id('00018','product_group')==='18','positive bigint normalized');
$reject(fn()=>R::id('0','product_uom'),'PRODUCT_UOM_INVALID_ID');
$reject(fn()=>R::id('18446744073709551616','product_group'),'PRODUCT_GROUP_INVALID_ID');
$g=R::payload('product_group',['code'=>'  ktá  ','name'=>' Nhóm hàng ','parent_id'=>12,'status'=>'draft','updated_by'=>5],true);
$ok($g['code']==='KTá' && $g['name']==='Nhóm hàng' && $g['parent_id']==='12','Group baseline ASCII strtoupper and Unicode name');
$ok($g['status']==='DRAFT' && !isset($g['updated_by']),'group initial client hint only and audit stripped');
$ok(R::payload('product_group',['code'=>'xx','name'=>'A'],true)['parent_id']===null,'group root defaults null');
$ok(R::payload('product_group',['parent_id'=>null],false)===['parent_id'=>null],'group can be reparented to root');
$reject(fn()=>R::payload('product_group',['parent_id'=>0],false),'PRODUCT_GROUP_PARENT_NOT_FOUND');
$reject(fn()=>R::payload('product_group',['parent_id'=>1.2],false),'PRODUCT_GROUP_PARENT_NOT_FOUND');
$reject(fn()=>R::payload('product_group',['parent_id'=>new stdClass()],false),'PRODUCT_GROUP_PARENT_NOT_FOUND');
$reject(fn()=>R::payload('product_group',['code'=>'','name'=>'A'],true),'PRODUCT_GROUP_REQUIRED_FIELD');
$reject(fn()=>R::payload('product_group',['name'=>'A'],true),'PRODUCT_GROUP_REQUIRED_FIELD');
$reject(fn()=>R::payload('product_group',['code'=>'X'],true),'PRODUCT_GROUP_REQUIRED_FIELD');
$reject(fn()=>R::payload('product_group',['code'=>str_repeat('a',51),'name'=>'B'],true),'PRODUCT_GROUP_REQUIRED_FIELD');
$reject(fn()=>R::payload('product_group',['code'=>'A','name'=>str_repeat('b',256)],true),'PRODUCT_GROUP_REQUIRED_FIELD');
$reject(fn()=>R::payload('product_group',['organization_id'=>5],false),'PRODUCT_GROUP_FIELD_UNSUPPORTED');
$reject(fn()=>R::payload('product_group',['status'=>'active'],false),'PRODUCT_GROUP_STATUS_REQUIRES_SETSTATE');
$reject(fn()=>R::payload('product_group',[],false),'PRODUCT_GROUP_UPDATE_EMPTY');
$reject(fn()=>R::payload('product_group',['description'=>new stdClass()],false),'PRODUCT_GROUP_INVALID_FIELD');
$u=R::payload('product_uom',['code'=>' kg ','name'=>'Kilôgam','status'=>'draft','created_at'=>'forged'],true);
$ok($u['code']==='kg' && $u['name']==='Kilôgam' && $u['status']==='DRAFT' && !isset($u['created_at']),'UOM respects case and locale');
$reject(fn()=>R::payload('product_uom',['code'=>null,'name'=>'N'],true),'PRODUCT_UOM_CODE_REQUIRED');
$reject(fn()=>R::payload('product_uom',['code'=>str_repeat('A',31),'name'=>'N'],true),'PRODUCT_UOM_CODE_TOO_LONG');
$reject(fn()=>R::payload('product_uom',['code'=>'k','name'=>str_repeat('A',101)],true),'PRODUCT_UOM_NAME_TOO_LONG');
$reject(fn()=>R::payload('product_uom',['code'=>'k'],true),'PRODUCT_UOM_NAME_REQUIRED');
$reject(fn()=>R::payload('product_uom',['parent_id'=>null],false),'PRODUCT_UOM_FIELD_UNSUPPORTED');
$reject(fn()=>R::payload('product_uom',['description'=>str_repeat('x',256)],false),'PRODUCT_UOM_DESCRIPTION_TOO_LONG');
$reject(fn()=>R::payload('product_uom',['status'=>'ACTIVE'],false),'PRODUCT_UOM_STATUS_REQUIRES_SETSTATE');
$reject(fn()=>R::payload('product_uom',[],false),'PRODUCT_UOM_UPDATE_EMPTY');
$ok(R::requestedState('product_group',['status'=>'inactive'])==='INACTIVE','state normalized');
$reject(fn()=>R::requestedState('product_group',['status'=>'ACTIVE','admin'=>true]),'PRODUCT_GROUP_INVALID_STATUS');
$reject(fn()=>R::requestedState('product_uom',['status'=>null]),'PRODUCT_UOM_INVALID_STATUS');
$tree=[['id'=>'1','parent_id'=>null],['id'=>'2','parent_id'=>'1'],['id'=>'3','parent_id'=>'2']];
R::assertAcyclicGroupParent($tree,'3','1');$ok(true,'valid reparent');
R::assertAcyclicGroupParent($tree,'2',null);$ok(true,'root accepted');
$reject(fn()=>R::assertAcyclicGroupParent($tree,'2','2'),'PRODUCT_GROUP_SELF_PARENT');
$reject(fn()=>R::assertAcyclicGroupParent($tree,'1','3'),'PRODUCT_GROUP_HIERARCHY_CYCLE');
$reject(fn()=>R::assertAcyclicGroupParent($tree,null,'999'),'PRODUCT_GROUP_PARENT_NOT_FOUND');
$reject(fn()=>R::assertAcyclicGroupParent([['id'=>'1','parent_id'=>'2'],['id'=>'2','parent_id'=>'1']],null,'1'),'PRODUCT_GROUP_CORRUPTED_HIERARCHY');
echo 'M6.1 CATALOG RULES: '.$n.'/'.$n." PASS\n";

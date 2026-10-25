<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductReadRules.php';
require_once __DIR__.'/../app/Modules/Product/Lookup/ProductLookupProjection.php';
require_once __DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipProjection.php';

use App\Modules\Product\Master\ProductReadRules;
use App\Modules\Product\Lookup\ProductRelationshipProjection as P;
use App\Exceptions\DomainException;

$n=0;
$ok=static function(bool $result,string $label)use(&$n):void{if(!$result)throw new RuntimeException('M7.4 DTO: '.$label);++$n;};
$root=['id'=>'1','code'=>'PR','sku'=>'R','product_type'=>'STANDARD','parent_product_id'=>null,
    'product_name'=>' Parent ','product_child_name'=>null,'product_group_id'=>'10','base_uom_id'=>'2',
    'status'=>'ACTIVE','price'=>'10.00','serial_management_enabled'=>'1','image_filename'=>'20261025product1234567.jpg',
    'image_mime'=>'image/jpeg','image_size_bytes'=>1000];
$child=array_replace($root,['id'=>'2','code'=>'CH','parent_product_id'=>'1','product_name'=>null,
    'product_child_name'=>' Child ','image_filename'=>null,'serial_management_enabled'=>'0']);
$simple=array_replace($root,['id'=>'3','code'=>'S','product_name'=>'Solo','status'=>'DRAFT','base_uom_id'=>null,
    'image_filename'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','price'=>'1.23']);
$combo=array_replace($root,['id'=>'4','code'=>'C','product_type'=>'COMBO','product_name'=>'Combo','image_filename'=>null]);
$original=ProductReadRules::describe([$root,$child,$simple,$combo]);
$rows=[array_replace($root,['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>1]),
    array_replace($child,['parent_type'=>'STANDARD','parent_parent_id'=>null,'parent_name'=>' Parent ','has_children'=>0]),
    array_replace($simple,['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>0]),
    array_replace($combo,['parent_type'=>null,'parent_parent_id'=>null,'parent_name'=>null,'has_children'=>0])];
foreach($rows as $row){
    $id=$row['id'];$dto=P::detail($row);$expected=$original[$id];
    foreach(['id','product_code','product_type','role','full_name','selectable',
        'leaf_transactionable','serial_management_enabled','image_available','image_url',
        'status','price','base_uom_id','product_group_id','sku'] as $key){
        $ok(($dto[$key]??null)===($expected[$key]??null),'Product projection parity id '.$id.' '.$key);
    }
    $ok(!isset($dto['code']) && !array_key_exists('image_filename',$dto),'no private columns in dto '.$id);
    $ok(!array_key_exists('parent_type',$dto) && !array_key_exists('has_children',$dto),'no joined auxiliary columns '.$id);
}
$ok($original['1']['role']==='PARENT' && $original['2']['role']==='CHILD','fixture includes root/child');
$ok($original['3']['selectable']===false,'inactive/draft is not newly selectable');
$ok($original['4']['role']==='COMBO' && !$original['4']['leaf_transactionable'],'combo semantics');
foreach([
    array_replace($rows[0], ['product_child_name'=>'invalid']),
    array_replace($rows[1], ['parent_type'=>'COMBO']),
    array_replace($rows[1], ['has_children'=>1]),
    array_replace($rows[3], ['parent_product_id'=>'1']),
] as $bad){
    try{P::detail($bad);throw new RuntimeException('Malformed Product relation accepted');}
    catch(DomainException $e){$ok($e->getErrorCode()==='PRODUCT_INVALID_ROLE','invalid joined Product role rejected');}
}
echo 'M7.4 PRODUCT DTO FIXTURE: '.$n.'/'.$n." PASS; no HTTP/DB\n";

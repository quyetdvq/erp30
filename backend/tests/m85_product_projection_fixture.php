<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
require_once __DIR__.'/../app/Modules/Product/Master/ProductReadRules.php';

use App\Modules\Product\Master\ProductReadRules as Rules;
use App\Exceptions\DomainException;

$n=0;
$ok=static function(bool $yes,string $name)use(&$n):void{if(!$yes)throw new RuntimeException('M8.5 PROJECT: '.$name);++$n;};
$root=['id'=>1,'code'=>'PX','sku'=>null,'product_type'=>'STANDARD','parent_product_id'=>null,
 'product_name'=>' Thang nhôm ','product_child_name'=>null,'status'=>'ACTIVE',
 'image_filename'=>'20261024product1234567.webp','serial_management_enabled'=>'1'];
$child=['id'=>2,'code'=>'PXC','sku'=>null,'product_type'=>'STANDARD','parent_product_id'=>1,
 'product_name'=>null,'product_child_name'=>' Cỡ nhỏ ', 'status'=>'ACTIVE',
 'image_filename'=>null,'serial_management_enabled'=>'0'];
$solo=['id'=>3,'code'=>'S','product_type'=>'STANDARD','parent_product_id'=>null,
 'product_name'=>'Sản phẩm riêng','product_child_name'=>null,'status'=>'INACTIVE',
 'image_filename'=>null,'serial_management_enabled'=>0];
$combo=['id'=>4,'code'=>'C','product_type'=>'COMBO','parent_product_id'=>null,
 'product_name'=>'Combo','product_child_name'=>null,'status'=>'DRAFT',
 'image_filename'=>null,'serial_management_enabled'=>0];
$all=Rules::describe([$root,$child,$solo,$combo]);
$joined=[
 array_replace($root,['__parent_name'=>null,'__parent_type'=>null,'__parent_parent_id'=>null,'__parent_child_name'=>null,'__has_children'=>1]),
 array_replace($child,['__parent_name'=>' Thang nhôm ','__parent_visible'=>1,'__parent_type'=>'STANDARD','__parent_parent_id'=>null,'__parent_child_name'=>null,'__has_children'=>0]),
 array_replace($solo,['__parent_name'=>null,'__parent_type'=>null,'__parent_parent_id'=>null,'__parent_child_name'=>null,'__has_children'=>0]),
 array_replace($combo,['__parent_name'=>null,'__parent_type'=>null,'__parent_parent_id'=>null,'__parent_child_name'=>null,'__has_children'=>0]),
];
foreach ($joined as $row) {
    $id=(string)$row['id'];
    $ok(Rules::describeJoined($row)===$all[$id],'bounded page DTO equals full catalog '.$id);
}
$ok(!isset(Rules::describeJoined($joined[0])['image_filename']),'image private filename absent');
$ok(Rules::describeJoined($joined[1])['full_name']==='Thang nhôm - Cỡ nhỏ','child label identical');
$ok(Rules::describeJoined($joined[0])['role']==='PARENT','PARENT derived from EXISTS');
$ok(Rules::describeJoined($joined[2])['role']==='SIMPLE','SIMPLE derived from not EXISTS');
$ok(Rules::describeJoined($joined[3])['role']==='COMBO','COMBO preserved');
$hiddenParent=array_replace($joined[1],['__parent_visible'=>0,'__parent_name'=>null]);
$hiddenDTO=Rules::describeJoined($hiddenParent);
$ok($hiddenDTO['role']==='CHILD' && $hiddenDTO['full_name']==='Cỡ nhỏ',
    'child remains visible but hidden parent name not leaked');
foreach ([
 array_replace($joined[0],['__has_children'=>null]),
 array_replace($joined[1],['__parent_type'=>'COMBO']),
 array_diff_key($joined[1], ['__parent_visible'=>true]),
 array_replace($joined[1],['__parent_parent_id'=>'9']),
 array_replace($joined[1],['__has_children'=>1]),
 array_replace($joined[3],['parent_product_id'=>'1']),
 array_replace($joined[0],['product_child_name'=>'unexpected']),
] as $bad) {
    try { Rules::describeJoined($bad);throw new RuntimeException('Malformed projection accepted'); }
    catch (DomainException $e) {$ok($e->getErrorCode()==='PRODUCT_INVALID_ROLE','invalid projection fails closed');}
}
echo 'M8.5 PRODUCT DTO PARITY: '.$n.'/'.$n." PASS\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require_once __DIR__ . '/../app/Modules/Product/Master/ProductReadRules.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Master\ProductReadRules as R;

$n = 0;
$ok = static function (bool $condition, string $why) use (&$n): void {
    if (! $condition) throw new RuntimeException('M6.2A Product role: ' . $why);
    ++$n;
};
$bad = static function (array $rows, string $why) use ($ok): void {
    try { R::describe($rows); }
    catch (DomainException $error) { $ok($error->getErrorCode() === 'PRODUCT_INVALID_ROLE', $why); return; }
    throw new RuntimeException('M6.2A Product role must reject ' . $why);
};
$simple = ['id'=>1, 'code'=>'SKU01', 'product_type'=>'STANDARD', 'status'=>'ACTIVE',
    'product_name'=>' Bánh mì ', 'parent_product_id'=>null, 'product_child_name'=>null, 'serial_management_enabled'=>'1',
    'image_filename'=>'a-private-file.jpg', 'image_mime'=>'image/jpeg'];
$child = ['id'=>2, 'code'=>'SKU02', 'product_type'=>'STANDARD', 'status'=>'ACTIVE',
    'product_name'=>null, 'product_child_name'=>' Cỡ nhỏ ', 'parent_product_id'=>1, 'serial_management_enabled'=>'0'];
$combo = ['id'=>3, 'code'=>'COMBO01', 'product_type'=>'COMBO', 'status'=>'DRAFT', 'parent_product_id'=>null,
    'product_name'=>'Combo món', 'product_child_name'=>null, 'serial_management_enabled'=>0];
$rows = R::describe([$simple, $child, $combo]);
$ok(count($rows)===3, 'complete catalog described');
$ok($rows[1]['role']==='PARENT' && $rows[1]['full_name']==='Bánh mì', 'root with child');
$ok($rows[1]['selectable']===false && $rows[1]['leaf_transactionable']===false, 'parent not sale leaf');
$ok($rows[2]['role']==='CHILD' && $rows[2]['full_name']==='Bánh mì - Cỡ nhỏ', 'child full name');
$ok($rows[2]['selectable']===true && $rows[2]['leaf_transactionable']===true, 'active child selectable');
$ok($rows[3]['role']==='COMBO' && $rows[3]['selectable']===false && $rows[3]['leaf_transactionable']===false, 'draft combo');
$ok($rows[1]['product_code']==='SKU01' && !isset($rows[1]['code']), 'public product_code alias');
$ok($rows[1]['serial_management_enabled'] === true && $rows[2]['serial_management_enabled'] === false, 'serial boolean normalized');
$ok($rows[1]['image_available']===true && $rows[1]['image_url']===null, 'unrecognized legacy media stays private');
$static = R::describe([array_replace($simple, ['image_filename'=>'20261024product1234567.webp'])]);
$ok($static[1]['image_url'] === '/uploads/2026/20261024product1234567.webp',
    'known Product filename resolves to static URL with no database image read');
$ok(!isset($rows[1]['image_filename'], $rows[1]['image_mime']), 'image metadata redacted');
$solo = R::describe([$simple]);
$ok($solo[1]['role']==='SIMPLE' && $solo[1]['selectable']===true, 'standalone is simple');
$ok(R::listQuery(['sort'=>'-product_id,price'])['sort']==='-id,price', 'frozen product_id alias');
$ok(R::listQuery(['sort'=>'full_name'])['sort']==='full_name', 'normal sort unchanged');
$ok(R::listQuery(['filter'=>['status'=>'ACTIVE']])['filter']['status']==='ACTIVE', 'filter unchanged');
$bad([$simple,$simple],'duplicate ID');
$bad([$child],'missing parent');
$bad([array_replace($simple,['id'=>4,'parent_product_id'=>4])],'self parent');
$bad([array_replace($combo,['id'=>4,'parent_product_id'=>1]),$simple],'combo must be root');
$bad([$simple,array_replace($child,['id'=>4,'parent_product_id'=>3]),$combo],'combo cannot parent');
$bad([$simple,array_replace($child,['id'=>4,'product_name'=>'illegal'])],'child must not contain root name');
$bad([array_replace($simple,['id'=>4,'product_child_name'=>'illegal'])],'root must not contain child name');
$bad([$simple,$child,array_replace($child,['id'=>4,'parent_product_id'=>2])],'nested child');
$bad([array_replace($simple,['id'=>0])],'positive IDs');
$bad([array_replace($simple,['product_type'=>'OTHER'])],'invalid product type');
$bad([array_replace($simple,['product_name'=>'  '])],'missing name');
$bad([array_replace($combo,['product_child_name'=>'xx'])],'combo cannot have child name');
echo 'M6.2A PRODUCT ROLE: '.$n.'/'.$n." PASS\n";

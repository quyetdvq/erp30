<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Product/Conversion/ProductConversionPlan.php';

use App\Exceptions\DomainException;
use App\Modules\Product\Conversion\ProductConversionPlan as P;

$n=0;
$ok=static function(bool $pass,string $why)use(&$n):void {if(!$pass)throw new RuntimeException('M7.1 plan fixture: '.$why);++$n;};
$old=[['id'=>'10','uom_id'=>'2','price'=>'10'],['id'=>'11','uom_id'=>'3','price'=>'20']];
$new=[['uom_id'=>'2','price'=>'12'],['uom_id'=>'4','price'=>'40']];
$p=P::build($old,$new);
$ok(count($p['deletes'])===1,'one delete');
$ok((string)$p['deletes'][0]['id']==='11','stale UOM deleted');
$ok(count($p['updates'])===1,'one update');
$ok($p['updates'][0]['old']['id']==='10','stable original row ID');
$ok($p['updates'][0]['new']['price']==='12','updated replacement price');
$ok(count($p['creates'])===1,'one create');
$ok($p['creates'][0]['uom_id']==='4','new alternate UOM');
$ok(P::build([],$new)['updates']===[],'fresh set has no updates');
$ok(P::build($old,[])['creates']===[],'clear set has no insert');
$ok(count(P::build($old,[])['deletes'])===2,'clear set deletes all');
$ok(P::build($old,$old)['deletes']===[],'identical set does not delete');
try {P::build($old,[['uom_id'=>'2'],['uom_id'=>'2']]); $ok(false,'reject duplicate');}
catch(DomainException $e){$ok($e->getErrorCode()==='PRODUCT_CONVERSION_DUPLICATE','duplicate plan fail-closed');}
try {P::build([$old[0],$old[0]],$new);$ok(false,'reject corrupted existing set');}
catch(DomainException $e){$ok($e->getErrorCode()==='PRODUCT_CONVERSION_MUTATION_FAILED','corrupted existing set fail-closed');}
// Simulate a transaction rollback with a copy-on-write model. Not a MariaDB test.
$data=$old;
try {
    foreach ($p['deletes'] as $row) {
        $data=array_values(array_filter($data,static fn($x)=>$x['id']!==$row['id']));
    }
    throw new RuntimeException('injected write error');
} catch (RuntimeException $e) {
    $data=$old; // model transaction restoration
}
$ok($data===$old,'fixture transaction rollback model');
echo 'M7.1 CONVERSION PLAN FIXTURE: '.$n.'/'.$n." PASS (not live SQL)\n";

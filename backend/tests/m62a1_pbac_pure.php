<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../app/Modules/Access/UnifiedAccessService.php';

use App\Modules\Access\UnifiedAccessService;

$n=0;
$ok = static function (bool $value, string $reason) use (&$n): void {
    if (! $value) throw new RuntimeException('M6.2A.1 PBAC: ' . $reason);
    ++$n;
};
// Inject only an already-resolved permission snapshot for this pure method;
// no Shield/CI4 connection, synthetic user or DB actor is ever trusted in HTTP.
$class = new ReflectionClass(UnifiedAccessService::class);
$object = $class->newInstanceWithoutConstructor();
$property = $class->getProperty('actor');
$property->setValue($object, ['permissions' => [
    'product_group.view' => true, 'product.view' => true,
]]);
$ok($object->hasBaseViewPermission('product_group'), 'ERP group grant must pass base precondition');
$ok($object->hasBaseViewPermission(' PRODUCT '), 'normalizes product resource name');
$ok(! $object->hasBaseViewPermission('product_uom'), 'missing view grant denied');
$ok(! $object->hasBaseViewPermission('policy'), 'unrelated grant denied');
try {
    $object->hasBaseViewPermission('product.view');
    throw new RuntimeException('invalid type accepted');
} catch (UnexpectedValueException) { $ok(true, 'malformed type fails closed'); }
$root=dirname(__DIR__);
foreach (['Catalog/ProductCatalogService.php' => "$".'this->access->hasBaseViewPermission($this->resource)',
    'Master/ProductReadService.php' => "$".'this->access->hasBaseViewPermission(\'product\')'] as $file => $expected) {
    $source = (string)file_get_contents($root.'/app/Modules/Product/'.$file);
    $ok(str_contains($source, $expected), $file.' uses UnifiedAccess snapshot');
    $ok(! str_contains($source, '$this->actor->can('), $file.' no inconsistent Shield-only gate');
    $ok(str_contains($source, '$this->access->canView('), $file.' keeps row PBAC');
}
$service = (string)file_get_contents($root.'/app/Modules/Access/UnifiedAccessService.php');
$ok(str_contains($service, 'RuntimeActorReader'), 'UnifiedAccess uses the persisted permission snapshot');
$ok(str_contains($service, "'permissions'"), 'snapshot permissions used, not actor hints');
echo 'M6.2A.1 PBAC: '.$n.'/'.$n." PASS\n";

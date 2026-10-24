<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Http/ApiErrorMapper.php';
use App\Exceptions\DomainException;
use App\Shared\Http\ApiErrorMapper;
$root=dirname(__DIR__,2);
$n=0;
$check=static function(bool $condition,string $reason)use(&$n):void {
    if(!$condition) throw new RuntimeException('M6.3A Security: '.$reason);
    ++$n;
};
$ctrl=(string)file_get_contents($root.'/backend/app/Modules/Product/Media/ProductImageController.php');
$service=(string)file_get_contents($root.'/backend/app/Modules/Product/Media/ProductImageService.php');
$mutation=(string)file_get_contents($root.'/backend/app/Modules/Product/Master/ProductMutationService.php');
$check(str_contains($ctrl, "getFile('image')"), 'named form part only');
$check(str_contains($ctrl,"'UNAUTHENTICATED', 401"),'login gate');
$check(str_contains($ctrl,"'ACCESS_MUTATION_NOT_ENABLED', 403"), 'DEV write gate');
$check(strpos($ctrl,'$this->authenticatedWriter()')<strpos($ctrl,"getFile('image')"),'identity before body');
$check(str_contains($service,"'product', 'edit', \$row"), 'persisted Product EDIT PBAC');
$check(str_contains($service,"'product', \$row"), 'persisted Product VIEW PBAC');
$check(strpos($service,"'product', 'edit', \$row")<strpos($service,'assertUpload($file)'), 'PBAC before image validation');
$check(strpos($service,'assertUpload($file)')<strpos($service,"->store(\$file->getTempName(), 'product')"), 'validation before storage');
$check(str_contains($service,'FOR UPDATE'), 'locked persisted row');
$check(str_contains($service,'transBegin()') && str_contains($service,'transRollback()') && str_contains($service,'transCommit()'), 'SQL transaction');
$check(str_contains($service,'GET_LOCK(') && str_contains($service,'RELEASE_LOCK('), 'advisory lock release');
$check(str_contains($service,"':product-master'") && str_contains($mutation,"':product-master'"), 'same mutex as Product Master');
$check(strpos($service,'$this->db->transCommit()') < strpos($service,'$this->cleanup($oldFilename)'), 'old image removal after commit');
$check(str_contains($service,'$this->cleanup($newFilename);'), 'rollback new file');
$check(str_contains($service,"ImageUrl::for(\$oldFilename, 'product')"), 'legacy old file untouched');
$check(str_contains($service,"'image_mime' => null") && str_contains($service,"'image_size_bytes' => null"), 'baseline legacy metadata layout');
$check(!str_contains($ctrl,'function getProductImage(') && !str_contains($ctrl,'readfile(') && !str_contains($service,'readfile('), 'no image-read endpoint');
$check(str_contains((string)file_get_contents($root.'/backend/app/Shared/Http/ApiResponder.php'),"'ProductRuntime.'"), 'Product bilingual error mapping');
foreach(['PRODUCT_IMAGE_INVALID_SIZE'=>422, 'PRODUCT_IMAGE_INVALID_TYPE'=>422,
    'PRODUCT_IMAGE_INVALID_CONTENT'=>422, 'PRODUCT_IMAGE_STORAGE_FAILED'=>500,
    'PRODUCT_AUTHORIZATION_DENIED'=>403] as $code=>$status) {
    $mapped=ApiErrorMapper::map(new DomainException($code));
    $check($mapped['status']===$status && $mapped['code']===$code, 'stable HTTP status '.$code);
}
$check(str_contains($service, "['field'=>'image']"), 'multipart errors reference image field');
foreach(['en','vi'] as $locale) {
    $msgs=require $root.'/backend/app/Language/'.$locale.'/ProductRuntime.php';
    foreach(['PRODUCT_IMAGE_INVALID_SIZE','PRODUCT_IMAGE_INVALID_TYPE','PRODUCT_IMAGE_INVALID_CONTENT','PRODUCT_IMAGE_STORAGE_FAILED'] as $code) {
        $check(is_string($msgs[$code]??null) && strlen($msgs[$code])>10, 'localized '.$locale.' '.$code);
    }
}
echo 'M6.3A SECURITY: '.$n.'/'.$n." PASS\n";

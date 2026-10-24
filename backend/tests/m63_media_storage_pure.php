<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
require_once __DIR__.'/../app/Shared/Media/UploadPaths.php';
require_once __DIR__.'/../app/Shared/Media/PublicImageStorage.php';

use App\Exceptions\DomainException;
use App\Shared\Media\PublicImageStorage;
use App\Shared\Media\UploadPaths;
$n=0;
$check=static function(bool $condition,string $reason)use(&$n):void {
    if(!$condition) throw new RuntimeException('M6.3A Storage: '.$reason);
    ++$n;
};
$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'m63_media_'.bin2hex(random_bytes(5));
if(!mkdir($root,0700,true)) throw new RuntimeException('Cannot create media temp directory');
$storage=new PublicImageStorage($root);
$pngFile=$root.DIRECTORY_SEPARATOR.'valid.png';
$gifFile=$root.DIRECTORY_SEPARATOR.'unsupported.gif';
$emptyFile=$root.DIRECTORY_SEPARATOR.'empty.png';
$largeFile=$root.DIRECTORY_SEPARATOR.'large.jpg';
$badFile=$root.DIRECTORY_SEPARATOR.'fake.png';
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGMQqTjxHwAETAJUozm2nwAAAABJRU5ErkJggg==',true);
if(!is_string($png)) throw new RuntimeException('PNG test fixture missing');
file_put_contents($pngFile,$png);
file_put_contents($gifFile,base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',true));
file_put_contents($emptyFile,'');
file_put_contents($largeFile,str_repeat('a',PublicImageStorage::PRODUCT_MAX_BYTES+1));
file_put_contents($badFile,'this is not a png');
try {
    $info=$storage->inspect($pngFile,'product');
    $check($info['mime']==='image/png' && $info['extension']==='png', 'valid PNG checked by bytes, not extension');
    $check($info['width']===1 && $info['height']===1, 'dimensions checked');
    $check($info['size']===strlen($png), 'size checked');
    foreach([[$emptyFile,'PRODUCT_IMAGE_INVALID_SIZE'], [$largeFile,'PRODUCT_IMAGE_INVALID_SIZE'],
        [$badFile,'PRODUCT_IMAGE_INVALID_CONTENT'], [$gifFile,'PRODUCT_IMAGE_INVALID_TYPE']] as [$path,$expected]) {
        try {
            $storage->inspect($path,'product');
            throw new RuntimeException('Invalid image accepted: '.$path);
        } catch(DomainException $error) {
            $check($error->getErrorCode()===$expected, 'rejected '.$expected);
        }
    }
    $check(PublicImageStorage::publicUrl('20261024product1234567.png','product')==='/uploads/2026/20261024product1234567.png', 'static Product URL');
    $check(PublicImageStorage::publicUrl('20261024avatar1234567.png','product')===null, 'avatar filename cannot claim Product');
    $check(PublicImageStorage::publicUrl(str_repeat('a',40).'.jpg','product')===null, 'private historical filenames rejected');
    $check(PublicImageStorage::publicUrl('../../../../test.png','product')===null, 'path traversal denied');
    $check(UploadPaths::imagePath('20261024product1234567.png','product')===UploadPaths::root().DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'20261024product1234567.png', 'physical public/upload path');
    try {$storage->path('../image.php','product'); throw new RuntimeException('Traversal path accepted');}
    catch(DomainException $e){$check($e->getErrorCode()==='PRODUCT_IMAGE_NOT_FOUND','storage path refuses traversal');}
    // The CI image codec is optional in the offline environment. On XAMPP,
    // production multipart/GD requires independent real HTTP verification.
    if(extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagetypes')
        && (imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP)) === (IMG_JPG | IMG_PNG | IMG_WEBP)) {
        $result=$storage->store($pngFile,'product');
        $check(preg_match('/\A[0-9]{8}product[0-9]{7}\.png\z/D',$result['filename'])===1, 'baseline Product filename');
        $check(is_file($storage->path($result['filename'],'product')), 'saved physical media exists');
        $storage->delete($result['filename'],'product');
        $check(!is_file($storage->path($result['filename'],'product')), 'file cleanup');
    } else {
        $check(!is_dir($root.DIRECTORY_SEPARATOR.'2026'), 'no GD means no image is written');
    }
} finally {
    foreach([$pngFile,$gifFile,$emptyFile,$largeFile,$badFile] as $file) if(is_file($file))unlink($file);
    foreach(glob($root.DIRECTORY_SEPARATOR.'*',GLOB_ONLYDIR)?:[] as $year) {
        foreach(glob($year.DIRECTORY_SEPARATOR.'*')?:[] as $name) if(is_file($name))unlink($name);
        rmdir($year);
    }
    rmdir($root);
}
echo 'M6.3A STORAGE: '.$n.'/'.$n." PASS\n";

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
$check=static function(bool $cond,string $label)use(&$n):void {
    if (!$cond) throw new RuntimeException('M6.3B Avatar storage: '.$label);
    $n++;
};
$dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'m63b_avatar_'.bin2hex(random_bytes(6));
if(!@mkdir($dir,0700,true)) throw new RuntimeException('Cannot create Avatar test folder');
$storage=new PublicImageStorage($dir);
$pngPath=$dir.DIRECTORY_SEPARATOR.'original.png';
$invalidPath=$dir.DIRECTORY_SEPARATOR.'invalid.png';
$oversized=$dir.DIRECTORY_SEPARATOR.'over-2MiB.webp';
$gifPath=$dir.DIRECTORY_SEPARATOR.'image.gif';
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGMQqTjxHwAETAJUozm2nwAAAABJRU5ErkJggg==',true);
if(!is_string($png)) throw new RuntimeException('Missing PNG fixture');
file_put_contents($pngPath,$png);
file_put_contents($invalidPath,'fake PNG');
file_put_contents($oversized,str_repeat('A',PublicImageStorage::AVATAR_MAX_BYTES+1));
file_put_contents($gifPath,base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',true));
try {
    $info=$storage->inspect($pngPath,'avatar');
    $check($info['mime']==='image/png'&&$info['extension']==='png','true PNG inspected by bytes');
    $check($info['width']===1&&$info['height']===1,'dimension inspected');
    $check($info['size']===strlen($png),'file size computed');
    foreach ([[$invalidPath,'USER_AVATAR_INVALID_CONTENT'],[$oversized,'USER_AVATAR_INVALID_SIZE'],
        [$gifPath,'USER_AVATAR_INVALID_TYPE']] as [$path,$expected]) {
        try { $storage->inspect($path,'avatar'); throw new RuntimeException('Unsafe Avatar accepted'); }
        catch(DomainException $error) {$check($error->getErrorCode()===$expected,'refuse '.$expected);}
    }
    $filename='20261024avatar1234567.png';
    $check(PublicImageStorage::publicUrl($filename,'avatar')==='/uploads/2026/'.$filename,'baseline static avatar URL');
    $check(UploadPaths::imagePath($filename,'avatar')===UploadPaths::root().DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.$filename,'public/upload/YYYY physical layout');
    foreach (['20261024product1234567.png','20260230avatar1234567.png',str_repeat('a',40).'.png','../../private.php'] as $bad) {
        $check(PublicImageStorage::publicUrl($bad,'avatar')===null, 'invalid/private static URL denied');
        try { $storage->path($bad,'avatar'); throw new RuntimeException('Unsafe path accepted'); }
        catch (DomainException $error) {$check($error->getErrorCode()==='USER_AVATAR_NOT_FOUND','invalid path blocked');}
    }
    if(extension_loaded('gd')&&function_exists('imagewebp')&&function_exists('imagetypes')
        &&(imagetypes()&(IMG_JPG|IMG_PNG|IMG_WEBP))===(IMG_JPG|IMG_PNG|IMG_WEBP)) {
        $result=$storage->store($pngPath,'avatar');
        $check(preg_match('/\A[0-9]{8}avatar[0-9]{7}\.png\z/D',$result['filename'])===1,'baseline avatar filename');
        $check(is_file($storage->path($result['filename'],'avatar')),'stored public image exists');
        $check(PublicImageStorage::publicUrl($result['filename'],'avatar')!==null,'stored file has static URL');
        $storage->delete($result['filename'],'avatar');
        $check(!is_file($storage->path($result['filename'],'avatar')),'delete cleans correct public file');
    } else {
        $check(!is_dir($dir.DIRECTORY_SEPARATOR.gmdate('Y')),'without GD no images written');
    }
} finally {
    foreach([$pngPath,$invalidPath,$oversized,$gifPath] as $path) if(is_file($path)) unlink($path);
    foreach(glob($dir.DIRECTORY_SEPARATOR.'*',GLOB_ONLYDIR)?:[] as $folder) {
        foreach(glob($folder.DIRECTORY_SEPARATOR.'*')?:[] as $file) if(is_file($file)) unlink($file);
        rmdir($folder);
    }
    rmdir($dir);
}
echo 'M6.3B AVATAR STORAGE: '.$n.'/'.$n." PASS\n";

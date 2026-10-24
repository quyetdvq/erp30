<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
require_once __DIR__.'/../app/Shared/Media/UploadPaths.php';
require_once __DIR__.'/../app/Shared/Media/PublicImageStorage.php';
require_once __DIR__.'/../app/Modules/Identity/Media/UserAvatarRules.php';

use App\Modules\Identity\Media\UserAvatarRules as Rules;
use App\Shared\Media\PublicImageStorage;
$n = 0;
$ok = static function (bool $condition, string $label) use (&$n): void {
    if (!$condition) throw new RuntimeException('M6.3B Rules: '.$label);
    $n++;
};
$ok(Rules::isOwner('1','1'), 'actor ID equals persisted target ID');
foreach ([['1','2'],['','1'],['01','1'],['1','01'],['0','1'],['1','0']] as [$actor,$target]) {
    $ok(!Rules::isOwner($actor,$target), 'non-owner never receives self exception '.$actor.'/'.$target);
}
$max = PublicImageStorage::AVATAR_MAX_BYTES;
$ok($max === 2*1024*1024, 'baseline 2 MiB');
$ok(Rules::uploadError(true, UPLOAD_ERR_OK, 1)===null, 'minimum 1 byte accepted for byte inspection');
$ok(Rules::uploadError(true, UPLOAD_ERR_OK, $max)===null, '2 MiB boundary accepted');
foreach ([null,0,-1,$max+1] as $size) {
    $ok(Rules::uploadError(true,UPLOAD_ERR_OK,$size)==='USER_AVATAR_INVALID_SIZE', 'reject invalid size');
}
foreach ([UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE] as $error) {
    $ok(Rules::uploadError(false,$error,null)==='USER_AVATAR_INVALID_SIZE','server upload limit code');
}
foreach ([null,UPLOAD_ERR_NO_FILE,UPLOAD_ERR_PARTIAL,UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE] as $error) {
    $ok(Rules::uploadError(false,$error,null)==='USER_AVATAR_INVALID_CONTENT','failed upload content code');
}
$filename = '20261024avatar1234567.png';
$none = Rules::response('123',null);
$ok($none===['user_id'=>'123','has_avatar'=>false,'avatar_url'=>null], 'DELETE exact shape');
$has = Rules::response('123',$filename);
$ok($has===['user_id'=>'123','has_avatar'=>true,'avatar_url'=>'/uploads/2026/'.$filename], 'upload exact shape');
$private = Rules::response('123',str_repeat('a',40).'.jpg');
$ok($private['has_avatar']===true && $private['avatar_url']===null, 'legacy image remains private but present');
foreach (['../../config.php','20260230avatar1234567.jpg','20261024product1234567.png'] as $bad) {
    $dto=Rules::response('123',$bad);
    $ok($dto['has_avatar'] && $dto['avatar_url']===null,'unsafe avatar URL redacted');
}
$ok(Rules::mayDeleteOld($filename,null), 'DELETE may cleanup public avatar');
$ok(Rules::mayDeleteOld($filename,'20261024avatar1234568.png'), 'REPLACE cleans old public avatar');
$ok(!Rules::mayDeleteOld($filename,$filename), 'never cleanup currently referenced avatar');
foreach ([null,'',str_repeat('a',40).'.jpg','../../config.php','20261024product1234567.png'] as $bad) {
    $ok(!Rules::mayDeleteOld($bad,null),'never delete private/untrusted path');
}
echo 'M6.3B AVATAR RULES: '.$n.'/'.$n." PASS\n";

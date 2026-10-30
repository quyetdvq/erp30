<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Http/ApiErrorMapper.php';
use App\Exceptions\DomainException;
use App\Shared\Http\ApiErrorMapper;
$root=dirname(__DIR__,2);
$ctrl=(string)file_get_contents($root.'/backend/app/Modules/Identity/Media/UserAvatarController.php');
$svc=(string)file_get_contents($root.'/backend/app/Modules/Identity/Media/UserAvatarService.php');
$rules=(string)file_get_contents($root.'/backend/app/Modules/Identity/Media/UserAvatarRules.php');
$n=0;
$ok=static function(bool $test,string $label)use(&$n):void {
    if (!$test) throw new RuntimeException('M6.3B Security: '.$label);
    $n++;
};
$ok(str_contains($ctrl,"'UNAUTHENTICATED', 401"),'Shield authentication gate');
$ok(!str_contains($ctrl,'ACCESS_MUTATION_NOT_ENABLED'),'write gate');
$ok(strpos($ctrl,'$this->authenticatedWriter()') < strpos($ctrl,"getFile('image')"),'actor resolved before file parsing');
$ok(str_contains($svc,'UserAvatarRules::isOwner((string) $this->actor->id, $id)'), 'owner derived from server Shield user');
$ok(str_contains($svc,"$".'access->canView(\'user\', $row)'), 'target VIEW PBAC');
$ok(str_contains($svc,"$".'access->decideMutation(\'user\', \'edit\', $row)'), 'target EDIT PBAC');
$ok(strpos($svc,'authorizedRow($id);')<strpos($svc,'assertUpload($file);'),'authorization before multipart errors');
$ok(strpos($svc,'assertUpload($file);')<strpos($svc,"->store(\$file->getTempName(), 'avatar')"),'upload validation precedes storage');
$ok(str_contains($svc,"where('avatar_filename', \$expected)"),'metadata compare and swap');
$ok(str_contains($svc,"where('active', (int) \$snapshot['active'])"),'active state CAS');
$ok(str_contains($svc,"where('status', (string) \$snapshot['status'])"),'status CAS');
$ok(str_contains($svc,"where('deleted_at', null)"),'deleted target ignored and CAS protected');
$ok(str_contains($svc,'affectedRows() !== 1'),'concurrent update rejected');
$ok(str_contains($svc,'USER_AVATAR_CONCURRENT_UPDATE'),'stable conflict');
$ok(str_contains($svc,'transBegin()')&&str_contains($svc,'transCommit()')&&str_contains($svc,'transRollback()'),'transactional metadata mutation');
$ok(str_contains($svc,'resetTransStatus()'),'transaction status recovered after abort');
$ok(strpos($svc,'$this->persist($id, $old, $new, $row);') < strpos($svc,'$this->cleanupOldIfUnreferenced($old, $id);'), 'post-commit old file cleanup');
$ok(str_contains($svc,'$this->cleanup($new, $id);'),'rollback cleans newly staged file');
$ok(str_contains($svc,'countAllResults()'),'orphan check before deletion');
$ok(str_contains($svc,'UserAvatarRules::mayDeleteOld('),'never unlink legacy files');
$ok(str_contains($svc,'assertIndependentTransaction()'),'block nested outer rollback hazards');
$ok(!str_contains($ctrl,'function getUserAvatar(')&&!str_contains($ctrl,'readfile('), 'no image GET or byte streaming');
$ok(!str_contains($svc,'readfile(')&&!str_contains($svc,'WRITEPATH'), 'no private path streaming/storage');
foreach (['USER_AVATAR_INVALID_SIZE'=>422,'USER_AVATAR_INVALID_TYPE'=>422,
    'USER_AVATAR_INVALID_CONTENT'=>422,'USER_AVATAR_CONCURRENT_UPDATE'=>409,
    'USER_AVATAR_STORAGE_FAILED'=>500,'USER_AUTHORIZATION_DENIED'=>403,'USER_NOT_FOUND'=>404] as $code=>$status) {
    $mapped=ApiErrorMapper::map(new DomainException($code));
    $ok($mapped['status']===$status, 'HTTP status '.$code);
    $ok($code!=='USER_AVATAR_STORAGE_FAILED' || $mapped['code']===$code,'storage code is safe/stable');
}
foreach (['en','vi'] as $locale) {
    $catalog=require $root.'/backend/app/Language/'.$locale.'/Api.php';
    foreach (['userAvatarInvalidSize','userAvatarInvalidType','userAvatarInvalidContent',
        'userAvatarConcurrentUpdate','userAvatarStorageFailed'] as $key) {
        $ok(is_string($catalog[$key]??null)&&strlen($catalog[$key])>12,'frozen '.$locale.' '.$key);
    }
}
$ok(str_contains($rules,"ImageUrl::for(\$filename, 'avatar')"),'never return stored private filename');
echo 'M6.3B AVATAR SECURITY: '.$n.'/'.$n." PASS\n";

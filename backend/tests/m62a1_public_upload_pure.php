<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require_once __DIR__ . '/../app/Shared/Media/UploadPaths.php';
require_once __DIR__ . '/../app/Shared/Media/PublicImageStorage.php';
require_once __DIR__ . '/../app/Shared/Media/PublicImageMigrator.php';

use App\Exceptions\DomainException;
use App\Shared\Media\PublicImageStorage;
use App\Shared\Media\PublicImageMigrator;
use App\Shared\Media\UploadPaths;

$n=0;
$ok = static function (bool $passed, string $reason) use (&$n): void {
    if (! $passed) throw new RuntimeException('M6.2A.1 media: '.$reason);
    ++$n;
};
$filename='20261024product1234567.png';
$ok(PublicImageStorage::publicUrl($filename, 'product')==='/uploads/2026/'.$filename, 'new product static URL');
$ok(PublicImageStorage::publicUrl('20261024avatar7654321.webp', 'avatar')==='/uploads/2026/20261024avatar7654321.webp', 'new avatar static URL');
$ok(PublicImageStorage::publicUrl(str_repeat('f',40).'.png', 'product')===null, 'legacy private hash stays hidden');
$ok(PublicImageStorage::publicUrl('../etc/passwd', 'product')===null, 'directory traversal denied');
$ok(PublicImageStorage::publicUrl('20260231product1234567.png', 'product')===null, 'invalid date denied');
$ok(UploadPaths::imagePath($filename, 'product')===UploadPaths::root().DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.$filename, 'physical path correct');
// Build the fixture path with the host separator. On Windows,
// PublicImageStorage::path() correctly emits backslashes rather than '/'.
$fixtureRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'm62a1_path_fixture';
$storage = new PublicImageStorage($fixtureRoot);
$expectedPath = $fixtureRoot . DIRECTORY_SEPARATOR . '2026' . DIRECTORY_SEPARATOR . $filename;
$ok($storage->path($filename, 'product') === $expectedPath, 'store path follows year on this OS');
try { $storage->path(str_repeat('a',40).'.jpg', 'product'); throw new RuntimeException('accepted private old'); }
catch (DomainException) { $ok(true, 'old hash not addressable from public root'); }
$htaccess=(string)file_get_contents(__DIR__.'/../.htaccess');
$public=(string)file_get_contents(__DIR__.'/../public/.htaccess');
$ok(str_contains($htaccess,'public/upload/$1/$2'), 'root DocumentRoot uses public upload alias');
$ok(str_contains($public,'upload/$1/$2'), 'public DocumentRoot uses public upload alias');
$ok(!str_contains($htaccess,'writable/uploads') && !str_contains($public,'writable/uploads'), 'no writable media aliases');
$ok(str_contains((string)file_get_contents(__DIR__.'/../public/upload/.htaccess'),'RemoveHandler .php'), 'public folder forbids executable handlers');

$temporary = sys_get_temp_dir().'/m62a1_'.bin2hex(random_bytes(6));
$source = $temporary.'/old'; $dest=$temporary.'/new';
if (!mkdir($source.'/2026',0700,true) || !mkdir($dest,0700,true)) throw new RuntimeException('fixture folders');
// One-pixel PNG; read file bytes in a standalone PHP test without GD extension.
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==',true);
if (!is_string($bytes)) throw new RuntimeException('fixture image');
file_put_contents($source.'/2026/'.$filename,$bytes);
$runner = new PublicImageMigrator($source,$dest);
try {
    $scan=$runner->run();
    $ok($scan['eligible']===1 && $scan['copied']===0, 'dry-run does not copy');
    $ok(!file_exists($dest.'/2026/'.$filename), 'dry-run no filesystem effect');
    $run=$runner->run(true);
    $ok($run['copied']===1 && hash_file('sha256',$dest.'/2026/'.$filename)===hash_file('sha256',$source.'/2026/'.$filename), 'copy verifies equal bytes');
    $ok(file_exists($source.'/2026/'.$filename), 'source never deleted');
    $again=$runner->run(true);
    $ok($again['existing']===1 && $again['copied']===0, 'identical target not overwritten');
    file_put_contents($dest.'/2026/'.$filename,'different');
    $conflict=$runner->run(true);
    $ok($conflict['conflicts']===1 && file_get_contents($dest.'/2026/'.$filename)==='different', 'conflict never overwritten');
    file_put_contents($source.'/2026/evil.php','<?php echo 1;');
    $bad=$runner->run();
    $ok($bad['invalid']===1,'unrecognized filename rejected');
} finally {
    foreach ([$source.'/2026/'.$filename,$source.'/2026/evil.php',$dest.'/2026/'.$filename] as $name) if(is_file($name)) unlink($name);
    foreach ([$source.'/2026',$source,$dest.'/2026',$dest,$temporary] as $name) if(is_dir($name)) rmdir($name);
}
echo 'M6.2A.1 PUBLIC MEDIA: '.$n.'/'.$n." PASS\n";

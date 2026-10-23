<?php

declare(strict_types=1);

/** MEDIA-018A: vendor-free production storage parity/security tests (no SQL, no DB). */
require dirname(__DIR__) . '/app/Exceptions/DomainException.php';
require dirname(__DIR__) . '/app/Infrastructure/Media/YearlyImageStorage.php';

use App\Exceptions\DomainException;
use App\Infrastructure\Media\YearlyImageStorage;

function mediaCheck(bool $ok, string $description): void
{
    if (! $ok) {
        throw new RuntimeException('MEDIA-018A FAIL: ' . $description);
    }
    echo '  ' . $description . ': PASS' . PHP_EOL;
}

mediaCheck(
    YearlyImageStorage::publicUrl('20261023product5689522.jpg', 'product') === '/writable/uploads/2026/20261023product5689522.jpg'
        && YearlyImageStorage::publicUrl('20261023avatar5689523.webp', 'avatar') === '/writable/uploads/2026/20261023avatar5689523.webp',
    'exact year/type URL from filename',
);
foreach (['../.env', '20261023avatar5689523.jpg', '20261323product5689522.jpg',
    '20261023product5689522.php', str_repeat('a', 40) . '.jpg'] as $bad) {
    mediaCheck(YearlyImageStorage::publicUrl($bad, 'product') === null, 'reject unsafe/legacy Product static path ' . substr($bad, 0, 14));
}
$files = new YearlyImageStorage(sys_get_temp_dir() . '/dinkerp-media-contract-' . bin2hex(random_bytes(4)));
foreach (['product', 'avatar'] as $type) {
    try {
        $files->path('../../.env', $type);
        throw new RuntimeException('Path traversal accepted.');
    } catch (DomainException $exception) {
        mediaCheck($exception->getErrorCode() === ($type === 'product' ? 'PRODUCT_IMAGE_NOT_FOUND' : 'USER_AVATAR_NOT_FOUND'),
            $type . ' read/delete path traversal fails closed');
    }
}
$testFile = tempnam(sys_get_temp_dir(), 'media-source');
if (! is_string($testFile)) {
    throw new RuntimeException('Cannot create temp source.');
}
try {
    file_put_contents($testFile, '<?php echo "unsafe";');
    foreach (['product', 'avatar'] as $type) {
        try {
            $files->inspect($testFile, $type);
            throw new RuntimeException('Executable accepted.');
        } catch (DomainException $exception) {
            mediaCheck($exception->getErrorCode() === ($type === 'product' ? 'PRODUCT_IMAGE_INVALID_CONTENT' : 'USER_AVATAR_INVALID_CONTENT'),
                $type . ' rejects executable image uploads');
        }
    }
} finally {
    @unlink($testFile);
}
$root = dirname(__DIR__) . '/app';
$product = file_get_contents($root . '/Services/Api050ProductCatalogService.php');
$avatar = file_get_contents($root . '/Modules/Identity/Application/UserAvatarService.php');
$thumbnail = file_get_contents(dirname(__DIR__, 2) . '/frontend/src/components/products/ProductListThumbnail.vue');
mediaCheck(str_contains($product, "YearlyImageStorage::publicUrl(") && str_contains($avatar, "YearlyImageStorage::publicUrl("),
    'Product and Avatar responses expose static URL only for validated new names');
mediaCheck(str_contains((string) $thumbnail, ':src="imageUrl"')
        && ! str_contains((string) $thumbnail, 'fetchProductImage')
        && str_contains((string) $thumbnail, 'uploadImageUrl('),
    'Product thumbnail uses validated static URL only');
if (extension_loaded('gd') && function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
    $rootDir = sys_get_temp_dir() . '/dinkerp-media-gd-' . bin2hex(random_bytes(4));
    $shared = new YearlyImageStorage($rootDir);
    $sourcePath = tempnam(sys_get_temp_dir(), 'media-gd');
    if (! is_string($sourcePath)) throw new RuntimeException('Cannot create source image.');
    $source = imagecreatetruecolor(3000, 1500);
    imagefilledrectangle($source, 0, 0, 2999, 1499, imagecolorallocate($source, 5, 80, 145));
    imagejpeg($source, $sourcePath, 85);
    imagedestroy($source);
    $first = $shared->store($sourcePath, 'product');
    $second = $shared->store($sourcePath, 'product');
    mediaCheck($first['filename'] !== $second['filename'], 'concurrent-second collision never overwrites existing file');
    mediaCheck(array_slice(getimagesize($shared->path($first['filename'], 'product')), 0, 2) === [2048, 1024],
        'resize 3000x1500 to 2048x1024 with aspect ratio');
    $shared->delete($first['filename'], 'product');
    $shared->delete($second['filename'], 'product');
    @unlink($sourcePath);
    $yearDir = $rootDir . '/' . gmdate('Y');
    @rmdir($yearDir);
    @rmdir($rootDir);
    echo '  GD processing: PASS' . PHP_EOL;
} else {
    echo '  GD processing: NOT_RUN (PHP GD extension unavailable)' . PHP_EOL;
}
echo 'MEDIA-018A SOURCE GATE PASS (runtime upload codec separately gated).'.PHP_EOL;

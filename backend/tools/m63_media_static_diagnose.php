<?php

declare(strict_types=1);

/** Read-only static media path diagnostic. No DB, HTTP, or image mutation. */
require_once __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require_once __DIR__ . '/../app/Shared/Media/UploadPaths.php';

use App\Shared\Media\ImageUrl;
use App\Shared\Media\UploadPaths;

$input = $argv[1] ?? '';
if (! is_string($input) || preg_match('~\A/uploads/(20[0-9]{2})/([0-9]{8}(product|avatar)[0-9]{7}\.(?:jpg|png|webp))\z~D', $input, $matches) !== 1
    || ImageUrl::for($matches[2], $matches[3]) !== $input) {
    fwrite(STDERR, "USAGE: php tools/m63_media_static_diagnose.php /uploads/YYYY/YYYYMMDDproductNNNNNNN.jpg\n");
    exit(2);
}
$physical = UploadPaths::imagePath($matches[2], $matches[3]);
if ($physical === null) {
    fwrite(STDERR, "MEDIA_PATH: INVALID\n");
    exit(2);
}
$root = dirname(__DIR__);
$public = (string) @file_get_contents($root . '/public/.htaccess');
$backend = (string) @file_get_contents($root . '/.htaccess');
echo 'MEDIA_URL: ' . $input . "\n";
echo 'PUBLIC_FILE: ' . (is_file($physical) ? 'PRESENT' : 'MISSING') . "\n";
echo 'PUBLIC_READABLE: ' . (is_readable($physical) ? 'YES' : 'NO') . "\n";
echo 'APACHE_PUBLIC_REWRITE: ' . (str_contains($public, 'upload/$1/$2') ? 'CONFIGURED_IN_SOURCE' : 'NOT_FOUND') . "\n";
echo 'APACHE_BACKEND_REWRITE: ' . (str_contains($backend, 'public/upload/$1/$2') ? 'CONFIGURED_IN_SOURCE' : 'NOT_FOUND') . "\n";
echo "HTTP_STATUS: NOT_CHECKED (check deployed Apache vhost and actual URL separately)\n";
// A missing image is an asset availability issue, not a reason to serve a private file.

<?php

declare(strict_types=1);

require __DIR__ . '/../app/Shared/Http/RequestMetadata.php';
require __DIR__ . '/../app/Shared/Http/ApiEnvelope.php';
require __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require __DIR__ . '/../app/Shared/Media/UploadPaths.php';
require __DIR__ . '/../app/Modules/Identity/DisplayName.php';

use App\Shared\Http\ApiEnvelope;
use App\Shared\Http\RequestMetadata;
use App\Shared\Media\ImageUrl;
use App\Shared\Media\UploadPaths;
use App\Modules\Identity\DisplayName;

function test(string $name, bool $ok): void
{
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    echo "PASS: {$name}\n";
}

RequestMetadata::begin('request-ABCD_123:abc', 'en');
test('keeps a trusted caller request ID', RequestMetadata::id() === 'request-ABCD_123:abc');
test('sets a supported locale', RequestMetadata::locale() === 'en');
RequestMetadata::begin('not valid\nX-Header:bad', 'fr');
test('rejects a malformed request ID', RequestMetadata::id() !== 'not valid\nX-Header:bad');
test('defaults unsupported locale to vi', RequestMetadata::locale() === 'vi');
test('new ID has UUID-4 structure', preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', RequestMetadata::id()) === 1);

$response = ApiEnvelope::success([
    'user' => ['id' => 19, 'rowVersion' => 2, 'name' => 'A', 'basePermissions' => ['product.view']],
    'price' => 3.25, 'quantity' => 5, 'version' => 2,
]);
test('canonical success envelope', isset($response['data'], $response['meta']['request_id']));
test('BIGINT id encoded as string', $response['data']['user']['id'] === '19');
test('row_version encoded as string', $response['data']['user']['row_version'] === '2');
test('preserves permission tokens', $response['data']['user']['base_permissions'][0] === 'product.view');
test('decimal price is not encoded as JSON number', $response['data']['price'] === '3.25');
test('decimal quantity is encoded as string', $response['data']['quantity'] === '5');
test('non-ID numeric field stays numeric', $response['data']['version'] === 2);

$failure = ApiEnvelope::failure('INVALID_CREDENTIALS', 'Invalid username or password', []);
test('error envelope has code/message/details/request_id', array_keys($failure) === ['error', 'meta']
    && $failure['error']['code'] === 'INVALID_CREDENTIALS'
    && is_array($failure['error']['details'])
    && is_string($failure['meta']['request_id']));

$filename = '20261023avatar1234567.jpg';
test('existing avatar has URL without writable', ImageUrl::for($filename, 'avatar') === '/uploads/2026/' . $filename);

test('upload root is backend/public/upload',
    UploadPaths::root() === dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload');
test('physical upload root exists', is_dir(UploadPaths::root()));
test('image resolves into physical year directory',
    UploadPaths::imagePath($filename, 'avatar') === UploadPaths::root()
        . DIRECTORY_SEPARATOR . '2026' . DIRECTORY_SEPARATOR . $filename);
test('image resolver forbids traversal', UploadPaths::imagePath('../evil.php', 'avatar') === null);
test('image resolver forbids wrong image owner',
    UploadPaths::imagePath('20261023product1234567.jpg', 'avatar') === null);

test('invalid image filename is blocked', ImageUrl::for('../evil.php', 'avatar') === null);
test('incorrect image type is blocked', ImageUrl::for('20261023product1234567.jpg', 'avatar') === null);
test('invalid image date is blocked', ImageUrl::for('20260231avatar1234567.jpg', 'avatar') === null);
test('trims Unicode display names', DisplayName::validate(" \tNguyễn Văn A \n") === 'Nguyễn Văn A');

foreach ([null, '', str_repeat('a', 256), " \n "] as $invalid) {
    try {
        DisplayName::validate($invalid);
        test('invalid display name rejected', false);
    } catch (InvalidArgumentException) {
        // expected
    }
}
test('rejects invalid display names', true);
RequestMetadata::clear();
test('clears metadata between requests', RequestMetadata::locale() === 'vi');

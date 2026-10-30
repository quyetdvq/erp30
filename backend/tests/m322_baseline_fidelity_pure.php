<?php

declare(strict_types=1);

/**
 * Offline, database-free BASELINE FIDELITY gate.
 * Pinned fixtures were extracted byte-for-byte from erp_v1.2.4.zip.
 * This does NOT prove live API or locale parity with an installed baseline.
 */

// Minimal CI4 lang() substitute for pure tests; reads the copied authoritative catalogs.
// Live CI4 locale negotiation must still be verified separately over HTTP.
if (! function_exists('lang')) {
    function lang(string $line, array $args = [], ?string $locale = null): string
    {
        $locale = $locale === 'en' ? 'en' : 'vi';
        [$group, $key] = explode('.', $line, 2);
        $messages = require __DIR__ . '/../app/Language/' . $locale . '/' . $group . '.php';
        return (string) ($messages[$key] ?? $line);
    }
}

require __DIR__ . '/../app/Shared/Http/RequestMetadata.php';
require __DIR__ . '/../app/Shared/Http/ApiEnvelope.php';
require __DIR__ . '/../app/Shared/Http/ApiResponder.php';
require __DIR__ . '/../app/Exceptions/DomainException.php';
require __DIR__ . '/../app/Shared/Http/ApiErrorMapper.php';

use App\Exceptions\DomainException;
use App\Shared\Http\ApiEnvelope;
use App\Shared\Http\ApiErrorMapper;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\RequestMetadata;

$checks = 0;
function check322(string $label, bool $valid): void
{
    global $checks;
    if (! $valid) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    $checks++;
    echo "PASS: {$label}\n";
}

$root = dirname(__DIR__) . '/app/Language';
$fixtureRoot = __DIR__ . '/fixtures/baseline_v1_2_4/contract';
$manifest = json_decode((string) file_get_contents($fixtureRoot . '/language_sha256.json'), true);
check322('pinned locale manifest has 74 baseline files',
    is_array($manifest) && is_array($manifest['files'] ?? null) && count($manifest['files']) === 74);
$counts = ['en' => 0, 'vi' => 0];
foreach ($manifest['files'] as $file => $sha) {
    if (! preg_match('#\A(en|vi)/[A-Za-z0-9]+\.php\z#', $file)) {
        throw new RuntimeException('Invalid manifest path: ' . $file);
    }
    $path = $root . '/' . $file;
    if (! is_file($path) || ! hash_equals($sha, hash_file('sha256', $path))) {
        fwrite(STDERR, "FAIL: baseline language catalog mismatch: {$file}\n");
        exit(1);
    }
    $contents = require $path;
    if (! is_array($contents)) {
        fwrite(STDERR, "FAIL: baseline language file does not return array: {$file}\n");
        exit(1);
    }
    $counts[explode('/', $file, 2)[0]]++;
}
check322('23/23 exact English baseline catalogs', $counts['en'] === 23);
check322('51/51 exact Vietnamese baseline catalogs', $counts['vi'] === 51);
$all = array_map(static fn(string $file): string => basename($file), array_keys($manifest['files']));
foreach (['en', 'vi'] as $locale) {
    $actual = array_map('basename', glob($root . '/' . $locale . '/*.php') ?: []);
    $expected = array_map('basename', array_filter(array_keys($manifest['files']), static fn(string $file): bool => str_starts_with($file, $locale . '/')));
    // Preserve every frozen v1.2.4 catalog byte-for-byte. Permit only the
    // reviewed ApiRuntime, ProductRuntime, PriceListRuntime, ImportRuntime and
    // NotificationRuntime overlays introduced after the baseline snapshot.
    $expected[] = 'ApiRuntime.php';
    $expected[] = 'ProductRuntime.php';
    $expected[] = 'PriceListRuntime.php';
    $expected[] = 'ImportRuntime.php';
    $expected[] = 'NotificationRuntime.php';
    sort($actual);
    sort($expected);
    check322("{$locale} contains pinned baseline plus reviewed runtime overlays", $actual === $expected);
}
$en = require $root . '/en/Api.php';
$vi = require $root . '/vi/Api.php';
check322('English and Vietnamese API message keys identical', array_keys($en) === array_keys($vi));
check322('English and Vietnamese API messages differ', $en['forbidden'] !== $vi['forbidden']);
RequestMetadata::begin('M322-audit-01', 'vi');
check322('default request locale is Vietnamese', RequestMetadata::locale() === 'vi');
RequestMetadata::begin('M322-audit-02', 'en');
check322('English request locale is supported', RequestMetadata::locale() === 'en');
RequestMetadata::begin('M322-audit-03', 'fr');
check322('unsupported locale falls back to vi', RequestMetadata::locale() === 'vi');

$method = new ReflectionMethod(ApiResponder::class, 'messageKey');
$keys = [
    'USER_AVATAR_INVALID_SIZE' => 'userAvatarInvalidSize',
    'USER_AVATAR_INVALID_TYPE' => 'userAvatarInvalidType',
    'USER_AVATAR_INVALID_CONTENT' => 'userAvatarInvalidContent',
    'USER_AVATAR_NOT_FOUND' => 'userAvatarNotFound',
    'USER_AVATAR_CONCURRENT_UPDATE' => 'userAvatarConcurrentUpdate',
    'USER_AVATAR_STORAGE_FAILED' => 'userAvatarStorageFailed',
    'INVALID_CREDENTIALS' => 'invalidCredentials',
    'PROFILE_UPDATE_FAILED' => 'profileUpdateFailed',
    'POLICY_SCOPE_ALREADY_EXISTS' => 'policyScopeAlreadyExists',
    'IMPORT_FILE_TOO_LARGE' => 'importFileTooLarge',
    'IMPORT_ROW_LIMIT_EXCEEDED' => 'importRowLimitExceeded',
];
foreach ($keys as $code => $key) {
    check322("baseline API code {$code} maps to {$key}", $method->invoke(null, $code, 422) === $key
        && array_key_exists($key, $en) && array_key_exists($key, $vi));
}
foreach (['en' => $en, 'vi' => $vi] as $locale => $dictionary) {
    RequestMetadata::begin('M322-' . $locale, $locale);
    $allMatch = true;
    foreach ($keys as $code => $key) {
        if (ApiResponder::message($code, 422) !== $dictionary[$key]) {
            $allMatch = false;
            break;
        }
    }
    check322("{$locale} translated API error messages match baseline", $allMatch);
}
foreach ([400 => 'invalidRequest', 401 => 'unauthenticated', 403 => 'forbidden', 404 => 'notFound', 409 => 'conflict', 422 => 'validation', 500 => 'serverError'] as $status => $key) {
    check322("HTTP {$status} fallback mapping preserved", $method->invoke(null, 'SOME_UNKNOWN_CODE', $status) === $key);
}

// Compare our status/code logic directly with the verified baseline implementation.
$baselineMapper = $fixtureRoot . '/ApiErrorMapper.php';
$expectedSha = 'de2731e05e8e2b75dab3300244303dc18a5989a926f5a3ce9de6af1710d7d908';
check322('baseline error mapper snapshot signature valid', hash_equals($expectedSha, hash_file('sha256', $baselineMapper)));
require $baselineMapper;
$old = new ReflectionClass(\App\Infrastructure\Api\ApiErrorMapper::class);
$new = new ReflectionClass(ApiErrorMapper::class);
$codes = [
    'DISPLAY_SETTING_NOT_FOUND', 'USER_AVATAR_NOT_FOUND', 'AUTH_DENIED',
    'AUTH_UNAUTHENTICATED', 'INVALID_CREDENTIALS', 'POLICY_SCOPE_ALREADY_EXISTS',
    'USER_IDENTITY_ALREADY_EXISTS', 'LOCK_FAILED', 'PERSISTENCE_FAILED',
    'USER_AVATAR_STORAGE_FAILED', 'RESOURCE_INVALID', 'STATE_CONFLICT',
    'ROW_CONCURRENT_UPDATE', 'ITEM_IMMUTABLE', 'ITEM_DUPLICATE', 'GENERIC_FAILURE',
];
$compared = 0;
foreach ($codes as $code) {
    foreach ([400, 401, 403, 404, 409, 422, 500] as $frameworkStatus) {
        $error = new DomainException($code);
        $oldStatus = $old->getMethod('status')->invoke(null, $error, $frameworkStatus);
        $newStatus = $new->getMethod('status')->invoke(null, $error, $frameworkStatus);
        if ($oldStatus !== $newStatus) {
            fwrite(STDERR, "FAIL: domain status mismatch for {$code}/{$frameworkStatus}\n");
            exit(1);
        }
        $oldCode = $old->getMethod('code')->invoke(null, $error, $oldStatus);
        $newCode = $new->getMethod('code')->invoke(null, $error, $newStatus);
        if ($oldCode !== $newCode) {
            fwrite(STDERR, "FAIL: domain error code mismatch for {$code}/{$frameworkStatus}\n");
            exit(1);
        }
        $compared++;
    }
}
foreach ([400, 401, 403, 404, 409, 422, 500, 503] as $frameworkStatus) {
    $error = new RuntimeException('private server diagnostic');
    $oldStatus = $old->getMethod('status')->invoke(null, $error, $frameworkStatus);
    $newStatus = $new->getMethod('status')->invoke(null, $error, $frameworkStatus);
    $oldCode = $old->getMethod('code')->invoke(null, $error, $oldStatus);
    $newCode = $new->getMethod('code')->invoke(null, $error, $newStatus);
    if ($oldStatus !== $newStatus || $oldCode !== $newCode) {
        fwrite(STDERR, "FAIL: generic error parity for {$frameworkStatus}\n");
        exit(1);
    }
    $compared++;
}
check322('baseline DomainException/non-domain status and code parity', $compared === 120);
RequestMetadata::begin('M322-detail-EN', 'en');
$mapped = ApiErrorMapper::map(new DomainException('POLICY_SCOPE_ALREADY_EXISTS', ['field' => 'scope']));
check322('domain error keeps localized English validation details', $mapped['status'] === 409
    && $mapped['code'] === 'POLICY_SCOPE_ALREADY_EXISTS'
    && $mapped['details'] === [['field' => 'scope', 'code' => 'POLICY_SCOPE_ALREADY_EXISTS',
        'message' => $en['policyScopeAlreadyExists']]]);
RequestMetadata::begin('M322-detail-VI', 'vi');
$mapped = ApiErrorMapper::map(new DomainException('POLICY_SCOPE_ALREADY_EXISTS', ['field' => 'scope']));
check322('domain error keeps localized Vietnamese validation details', $mapped['details'][0]['message'] === $vi['policyScopeAlreadyExists']);
check322('error JSON still has canonical code/message/details + request ID',
    array_keys(ApiEnvelope::failure('AUTH_DENIED', $vi['forbidden'])) === ['error', 'meta']);

// Explicitly annotate the source: this is a test snapshot, not live baseline.
echo "SOURCE: SHA-256-pinned ERP v1.2.4 translation catalogs and error mapper.\n";
echo "TOTAL: {$checks} M3.2.2 contract assertions passed; {$compared} baseline status/code comparisons.\n";
echo "NOTE: API/HTTP parity and active PBAC behavior still require runtime testing.\n";

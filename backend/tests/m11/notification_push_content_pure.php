<?php

declare(strict_types=1);

// Vendor-free RFC 8291 smoke. A fake browser key pair independently decrypts
// the single-record envelope produced by our server encryption implementation.
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPushRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPushCrypto.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPushEcKeyGenerator.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPushContentEncryption.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPushPresentation.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationCatalog.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPolicyResolver.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRuntimeConfigRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationFlags.php';

use App\Modules\Notifications\NotificationPushContentEncryption;
use App\Modules\Notifications\NotificationPushEcKeyGenerator;
use App\Modules\Notifications\NotificationPushCrypto;
use App\Modules\Notifications\NotificationPushPresentation;
use App\Modules\Notifications\NotificationPushRules;
use App\Modules\Notifications\NotificationCatalog;

$asserts = 0;
$assert = static function (bool $condition, string $name) use (&$asserts): void {
    if (! $condition) throw new RuntimeException('M11 PUSH CONTENT FAIL: ' . $name);
    ++$asserts;
};
$browser = NotificationPushEcKeyGenerator::generate();
$details = openssl_pkey_get_details($browser);
$assert(is_array($details) && ($details['ec']['curve_name'] ?? '') === 'prime256v1'
    && ($details['bits'] ?? null) === 256, 'browser test key is a real 256-bit P-256 key');
$raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];
$other = NotificationPushEcKeyGenerator::generate();
$otherDetails = openssl_pkey_get_details($other);
$assert(is_array($otherDetails) && $otherDetails['ec']['x'] !== $details['ec']['x'],
    'P-256 keys are freshly generated, not reused');
$config = __DIR__ . '/../../app/Modules/Notifications/openssl-p256.cnf';
$assert(is_file($config) && str_contains((string) file_get_contents($config), 'default_bits = 384'),
    'bundled OpenSSL config declares PHP compatibility default_bits');
$fallback = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1',
    'private_key_bits' => 384, 'config' => $config,
]);
$assert($fallback instanceof OpenSSLAsymmetricKey, 'bundled OpenSSL config can generate P-256');
$fallbackDetails = $fallback instanceof OpenSSLAsymmetricKey ? openssl_pkey_get_details($fallback) : false;
$assert(is_array($fallbackDetails) && ($fallbackDetails['bits'] ?? null) === 256
    && ($fallbackDetails['ec']['curve_name'] ?? null) === 'prime256v1',
    'PHP compatibility bit hint never changes the EC curve to P-384');
$auth = random_bytes(16);
$encoded = NotificationPushRules::encode($raw);
$secret = NotificationPushRules::encode($auth);
$storeKey = random_bytes(32);
$atRest = NotificationPushCrypto::encryptKeys($encoded, $secret, $storeKey);
$keys = NotificationPushCrypto::decryptKeys($atRest, $storeKey);
$assert($keys === ['p256dh' => $encoded, 'auth' => $secret], 'encrypt/decrypt subscription key materials');
$assert(! str_contains($atRest, $encoded), 'keys never at rest as plaintext');
try {
    NotificationPushCrypto::decryptKeys($atRest, random_bytes(32));
    throw new RuntimeException('tampered keys accepted');
} catch (RuntimeException $e) {
    $assert($e->getMessage() === 'M11_PUSH_KEYS_DECRYPT_FAILED', 'wrong encryption key rejected');
}
$testEvents = [
    [['event_type' => 'import.completed', 'source_type' => 'import_job', 'source_id' => '7'],
        ['success_rows' => 28, 'error_rows' => 0]],
    [['event_type' => 'import.failed', 'source_type' => 'import_job', 'source_id' => '8'],
        ['success_rows' => 0, 'error_rows' => 3]],
    [['event_type' => 'workflow.action_required', 'source_type' => 'workflow_transition', 'source_id' => '11',
        'payload_json' => '{"schema":2,"resource_type":"product","to_state":"ACTIVE"}'], null],
];
foreach ($testEvents as [$event, $job]) {
    $message = NotificationPushPresentation::payload($event, $job);
    $envelope = NotificationPushContentEncryption::encrypt($message, $encoded, $secret);
    $assert(substr($envelope, 16, 4) === pack('N', 4096), '4096 aes128gcm record size');
    $assert(ord($envelope[20]) === 65 && $envelope[21] === "\x04", 'sender keyid is raw P-256 point');
    $senderPoint = substr($envelope, 21, 65);
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $senderPoint;
    $senderPublic = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
    $shared = openssl_pkey_derive($senderPublic, $browser, 32);
    $firstPrk = hash_hmac('sha256', $shared, $auth, true);
    $ikm = hash_hmac('sha256', "WebPush: info\x00" . $raw . $senderPoint . "\x01", $firstPrk, true);
    $secondPrk = hash_hmac('sha256', $ikm, substr($envelope, 0, 16), true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $secondPrk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $secondPrk, true), 0, 12);
    $record = substr($envelope, 86);
    $plain = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek,
        OPENSSL_RAW_DATA, $nonce, substr($record, -16), '');
    $assert($plain === $message . "\x02", 'independent RFC 8291 roundtrip ' . $event['event_type']);
    $assert(strlen($envelope) < 4096, 'envelope bounded');
}
$catalog = NotificationCatalog::definitions();
foreach (['import.completed.push', 'import.completed_with_errors.push', 'import.failed.push',
    'workflow.action_required.push'] as $key) {
    $assert(isset($catalog[$key]) && ! $catalog[$key]['default'] && $catalog[$key]['requires_explicit_consent'],
        'opt-in ' . $key);
}
echo "M11 PUSH CONTENT PURE: {$asserts}/{$asserts} PASS\n";

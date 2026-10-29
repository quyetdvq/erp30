<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/** Encrypt bearer push endpoints at rest; key is server-side only, never in database/API. */
final class NotificationPushCrypto
{
    public static function key(): string
    {
        $encoded = (string) (function_exists('env') ? env('DINKERP_M11_PUSH_ENDPOINT_KEY_B64', '') : getenv('DINKERP_M11_PUSH_ENDPOINT_KEY_B64'));
        $key = base64_decode($encoded, true);
        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('M11_PUSH_STORAGE_KEY_MISSING');
        }
        return $key;
    }

    public static function fingerprint(string $endpoint, string $key): string
    {
        return hash_hmac('sha256', $endpoint, $key);
    }

    public static function encrypt(string $endpoint, string $key): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($endpoint, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'M11-PUSH-ENDPOINT-v1');
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('M11_PUSH_ENCRYPT_FAILED');
        }
        return base64_encode($nonce . $tag . $cipher);
    }

    /** Encrypt P-256 browser key and auth secret with a distinct AAD from endpoint storage. */
    public static function encryptKeys(string $p256dh, string $auth, string $key): string
    {
        $public = NotificationPushRules::decode($p256dh);
        $secret = NotificationPushRules::decode($auth);
        if ($public === null || strlen($public) !== 65 || $public[0] !== "\x04"
            || $secret === null || strlen($secret) !== 16) {
            throw new RuntimeException('M11_PUSH_KEYS_INVALID');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $encoded = json_encode(['p256dh' => $p256dh, 'auth' => $auth], JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($encoded, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'M11-PUSH-KEYS-v1');
        if (! is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('M11_PUSH_KEYS_ENCRYPT_FAILED');
        }
        return base64_encode($nonce . $tag . $cipher);
    }

    /** @return array{p256dh:string,auth:string} */
    public static function decryptKeys(string $encoded, string $key): array
    {
        $raw = base64_decode($encoded, true);
        if (! is_string($raw) || strlen($raw) < 29) {
            throw new RuntimeException('M11_PUSH_KEYS_DECRYPT_FAILED');
        }
        $json = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), 'M11-PUSH-KEYS-v1');
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ! isset($data['p256dh'], $data['auth']) || ! is_string($data['p256dh'])
            || ! is_string($data['auth']) || strlen((string) NotificationPushRules::decode($data['p256dh'])) !== 65
            || strlen((string) NotificationPushRules::decode($data['auth'])) !== 16) {
            throw new RuntimeException('M11_PUSH_KEYS_DECRYPT_FAILED');
        }
        return ['p256dh' => $data['p256dh'], 'auth' => $data['auth']];
    }

    public static function decrypt(string $encoded, string $key): string
    {
        $raw = base64_decode($encoded, true);
        if (! is_string($raw) || strlen($raw) < 29) {
            throw new RuntimeException('M11_PUSH_DECRYPT_FAILED');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), 'M11-PUSH-ENDPOINT-v1');
        if (! is_string($plain) || ! NotificationPushRules::endpoint($plain)) {
            throw new RuntimeException('M11_PUSH_DECRYPT_FAILED');
        }
        return $plain;
    }
}

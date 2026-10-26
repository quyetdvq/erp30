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

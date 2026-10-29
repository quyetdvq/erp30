<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Generate a fresh ephemeral P-256 ECDH key per Web Push message.
 *
 * PHP CLI/OpenSSL on Windows may not locate the system openssl.cnf even when
 * the extension supports prime256v1. Retry with a minimal, bundled config;
 * never use a static private key or silently downgrade to generic content.
 */
final class NotificationPushEcKeyGenerator
{
    public static function generate(): OpenSSLAsymmetricKey
    {
        if (! function_exists('openssl_pkey_new') || ! defined('OPENSSL_KEYTYPE_EC')) {
            throw new RuntimeException('M11_PUSH_ECDH_KEY_FAILED');
        }

        // PHP/OpenSSL builds can reject EC key generation when private_key_bits
        // defaults to zero (minimum 384 in PHP's generic key validator).
        // This is a compatibility hint ONLY: curve_name remains prime256v1,
        // and validP256() rejects anything other than a real 256-bit EC key.
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'private_key_bits' => 384,
        ];
        $key = @openssl_pkey_new($options);
        if (self::validP256($key)) {
            return $key;
        }

        // Explicit config avoids an absent/broken OPENSSL_CONF on PHP CLI.
        // The file contains no secrets and does not disable crypto checks.
        $bundled = __DIR__ . '/openssl-p256.cnf';
        if (is_file($bundled) && is_readable($bundled)) {
            $key = @openssl_pkey_new($options + ['config' => $bundled]);
            if (self::validP256($key)) {
                return $key;
            }
        }

        // Fail closed: the worker handles a failed delivery via normal retry.
        throw new RuntimeException('M11_PUSH_ECDH_KEY_FAILED');
    }

    private static function validP256(OpenSSLAsymmetricKey|false $key): bool
    {
        if (! $key instanceof OpenSSLAsymmetricKey) {
            return false;
        }
        $details = openssl_pkey_get_details($key);
        return is_array($details) && ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC
            && ($details['bits'] ?? null) === 256
            && ($details['ec']['curve_name'] ?? null) === 'prime256v1'
            && strlen((string) ($details['ec']['x'] ?? '')) === 32
            && strlen((string) ($details['ec']['y'] ?? '')) === 32;
    }
}

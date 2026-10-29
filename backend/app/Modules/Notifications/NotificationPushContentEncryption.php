<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use OpenSSLAsymmetricKey;
use RuntimeException;

/** RFC 8291 aes128gcm single-record Web Push content encryption (never log secrets). */
final class NotificationPushContentEncryption
{
    public static function encrypt(string $plaintext, string $p256dh, string $auth): string
    {
        if ($plaintext === '' || strlen($plaintext) > 2500) {
            throw new RuntimeException('M11_PUSH_PAYLOAD_SIZE_INVALID');
        }
        $receiver = NotificationPushRules::decode($p256dh);
        $authSecret = NotificationPushRules::decode($auth);
        if ($receiver === null || strlen($receiver) !== 65 || $receiver[0] !== "\x04"
            || $authSecret === null || strlen($authSecret) !== 16) {
            throw new RuntimeException('M11_PUSH_RECEIVER_KEYS_INVALID');
        }
        // DER SubjectPublicKeyInfo for a secp256r1 uncompressed point.
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $receiver;
        $receiverKey = openssl_pkey_get_public('-----BEGIN PUBLIC KEY-----' . "\n"
            . chunk_split(base64_encode($der), 64, "\n") . '-----END PUBLIC KEY-----' . "\n");
        $senderKey = NotificationPushEcKeyGenerator::generate();
        if (! $receiverKey instanceof OpenSSLAsymmetricKey || ! $senderKey instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('M11_PUSH_ECDH_KEY_FAILED');
        }
        $details = openssl_pkey_get_details($senderKey);
        if (! is_array($details) || strlen((string) ($details['ec']['x'] ?? '')) !== 32
            || strlen((string) ($details['ec']['y'] ?? '')) !== 32) {
            throw new RuntimeException('M11_PUSH_ECDH_KEY_FAILED');
        }
        $senderPublic = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $secret = openssl_pkey_derive($receiverKey, $senderKey, 32);
        if (! is_string($secret) || strlen($secret) !== 32) {
            throw new RuntimeException('M11_PUSH_ECDH_DERIVE_FAILED');
        }
        $keyInfo = 'WebPush: info' . "\x00" . $receiver . $senderPublic;
        $ikm = self::expand(hash_hmac('sha256', $secret, $authSecret, true), $keyInfo, 32);
        $salt = random_bytes(16);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = self::expand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::expand($prk, "Content-Encoding: nonce\x00", 12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek,
            OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if (! is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('M11_PUSH_AEAD_ENCRYPT_FAILED');
        }
        // 16 salt + 4 record size + 1 key-id length + 65 ephemeral public + ciphertext + tag.
        return $salt . pack('N', 4096) . chr(65) . $senderPublic . $cipher . $tag;
    }

    private static function expand(string $prk, string $info, int $length): string
    {
        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $length);
    }
}

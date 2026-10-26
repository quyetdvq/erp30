<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use OpenSSLAsymmetricKey;
use RuntimeException;

/** Payload-free RFC 8030 / RFC 8292 Web Push, signed using an ES256 VAPID key. */
final class NotificationVapidSender implements NotificationPushSender
{
    private readonly OpenSSLAsymmetricKey $private;
    private readonly string $public;
    private readonly string $subject;

    public function __construct()
    {
        [$this->private, $this->public] = self::credentials();
        $subject = (string) (function_exists('env')
            ? env('DINKERP_M11_PUSH_VAPID_SUBJECT', '') : getenv('DINKERP_M11_PUSH_VAPID_SUBJECT'));
        if (strlen($subject) > 254 || ! str_starts_with($subject, 'mailto:')
            || NotificationEmailRules::address(substr($subject, 7)) === null) {
            throw new RuntimeException('M11_PUSH_VAPID_SUBJECT_MISSING');
        }
        $this->subject = $subject;
    }

    public static function publicKey(): string
    {
        [, $public] = self::credentials();
        return $public;
    }

    /** Public-key fingerprint of the repository's publicly known PHPUnit fixture. */
    public static function isPublicTestFixture(string $public): bool
    {
        return hash_equals(
            '72cfff1063c7795d784742c8c8015850eab8369168734a1fe2ade418f3254c09',
            hash('sha256', $public),
        );
    }

    /** @return array{0:OpenSSLAsymmetricKey,1:string} */
    private static function credentials(): array
    {
        $encoded = (string) (function_exists('env')
            ? env('DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64', '') : getenv('DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64'));
        $pem = base64_decode($encoded, true);
        $key = is_string($pem) ? openssl_pkey_get_private($pem) : false;
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('M11_PUSH_VAPID_PRIVATE_KEY_MISSING');
        }
        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ($details['type'] ?? -1) !== OPENSSL_KEYTYPE_EC
            || ($details['ec']['curve_name'] ?? '') !== 'prime256v1'
            || strlen((string) ($details['ec']['x'] ?? '')) !== 32
            || strlen((string) ($details['ec']['y'] ?? '')) !== 32) {
            throw new RuntimeException('M11_PUSH_VAPID_INVALID_CURVE');
        }
        $raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $public = NotificationPushRules::encode($raw);
        // The fixture private key ships in source control for PHPUnit. Never
        // permit its known public counterpart as a live DEV/production identity.
        if (self::isPublicTestFixture($public)
            && (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing')) {
            throw new RuntimeException('M11_PUSH_TEST_VAPID_KEY_FORBIDDEN');
        }
        return [$key, $public];
    }

    public function send(string $endpoint): string
    {
        if (! NotificationPushRules::endpoint($endpoint)) {
            throw new RuntimeException('M11_PUSH_ENDPOINT_REJECTED');
        }
        if (! function_exists('curl_init')) {
            throw new RuntimeException('M11_PUSH_CURL_EXTENSION_REQUIRED');
        }
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ! isset($parts['host'])) {
            throw new RuntimeException('M11_PUSH_ENDPOINT_REJECTED');
        }
        $audience = 'https://' . strtolower($parts['host']);
        $jwt = $this->jwt($audience);
        $curl = curl_init($endpoint);
        if ($curl === false) {
            throw new RuntimeException('M11_PUSH_CURL_FAILED');
        }
        try {
            $options = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '',
                CURLOPT_HTTPHEADER => [
                    'Authorization: vapid t=' . $jwt . ', k=' . $this->public,
                    'TTL: 60',
                    'Content-Length: 0',
                    'Urgency: normal',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_MAXREDIRS => 0,
            ];
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $options[CURLOPT_PROTOCOLS_STR] = 'https';
            } else {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            if (! curl_setopt_array($curl, $options)) {
                throw new RuntimeException('M11_PUSH_CURL_OPTIONS_FAILED');
            }
            $result = curl_exec($curl);
            if ($result === false) {
                // Do not expose token, endpoint, response body or network details.
                throw new RuntimeException('M11_PUSH_NETWORK_FAILED');
            }
            $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            return match (true) {
                $code === 201 || $code === 202 || $code === 200 => 'sent',
                $code === 404 || $code === 410 => 'gone',
                $code === 408 || $code === 425 || $code === 429 || $code >= 500 => 'retry',
                default => 'dead',
            };
        } finally {
            curl_close($curl);
        }
    }

    private function jwt(string $audience): string
    {
        $header = NotificationPushRules::encode('{"typ":"JWT","alg":"ES256"}');
        $claims = NotificationPushRules::encode(json_encode([
            'aud' => $audience, 'exp' => time() + 3600, 'sub' => $this->subject,
        ], JSON_THROW_ON_ERROR));
        $input = $header . '.' . $claims;
        $signature = '';
        if (! openssl_sign($input, $signature, $this->private, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('M11_PUSH_VAPID_SIGN_FAILED');
        }
        return $input . '.' . NotificationPushRules::encode(self::derToJose($signature));
    }

    /** Convert OpenSSL DER ECDSA signature into JWT's fixed-width 64-byte r||s form. */
    public static function derToJose(string $der): string
    {
        $offset = 0;
        if (strlen($der) < 8 || ord($der[$offset++]) !== 0x30) {
            throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
        }
        $sequenceLength = self::length($der, $offset);
        if ($sequenceLength !== strlen($der) - $offset) {
            throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
        }
        $values = [];
        for ($i = 0; $i < 2; ++$i) {
            if (! isset($der[$offset]) || ord($der[$offset++]) !== 0x02) {
                throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
            }
            $length = self::length($der, $offset);
            $part = substr($der, $offset, $length);
            if ($length === 0 || strlen($part) !== $length) {
                throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
            }
            $offset += $length;
            $part = ltrim($part, "\x00");
            if (strlen($part) > 32) {
                throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
            }
            $values[] = str_pad($part, 32, "\x00", STR_PAD_LEFT);
        }
        if ($offset !== strlen($der)) {
            throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
        }
        return $values[0] . $values[1];
    }

    private static function length(string $der, int &$offset): int
    {
        if (! isset($der[$offset])) {
            throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
        }
        $first = ord($der[$offset++]);
        if ($first < 128) {
            return $first;
        }
        if ($first !== 0x81 || ! isset($der[$offset])) {
            throw new RuntimeException('M11_PUSH_INVALID_SIGNATURE');
        }
        return ord($der[$offset++]);
    }
}

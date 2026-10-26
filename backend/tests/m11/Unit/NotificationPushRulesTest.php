<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationPushCrypto;
use App\Modules\Notifications\NotificationPushRules;
use App\Modules\Notifications\NotificationVapidSender;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class NotificationPushRulesTest extends TestCase
{
    private function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return ['endpoint' => $endpoint, 'keys' => [
            'p256dh' => NotificationPushRules::encode("\x04" . random_bytes(64)),
            'auth' => NotificationPushRules::encode(random_bytes(16)),
        ]];
    }

    public function testAllowlistedHttpsEndpointAndOpaqueKeysAreRequired(): void
    {
        self::assertSame('https://fcm.googleapis.com/fcm/send/abc123',
            NotificationPushRules::subscription($this->subscription())['endpoint']);
        self::assertTrue(NotificationPushRules::endpoint('https://updates.push.services.mozilla.com/wpush/v2/key'));
        self::assertTrue(NotificationPushRules::endpoint('https://web.push.apple.com/QABCB123'));
    }

    /** @dataProvider unsafeEndpoints */
    public function testSsrfAndMalformedEndpointAreRejected(string $endpoint): void
    {
        self::assertFalse(NotificationPushRules::endpoint($endpoint));
        $this->expectException(DomainException::class);
        NotificationPushRules::subscription($this->subscription($endpoint));
    }

    /** @return iterable<string,array{string}> */
    public static function unsafeEndpoints(): iterable
    {
        foreach ([
            'http://fcm.googleapis.com/fcm/send/abc',
            'https://localhost/internal',
            'https://127.0.0.1/private',
            'https://fcm.googleapis.com.evil.test/fcm/send/abc',
            'https://fcm.googleapis.com:8080/fcm/send/abc',
            'https://user:pass@fcm.googleapis.com/fcm/send/abc',
            "https://fcm.googleapis.com/fcm/send/abc\r\nHeader: evil",
            'https://fcm.googleapis.com/fcm/send/a#fragment',
            'file:///etc/passwd',
            'https://fcm.googleapis.com/',
        ] as $name => $value) {
            yield 'blocked-' . $name => [$value];
        }
    }

    public function testMalformedKeyAndExtraFieldsRejected(): void
    {
        $payload = $this->subscription();
        $payload['keys']['auth'] = 'short';
        $this->expectException(DomainException::class);
        NotificationPushRules::subscription($payload);
    }

    public function testCurrentEpochExplicitConsentOnly(): void
    {
        $policy = ['setting_key' => 'import.completed.push', 'default_value' => 0,
            'allow_user_override' => 1, 'revision' => '1', 'override_epoch' => '4'];
        $pref = ['override_value' => 1, 'override_epoch' => '4', 'user_revision' => '2'];
        self::assertTrue(NotificationPushRules::consented($policy, $pref));
        self::assertFalse(NotificationPushRules::consented($policy, null));
        self::assertFalse(NotificationPushRules::consented($policy, array_replace($pref, ['override_epoch' => '3'])));
        self::assertFalse(NotificationPushRules::consented($policy, array_replace($pref, ['override_value' => 0])));
        self::assertFalse(NotificationPushRules::consented(array_replace($policy, ['allow_user_override' => 0]), $pref));
    }

    public function testEndpointEncryptedAndTamperingFailsClosed(): void
    {
        $key = random_bytes(32);
        $url = 'https://fcm.googleapis.com/fcm/send/topsecret';
        $cipher = NotificationPushCrypto::encrypt($url, $key);
        self::assertNotSame($url, $cipher);
        self::assertStringNotContainsString('topsecret', $cipher);
        self::assertSame($url, NotificationPushCrypto::decrypt($cipher, $key));
        self::assertSame(64, strlen(NotificationPushCrypto::fingerprint($url, $key)));
        $this->expectException(RuntimeException::class);
        NotificationPushCrypto::decrypt($cipher, random_bytes(32));
    }

    public function testVapidJwtIsEs256AndOnlyPublicKeyIsExposed(): void
    {
        // Fixed TEST-ONLY P-256 fixture: do not require OpenSSL key generation
        // or a platform-specific openssl.cnf (often absent on Windows XAMPP).
        // Never use this fixture as a real VAPID private key.
        $privatePem = file_get_contents(__DIR__ . '/../Fixtures/vapid-p256-test-only.pem');
        self::assertIsString($privatePem);
        $key = openssl_pkey_get_private($privatePem);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key,
            'PHP/OpenSSL must be able to load the test-only P-256 PEM to sign VAPID JWTs.');

        $values = [
            'DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64' => base64_encode($privatePem),
            'DINKERP_M11_PUSH_VAPID_SUBJECT' => 'mailto:push@example.test',
        ];
        $before = [];
        foreach ($values as $name => $value) {
            $before[$name] = [
                'getenv' => getenv($name),
                'env_exists' => array_key_exists($name, $_ENV),
                'env' => $_ENV[$name] ?? null,
                'server_exists' => array_key_exists($name, $_SERVER),
                'server' => $_SERVER[$name] ?? null,
            ];
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        try {
            $sender = new NotificationVapidSender();
            self::assertSame(65, strlen(NotificationPushRules::decode(NotificationVapidSender::publicKey())));
            $jwt = (new ReflectionMethod($sender, 'jwt'))->invoke($sender, 'https://fcm.googleapis.com');
            self::assertCount(3, explode('.', $jwt));
            [$h, $p, $signature] = explode('.', $jwt);
            $claims = json_decode(NotificationPushRules::decode($p), true, 8, JSON_THROW_ON_ERROR);
            self::assertSame('https://fcm.googleapis.com', $claims['aud']);
            self::assertLessThanOrEqual(time() + 3600, $claims['exp']);
            self::assertSame(64, strlen(NotificationPushRules::decode($signature)));
            self::assertSame('ES256', json_decode(NotificationPushRules::decode($h), true)['alg']);
            // Verify the ES256 signature against the public key, not just its shape.
            $raw = NotificationPushRules::decode($signature);
            self::assertNotNull($raw);
            $parts = [substr($raw, 0, 32), substr($raw, 32, 32)];
            $integers = [];
            foreach ($parts as $part) {
                $part = ltrim($part, "\x00");
                $part = $part === '' ? "\x00" : $part;
                if ((ord($part[0]) & 0x80) !== 0) {
                    $part = "\x00" . $part;
                }
                $integers[] = "\x02" . chr(strlen($part)) . $part;
            }
            $sequence = implode('', $integers);
            $derSignature = "\x30" . chr(strlen($sequence)) . $sequence;
            $details = openssl_pkey_get_details($key);
            self::assertIsArray($details);
            self::assertSame(1, openssl_verify($h . '.' . $p, $derSignature,
                $details['key'], OPENSSL_ALGO_SHA256));
        } finally {
            foreach ($before as $name => $old) {
                if ($old['getenv'] === false) {
                    putenv($name);
                } else {
                    putenv($name . '=' . $old['getenv']);
                }
                if ($old['env_exists']) {
                    $_ENV[$name] = $old['env'];
                } else {
                    unset($_ENV[$name]);
                }
                if ($old['server_exists']) {
                    $_SERVER[$name] = $old['server'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
        }
    }
}

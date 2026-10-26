<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Fail-closed subscription, consent and HTTPS push-service origin validation. */
final class NotificationPushRules
{
    /** @param array<string,mixed> $body @return array{endpoint:string} */
    public static function subscription(array $body): array
    {
        if (array_keys($body) !== ['endpoint', 'keys'] && array_keys($body) !== ['keys', 'endpoint']) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $endpoint = $body['endpoint'];
        $keys = $body['keys'];
        if (! is_string($endpoint) || ! self::endpoint($endpoint) || ! is_array($keys)
            || (array_keys($keys) !== ['p256dh', 'auth'] && array_keys($keys) !== ['auth', 'p256dh'])) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $point = self::decode($keys['p256dh'] ?? null);
        $auth = self::decode($keys['auth'] ?? null);
        if ($point === null || strlen($point) !== 65 || $point[0] !== "\x04"
            || $auth === null || strlen($auth) !== 16) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return ['endpoint' => $endpoint]; // No key material is stored for payload-free pushes.
    }

    public static function endpoint(mixed $value): bool
    {
        if (! is_string($value) || $value !== trim($value) || strlen($value) < 20
            || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value) === 1
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($value);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'], $parts['pass']) || isset($parts['user'])
            || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)
            || ! isset($parts['host']) || ! isset($parts['path']) || $parts['path'] === '/') {
            return false;
        }
        // No arbitrary URLs: bearer endpoints must never enable server-side request forgery.
        return in_array(strtolower($parts['host']), [
            'fcm.googleapis.com', 'updates.push.services.mozilla.com',
            'web.push.apple.com', 'web.push.icloud.com',
        ], true);
    }

    /** @param array<string,mixed> $policy @param array<string,mixed>|null $preference */
    public static function consented(array $policy, ?array $preference): bool
    {
        NotificationPolicyResolver::assertPolicy($policy, NotificationCatalog::definitions()['import.completed.push']);
        return (string) $policy['allow_user_override'] === '1'
            && $preference !== null
            && (string) ($preference['override_value'] ?? '') === '1'
            && (string) ($preference['override_epoch'] ?? '') === (string) $policy['override_epoch']
            && NotificationRules::version((string) ($preference['user_revision'] ?? ''));
    }

    public static function id(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/\A[1-9][0-9]{0,19}\z/D', $id)
            || ! NotificationRules::version($id)) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return $id;
    }

    public static function decode(mixed $encoded): ?string
    {
        if (! is_string($encoded) || ! preg_match('/\A[A-Za-z0-9_-]+\z/D', $encoded)) {
            return null;
        }
        $padding = strlen($encoded) % 4;
        if ($padding === 1) {
            return null;
        }
        $binary = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - $padding) % 4), true);
        return is_string($binary) && rtrim(strtr(base64_encode($binary), '+/', '-_'), '=') === $encoded
            ? $binary : null;
    }

    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

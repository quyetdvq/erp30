<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use JsonException;

/** Signed keyset pagination token. Never trust cursor user/filter/sort from a caller. */
final class NotificationCursor
{
    /** @return array{created_at:string,id:string} */
    public static function decode(string $token, string $user, string $filter, int $pageSize, string $secret): array
    {
        if (strlen($token) > 1024 || ! preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D', $token)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        [$body, $signature] = explode('.', $token, 2);
        $raw = self::base64urlDecode($body);
        $sig = self::base64urlDecode($signature);
        if ($raw === false || $sig === false || ! hash_equals(hash_hmac('sha256', $raw, $secret, true), $sig)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR', [], $e);
        }
        if (! is_array($data) || array_keys($data) !== ['v', 'u', 'f', 's', 't', 'id', 'exp']
            || $data['v'] !== 1 || $data['u'] !== $user || $data['f'] !== $filter || $data['s'] !== $pageSize
            || ! is_string($data['t']) || ! preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', $data['t'])
            || ! is_string($data['id']) || ! preg_match('/\A[1-9][0-9]{0,19}\z/D', $data['id'])
            || ! is_int($data['exp']) || $data['exp'] < time() || $data['exp'] > time() + 86400) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        return ['created_at' => $data['t'], 'id' => $data['id']];
    }

    public static function encode(string $user, string $filter, int $pageSize, string $createdAt, string $id, string $secret): string
    {
        $data = ['v' => 1, 'u' => $user, 'f' => $filter, 's' => $pageSize,
            't' => $createdAt, 'id' => $id, 'exp' => time() + 86400];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        return self::base64url($body) . '.' . self::base64url(hash_hmac('sha256', $body, $secret, true));
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}

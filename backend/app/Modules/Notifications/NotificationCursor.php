<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use JsonException;

/** Signed keyset pagination token. Never trust cursor user/filter/sort from a caller. */
final class NotificationCursor
{
    /** Legacy v1 cursor shape and bytes stay unchanged for existing clients. */
    /** @return array{created_at:string,id:string} */
    public static function decode(string $token, string $user, string $filter, int $pageSize, string $secret): array
    {
        $data = self::verifiedPayload($token, $secret);
        if (array_keys($data) !== ['v', 'u', 'f', 's', 't', 'id', 'exp']
            || $data['v'] !== 1 || $data['u'] !== $user || $data['f'] !== $filter || $data['s'] !== $pageSize
            || ! self::validPosition($data)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        return ['created_at' => $data['t'], 'id' => $data['id']];
    }

    public static function encode(string $user, string $filter, int $pageSize, string $createdAt, string $id, string $secret): string
    {
        return self::signedToken(['v' => 1, 'u' => $user, 'f' => $filter, 's' => $pageSize,
            't' => $createdAt, 'id' => $id, 'exp' => time() + 86400], $secret);
    }

    /** New filter combinations use v2: HMAC binds actor, canonical filter fingerprint, size and position. */
    public static function encodeV2(string $user, string $filter, int $pageSize,
        string $group, ?string $from, ?string $to, string $createdAt, string $id, string $secret): string
    {
        return self::signedToken(['v' => 2, 'u' => $user, 'f' => self::fingerprint($filter, $group, $from, $to),
            's' => $pageSize, 't' => $createdAt, 'id' => $id, 'exp' => time() + 86400], $secret);
    }

    /** @return array{created_at:string,id:string} */
    public static function decodeV2(string $token, string $user, string $filter, int $pageSize,
        string $group, ?string $from, ?string $to, string $secret): array
    {
        $data = self::verifiedPayload($token, $secret);
        $fingerprint = self::fingerprint($filter, $group, $from, $to);
        if (array_keys($data) !== ['v', 'u', 'f', 's', 't', 'id', 'exp']
            || $data['v'] !== 2 || $data['u'] !== $user || ! is_string($data['f'])
            || ! hash_equals($fingerprint, $data['f']) || $data['s'] !== $pageSize
            || ! self::validPosition($data)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        return ['created_at' => $data['t'], 'id' => $data['id']];
    }

    private static function fingerprint(string $filter, string $group, ?string $from, ?string $to): string
    {
        return hash('sha256', json_encode([$filter, $group, $from, $to], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $data */
    private static function validPosition(array $data): bool
    {
        return is_string($data['t'])
            && (bool) preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', $data['t'])
            && is_string($data['id']) && (bool) preg_match('/\A[1-9][0-9]{0,19}\z/D', $data['id'])
            && is_int($data['exp']) && $data['exp'] >= time() && $data['exp'] <= time() + 86400;
    }

    /** @param array<string,mixed> $data */
    private static function signedToken(array $data, string $secret): string
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        return self::base64url($body) . '.' . self::base64url(hash_hmac('sha256', $body, $secret, true));
    }

    /** @return array<string,mixed> */
    private static function verifiedPayload(string $token, string $secret): array
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
        if (! is_array($data)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        return $data;
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

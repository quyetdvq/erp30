<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use JsonException;

/** Own-user HMAC keyset token; independent of M11 Inbox cursor versions. */
final class MyWorkflowCursor
{
    /** @return array{created_at:string,resource_type:string,resource_id:string} */
    public static function decode(string $token, string $userId, string $filter, int $size, string $secret): array
    {
        if (strlen($token) > 1024 || preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D', $token) !== 1) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        [$body, $signature] = explode('.', $token, 2);
        $raw = base64_decode(strtr($body, '-_', '+/'), true);
        $sig = base64_decode(strtr($signature, '-_', '+/'), true);
        if ($raw === false || $sig === false || ! hash_equals(hash_hmac('sha256', $raw, $secret, true), $sig)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR', [], $e);
        }
        if (! is_array($data) || array_keys($data) !== ['v','u','r','s','t','rt','id','exp']
            || $data['v'] !== 1 || $data['u'] !== $userId || $data['r'] !== $filter || $data['s'] !== $size
            || ! self::validPosition($data['t'] ?? null, $data['rt'] ?? null, $data['id'] ?? null)
            || ! is_int($data['exp']) || $data['exp'] < time() || $data['exp'] > time() + 86400) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        return ['created_at' => $data['t'], 'resource_type' => $data['rt'], 'resource_id' => $data['id']];
    }

    public static function encode(string $userId, string $filter, int $size,
        string $createdAt, string $resourceType, string $resourceId, string $secret): string
    {
        if (! self::validPosition($createdAt, $resourceType, $resourceId)) {
            throw new DomainException('NOTIFICATION_INVALID_CURSOR');
        }
        $body = json_encode(['v' => 1, 'u' => $userId, 'r' => $filter, 's' => $size,
            't' => $createdAt, 'rt' => $resourceType, 'id' => $resourceId,
            'exp' => time() + 86400], JSON_THROW_ON_ERROR);
        return self::b64($body) . '.' . self::b64(hash_hmac('sha256', $body, $secret, true));
    }

    private static function validPosition(mixed $at, mixed $type, mixed $id): bool
    {
        return is_string($at) && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', $at) === 1
            && is_string($type) && preg_match('/\A[a-z][a-z0-9_]{0,39}\z/D', $type) === 1
            && is_string($id) && preg_match('/\A[1-9][0-9]{0,19}\z/D', $id) === 1;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

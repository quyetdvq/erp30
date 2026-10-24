<?php

declare(strict_types=1);

/** Fixed, intentionally invalid Product requests. They cannot reach persistence. */
final class M62BDevPlan
{
    /** @return list<array{key:string,method:string,path:string,payload:array<string,mixed>,code:string}> */
    public static function cases(string $productId): array
    {
        self::productId($productId);
        return [
            [
                'key' => 'create_reject_unknown_field', 'method' => 'POST',
                'path' => '/api/v1/products',
                'payload' => ['__m62b_dev_probe_unknown_field__' => true],
                'code' => 'PRODUCT_INVALID_FIELD',
            ],
            [
                'key' => 'patch_reject_direct_state', 'method' => 'PATCH',
                'path' => '/api/v1/products/' . $productId,
                'payload' => ['status' => 'ACTIVE'],
                'code' => 'PRODUCT_STATUS_REQUIRES_SETSTATE',
            ],
            [
                'key' => 'setstate_reject_invalid_state', 'method' => 'POST',
                'path' => '/api/v1/products/' . $productId . '/actions/set-state',
                'payload' => ['status' => 'INVALID*STATE'],
                'code' => 'PRODUCT_INVALID_STATUS',
            ],
        ];
    }

    public static function productId(string $value): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Product ID must be a positive decimal ID.');
        }
        return $value;
    }

    /** Exact allowlist: no arbitrary business method/path can pass the transport. */
    public static function pathAllowed(string $method, string $path): bool
    {
        if (in_array([$method, $path], [
            ['GET', '/api/v1/health'],
            ['GET', '/api/v1/auth/csrf'],
            ['GET', '/api/v1/auth/me'],
            ['POST', '/api/v1/auth/login'],
            ['POST', '/api/v1/products'],
        ], true)) return true;
        if (preg_match('#\A/api/v1/products/([1-9][0-9]{0,17})(/actions/set-state)?\z#D', $path, $m) !== 1) {
            return false;
        }
        return ($method === 'GET' && !isset($m[2]))
            || ($method === 'PATCH' && !isset($m[2]))
            || ($method === 'POST' && isset($m[2]));
    }
}

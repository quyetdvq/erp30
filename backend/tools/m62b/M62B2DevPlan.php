<?php

declare(strict_types=1);

/** Fixed, explicitly opt-in DEV Product write scenarios. No delete or bulk operations. */
final class M62B2DevPlan
{
    public static function id(string $id): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('A positive decimal ID is required.');
        }
        return $id;
    }

    public static function marker(string $hex): string
    {
        if (preg_match('/\A[a-f0-9]{12}\z/D', $hex) !== 1) {
            throw new InvalidArgumentException('Invalid DEV marker.');
        }
        return 'M62B2_' . strtoupper($hex);
    }

    /** @return array<string,mixed> */
    public static function standard(string $marker, string $groupId, string $uomId): array
    {
        self::assertMarker($marker);
        return [
            'product_code' => 'DEV_' . $marker,
            'sku' => 'DEV_' . $marker,
            'product_name' => '[DEV QA] Product ' . $marker,
            'product_type' => 'STANDARD',
            'product_group_id' => self::id($groupId),
            'base_uom_id' => self::id($uomId),
            'price' => '1.00',
            'description' => 'M6.2B.2 disposable DEV fixture ' . $marker,
        ];
    }

    /** @return array<string,mixed> */
    public static function duplicateSku(array $original, string $marker, bool $auto): array
    {
        self::assertMarker($marker);
        $payload = $original;
        $payload['product_code'] = 'DEV_DUP_' . $marker;
        $payload['product_name'] = '[DEV QA] Duplicate SKU ' . $marker;
        if ($auto) unset($payload['product_code']);
        return $payload;
    }

    /** @return array<string,mixed> */
    public static function failedCombo(array $original, string $marker, bool $auto): array
    {
        self::assertMarker($marker);
        $payload = $original;
        $payload['product_code'] = 'DEV_COMBO_' . $marker;
        $payload['sku'] = 'DEV_COMBO_' . $marker;
        $payload['product_type'] = 'COMBO';
        $payload['product_name'] = '[DEV QA] Rollback COMBO ' . $marker;
        $payload['combo_items'] = [
            ['component_product_id' => '999999999999999999', 'quantity' => '1.000000'],
        ];
        if ($auto) unset($payload['product_code']);
        return $payload;
    }

    public static function updatedDescription(string $marker): string
    {
        self::assertMarker($marker);
        return 'M6.2B.2 HTTP PATCH confirmed ' . $marker;
    }

    public static function allowed(string $method, string $path): bool
    {
        if (in_array([$method, $path], [
            ['GET', '/api/v1/auth/csrf'], ['POST', '/api/v1/auth/login'],
            ['GET', '/api/v1/auth/me'], ['POST', '/api/v1/products'],
        ], true)) return true;
        if ($method === 'GET' && preg_match('#\A/api/v1/products\?q=M62B2_[A-F0-9]{12}&page=1&page_size=100\z#D', $path) === 1) {
            return true;
        }
        if ($method === 'GET' && preg_match('#\A/api/v1/(?:product-groups|product-uoms)/[1-9][0-9]{0,17}\z#D', $path) === 1) {
            return true;
        }
        if (preg_match('#\A/api/v1/products/[1-9][0-9]{0,17}(/actions/set-state)?\z#D', $path, $m) !== 1) {
            return false;
        }
        return ($method === 'GET' && !isset($m[1]))
            || ($method === 'PATCH' && !isset($m[1]))
            || ($method === 'POST' && isset($m[1]));
    }

    private static function assertMarker(string $marker): void
    {
        if (preg_match('/\AM62B2_[A-F0-9]{12}\z/D', $marker) !== 1) {
            throw new InvalidArgumentException('Invalid DEV marker.');
        }
    }
}

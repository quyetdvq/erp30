<?php

declare(strict_types=1);

/** Strict proof that an HTTP/SQL Product is the retained M6.2B.2 DEV fixture. */
final class M62B3Fixture
{
    public static function marker(string $value): string
    {
        if (preg_match('/\AM62B2_[A-F0-9]{12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('M6.2B.2 DEV marker required.');
        }
        return $value;
    }

    public static function id(string $value): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Existing positive DEV fixture ID required.');
        }
        return $value;
    }

    public static function target(string $value): string
    {
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Canonical uppercase Lifecycle target required.');
        }
        return $value;
    }

    /** @param array<string,mixed> $row */
    public static function assertRow(array $row, string $marker, string $id): void
    {
        self::marker($marker);
        self::id($id);
        if ((string) ($row['id'] ?? '') !== $id
            || ($row['sku'] ?? null) !== 'DEV_'.$marker
            || ($row['product_name'] ?? null) !== '[DEV QA] Product '.$marker
            || ($row['description'] ?? null) !== 'M6.2B.2 HTTP PATCH confirmed '.$marker
            || ($row['product_type'] ?? null) !== 'STANDARD'
            || ($row['parent_product_id'] ?? null) !== null
            || !is_string($row['status'] ?? null)
            || !is_string($row['code'] ?? $row['product_code'] ?? null)
            || ($row['code'] ?? $row['product_code'] ?? '') === '') {
            throw new RuntimeException('Target Product is not the unchanged M6.2B.2 DEV fixture; no mutation permitted.');
        }
        self::target((string)$row['status']);
    }

    /** @param array<string,mixed> $response */
    public static function readDto(array $response, string $marker, string $id): array
    {
        if (($response['status'] ?? null) !== 200 || !is_array($response['json']['data'] ?? null)) {
            throw new RuntimeException('DEV Product detail GET did not return HTTP 200.');
        }
        $dto = $response['json']['data'];
        self::assertRow($dto, $marker, $id);
        return $dto;
    }
}

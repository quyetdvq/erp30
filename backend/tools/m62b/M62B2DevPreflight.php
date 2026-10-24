<?php

declare(strict_types=1);

/** Bounded, non-sensitive DEV reference diagnostics. Never retain HTTP bodies. */
final class M62B2DevPreflight
{
    /** @param array<string,mixed> $response @return array<string,mixed> */
    public static function http(array $response): array
    {
        $status = (int) ($response['status'] ?? 0);
        $raw = $response['json']['error']['code'] ?? null;
        $code = is_string($raw) && preg_match('/\A[A-Z][A-Z0-9_]{0,79}\z/D', $raw) === 1 ? $raw : null;
        return ['http_status' => $status, 'error_code' => $code];
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public static function reference(string $resource, string $expectedId, array $response): array
    {
        if (!in_array($resource, ['product-groups', 'product-uoms'], true)) {
            throw new InvalidArgumentException('Invalid DEV reference resource.');
        }
        M62B2DevPlan::id($expectedId);
        $brief = self::http($response);
        $data = $response['json']['data'] ?? null;
        $actualId = is_array($data) ? (string) ($data['id'] ?? '') : '';
        $state = is_array($data) && is_string($data['status'] ?? null)
            ? strtoupper($data['status']) : '';
        // Record only a catalog-like state token, never arbitrary response data.
        $brief['record_state'] = preg_match('/\A[A-Z][A-Z0-9_]{0,23}\z/D', $state) === 1 ? $state : null;
        $idMatches = $actualId === $expectedId;
        $brief['id_matches'] = $idMatches;
        $brief['pass'] = $brief['http_status'] === 200 && $idMatches && $brief['record_state'] === 'ACTIVE';
        $brief['reason'] = $brief['http_status'] !== 200 ? 'HTTP_NOT_200'
            : (!is_array($data) ? 'INVALID_DATA'
            : (!$idMatches ? 'ID_MISMATCH'
            : ($brief['record_state'] !== 'ACTIVE' ? 'NOT_ACTIVE' : 'PASS')));
        return $brief;
    }
}

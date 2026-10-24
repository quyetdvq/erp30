<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/m4/M4SourceAcceptance.php';

/** Frozen v1.2.4 14-route M4 differential-test inventory; no runtime I/O. */
final class M42CPlan
{
    // Same immutable v1.2.4 source: exact LF archive bytes, or Git-for-Windows
    // CRLF checkout bytes. Never normalize source code or permit an unpinned hash.
    private const BASELINE_ROUTE_SHA256 = '38b48e08f3add7668d32703ef149dee6caf43e9bbc983401444959a3287e5af7';
    private const BASELINE_ROUTE_CRLF_SHA256 = '2b0e228378648b2d045d486ddf69ae6d85ecd221048656a1704bf3ecd2632cb2';

    public static function pinnedBaselineRoutes(string $source): bool
    {
        $hash = hash('sha256', $source);
        return hash_equals(self::BASELINE_ROUTE_SHA256, $hash)
            || hash_equals(self::BASELINE_ROUTE_CRLF_SHA256, $hash);
    }

    /** @return array<string,array{method:string,path:string,status:int,read_only:bool}> */
    public static function operations(string $root): array
    {
        $accepted = M4SourceAcceptance::verify($root);
        if (($accepted['m4_routes'] ?? null) !== 14 || ($accepted['total_routes'] ?? null) !== 84) {
            throw new M34Failure('M4 source inventory changed');
        }
        $routes = $root . '/backend_baseline/app/Config/Routes.php';
        $source = is_file($routes) ? file_get_contents($routes) : false;
        if (!is_string($source) || !self::pinnedBaselineRoutes($source)) {
            throw new M34Failure('Bundled v1.2.4 baseline Routes.php is not the independent pinned source (LF/CRLF only)');
        }
        $manifest = $root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        if (!is_file($manifest)) {
            $manifest = $root . '/backend/tests/fixtures/baseline_v1_2_4/contract/openapi-operations-v1.2.4.json';
        }
        if (!is_file($manifest) || !hash_equals(M34Contract::MANIFEST_SHA256, (string) hash_file('sha256', $manifest))) {
            throw new M34Failure('Frozen 150-operation manifest mismatch');
        }
        $frozen = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($frozen['operations'] ?? null) || count($frozen['operations']) !== 150) {
            throw new M34Failure('Frozen manifest count differs from 150');
        }
        $indexed = [];
        foreach ($frozen['operations'] as $op) {
            if (!is_array($op) || !is_string($op['operation_id'] ?? null)) {
                throw new M34Failure('Malformed frozen OpenAPI operation');
            }
            $indexed[$op['operation_id']] = $op;
        }
        $result = [];
        foreach (M4SourceAcceptance::expected() as $route => $owned) {
            [$method] = explode(' ', $route, 2);
            $name = $owned[2];
            $f = $indexed[$name] ?? null;
            if (!is_array($f) || ($f['method'] ?? null) !== $method || ($f['success_statuses'] ?? null) !== [$owned[3]]) {
                throw new M34Failure('M4 baseline operation/status mismatch: ' . $name);
            }
            $path = (string) ($f['path'] ?? '');
            if (!str_starts_with($path, '/api/v1/')) {
                throw new M34Failure('Unexpected M4 contract path');
            }
            $readOnly = $method === 'GET' || $name === 'validateNumberingSeriesConfig';
            if (isset($result[$name])) {
                throw new M34Failure('Duplicate M4 operation id');
            }
            $result[$name] = ['method' => $method, 'path' => $path, 'status' => $owned[3], 'read_only' => $readOnly];
        }
        if (count($result) !== 14 || count(array_filter($result, static fn ($op) => $op['read_only'])) !== 8) {
            throw new M34Failure('M4 expected exactly 8 read/dry-run and 6 write operations');
        }
        return $result;
    }

    /**
     * Refuse arbitrary URLs and queries, even for manually-prepared clone cases.
     * Only system display-settings values may use the one approved query string.
     */
    public static function pathAllowed(string $name, string $path, array $ops): bool
    {
        $op = $ops[$name] ?? null;
        if (!is_array($op) || strlen($path) > 250 || str_contains($path, '..')) {
            return false;
        }
        if ($name === 'listDisplaySettingValues') {
            return $path === '/api/v1/display-settings/values?context_type=SYSTEM';
        }
        $pattern = preg_quote($op['path'], '~');
        $pattern = str_replace('\\{resource_id\\}', '[1-9][0-9]{0,17}', $pattern);
        $pattern = str_replace('\\{series_id\\}', '[1-9][0-9]{0,17}', $pattern);
        $pattern = str_replace('\\{id\\}', '[1-9][0-9]{0,17}', $pattern);
        $pattern = str_replace('\\{resource\\}', 'organization', $pattern);
        $pattern = str_replace('\\{action_code\\}', '[a-z][a-z0-9_-]{0,49}', $pattern);
        return preg_match('~^' . $pattern . '$~D', $path) === 1;
    }

    /** @param array<string,mixed> $ops */
    public static function invalidWritePath(string $name, array $ops): string
    {
        $op = $ops[$name] ?? null;
        if (!is_array($op) || $op['read_only']) {
            throw new M34Failure('Not a registered M4 write operation');
        }
        return str_replace(
            ['{resource_id}', '{series_id}', '{id}', '{resource}', '{action_code}'],
            ['999999999', '999999999', '999999999', 'organization', 'unregistered_action'],
            $op['path']
        );
    }

    /** @return list<string> */
    public static function readNames(array $ops): array
    {
        return array_values(array_keys(array_filter($ops, static fn ($op) => $op['read_only'])));
    }

    /** @return list<string> */
    public static function writeNames(array $ops): array
    {
        return array_values(array_keys(array_filter($ops, static fn ($op) => !$op['read_only'])));
    }
}

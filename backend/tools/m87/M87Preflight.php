<?php

declare(strict_types=1);

/**
 * M8.7A: deterministic, credential-free local readiness diagnosis.
 * Never reads or prints credential values. No HTTP or DB access.
 */
final class M87Preflight
{
    private const ACTOR_KEYS = [
        'M87_ACTOR_A_USER', 'M87_ACTOR_A_PASSWORD',
        'M87_ACTOR_B_USER', 'M87_ACTOR_B_PASSWORD',
    ];

    /**
     * @param array<string, string|false|null> $env
     * @return list<string> stable sanitized error codes
     */
    public static function environment(array $env): array
    {
        $issues = [];
        if (($env['M87_DEV_CONSENT'] ?? null) !== 'DEV_HTTP_READ_ONLY') {
            $issues[] = 'MISSING_HTTP_DEV_CONSENT';
        }
        $host = $env['M87_DEV_HOST'] ?? null;
        if (!in_array($host, ['localhost', 'api.monmin.net'], true)) {
            $issues[] = 'INVALID_OR_MISSING_LOCAL_VHOST';
        }
        if (!in_array(($env['M87_DEV_SCHEME'] ?? null), ['http', 'https'], true)) {
            $issues[] = 'INVALID_OR_MISSING_LOCAL_SCHEME';
        }
        foreach (self::ACTOR_KEYS as $key) {
            $value = $env[$key] ?? null;
            if (!is_string($value) || $value === '') {
                $issues[] = 'MISSING_' . $key;
            }
        }
        if (is_string($env['M87_ACTOR_A_USER'] ?? null)
            && ($env['M87_ACTOR_A_USER'] ?? '') !== ''
            && ($env['M87_ACTOR_A_USER'] ?? null) === ($env['M87_ACTOR_B_USER'] ?? null)) {
            $issues[] = 'ACTOR_LOGINS_MUST_BE_DISTINCT';
        }
        return $issues;
    }

    /** @return array<string,string|false> */
    public static function processEnvironment(): array
    {
        $keys = array_merge(['M87_DEV_CONSENT','M87_DEV_HOST','M87_DEV_SCHEME'], self::ACTOR_KEYS);
        $values = [];
        foreach ($keys as $key) $values[$key] = getenv($key);
        return $values;
    }

    /**
     * Review an existing DB audit report. A verified source is returned solely
     * for local processing; do not echo its raw contents.
     * @return array{source:?array, codes:list<string>, path:?string}
     */
    public static function report(string $input, string $folder, bool $requireFresh=true, ?int $now=null): array
    {
        $codes = [];
        $root = realpath($folder);
        $path = realpath($input);
        if ($root === false || $path === false
            || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            || !preg_match('/\Am87_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}\.json\z/D', basename($path))
            || !is_file($path) || is_link($input) || is_link($path) || !is_readable($path)
            || filesize($path) > 1048576) {
            return ['source'=>null, 'codes'=>['INVALID_OR_MISSING_LOCAL_DB_REPORT'], 'path'=>null];
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['source'=>null, 'codes'=>['INVALID_DB_REPORT_JSON'], 'path'=>$path];
        }
        if (!is_array($decoded) || ($decoded['milestone']??null) !== 'M8.7') {
            return ['source'=>null,'codes'=>['INVALID_DB_REPORT_SCHEMA'],'path'=>$path];
        }
        if (!in_array(($decoded['status']??null),
            ['PASS_DEV_SQL_PARITY','INCONCLUSIVE_DB_FIXTURE_COVERAGE'], true)) {
            $codes[]='DB_PARITY_REPORT_NOT_USABLE';
        }
        $time = $decoded['timestamp_utc']??null;
        if (!is_string($time) || $time === '' || ($timestamp = strtotime($time)) === false) {
            $codes[]='DB_REPORT_TIMESTAMP_INVALID';
        } elseif ($requireFresh && abs(($now??time())-$timestamp)>900) {
            $codes[]='DB_REPORT_OUTSIDE_15_MIN_WINDOW';
        }
        $actors=$decoded['actor_ids']??null;
        if (!is_array($actors) || count($actors)!==2 || !array_is_list($actors)
            || !is_string($actors[0]) || !is_string($actors[1])
            || !preg_match('/\A[1-9][0-9]*\z/D',$actors[0])
            || !preg_match('/\A[1-9][0-9]*\z/D',$actors[1])
            || $actors[0]===$actors[1] || !is_array($decoded['data']??null)) {
            $codes[]='DB_REPORT_ACTOR_DATA_INVALID';
        }
        return ['source'=>$decoded,'codes'=>$codes,'path'=>$path];
    }

    /** @return list<string> */
    public static function issues(string $reportPath, string $reportsFolder, bool $requireFresh=true): array
    {
        return array_values(array_unique(array_merge(
            self::environment(self::processEnvironment()),
            self::report($reportPath,$reportsFolder,$requireFresh)['codes']
        )));
    }
}

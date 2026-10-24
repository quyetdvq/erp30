<?php

declare(strict_types=1);

namespace Tests\Support\Foundation;

use RuntimeException;

/** Fail closed BEFORE DatabaseTestTrait can refresh any tables. */
final class RuntimeGuard
{
    /** @param array<string,mixed> $tests */
    public static function assertConfiguration(string $environment, string $group, array $tests): string
    {
        if ($environment !== 'testing' || $group !== 'tests') {
            throw new RuntimeException('Foundation tests require ENVIRONMENT=testing and defaultGroup=tests.');
        }
        $host = (string) ($tests['hostname'] ?? '');
        $port = filter_var($tests['port'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || ($tests['database'] ?? null) !== 'erp_test'
            || ($tests['DBDriver'] ?? null) !== 'MySQLi' || $port === false) {
            throw new RuntimeException('Refusing target: only loopback MySQLi / erp_test with a valid port is allowed.');
        }
        if (($tests['DSN'] ?? '') !== '' || ($tests['failover'] ?? []) !== []
            || filter_var($tests['pConnect'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || preg_match('/\A[A-Za-z0-9_]*\z/', (string) ($tests['DBPrefix'] ?? '')) !== 1) {
            throw new RuntimeException('Refusing DSN/failover/persistent connection or unsafe table prefix.');
        }
        return $host . ':' . $port . '/erp_test';
    }
}

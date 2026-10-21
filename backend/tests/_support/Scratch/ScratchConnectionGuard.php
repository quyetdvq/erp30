<?php

declare(strict_types=1);

namespace Tests\Support\Scratch;

use CodeIgniter\Database\BaseConnection;
use Config\Database as DatabaseConfig;
use RuntimeException;

/**
 * 03C-04D: prove the SAME CodeIgniter connection used by a scratch PHPUnit
 * method, never merely a disposable probe opened by its bootstrap.
 * Only SELECT and SHOW GRANTS are permitted. This is not a write authorization.
 */
final class ScratchConnectionGuard
{
    public const DATABASE = 'erp_test_scratch';
    public const ACCOUNT = 'erp_scratch_runner@127.0.0.1';
    public const PREFIX = 'dinktech_';

    /** @param array<string,mixed> $settings */
    public static function assertSettings(array $settings, string $user, string $password): void
    {
        $required = [
            'DSN' => '', 'hostname' => '127.0.0.1', 'port' => 3306,
            'database' => self::DATABASE, 'username' => 'erp_scratch_runner',
            'DBDriver' => 'MySQLi', 'DBPrefix' => self::PREFIX, 'pConnect' => false,
            'failover' => [],
        ];
        foreach ($required as $key => $expected) {
            if (! array_key_exists($key, $settings) || $settings[$key] !== $expected) {
                throw new RuntimeException('03C-04D HOLD: scratch connection configuration is not exact.');
            }
        }
        if ($user !== 'erp_scratch_runner' || $password === ''
            || ! isset($settings['password']) || ! is_string($settings['password'])
            || ! hash_equals($password, $settings['password'])) {
            throw new RuntimeException('03C-04D HOLD: effective scratch credential configuration is not exact.');
        }
    }

    public static function assertConfiguration(DatabaseConfig $config, string $user, string $password): void
    {
        if ($config->defaultGroup !== 'tests') {
            throw new RuntimeException('03C-04D HOLD: default DB group is not tests.');
        }
        self::assertSettings($config->tests, $user, $password);
        self::assertSettings($config->default, $user, $password);
    }

    /** @param array<string,mixed> $row */
    public static function assertIdentity(array $row): void
    {
        if (($row['account'] ?? null) !== self::ACCOUNT
            || ($row['db_name'] ?? null) !== self::DATABASE
            || (int) ($row['port'] ?? 0) !== 3306
            || ! in_array(strtoupper((string) ($row['active_role'] ?? '')), ['', 'NONE'], true)) {
            throw new RuntimeException('03C-04D HOLD: effective MySQL connection is not scratch-only.');
        }
    }

    /**
     * Verifies both identity and grants on THIS exact BaseConnection.
     * @return array{account:string,database:string}
     */
    public static function assertEffective(BaseConnection $db, bool $requireNoTransaction = true): array
    {
        if ($db->DBPrefix !== self::PREFIX || $db->DBDriver !== 'MySQLi'
            || ($requireNoTransaction && $db->transDepth !== 0)) {
            throw new RuntimeException('03C-04D HOLD: active PHPUnit connection prefix, driver or depth differs.');
        }
        $identity = $db->query('SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS active_role, DATABASE() AS db_name, @@port AS port')->getRowArray();
        if (! is_array($identity)) {
            throw new RuntimeException('03C-04D HOLD: actual PHPUnit connection identity unavailable.');
        }
        self::assertIdentity($identity);
        $grants = [];
        foreach ($db->query('SHOW GRANTS')->getResultArray() as $row) {
            $grant = reset($row);
            if (! is_string($grant)) {
                throw new RuntimeException('03C-04D HOLD: malformed effective grants.');
            }
            $grants[] = $grant;
        }
        \ScratchReadinessGate::assertDmlOnlyGrants(
            $grants, (string) $identity['account'], (string) $identity['active_role'],
        );
        return ['account' => self::ACCOUNT, 'database' => self::DATABASE];
    }

    /**
     * CI4 caches shared DB connections by group. Checking only connect(group,
     * false) does NOT validate an earlier cached connect(group, true).
     * Examine every existing shared connection before a test can write.
     */
    public static function assertSharedConnections(): void
    {
        foreach (\Config\Database::getConnections() as $name => $connection) {
            if (! is_string($name) || ! $connection instanceof BaseConnection) {
                throw new RuntimeException('03C-04D HOLD: unrecognized cached database connection.');
            }
            self::assertEffective($connection);
        }
    }
}

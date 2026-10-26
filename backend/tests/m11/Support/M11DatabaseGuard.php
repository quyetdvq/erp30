<?php

declare(strict_types=1);

namespace Tests\M11\Support;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/** Never obtain a test connection by falling back to the application/default DB. */
final class M11DatabaseGuard
{
    /** Validate the configured test target and server identity, without requiring M11 tables yet. */
    public static function connectTarget(): BaseConnection
    {
        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
            throw new RuntimeException('M11 database access requires CI_ENVIRONMENT=testing.');
        }
        $config = config(Database::class);
        if (! $config instanceof Database) {
            throw new RuntimeException('M11 cannot load the CodeIgniter database tests configuration.');
        }
        self::assertSafeTestGroup($config->tests);
        // CodeIgniter stores migration history in the default database group.
        // Prevent the testing runner from ever writing migration history to `erp`.
        if ($config->defaultGroup !== 'tests') {
            throw new RuntimeException('M11 requires defaultGroup=tests while CI_ENVIRONMENT=testing.');
        }

        $db = Database::connect('tests', false);
        try {
            $actual = $db->query('SELECT DATABASE() AS name, CURRENT_USER() AS login, VERSION() AS version')->getRowArray();
            if (($actual['name'] ?? null) !== 'erp_test'
                || strtok((string) ($actual['login'] ?? ''), '@') !== 'erp_test_runner'
                || ! str_contains((string) ($actual['version'] ?? ''), 'MariaDB')) {
                throw new RuntimeException('M11 requires isolated MariaDB erp_test as erp_test_runner.');
            }
        } catch (\Throwable $e) {
            $db->close();
            throw $e;
        }
        return $db;
    }

    public static function connect(): BaseConnection
    {
        $db = self::connectTarget();
        $prefix = (string) $db->DBPrefix;

        // Everything created by the fixture must roll back: no MyISAM, no DDL in a test.
        $tables = [
            'users', 'auth_identities', 'auth_permissions_users', 'auth_groups_users', 'organizations',
            'employees', 'employee_assignments',
            'provinces', 'resources', 'resource_actions', 'groups', 'group_permissions',
            'policy_actions', 'workflow_states', 'lifecycles',
            'lifecycle_states', 'lifecycle_transitions', 'policies',
            'workflow_notification_assignment_sets', 'workflow_notification_assignees',
            'configuration_audit_events', 'settings', 'import_jobs', 'notification_settings', 'notification_user_preferences',
            'notification_outbox', 'user_notifications', 'notification_email_deliveries',
            'notification_push_subscriptions', 'notification_push_deliveries',
        ];
        $names = array_map(static fn (string $table): string => $prefix . $table, $tables);
        $rows = $db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('
            . implode(',', array_fill(0, count($names), '?')) . ')', $names)->getResultArray();
        $actualEngines = [];
        foreach ($rows as $row) {
            $actualEngines[(string) $row['TABLE_NAME']] = strtoupper((string) $row['ENGINE']);
        }
        foreach ($names as $table) {
            if (($actualEngines[$table] ?? null) !== 'INNODB') {
                $db->close();
                throw new RuntimeException('Missing InnoDB M11 fixture table: ' . $table
                    . '. First run .\\tools\\test-unit.ps1 PREPARE M11 for missing 019-022 migrations.');
            }
        }

        // The dispatcher claims globally, not by fixture. Never let a PHPUnit
        // scenario claim unrelated pending/retry/leased events in a shared test DB.
        // Completed historical rows are fine; fixture assertions are isolated.
        $activeOutbox = $db->table('notification_outbox')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        if ($activeOutbox !== 0) {
            $db->close();
            throw new RuntimeException('erp_test has active Outbox events; M11 integration requires an idle test queue.');
        }
        // A separate SMTP worker must not claim unrelated committed test receipts.
        $activeEmail = $db->table('notification_email_deliveries')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        if ($activeEmail !== 0) {
            $db->close();
            throw new RuntimeException('erp_test has active Email receipts; M11 tests require an idle email queue.');
        }
        $activePush = $db->table('notification_push_deliveries')
            ->whereIn('status', ['PENDING','RETRY','LEASED'])->countAllResults();
        if ($activePush !== 0) {
            $db->close();
            throw new RuntimeException('erp_test has active Push receipts; M11 tests require an idle push queue.');
        }
        return $db;
    }

    /**
     * Reject unsafe targets before any network/SQL call. Credentials remain in
     * backend/.env (database.tests.*), never in source or test-runner arguments.
     *
     * @param array<string, mixed> $group
     */
    public static function assertSafeTestGroup(array $group): void
    {
        if (($group['database'] ?? null) !== 'erp_test') {
            throw new RuntimeException('M11 tests must use database.tests.database=erp_test, never the application database.');
        }
        if (($group['hostname'] ?? null) !== '127.0.0.1'
            || ! in_array($group['port'] ?? null, [3306, '3306'], true)
            || ($group['DBPrefix'] ?? null) !== 'dinktech_'
            || strtolower((string) ($group['DBDriver'] ?? '')) !== 'mysqli'
            || ($group['username'] ?? null) !== 'erp_test_runner'
            || ! is_string($group['password'] ?? null)
            || $group['password'] === ''
            || (string) ($group['DSN'] ?? '') !== ''
            || (bool) ($group['pConnect'] ?? false)) {
            throw new RuntimeException('Unsafe M11 tests group: require local MySQLi, dinktech_ and the restricted erp_test_runner account.');
        }
    }
}

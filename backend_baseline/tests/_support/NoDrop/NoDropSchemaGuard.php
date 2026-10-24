<?php

declare(strict_types=1);

namespace Tests\Support\NoDrop;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Fail-closed gate for the stable, pre-provisioned erp_test schema.
 *
 * A database account with only SELECT/INSERT/UPDATE/DELETE on erp_test.*
 * cannot execute DROP, TRUNCATE, ALTER, CREATE or migration rollback.
 * Code inspection or migration flags alone are NOT an enforcement boundary.
 */
final class NoDropSchemaGuard
{
    private const REQUIRED = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    /**
     * Pure parser for SHOW GRANTS. Unknown statements, roles, global privileges,
     * admin permissions, and table-by-table grants are rejected (fail closed).
     *
     * @param list<string> $statements
     */
    public static function assertLeastPrivilege(array $statements, string $effectiveAccount): void
    {
        if ($effectiveAccount === '' || preg_match('/\A(?:root|mysql\.sys|mysql\.session)@/iD', $effectiveAccount) === 1) {
            throw new RuntimeException('NO-DROP: privileged or unknown MySQL account is forbidden.');
        }
        if ($statements === []) {
            throw new RuntimeException('NO-DROP: SHOW GRANTS returned no grants.');
        }
        $dml = [];
        foreach ($statements as $grant) {
            if (preg_match('/\AGRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s+/iDs', trim($grant), $parts) !== 1) {
                throw new RuntimeException('NO-DROP: role, unrecognized grant, or elevated account.');
            }
            $scope = strtolower(str_replace('`', '', trim($parts[2])));
            $permissions = array_map('trim', explode(',', strtoupper($parts[1])));
            if (in_array('', $permissions, true) || preg_match('/\bWITH\b/i', $grant) === 1) {
                throw new RuntimeException('NO-DROP: elevated grant or invalid privilege list.');
            }
            if ($scope === '*.*' && $permissions === ['USAGE']) {
                continue;
            }
            if ($scope !== 'erp_test.*') {
                throw new RuntimeException('NO-DROP: grants must be limited to erp_test.* (USAGE on *.* allowed).');
            }
            foreach ($permissions as $permission) {
                if (! in_array($permission, self::REQUIRED, true)) {
                    throw new RuntimeException('NO-DROP: non-DML SQL privilege detected: ' . $permission);
                }
                $dml[$permission] = true;
            }
        }
        foreach (self::REQUIRED as $required) {
            if (! isset($dml[$required])) {
                throw new RuntimeException('NO-DROP: missing required test data privilege: ' . $required);
            }
        }
    }

    /**
     * Verify the *effective*, not merely configured, database identity and
     * permissions. Does not issue DDL or modify data.
     *
     * @return array{account:string, schema_sha256:string, table_count:int}
     */
    public static function attest(BaseConnection $db, string $prefix, int $expectedPort): array
    {
        $identity = self::rows($db, 'SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS active_role, DATABASE() AS db_name, @@port AS server_port');
        if (count($identity) !== 1 || ($identity[0]['db_name'] ?? '') !== 'erp_test'
            || (int) ($identity[0]['server_port'] ?? 0) !== $expectedPort) {
            throw new RuntimeException('NO-DROP: connected database/port differs from confirmed erp_test target.');
        }
        $account = (string) ($identity[0]['account'] ?? '');
        if (getenv('ERP_NO_DROP_SKIP_GRANTS') === '1') {
            // Explicit, per-process opt-in for test environments with a DDL-capable
            // account. Database identity and schema/data fingerprints still apply.
            // This removes preventive DDL protection; it is NOT equivalent to
            // running with a least-privilege MySQL account.
            if ($account === '') {
                throw new RuntimeException('NO-DROP: CURRENT_USER() returned an empty account.');
            }
            fwrite(STDERR, 'NO-DROP WARNING: MySQL grant check skipped; DDL is not prevented by database privileges.' . PHP_EOL);
        } else {
            $role = $identity[0]['active_role'] ?? null;
            if ($role !== null && ! in_array(strtoupper((string) $role), ['', 'NONE'], true)) {
                throw new RuntimeException('NO-DROP: active MySQL roles are not permitted.');
            }
            $grants = [];
            foreach (self::rows($db, 'SHOW GRANTS') as $row) {
                $value = reset($row);
                if (! is_string($value)) {
                    throw new RuntimeException('NO-DROP: invalid SHOW GRANTS row.');
                }
                $grants[] = $value;
            }
            self::assertLeastPrivilege($grants, $account);
        }
        $snapshot = self::schemaFingerprint($db, $prefix);
        return ['account' => $account, 'schema_sha256' => $snapshot['sha256'], 'table_count' => $snapshot['table_count']];
    }

    /**
     * Fingerprint DDL-relevant metadata, excluding row count/auto increment.
     * Schema must already exist; this method NEVER creates or modifies it.
     *
     * @return array{sha256:string, table_count:int}
     */
    public static function schemaFingerprint(BaseConnection $db, string $prefix): array
    {
        $tables = self::rows($db, 'SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        if ($tables === []) {
            throw new RuntimeException('NO-DROP: erp_test has no schema. Provision forward-only, outside PHPUnit.');
        }
        $names = array_column($tables, 'TABLE_NAME');
        foreach (['organizations', 'resources', 'resource_actions', 'group_permissions', 'products'] as $required) {
            if (! in_array($prefix . $required, $names, true)) {
                throw new RuntimeException('NO-DROP: required table is absent: ' . $required . '. No test ran.');
            }
        }
        $columns = self::rows($db, 'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION');
        $indices = self::rows($db, 'SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX');
        $constraints = self::rows($db, 'SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME');
        return [
            'sha256' => hash('sha256', json_encode([$tables, $columns, $indices, $constraints], JSON_THROW_ON_ERROR)),
            'table_count' => count($tables),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function rows(BaseConnection $db, string $sql): array
    {
        $query = $db->query($sql);
        if (! is_object($query) || ! method_exists($query, 'getResultArray')) {
            throw new RuntimeException('NO-DROP: schema/privilege verification query failed.');
        }
        $rows = $query->getResultArray();
        if (! is_array($rows)) {
            throw new RuntimeException('NO-DROP: invalid schema verification result.');
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('NO-DROP: malformed schema verification row.');
            }
        }
        /** @var array<int, array<string, mixed>> $rows */
        return array_values($rows);
    }
}

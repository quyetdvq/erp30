<?php

declare(strict_types=1);

namespace Tests\Support\NoDrop;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Read-only, order-independent row snapshot of EVERY base table in erp_test.
 *
 * Hashes the actual column values (including binary data and NULL) rather
 * than row counts, AUTO_INCREMENT or nondeterministic SELECT row order.
 * No table cleanup or mutation is attempted on a mismatch.
 *
 * This is a bounded class-level audit, NOT per-test isolation proof.
 */
final class NoDropDataGuard
{
    private const MAX_ROWS_PER_TABLE = 50_000;

    private const MAX_TOTAL_ROWS = 200_000;

    /**
     * @return array{data_sha256:string,row_count:int,table_sha256:array<string,string>}
     */
    public static function fingerprint(BaseConnection $db): array
    {
        $tables = self::rows($db, 'SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        if ($tables === []) {
            throw new RuntimeException('NO-DROP DATA: no tables in the confirmed test schema.');
        }

        $tableHashes = [];
        $totalRows = 0;
        foreach ($tables as $table) {
            $name = $table['TABLE_NAME'] ?? null;
            $type = $table['TABLE_TYPE'] ?? null;
            if (! is_string($name) || preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name) !== 1
                || $type !== 'BASE TABLE') {
                // Fail closed rather than silently exclude a table or hash a view.
                throw new RuntimeException('NO-DROP DATA: unsupported table metadata; audit all base tables only.');
            }

            $sql = 'SELECT * FROM `' . $name . '` LIMIT ' . (self::MAX_ROWS_PER_TABLE + 1);
            $rows = self::rows($db, $sql);
            $count = count($rows);
            if ($count > self::MAX_ROWS_PER_TABLE || $totalRows + $count > self::MAX_TOTAL_ROWS) {
                throw new RuntimeException('NO-DROP DATA: bounded snapshot limit exceeded; no tests may run without a reviewed audit plan.');
            }
            $totalRows += $count;
            $tableHashes[$name] = self::tableHash($name, $rows);
        }

        ksort($tableHashes, SORT_STRING);

        return [
            'data_sha256' => hash('sha256', serialize($tableHashes)),
            'row_count' => $totalRows,
            'table_sha256' => $tableHashes,
        ];
    }

    /**
     * Pure, deterministic multiset hash: no primary key or row order required.
     * Includes column names, types and values; duplicate rows remain visible.
     *
     * @param list<array<string,mixed>> $rows
     */
    public static function tableHash(string $tableName, array $rows): string
    {
        $digests = [];
        foreach ($rows as $row) {
            ksort($row, SORT_STRING);
            $digests[] = hash('sha256', serialize($row));
        }
        sort($digests, SORT_STRING);
        return hash('sha256', serialize([$tableName, count($digests), $digests]));
    }

    /** @return list<array<string,mixed>> */
    private static function rows(BaseConnection $db, string $sql): array
    {
        $result = $db->query($sql);
        if (! is_object($result) || ! method_exists($result, 'getResultArray')) {
            throw new RuntimeException('NO-DROP DATA: read-only snapshot query failed.');
        }
        $rows = $result->getResultArray();
        if (! is_array($rows)) {
            throw new RuntimeException('NO-DROP DATA: malformed snapshot result.');
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('NO-DROP DATA: malformed row in snapshot.');
            }
        }
        /** @var array<int,array<string,mixed>> $rows */
        return array_values($rows);
    }
}

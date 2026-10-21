<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;
use Tests\Support\NoDrop\NoDropDataGuard;

/**
 * 03C-04D - SELECT-only proof for the three S04B rows observed on erp_test.
 * No cleanup methods, SQL mutation or authorization to delete are present.
 */
final class ScratchIncidentVirtualBaseline
{
    private const TARGETS = [
        'dinktech_resources',
        'dinktech_organizations',
        'dinktech_auto_numbering_series',
    ];
    private const EXPECTED_ROWS = [
        'dinktech_auto_numbering_series' => 1,
        'dinktech_migrations' => 23,
        'dinktech_organizations' => 1,
        'dinktech_resource_actions' => 10,
        'dinktech_resources' => 3,
        'dinktech_workflow_states' => 8,
    ];
    private const BASELINE_SHA = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb';
    private const INCIDENT_SHA = '4db457abe0dbac555ecfccd8f8654aa2570661734acba429c87e57dfdde01e14';

    /**
     * Strict proof for the exact first quarantined method. No other methods
     * or row sets are accepted. Output must not disclose PKs or marker token.
     * @param list<array<string,mixed>> $resource
     * @param list<array<string,mixed>> $org
     * @param list<array<string,mixed>> $series
     * @return array<string,int> internal target PKs only
     */
    public static function proveThree(array $resource, array $org, array $series): array
    {
        if (count($resource) !== 1 || count($org) !== 1 || count($series) !== 1) {
            throw new RuntimeException('03C-04D HOLD: expected exactly three marked rows.');
        }
        $r = $resource[0];
        $o = $org[0];
        $s = $series[0];
        foreach ([$r, $o, $s] as $row) {
            if (! isset($row['id']) || ! ctype_digit((string) $row['id']) || (int) $row['id'] < 1) {
                throw new RuntimeException('03C-04D HOLD: marker row has no unambiguous PK.');
            }
        }
        $name = (string) ($r['name'] ?? '');
        if (preg_match('/\ANUM005-S04B-([A-F0-9]{16})\z/D', $name, $matches) !== 1) {
            throw new RuntimeException('03C-04D HOLD: resource marker does not identify the incident.');
        }
        $token = $matches[1];
        if (($r['type'] ?? '') === ''
            || preg_match('/\Anum005_length_[0-9a-f]{8}-s04b-' . strtolower($token) . '\z/D', (string) $r['type']) !== 1
            || ($r['domain'] ?? '') !== 'SYSTEM'
            || ($r['created_at'] ?? '') !== '2026-10-06 04:05:06'
            || ($o['type'] ?? '') !== 'GROUP' || ! array_key_exists('parent_id', $o) || $o['parent_id'] !== null
            || ($o['name'] ?? '') !== 'NUM005 Group'
            || ($o['status'] ?? '') !== 'ACTIVE'
            || preg_match('/\ANUM005-GROUP-[0-9a-f]{13}-S04B-' . $token . '\z/D', (string) ($o['code'] ?? '')) !== 1
            || ($s['resource_id'] ?? null) === null || (int) $s['resource_id'] !== (int) $r['id']
            || ($s['series_code'] ?? '') !== 'DEFAULT-S04B-' . $token
            || ($s['name'] ?? '') !== 'Default'
            || ($s['scope_type'] ?? '') !== 'GROUP'
            || ($s['pattern'] ?? '') !== 'X{SEQ:3}'
            || ($s['reset_policy'] ?? '') !== 'NEVER'
            || ($s['generation_mode'] ?? '') !== 'AUTO_REQUIRED'
            || (int) ($s['start_number'] ?? -1) !== 10000
            || (int) ($s['increment_by'] ?? -1) !== 1
            || (int) ($s['is_default'] ?? -1) !== 1
            || ($s['status'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('03C-04D HOLD: source method fixture ownership not proven.');
        }
        return [
            'dinktech_resources' => (int) $r['id'],
            'dinktech_organizations' => (int) $o['id'],
            'dinktech_auto_numbering_series' => (int) $s['id'],
        ];
    }

    /** @param list<array<string,mixed>> $foreignKeys */
    public static function proveNoDependentRows(BaseConnection $db, array $foreignKeys, array $owned): int
    {
        if (count($foreignKeys) !== 50 || array_diff(self::TARGETS, array_keys($owned)) !== []) {
            throw new RuntimeException('03C-04D HOLD: incomplete FK snapshot/owned rows.');
        }
        $verified = 0;
        foreach ($foreignKeys as $fk) {
            $parent = $fk['REFERENCED_TABLE_NAME'] ?? null;
            if (! is_string($parent) || ! isset($owned[$parent])) {
                continue;
            }
            $child = $fk['TABLE_NAME'] ?? null;
            $column = $fk['COLUMN_NAME'] ?? null;
            if (! is_string($child) || ! is_string($column)
                || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $child) !== 1
                || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $column) !== 1
                || ($fk['REFERENCED_COLUMN_NAME'] ?? null) !== 'id'
                || (int) ($fk['ORDINAL_POSITION'] ?? -1) !== 1) {
                throw new RuntimeException('03C-04D HOLD: composite/unreviewed FK reference.');
            }
            $query = $db->query('SELECT COUNT(*) AS n FROM `' . $child . '` WHERE `' . $column . '` = ?', [$owned[$parent]]);
            $result = $query->getRowArray();
            if (! is_array($result) || ! ctype_digit((string) ($result['n'] ?? ''))) {
                throw new RuntimeException('03C-04D HOLD: FK dependent-row query not proven.');
            }
            $expected = $parent === 'dinktech_resources'
                && $child === 'dinktech_auto_numbering_series' && $column === 'resource_id' ? 1 : 0;
            if ((int) $result['n'] !== $expected) {
                throw new RuntimeException('03C-04D HOLD: incident PK is referenced by unexpected rows.');
            }
            $verified++;
        }
        if ($verified !== 11) {
            throw new RuntimeException('03C-04D HOLD: inbound FK coverage differs from reviewed metadata.');
        }
        return $verified;
    }

    /**
     * Recompute the EXACT original NoDropDataGuard digest with the three
     * proven PKs EXCLUDED IN MEMORY. No SQL DELETE or mutation is used.
     * This detects alterations to any of the original 43 rows.
     * @param array<string,int> $owned
     * @param list<array<string,mixed>> $tables
     * @return array{row_count:int,sha256:string}
     */
    public static function hypotheticalBaseline(BaseConnection $db, array $owned, array $tables): array
    {
        if (count($owned) !== 3 || count($tables) !== 41) {
            throw new RuntimeException('03C-04D HOLD: incomplete hypothetical baseline input.');
        }
        $seen = [];
        $hashes = [];
        $rowCount = 0;
        foreach ($tables as $table) {
            $name = $table['TABLE_NAME'] ?? null;
            if (! is_string($name) || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $name) !== 1) {
                throw new RuntimeException('03C-04D HOLD: invalid immutable table metadata.');
            }
            $result = $db->query('SELECT * FROM `' . $name . '` LIMIT 50001')->getResultArray();
            if (! is_array($result) || count($result) > 50000) {
                throw new RuntimeException('03C-04D HOLD: unbounded baseline read.');
            }
            $rows = [];
            foreach ($result as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException('03C-04D HOLD: invalid baseline row.');
                }
                if (isset($owned[$name]) && isset($row['id']) && (int) $row['id'] === $owned[$name]) {
                    $seen[$name] = ($seen[$name] ?? 0) + 1;
                    continue;
                }
                $rows[] = $row;
            }
            $rowCount += count($rows);
            $hashes[$name] = NoDropDataGuard::tableHash($name, $rows);
        }
        foreach ($owned as $table => $id) {
            if (($seen[$table] ?? 0) !== 1) {
                throw new RuntimeException('03C-04D HOLD: expected owned PK missing or repeated.');
            }
        }
        ksort($hashes, SORT_STRING);
        return ['row_count' => $rowCount, 'sha256' => hash('sha256', serialize($hashes))];
    }

    /** @param array<string,int> $counts */
    public static function assertIncidentCounts(array $counts): void
    {
        if (count($counts) !== 41) {
            throw new RuntimeException('03C-04D HOLD: not all 41 tables counted.');
        }
        foreach ($counts as $table => $actual) {
            $expected = self::EXPECTED_ROWS[$table] ?? 0;
            if ($actual !== $expected) {
                throw new RuntimeException('03C-04D HOLD: current database inventory differs from incident snapshot.');
            }
        }
    }

    public static function assertIncidentFingerprint(string $fingerprint, int $rows): void
    {
        if ($fingerprint !== self::INCIDENT_SHA || $rows !== 46) {
            throw new RuntimeException('03C-04D HOLD: incident data has changed since forensic attestation.');
        }
    }

    public static function assertVirtualFingerprint(string $fingerprint, int $rows): void
    {
        if ($fingerprint !== self::BASELINE_SHA || $rows !== 43) {
            throw new RuntimeException('03C-04D HOLD: excluding three fixtures does not reproduce original 43-row baseline.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/**
 * Read-only migration onboarding diagnostic for the 18 legacy schema files.
 * No CREATE/ALTER/DROP, no writes to the migrations ledger.
 * Table-name checks are only a first gate, NOT a full schema equivalence proof.
 */
final class LegacySchemaPreflight extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'db:legacy-preflight';
    protected $description = 'Read-only check of inherited ERP schema and CI4 migration history.';

    /** Last migration inherited from backend_baseline; M11 and newer migrations are outside this legacy check. */
    private const LAST_INHERITED_MIGRATION = '2026-10-15-000018';

    /** Tables created by legacy migrations 001-008 and 011; excludes Shield tables. */
    private const TABLES = [
        'provinces', 'wards', 'organizations', 'contacts',
        'departments', 'positions', 'employees', 'employee_assignments',
        'lifecycles', 'workflow_states', 'lifecycle_states', 'lifecycle_transitions',
        'resources', 'resource_actions', 'auto_numbering_series',
        'auto_numbering_counters', 'groups', 'group_permissions', 'policies',
        'policy_actions', 'authorization_logs',
        'product_groups', 'product_uoms', 'products', 'product_uom_conversions',
        'product_combo_items', 'price_lists', 'product_price_list_items',
        'import_jobs', 'import_job_rows', 'configuration_audit_events',
    ];

    /** @param list<string> $params */
    public function run(array $params): void
    {
        if ($params !== []) {
            throw new RuntimeException('Usage: php spark db:legacy-preflight (read-only, no arguments).');
        }

        $db = db_connect();
        $dbInfo = $db->query('SELECT DATABASE() AS db_name');
        if ($dbInfo === false || ($database = (string) ($dbInfo->getRowArray()['db_name'] ?? '')) === '') {
            throw new RuntimeException('No active MariaDB database configured.');
        }
        $prefix = (string) $db->DBPrefix;
        CLI::write('Database: ' . $database . ' | configured DBPrefix: ' . var_export($prefix, true));
        if ($prefix !== 'dinktech_') {
            throw new RuntimeException('Configured DBPrefix differs from the existing ERP physical prefix dinktech_; verify connection before any migration.');
        }

        $rowsResult = $db->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$database],
        );
        if ($rowsResult === false) {
            throw new RuntimeException('Unable to read database table metadata.');
        }
        $tables = [];
        foreach ($rowsResult->getResultArray() as $row) {
            $tables[(string) $row['TABLE_NAME']] = $row;
        }

        $found = 0;
        $missing = [];
        $notInnoDb = [];
        foreach (self::TABLES as $logical) {
            $physical = $db->prefixTable($logical);
            if (! isset($tables[$physical])) {
                $missing[] = $physical;
                continue;
            }
            $found++;
            if (strtoupper((string) ($tables[$physical]['ENGINE'] ?? '')) !== 'INNODB') {
                $notInnoDb[] = $physical;
            }
        }
        CLI::write('Business tables: ' . $found . '/' . count(self::TABLES) . ' found.');
        if ($missing !== []) {
            CLI::write('Missing tables: ' . implode(', ', $missing), 'yellow');
        }
        if ($notInnoDb !== []) {
            CLI::write('WARN: non-InnoDB tables: ' . implode(', ', $notInnoDb), 'yellow');
        }

        $ledger = $db->prefixTable((string) config('Migrations')->table);
        $recorded = [];
        if (isset($tables[$ledger])) {
            $name = $this->quoteIdentifier($ledger);
            $history = $db->query('SELECT version, class, namespace FROM ' . $name);
            if ($history === false) {
                throw new RuntimeException('Existing migrations ledger cannot be read; investigate before migrate.');
            }
            foreach ($history->getResultArray() as $row) {
                $recorded[(string) ($row['version'] ?? '')][] = $row;
            }
            CLI::write('Migration history table present: ' . $ledger);
        } else {
            CLI::write('Migration history table absent: ' . $ledger, 'yellow');
        }

        $files = glob(APPPATH . 'Database/Migrations/*.php') ?: [];
        $ported = [];
        $pending = [];
        foreach ($files as $file) {
            if (! preg_match('/\A(\d{4}-\d{2}-\d{2}-\d{6})_[A-Za-z][A-Za-z0-9]*\.php\z/D', basename($file), $m)) {
                continue;
            }
            // Only verify the original 18 inherited migrations. New feature migrations
            // are checked by `php spark migrate:status`, not this onboarding diagnostic.
            if (strcmp($m[1], self::LAST_INHERITED_MIGRATION) > 0) {
                continue;
            }
            $ported[] = basename($file);
            $expectedClass = substr(basename($file), strlen($m[1]) + 1, -4);
            $matches = false;
            foreach ($recorded[$m[1]] ?? [] as $row) {
                $rawClass = (string) ($row['class'] ?? '');
                $recordedClass = basename(str_replace('\\', '/', $rawClass));
                $recordedNamespace = (string) ($row['namespace'] ?? '');
                if ($recordedClass === $expectedClass
                    && ($recordedNamespace === 'App' || str_starts_with($recordedNamespace, 'App\\')
                        || str_starts_with($rawClass, 'App\\'))) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                $pending[] = basename($file);
            }
        }
        sort($ported);
        sort($pending);
        CLI::write('Source legacy migrations: ' . count($ported) . ' | versions absent from ledger: ' . count($pending));
        if (count($ported) !== 18) {
            throw new RuntimeException('Expected exactly 18 inherited migrations; check source inventory.');
        }

        if ($found === 0 && count($pending) === count($ported)) {
            CLI::write('EMPTY BUSINESS SCHEMA: run migrations only on a deliberately disposable database, with Shield installed.', 'yellow');
        } elseif ($found === count(self::TABLES) && $pending === []) {
            CLI::write('PRELIMINARY READY: inherited tables and version rows present; review feature migrations via php spark migrate:status.', 'green');
        } elseif ($found === count(self::TABLES) && $pending !== []) {
            throw new RuntimeException('STOP: existing business tables but inherited migration versions missing. Reconcile schema/history first.');
        } else {
            throw new RuntimeException('STOP: partial schema or inconsistent migration history. Do not run php spark migrate.');
        }
        CLI::write('READ ONLY. No migration history was changed and no DDL was executed.');
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/D', $identifier) !== 1) {
            throw new RuntimeException('Unexpected migration history table identifier.');
        }
        return '`' . $identifier . '`';
    }
}

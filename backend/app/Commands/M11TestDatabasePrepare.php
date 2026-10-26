<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Tests\M11\Support\M11DatabaseGuard;
use Tests\M11\Support\M11TestSchemaPlan;


/** Opt-in test-only DDL. Never runs as part of PHPUnit RUN or against the ERP app DB. */
final class M11TestDatabasePrepare extends BaseCommand
{
    protected $group = 'DinkERP Tests';
    protected $name = 'm11:test-db-prepare';
    protected $description = 'Prepare M11 migrations on the verified erp_test database ONLY.';

    /** @param array<array-key, mixed> $params */
    public function run(array $params): void
    {
        if (count($params) !== 1 || ! in_array('CONFIRM_ERP_TEST', $params, true)) {
            throw new RuntimeException('Usage: php spark m11:test-db-prepare CONFIRM_ERP_TEST (testing environment only).');
        }
        // These test-only helpers are loaded only for this explicit CLI command.
        require_once ROOTPATH . 'tests/m11/Support/M11DatabaseGuard.php';
        require_once ROOTPATH . 'tests/m11/Support/M11TestSchemaPlan.php';
        // The safety guard verifies ENVIRONMENT, database.tests.*, DATABASE(),
        // CURRENT_USER() and MariaDB before any DDL is attempted.
        $db = M11DatabaseGuard::connectTarget();
        try {
            $rows = $db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')
                ->getResultArray();
            $engines = [];
            foreach ($rows as $row) {
                $engines[(string) $row['TABLE_NAME']] = (string) ($row['ENGINE'] ?? '');
            }
            $prefix = (string) $db->DBPrefix;
            $ledger = $db->prefixTable((string) config('Migrations')->table);
            if (strtoupper((string) ($engines[$ledger] ?? '')) !== 'INNODB') {
                throw new RuntimeException('erp_test needs an existing InnoDB migrations ledger and legacy schema before preparing M11.');
            }
            if (preg_match('/\A[A-Za-z0-9_]+\z/D', $ledger) !== 1) {
                throw new RuntimeException('Unsafe migrations ledger table name.');
            }
            $rows = $db->query('SELECT version FROM `' . $ledger . '` WHERE namespace = ? AND `group` = ?',
                ['App', 'tests'])->getResultArray();
            $recorded = [];
            foreach ($rows as $row) {
                $recorded[(string) $row['version']] = true;
            }

            // No backfilling old ledgers, no adoption of pre-existing partial tables.
            $legacy = [];
            $sourceVersions = [];
            foreach (glob(APPPATH . 'Database/Migrations/*.php') ?: [] as $file) {
                if (preg_match('/\A(\d{4}-\d{2}-\d{2}-\d{6})_[A-Za-z][A-Za-z0-9]*\.php\z/D',
                    basename($file), $match) !== 1) {
                    continue;
                }
                $sourceVersions[$match[1]] = true;
                if (strcmp($match[1], '2026-10-15-000018') <= 0) {
                    $legacy[$match[1]] = true;
                }
            }
            $expectedSourceVersions = $legacy + array_fill_keys(array_keys(M11TestSchemaPlan::TABLES_BY_MIGRATION), true);
            if (count($sourceVersions) !== 22 || array_diff_key($sourceVersions, $expectedSourceVersions) !== []) {
                throw new RuntimeException('M11 test schema preparer only supports the exact 001-022 App migration inventory.');
            }
            $missingLegacy = array_diff_key($legacy, $recorded);
            if (count($legacy) !== 18 || $missingLegacy !== []) {
                throw new RuntimeException('erp_test must first have all 18 inherited App migrations recorded in its tests ledger. '
                    . 'Missing: ' . implode(', ', array_keys($missingLegacy)));
            }
            foreach (['users', 'auth_permissions_users', 'provinces'] as $logical) {
                $physical = $prefix . $logical;
                if (strtoupper((string) ($engines[$physical] ?? '')) !== 'INNODB') {
                    throw new RuntimeException('erp_test is missing required legacy/Shield InnoDB table: ' . $physical);
                }
            }
            foreach (['2026-10-26-000019_CreateNotificationTables.php',
                '2026-10-26-000020_CreateWorkflowNotificationAssignments.php',
                '2026-10-26-000021_CreateNotificationEmailDeliveries.php',
                '2026-10-26-000022_CreateNotificationPushTables.php'] as $migrationFile) {
                if (! is_file(APPPATH . 'Database/Migrations/' . $migrationFile)) {
                    throw new RuntimeException('Missing M11 migration source: ' . $migrationFile);
                }
            }
            $pending = M11TestSchemaPlan::pending($recorded, $engines, $prefix);
            CLI::write('Verified: database.tests = erp_test / erp_test_runner / ' . $prefix, 'green');
            if ($pending === []) {
                CLI::write('M11 test schema READY: Migrations 019-022 already present. No DDL.', 'green');
                return;
            }
            CLI::write('M11 test schema pending: ' . implode(', ', $pending), 'yellow');
            CLI::write('Applying ONLY missing App migrations using CodeIgniter tests group.');

            // The ledger was verified to contain 001-018 for this exact group.
            // Therefore latest('tests') can only run the pending App 019-022 files.
            // MariaDB DDL is not transactional: a partial failure requires manual
            // schema/ledger reconciliation, never an automatic rerun.
            $runner = service('migrations');
            $runner->setNamespace('App');
            try {
                if (! $runner->latest('tests')) {
                    throw new RuntimeException('CodeIgniter migration runner did not complete.');
                }
            } catch (\Throwable $e) {
                throw new RuntimeException('M11 test-only DDL failed. Check erp_test-only DDL permissions and '
                    . 'inspect partial tables/ledger before retrying. Cause: ' . $e->getMessage(), 0, $e);
            }
            // A successful migration return is not enough: confirm the exact
            // test ledger and all nine InnoDB tables before declaring READY.
            $postRecorded = [];
            foreach ($db->query('SELECT version FROM `' . $ledger . '` WHERE namespace = ? AND `group` = ?',
                ['App', 'tests'])->getResultArray() as $row) {
                $postRecorded[(string) $row['version']] = true;
            }
            $postEngines = [];
            foreach ($db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')
                ->getResultArray() as $row) {
                $postEngines[(string) $row['TABLE_NAME']] = (string) ($row['ENGINE'] ?? '');
            }
            if (M11TestSchemaPlan::pending($postRecorded, $postEngines, $prefix) !== []) {
                throw new RuntimeException('M11 test schema remains incomplete after migration. STOP.');
            }
            CLI::write('M11 tests schema READY: 019-022 recorded; all nine tables are InnoDB.', 'green');
        } finally {
            $db->close();
        }
    }
}

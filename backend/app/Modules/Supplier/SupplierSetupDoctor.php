<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Read-only Supplier schema/catalog diagnostic on the currently selected CI4 DB.
 * A PASS confirms database structure and catalog, NOT PBAC or HTTP acceptance.
 */
final class SupplierSetupDoctor
{
    private const MIGRATION = '2026-10-30-000028';

    private const REQUIRED_COLUMNS = [
        'suppliers' => [
            'id', 'group_organization_id', 'supplier_code', 'name', 'supplier_type',
            'supplier_group_code', 'phone', 'email', 'tax_code', 'address',
            'province_id', 'ward_id', 'payment_term_code', 'all_companies',
            'status', 'row_version', 'bank_ciphertext', 'bank_nonce', 'bank_tag',
            'bank_key_version', 'bank_last4', 'created_by', 'updated_by',
            'created_at', 'updated_at',
        ],
        'supplier_company_usages' => ['supplier_id', 'company_organization_id'],
    ];

    public function __construct(private readonly BaseConnection $db) {}

    /**
     * @return array{status:string,read_only:bool,connection:array<string,string>,checks:list<array{label:string,pass:bool,detail:string}>}
     */
    public function inspect(): array
    {
        $report = [
            'status' => 'FAIL',
            'read_only' => true,
            'connection' => ['environment' => defined('ENVIRONMENT') ? (string) ENVIRONMENT : 'unknown',
                'schema' => 'unknown', 'server' => 'unknown', 'port' => 'unknown',
                'prefix' => (string) $this->db->DBPrefix],
            'checks' => [],
        ];

        try {
            $row = $this->db->query('SELECT DATABASE() AS db_name, @@hostname AS db_server, @@port AS db_port')
                ->getRowArray();
            $schema = (string) ($row['db_name'] ?? '');
            $report['connection']['schema'] = $schema === '' ? 'unknown' : $schema;
            $report['connection']['server'] = (string) ($row['db_server'] ?? 'unknown');
            $report['connection']['port'] = (string) ($row['db_port'] ?? 'unknown');
            $this->add($report, 'Connection', $schema !== '' && $this->db->DBPrefix === 'dinktech_',
                'Selected schema and expected dinktech_ prefix');

            if ($schema === '' || $this->db->DBPrefix !== 'dinktech_') {
                $this->add($report, 'Schema/catalog', false, 'STOP: verify the active DB connection/prefix');
                return $report;
            }

            // Two entity tables plus catalog and CI4 migration ledger. Never read bank values.
            $required = ['migrations', 'suppliers', 'supplier_company_usages', 'resources',
                'resource_actions', 'lifecycles', 'lifecycle_states', 'workflow_states'];
            $missing = [];
            foreach ($required as $name) {
                if (! $this->db->tableExists($name)) $missing[] = $name;
            }
            $this->add($report, 'Required tables', $missing === [],
                $missing === [] ? '8/8 present' : 'Missing: ' . implode(', ', $missing));
            if ($missing !== []) return $report;

            // Check the actual CI4 ledger, not just presence of matching table names.
            $history = $this->db->table('migrations')->select('version, class, namespace')
                ->where('version', self::MIGRATION)->get()->getResultArray();
            $applied = 0;
            foreach ($history as $entry) {
                $class = basename(str_replace('\\', '/', (string) ($entry['class'] ?? '')));
                $namespace = (string) ($entry['namespace'] ?? '');
                if ($class === 'CreateSupplierCoreTables'
                    && ($namespace === 'App' || str_starts_with($namespace, 'App\\'))) ++$applied;
            }
            $this->add($report, 'Migration 000028', $applied === 1,
                $applied === 1 ? 'Recorded in CI4 migrations ledger' : 'Missing/ambiguous ledger entry');

            // One metadata-only information_schema read replaces manual DESCRIBE/DDL queries.
            $names = array_keys(self::REQUIRED_COLUMNS);
            $metadata = $this->db->query(
                'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)',
                [$schema, $this->db->prefixTable($names[0]), $this->db->prefixTable($names[1])],
            )->getResultArray();
            $columns = [];
            foreach ($metadata as $column) {
                $columns[(string) $column['TABLE_NAME']][(string) $column['COLUMN_NAME']] = true;
            }
            $engineRows = $this->db->query(
                'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)',
                [$schema, $this->db->prefixTable($names[0]), $this->db->prefixTable($names[1])],
            )->getResultArray();
            $engines = [];
            foreach ($engineRows as $entry) {
                $engines[(string) $entry['TABLE_NAME']] = strtoupper((string) $entry['ENGINE']);
            }
            foreach (self::REQUIRED_COLUMNS as $logical => $expected) {
                $physical = $this->db->prefixTable($logical);
                $missingColumns = array_values(array_filter($expected,
                    static fn(string $name): bool => !isset($columns[$physical][$name])));
                $this->add($report, 'Schema ' . $logical, $missingColumns === [],
                    $missingColumns === [] ? count($expected) . ' required columns found'
                        : 'Missing: ' . implode(', ', $missingColumns));
                $this->add($report, 'Engine ' . $logical, ($engines[$physical] ?? '') === 'INNODB',
                    ($engines[$physical] ?? 'missing') . ' (requires InnoDB)');
            }

            $supplierCount = $this->db->table('suppliers')->countAllResults();
            $usageCount = $this->db->table('supplier_company_usages')->countAllResults();
            $this->add($report, 'Business row counts', true,
                'suppliers=' . $supplierCount . ', company_usages=' . $usageCount . ' (informational)');

            $resources = $this->db->table('resources')->select('id,type,domain,status,lifecycle_id')
                ->whereIn('type', ['supplier', 'supplier_contact', 'customer_contact'])
                ->get()->getResultArray();
            $byType = [];
            foreach ($resources as $record) $byType[(string) $record['type']][] = $record;

            $customerId = null;
            try {
                $customerId = SupplierCatalogPolicy::contactLifecycleId($byType['customer_contact'] ?? []);
                $this->add($report, 'Contact prerequisite', true, 'customer_contact Lifecycle present');
            } catch (Throwable) {
                $this->add($report, 'Contact prerequisite', false, 'customer_contact Resource/Lifecycle invalid');
            }

            foreach (SupplierCatalogPolicy::ACTIONS as $type => $expected) {
                $matches = $byType[$type] ?? [];
                if (count($matches) !== 1) {
                    $this->add($report, 'Resource ' . $type, false,
                        'Expected exactly one MASTER ACTIVE Resource; found ' . count($matches));
                    continue;
                }
                $resource = $matches[0];
                $lifecycleId = (string) ($resource['lifecycle_id'] ?? '');
                try {
                    SupplierCatalogPolicy::assertResource($resource, $type, $lifecycleId);
                    if ($type === 'supplier_contact' && $lifecycleId !== $customerId) {
                        throw new \RuntimeException('CONTACT_LIFECYCLE_MISMATCH');
                    }
                    $this->add($report, 'Resource ' . $type, true, 'MASTER / ACTIVE');
                } catch (Throwable) {
                    $this->add($report, 'Resource ' . $type, false,
                        'Resource status/domain/Lifecycle binding invalid');
                    continue;
                }

                $actions = $this->db->table('resource_actions')->select('code')
                    ->where('resource_id', $resource['id'])->get()->getResultArray();
                try {
                    SupplierCatalogPolicy::assertActionRows($type, $actions);
                    $actual = array_column($actions, 'code');
                    sort($actual);
                    $wanted = $expected;
                    sort($wanted);
                    $ok = $actual === $wanted;
                } catch (Throwable) {
                    $ok = false;
                }
                $this->add($report, 'Actions ' . $type, $ok,
                    count($actions) . '/' . count($expected) . ' exact action codes');

                $this->checkLifecycle($report, $type, $lifecycleId);
            }
        } catch (Throwable) {
            // Never log raw DB errors, SQL, credentials or business records in this diagnostic.
            $this->add($report, 'Read-only query', false,
                'Query failed; check DB access/schema and application logs');
        }

        $report['status'] = $this->allPassed($report['checks']) ? 'PASS' : 'FAIL';
        return $report;
    }

    /** @param array<string,mixed> $report */
    private function checkLifecycle(array &$report, string $type, string $id): void
    {
        try {
            $lifecycle = $this->db->table('lifecycles')->select('id,code,status')
                ->where('id', $id)->get()->getRowArray() ?? [];
            $states = $this->db->table('lifecycle_states ls')
                ->select('ws.id, ws.code, ws.status, ls.is_initial')
                ->join('workflow_states ws', 'ws.id = ls.state_id')
                ->where('ls.lifecycle_id', $id)->orderBy('ls.sequence', 'ASC')
                ->get()->getResultArray();
            SupplierCatalogPolicy::assertLifecycle($lifecycle, $states);
            $initial = '';
            foreach ($states as $state) {
                if (in_array($state['is_initial'] ?? null, [true, 1, '1'], true)) {
                    $initial = (string) $state['code'];
                }
            }
            $this->add($report, 'Lifecycle ' . $type, true,
                (string) ($lifecycle['code'] ?? '') . ', initial=' . $initial);
        } catch (Throwable) {
            $this->add($report, 'Lifecycle ' . $type, false,
                'Must be ACTIVE with exactly one active initial State');
        }
    }

    /** @param array<string,mixed> $report */
    private function add(array &$report, string $label, bool $pass, string $detail): void
    {
        $report['checks'][] = compact('label', 'pass', 'detail');
    }

    /** @param list<array{pass:bool}> $checks */
    private function allPassed(array $checks): bool
    {
        if ($checks === []) return false;
        foreach ($checks as $item) if (! $item['pass']) return false;
        return true;
    }
}

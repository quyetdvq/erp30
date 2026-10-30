<?php

declare(strict_types=1);

// Offline fake-read DB: no CodeIgniter bootstrap, no MariaDB and no DML methods.
namespace CodeIgniter\Database {
    class BaseConnection
    {
        public string $DBPrefix = 'dinktech_';
    }
}

namespace CodeIgniter\CLI {
    class BaseCommand {}
    class CLI
    {
        public static array $output = [];
        public static function write(string $message, ?string $color = null): void
        {
            self::$output[] = $message;
        }
    }
}

namespace {
    use App\Commands\SupplierDoctor;
    use App\Modules\Supplier\SupplierCatalogPolicy;
    use App\Modules\Supplier\SupplierSetupDoctor;
    use CodeIgniter\CLI\CLI;
    use CodeIgniter\Database\BaseConnection;

    final class FakeResult
    {
        public function __construct(private readonly array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }

    final class FakeBuilder
    {
        private array $filters = [];
        private array $inFilters = [];
        public function __construct(private readonly FakeDb $db, private readonly string $table) {}
        public function select(string $columns): self { return $this; }
        public function where(string $column, mixed $value): self
        {
            $this->filters[basename(str_replace('.', '/', $column))] = (string) $value;
            return $this;
        }
        public function whereIn(string $column, array $values): self
        {
            $this->inFilters[$column] = array_map('strval', $values);
            return $this;
        }
        public function join(string $table, string $on): self { return $this; }
        public function orderBy(string $field, string $direction): self { return $this; }
        private function rows(): array
        {
            $key = explode(' ', $this->table, 2)[0];
            $rows = $this->db->data[$key] ?? [];
            return array_values(array_filter($rows, function (array $row): bool {
                foreach ($this->filters as $column => $expected) {
                    if ((string) ($row[$column] ?? '') !== $expected) return false;
                }
                foreach ($this->inFilters as $column => $expected) {
                    if (!in_array((string) ($row[$column] ?? ''), $expected, true)) return false;
                }
                return true;
            }));
        }
        public function get(): FakeResult { return new FakeResult($this->rows()); }
        public function countAllResults(): int { return count($this->rows()); }
    }

    final class FakeDb extends BaseConnection
    {
        public array $data;
        public array $columns;
        public array $missing = [];
        public bool $throwOnQuery = false;
        public array $engines = ['suppliers' => 'InnoDB', 'supplier_company_usages' => 'InnoDB'];
        public function __construct()
        {
            $this->data = [
                'migrations' => [['version' => '2026-10-30-000028', 'namespace' => 'App',
                    'class' => 'App\\Database\\Migrations\\CreateSupplierCoreTables']],
                'suppliers' => [], 'supplier_company_usages' => [],
                'resources' => [
                    ['id' => '101', 'type' => 'supplier', 'domain' => 'MASTER',
                        'status' => 'ACTIVE', 'lifecycle_id' => '301'],
                    ['id' => '102', 'type' => 'supplier_contact', 'domain' => 'MASTER',
                        'status' => 'ACTIVE', 'lifecycle_id' => '401'],
                    ['id' => '103', 'type' => 'customer_contact', 'domain' => 'MASTER',
                        'status' => 'ACTIVE', 'lifecycle_id' => '401'],
                ],
                'resource_actions' => [],
                'lifecycles' => [
                    ['id' => '301', 'code' => 'SUP_ACTIVE_INACTIVE', 'status' => 'ACTIVE'],
                    ['id' => '401', 'code' => 'CONTACT_LIFECYCLE', 'status' => 'ACTIVE'],
                ],
                'lifecycle_states' => [
                    ['lifecycle_id' => '301', 'id' => '10', 'code' => 'ACTIVE', 'status' => 'ACTIVE', 'is_initial' => '1'],
                    ['lifecycle_id' => '301', 'id' => '11', 'code' => 'INACTIVE', 'status' => 'ACTIVE', 'is_initial' => '0'],
                    ['lifecycle_id' => '401', 'id' => '12', 'code' => 'DRAFT', 'status' => 'ACTIVE', 'is_initial' => '1'],
                    ['lifecycle_id' => '401', 'id' => '13', 'code' => 'ACTIVE', 'status' => 'ACTIVE', 'is_initial' => '0'],
                ],
                'workflow_states' => [],
            ];
            foreach (SupplierCatalogPolicy::ACTIONS as $type => $actions) {
                $id = $type === 'supplier' ? '101' : '102';
                foreach ($actions as $action) $this->data['resource_actions'][] =
                    ['resource_id' => $id, 'code' => $action];
            }
            $this->columns = SupplierSetupDoctorColumns::all();
        }
        public function prefixTable(string $name): string { return $this->DBPrefix . $name; }
        public function tableExists(string $name): bool
        {
            return array_key_exists($name, $this->data) && !in_array($name, $this->missing, true);
        }
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
        public function query(string $sql, array $binds = []): FakeResult
        {
            if ($this->throwOnQuery) throw new \RuntimeException('PASSWORD=must-not-leak');
            if (str_contains($sql, 'SELECT DATABASE()')) {
                return new FakeResult([['db_name' => 'erp_dev', 'db_server' => 'db-dev', 'db_port' => 3306]]);
            }
            if (str_contains($sql, 'information_schema.TABLES')) {
                $rows = [];
                foreach ($this->engines as $table => $engine) {
                    $rows[] = ['TABLE_NAME' => $this->prefixTable($table), 'ENGINE' => $engine];
                }
                return new FakeResult($rows);
            }
            if (str_contains($sql, 'information_schema.COLUMNS')) {
                $rows = [];
                foreach ($this->columns as $table => $fields) {
                    foreach ($fields as $field) $rows[] = ['TABLE_NAME' => $this->prefixTable($table), 'COLUMN_NAME' => $field];
                }
                return new FakeResult($rows);
            }
            throw new \RuntimeException('Unexpected SQL');
        }
    }

    // Exact fixture columns from migration 000028, not Bank values.
    final class SupplierSetupDoctorColumns
    {
        public static function all(): array
        {
            return [
                'suppliers' => ['id','group_organization_id','supplier_code','name','supplier_type',
                    'supplier_group_code','phone','email','tax_code','address','province_id','ward_id',
                    'payment_term_code','all_companies','status','row_version','bank_ciphertext','bank_nonce',
                    'bank_tag','bank_key_version','bank_last4','created_by','updated_by','created_at','updated_at'],
                'supplier_company_usages' => ['supplier_id', 'company_organization_id'],
            ];
        }
    }

    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierCatalogPolicy.php';
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierSetupDoctor.php';
    require dirname(__DIR__, 2) . '/app/Commands/SupplierDoctor.php';
    $GLOBALS['fakeDB'] = null;
    function db_connect(): FakeDb { return $GLOBALS['fakeDB']; }

    $passes = 0;
    $check = static function (bool $condition, string $name) use (&$passes): void {
        if (! $condition) throw new \RuntimeException('SUP DOCTOR FAIL: ' . $name);
        ++$passes;
    };
    $probe = static fn(FakeDb $db): array => (new SupplierSetupDoctor($db))->inspect();
    $find = static function (array $report, string $label): array {
        foreach ($report['checks'] as $check) if ($check['label'] === $label) return $check;
        return ['pass' => false, 'detail' => 'missing'];
    };

    $db = new FakeDb();
    $ok = $probe($db);
    $check($ok['status'] === 'PASS' && $ok['read_only'], 'healthy catalog is PASS and read only');
    $check($find($ok, 'Migration 000028')['pass'], 'migration ledger evidence');
    $check($find($ok, 'Actions supplier')['detail'] === '6/6 exact action codes', 'supplier action parity');
    $check($find($ok, 'Actions supplier_contact')['detail'] === '4/4 exact action codes', 'Contact action parity');
    $check(str_contains($find($ok, 'Lifecycle supplier_contact')['detail'], 'initial=DRAFT'), 'non-ACTIVE initial supported');
    $check($ok['connection']['schema'] === 'erp_dev' && $ok['connection']['prefix'] === 'dinktech_', 'active DB identity shown');

    $bad = new FakeDb(); unset($bad->data['migrations'][0]);
    $check($probe($bad)['status'] === 'FAIL', 'missing migration ledger fails');
    $bad = new FakeDb(); $bad->missing[] = 'supplier_company_usages';
    $check($probe($bad)['status'] === 'FAIL', 'missing usage table fails');
    $bad = new FakeDb(); $bad->columns['suppliers'] = array_diff($bad->columns['suppliers'], ['bank_tag']);
    $check(! $find($probe($bad), 'Schema suppliers')['pass'], 'partial 000028 columns fail');
    $bad = new FakeDb(); $bad->engines['suppliers'] = 'MyISAM';
    $check(! $find($probe($bad), 'Engine suppliers')['pass'], 'non-InnoDB Supplier table fails');
    $bad = new FakeDb(); $bad->DBPrefix = 'wrong_';
    $check(! $find($probe($bad), 'Connection')['pass'], 'wrong DB prefix fails closed');
    $bad = new FakeDb(); $bad->data['resources'][] = $bad->data['resources'][0];
    $check(! $find($probe($bad), 'Resource supplier')['pass'], 'duplicate resource fails');
    $bad = new FakeDb(); $bad->data['resources'][0]['domain'] = 'SYSTEM';
    $check(! $find($probe($bad), 'Resource supplier')['pass'], 'wrong domain fails');
    $bad = new FakeDb(); array_pop($bad->data['resource_actions']);
    $check(! $find($probe($bad), 'Actions supplier_contact')['pass'], 'missing Action fails');
    $bad = new FakeDb(); $bad->data['resource_actions'][] = ['resource_id' => '101', 'code' => 'unknown'];
    $check(! $find($probe($bad), 'Actions supplier')['pass'], 'unexpected Action fails');
    $bad = new FakeDb(); $bad->data['resource_actions'][] = $bad->data['resource_actions'][0];
    $check(! $find($probe($bad), 'Actions supplier')['pass'], 'duplicate Action fails');
    $bad = new FakeDb(); $bad->data['lifecycle_states'][0]['is_initial'] = '0';
    $check(! $find($probe($bad), 'Lifecycle supplier')['pass'], 'missing initial state fails');
    $bad = new FakeDb(); $bad->data['resources'][1]['lifecycle_id'] = '301';
    $check(! $find($probe($bad), 'Resource supplier_contact')['pass'], 'Contact Lifecycle mismatch fails');
    $bad = new FakeDb(); $bad->throwOnQuery = true;
    $failure = $probe($bad);
    $check($failure['status'] === 'FAIL', 'DB query errors fail closed');
    $check(! str_contains(json_encode($failure, JSON_THROW_ON_ERROR), 'PASSWORD'), 'DB error secrets suppressed');

    $db = new FakeDb(); $GLOBALS['fakeDB'] = $db;
    (new SupplierDoctor())->run([]);
    $check(str_contains(implode('\n', CLI::$output), 'RESULT: PASS'), 'CLI presents PASS');
    $check(str_contains(implode('\n', CLI::$output), 'No DB writes'), 'CLI explicitly communicates limits');
    $GLOBALS['fakeDB'] = new FakeDb(); $GLOBALS['fakeDB']->missing[] = 'suppliers';
    try {
        (new SupplierDoctor())->run([]);
        $check(false, 'CLI fails on blocked schema');
    } catch (\RuntimeException $error) {
        $check($error->getMessage() === 'SUP_DOCTOR_SCHEMA_OR_CATALOG_FAILED', 'CLI nonzero exception on FAIL');
    }
    $source = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierSetupDoctor.php');
    $check(is_string($source) && !preg_match('/->(insert|update|delete|replace|transBegin|transCommit|query\s*\(\s*[\'\"]\s*(?:INSERT|UPDATE|DELETE|ALTER|CREATE))/i', $source), 'inspector has no write path');

    echo "SUP SETUP DOCTOR FIXTURE: {$passes}/{$passes} PASS (fake connection; no MariaDB)\n";
}

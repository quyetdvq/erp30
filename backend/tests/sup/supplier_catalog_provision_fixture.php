<?php

declare(strict_types=1);

namespace CodeIgniter\Database {
    class BaseConnection {
        public array $data = [];
        public array $backup = [];
        public array $counts = [];
        public bool $started = false;
        public function __construct(array $data) { $this->data = $data; }
        public function tableExists(string $table): bool { return isset($this->data[$table]); }
        public function table(string $table): Builder { return new Builder($this, explode(' ', $table)[0]); }
        public function transBegin(): bool { $this->backup = $this->data; $this->started = true; return true; }
        public function transStatus(): bool { return true; }
        public function transCommit(): bool { $this->started = false; return true; }
        public function transRollback(): bool { $this->data = $this->backup; $this->started = false; return true; }
        public function resetTransStatus(): void {}
        public function insertID(): int { return $this->counts['last'] ?? 0; }
    }
    class Result {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    class Builder {
        private array $predicates = [];
        private ?array $selected = null;
        private bool $joinStates = false;
        public function __construct(private BaseConnection $db, private string $name) {}
        public function where(string $column, mixed $value): self { $this->predicates[] = [preg_replace('/^.*\./', '', $column), $value, false]; return $this; }
        public function whereIn(string $column, array $value): self { $this->predicates[] = [preg_replace('/^.*\./', '', $column), $value, true]; return $this; }
        public function select(string $fields): self { $this->selected = explode(',', $fields); return $this; }
        public function orderBy(string $column, string $direction): self { return $this; }
        public function join(string $table, string $on): self { $this->joinStates = true; return $this; }
        public function get(): Result {
            $rows = $this->db->data[$this->name] ?? [];
            if ($this->joinStates && $this->name === 'lifecycle_states') {
                $joins = [];
                foreach ($rows as $row) {
                    foreach ($this->db->data['workflow_states'] ?? [] as $state) {
                        if ((string)$state['id'] !== (string)$row['state_id']) continue;
                        $joins[] = ['id' => $state['id'], 'code' => $state['code'], 'status' => $state['status'], 'is_initial' => $row['is_initial'], 'lifecycle_id' => $row['lifecycle_id']];
                    }
                }
                $rows = $joins;
            }
            foreach ($this->predicates as [$key, $value, $array]) {
                $rows = array_values(array_filter($rows, static fn($r) => $array
                    ? in_array((string)($r[$key] ?? ''), array_map('strval', $value), true)
                    : (string)($r[$key] ?? '') === (string)$value));
            }
            return new Result($rows);
        }
        public function countAllResults(): int { return count($this->get()->getResultArray()); }
        public function insert(array $data): bool {
            $this->db->counts[$this->name] = ($this->db->counts[$this->name] ?? 200) + 1;
            $id = $this->db->counts[$this->name];
            if (!isset($data['id'])) $data['id'] = $id;
            $this->db->data[$this->name][] = $data;
            $this->db->counts['last'] = $id;
            return true;
        }
    }
}

namespace App\Modules\Access {
    class ResourceStateRepository {
        public function __construct(private \CodeIgniter\Database\BaseConnection $db) {}
        public function definitionFor(string $type): array {
            $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            if ($row === null) throw new \RuntimeException('missing resource');
            return $row;
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierCatalogPolicy.php';
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierCatalogProvisioner.php';
    use App\Modules\Supplier\SupplierCatalogProvisioner;
    use CodeIgniter\Database\BaseConnection;
    function config(string $name): object { return (object)['tables' => ['permissions_users' => 'auth_permissions_users']]; }
    $pass = 0;
    function check(bool $yes, string $name): void { global $pass; if (!$yes) throw new \RuntimeException('FAIL: ' . $name); ++$pass; }
    function denied(callable $run, string $name): void {
        try { $run(); } catch (\RuntimeException) { check(true, $name); return; }
        throw new \RuntimeException('FAIL: expected denial ' . $name);
    }
    function dataset(): array {
        $rows = ['resources' => [], 'resource_actions' => [], 'lifecycles' => [],
            'lifecycle_states' => [], 'lifecycle_transitions' => [], 'suppliers' => [],
            'supplier_company_usages' => [], 'auth_permissions_users' => [],
            'workflow_states' => [
                ['id' => '1', 'code' => 'ACTIVE', 'status' => 'ACTIVE'],
                ['id' => '2', 'code' => 'INACTIVE', 'status' => 'ACTIVE'],
                ['id' => '3', 'code' => 'DRAFT', 'status' => 'ACTIVE'],
            ] ];
        $rows['lifecycles'][] = ['id' => '9', 'status' => 'ACTIVE', 'code' => 'P1_CONTACT'];
        $rows['lifecycle_states'][] = ['lifecycle_id' => '9', 'state_id' => '1', 'sequence' => 1, 'is_initial' => 1];
        $rows['lifecycle_states'][] = ['lifecycle_id' => '9', 'state_id' => '2', 'sequence' => 2, 'is_initial' => 0];
        foreach ([['organization_contact','ORGANIZATION'],['employee_contact','ORGANIZATION'],['customer_contact','MASTER']] as $i => [$type,$domain]) {
            $rows['resources'][] = ['id' => (string)($i+1), 'type' => $type, 'domain' => $domain, 'status' => 'ACTIVE', 'lifecycle_id' => '9'];
        }
        return $rows;
    }
    $db = new BaseConnection(dataset());
    $core = new SupplierCatalogProvisioner($db);
    $core->provision();
    check(count($db->data['resources']) === 5, 'two resources created');
    check(count($db->data['resource_actions']) === 10, 'ten actions created');
    check(count($db->data['lifecycles']) === 2, 'default lifecycle created');
    check(count($db->data['lifecycle_states']) === 4, 'two default state members created');
    check(count($db->data['lifecycle_transitions']) === 2, 'default transitions created');
    check($db->data['auth_permissions_users'] === [], 'no direct grants');
    $before = $db->data;
    $core->provision();
    check($db->data === $before, 'idempotent on repeat');
    $custom = dataset();
    $custom['lifecycles'][] = ['id' => '20', 'status' => 'ACTIVE', 'code' => 'CUSTOM_SUP'];
    $custom['lifecycle_states'][] = ['lifecycle_id' => '20', 'state_id' => '3', 'is_initial' => 1];
    $custom['lifecycles'][] = ['id' => '21', 'status' => 'ACTIVE', 'code' => 'NOT_SUP'];
    $custom['resources'][] = ['id' => '111', 'type' => 'supplier', 'domain' => 'MASTER', 'status' => 'ACTIVE', 'lifecycle_id' => '20'];
    $db2 = new BaseConnection($custom);
    (new SupplierCatalogProvisioner($db2))->provision();
    check(count($db2->data['lifecycles']) === 3, 'custom lifecycle preserved, default not created');
    check($db2->table('resources')->where('type', 'supplier')->get()->getRowArray()['lifecycle_id'] === '20', 'manual Supplier lifecycle binding retained');
    $stale = dataset();
    $stale['auth_permissions_users'][] = ['permission' => 'supplier.view'];
    $db3 = new BaseConnection($stale);
    denied(fn() => (new SupplierCatalogProvisioner($db3))->provision(), 'reject stale direct Shield grant');
    check($db3->data === $stale, 'stale grant rollback all inserts');
    // Organization and Employee may each be assigned a separate Lifecycle.
    // Supplier Contact must follow the existing Customer Contact configuration.
    $differentContacts = dataset();
    $differentContacts['lifecycles'][] = ['id' => '25', 'status' => 'ACTIVE', 'code' => 'OTHER_CONTACT'];
    $differentContacts['lifecycle_states'][] = ['lifecycle_id' => '25', 'state_id' => '1', 'sequence' => 1, 'is_initial' => 1];
    $differentContacts['resources'][0]['lifecycle_id'] = '25';
    $db4 = new BaseConnection($differentContacts);
    (new SupplierCatalogProvisioner($db4))->provision();
    check($db4->table('resources')->where('type', 'supplier_contact')->get()->getRowArray()['lifecycle_id'] === '9',
        'Supplier Contact uses Customer Contact lifecycle despite separate Organization Contact');
    $unrelatedInactive = dataset();
    $unrelatedInactive['resources'][0]['status'] = 'INACTIVE';
    $dbInactive = new BaseConnection($unrelatedInactive);
    (new SupplierCatalogProvisioner($dbInactive))->provision();
    check($dbInactive->table('resources')->where('type', 'supplier_contact')->get()->getRowArray()['lifecycle_id'] === '9',
        'Unrelated Organization Contact inactive does not block Supplier Contact');
    $badContact = dataset();
    $badContact['resources'][2]['status'] = 'INACTIVE';
    $dbBad = new BaseConnection($badContact);
    denied(fn() => (new SupplierCatalogProvisioner($dbBad))->provision(), 'inactive Customer Contact resource');
    check($dbBad->data === $badContact, 'invalid Customer Contact binding rolls back');
    $noSchema = dataset(); unset($noSchema['suppliers']);
    denied(fn() => (new SupplierCatalogProvisioner(new BaseConnection($noSchema)))->provision(), 'requires migrated schema');
    echo "SUP-T01B PROVISION FIXTURE: {$pass}/{$pass} PASS (fake connection, NOT MariaDB)\n";
}

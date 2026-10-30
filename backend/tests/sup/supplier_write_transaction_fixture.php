<?php

declare(strict_types=1);

// Isolated in-memory transactional SQL fixture; never connects to MariaDB.
namespace CodeIgniter\Database {
    final class BaseConnection
    {
        public int $transDepth = 0;
        /** @var array<string,list<array<string,mixed>>> */
        public array $tables = [];
        public bool $allow = true;
        public bool $view = true;
        public bool $branching = false;
        public string $initial = 'DRAFT';
        public bool $failUsageInsert = false;
        public bool $failCounterWrite = false;
        public bool $failSupplierDuplicate = false;
        public bool $forceCasZero = false;
        public bool $failHistoryInsert = false;
        public int $lastAffected = 0;
        public string $lastInsertId = '0';
        public array $logs = [];
        public array $lastError = [];
        private array $backup = [];
        public function table(string $table): \SupT04Builder { return new \SupT04Builder($this, $table); }
        public function escape(string $text): string { return "'" . str_replace("'", "''", $text) . "'"; }
        public function getDatabase(): string { return 'erp_test'; }
        public function transBegin(): bool {
            if ($this->transDepth !== 0) throw new \RuntimeException('Unexpected nested tx');
            $this->backup = unserialize(serialize($this->tables));
            $this->transDepth = 1;
            $this->logs[] = 'BEGIN';
            return true;
        }
        public function transCommit(): bool {
            if ($this->transDepth !== 1) throw new \RuntimeException('Commit outside tx');
            $this->transDepth = 0;
            $this->logs[] = 'COMMIT';
            return true;
        }
        public function transRollback(): bool {
            if ($this->transDepth !== 1) throw new \RuntimeException('Rollback outside tx');
            $this->tables = $this->backup;
            $this->transDepth = 0;
            $this->logs[] = 'ROLLBACK';
            return true;
        }
        public function transStatus(): bool { return true; }
        public function resetTransStatus(): void {}
        public function insertID(): string { return $this->lastInsertId; }
        public function affectedRows(): int { return $this->lastAffected; }
        public function error(): array { return $this->lastError; }
        public function query(string $sql): \SupT04Result|false {
            if (str_contains($sql, 'GET_LOCK(')) {
                $this->logs[] = 'GET_LOCK';
                return new \SupT04Result([['acquired'=>1]]);
            }
            if (str_contains($sql, 'RELEASE_LOCK(')) {
                $this->logs[] = 'RELEASE_LOCK';
                return new \SupT04Result([['released'=>1]]);
            }
            if (str_starts_with($sql, 'FAKE_SELECT:') && str_ends_with($sql, ' FOR UPDATE')) {
                if ($this->transDepth !== 1) throw new \RuntimeException('FOR UPDATE outside tx');
                $payload = json_decode(base64_decode(substr($sql, 12, -11), true), true, 512, JSON_THROW_ON_ERROR);
                $this->logs[] = 'LOCK:' . $payload['table'];
                return new \SupT04Result($this->select($payload));
            }
            throw new \RuntimeException('Unreviewed fixture query');
        }
        /** @param array<string,mixed> $payload @return list<array<string,mixed>> */
        public function select(array $payload): array {
            $rows = array_values(array_filter($this->tables[$payload['table']] ?? [], static function (array $row) use ($payload): bool {
                foreach ($payload['where_in'] as $name => $values) {
                    if (!in_array((string)($row[$name] ?? ''), array_map('strval', $values), true)) return false;
                }
                foreach ($payload['where'] as $name => $value) {
                    if ($value === null) {
                        if (($row[$name] ?? null) !== null) return false;
                    } elseif ((string)($row[$name] ?? '') !== (string)$value) return false;
                }
                return true;
            }));
            if ($payload['order'] !== null) {
                $field = $payload['order'];
                usort($rows, static fn (array $a, array $b) => (int)($a[$field] ?? 0) <=> (int)($b[$field] ?? 0));
            }
            if ($payload['limit'] !== null) $rows = array_slice($rows, 0, $payload['limit']);
            if ($payload['select'] !== null && $payload['select'] !== '') {
                $cols = array_map('trim', explode(',', $payload['select']));
                $rows = array_map(static fn (array $r): array => array_intersect_key($r, array_fill_keys($cols, true)), $rows);
            }
            return $rows;
        }
    }
}
namespace CodeIgniter\Shield\Entities { class User { public int $id = 12; } }
namespace CodeIgniter\Exceptions { class PageNotFoundException extends \RuntimeException {} }
namespace CodeIgniter\Settings\Config {
    class Services {
        public static function settings(): object {
            return new class { public function get(string $name, ?string $group = null): string { return 'Asia/Ho_Chi_Minh'; } };
        }
    }
}
namespace App\Modules\Access {
    class ResourceStateRepository {
        public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
        public function initialState(string $resource): ?string { return $this->db->initial; }
        public function transitionTarget(string $type, array $old, string $action): string {
            if ($this->db->branching) throw new \UnexpectedValueException('Transition not allowed or ambiguous for current State.');
            return match ($old['status']) {
                'DRAFT' => 'ACTIVE', 'ACTIVE' => 'INACTIVE',
                default => throw new \UnexpectedValueException('Transition not allowed or ambiguous for current State.'),
            };
        }
    }
    class UnifiedAccessService {
        public function __construct(private readonly \CodeIgniter\Shield\Entities\User $user,
            private readonly \CodeIgniter\Database\BaseConnection $db, ?\DateTimeImmutable $instant = null) {
            if ($this->db->transDepth !== 1) throw new \RuntimeException('PBAC snapshot outside tx');
        }
        public function trustedGroupRootId(): string { return '99'; }
        public function canView(string $resource, array $row): bool { return $this->db->view && $row['group_organization_id'] === '99'; }
        public function decideMutation(string $resource, string $action, ?array $old, array $candidate = [], bool $transition = false): array {
            return ['allowed'=>$this->db->allow];
        }
        public function decideTransitionAction(string $resource, string $action, array $row): array {
            return ['allowed'=>$this->db->allow];
        }
    }
}
namespace App\Modules\Access\Query {
    class EffectiveRowActionHints {
        public static function forViewedRow(\App\Modules\Access\UnifiedAccessService $access, string $resource, array $row): array {
            return ['edit'=>true, 'setstate'=>true];
        }
    }
}
namespace App\Shared\Http {
    class RequestMetadata { public static function id(): string { return '00000000-0000-4000-8000-000000000001'; } }
}
namespace {
    $base = dirname(__DIR__, 2) . '/app/';
    foreach ([
        'Exceptions/DomainException.php',
        'Modules/Numbering/Configuration/NumberingRules.php',
        'Modules/Supplier/SupplierRules.php',
        'Modules/Supplier/SupplierNumberingPolicy.php',
        'Modules/Supplier/SupplierNumberIssuer.php',
        'Modules/Supplier/SupplierSql.php',
        'Modules/Supplier/SupplierWritePayload.php',
        'Modules/History/HistoryPolicy.php',
        'Modules/History/HistoryRecorder.php',
        'Modules/Supplier/SupplierWriteService.php',
    ] as $path) require $base . $path;

    use App\Exceptions\DomainException;
    use App\Modules\Supplier\SupplierWriteService;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    final class SupT04Result {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private readonly array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class SupT04Builder {
        /** @var array<string,mixed> */
        private array $where = [];
        private array $whereIn = [];
        private ?string $select = null;
        private ?string $order = null;
        private ?int $limit = null;
        public function __construct(private readonly BaseConnection $db, private readonly string $table) {}
        public function where(string $field, mixed $value): self { $this->where[$field] = $value; return $this; }
        public function whereIn(string $field, array $values): self { $this->whereIn[$field] = $values; return $this; }
        public function select(string $fields): self { $this->select = $fields; return $this; }
        public function orderBy(string $field, string $direction): self { $this->order = $field; return $this; }
        public function limit(int $n): self { $this->limit = $n; return $this; }
        private function payload(): array {
            return ['table'=>$this->table, 'where'=>$this->where, 'where_in'=>$this->whereIn, 'select'=>$this->select,
                'order'=>$this->order, 'limit'=>$this->limit];
        }
        public function getCompiledSelect(): string {
            return 'FAKE_SELECT:' . base64_encode(json_encode($this->payload(), JSON_THROW_ON_ERROR));
        }
        public function get(): SupT04Result { return new \SupT04Result($this->db->select($this->payload())); }
        public function insert(array $row): bool {
            if ($this->db->transDepth !== 1) throw new RuntimeException('INSERT outside tx');
            if ($this->table === 'supplier_company_usages' && $this->db->failUsageInsert) return false;
            if ($this->table === 'auto_numbering_counters' && $this->db->failCounterWrite) return false;
            if ($this->table === 'business_history_events' && $this->db->failHistoryInsert) return false;
            if ($this->table === 'suppliers' && $this->db->failSupplierDuplicate) {
                $this->db->lastError = ['code'=>1062, 'message'=>'Duplicate entry for key uq_supplier_group_code'];
                throw new RuntimeException('uq_supplier_group_code', 1062);
            }
            $rows = $this->db->tables[$this->table] ?? [];
            if ($this->table === 'suppliers') {
                foreach ($rows as $old) if ($old['group_organization_id'] === $row['group_organization_id']
                    && $old['supplier_code'] === $row['supplier_code']) {
                    $this->db->lastError = ['code'=>1062,'message'=>'uq_supplier_group_code'];
                    throw new RuntimeException('uq_supplier_group_code', 1062);
                }
            }
            if (!in_array($this->table, ['supplier_company_usages'], true)) {
                $max = 0;
                foreach ($rows as $old) $max = max($max, (int)($old['id'] ?? 0));
                $row['id'] = (string)($max + 1);
            }
            $rows[] = $row;
            $this->db->tables[$this->table] = $rows;
            if (isset($row['id'])) $this->db->lastInsertId = (string)$row['id'];
            $this->db->lastAffected = 1;
            return true;
        }
        public function update(array $changes): bool {
            if ($this->db->transDepth !== 1) throw new RuntimeException('UPDATE outside tx');
            if ($this->table === 'auto_numbering_counters' && $this->db->failCounterWrite) return false;
            if ($this->table === 'suppliers' && $this->db->forceCasZero) {
                $this->db->lastAffected = 0;
                return true;
            }
            $rows = $this->db->tables[$this->table] ?? [];
            $count = 0;
            foreach ($rows as &$row) {
                $match = true;
                foreach ($this->where as $key => $value) if ((string)($row[$key] ?? '') !== (string)$value) $match = false;
                if ($match) { $row = array_replace($row, $changes); ++$count; }
            }
            unset($row);
            $this->db->tables[$this->table] = $rows;
            $this->db->lastAffected = $count;
            return true;
        }
        public function delete(): bool {
            if ($this->db->transDepth !== 1) throw new RuntimeException('DELETE outside tx');
            $before = count($this->db->tables[$this->table] ?? []);
            $this->db->tables[$this->table] = array_values(array_filter($this->db->tables[$this->table] ?? [], function (array $row): bool {
                foreach ($this->where as $key => $value) if ((string)($row[$key] ?? '') !== (string)$value) return true;
                return false;
            }));
            $this->db->lastAffected = $before - count($this->db->tables[$this->table]);
            return true;
        }
    }

    $assertions = 0;
    function okTx(bool $value, string $label): void {
        global $assertions;
        if (!$value) throw new RuntimeException('SUP-T04 TX FIXTURE FAIL: ' . $label);
        ++$assertions;
    }
    function expectErrorTx(callable $task, string $code): void {
        try { $task(); } catch (DomainException $e) {
            okTx($e->getErrorCode() === $code, 'expected ' . $code . ' got ' . $e->getErrorCode());
            return;
        }
        throw new RuntimeException('SUP-T04 TX FIXTURE expected ' . $code);
    }
    function fixtureTx(bool $auto = false): BaseConnection {
        $db = new BaseConnection();
        $db->tables = [
            'resources'=>[['id'=>'7','domain'=>'MASTER','type'=>'supplier','status'=>'ACTIVE']],
            'organizations'=>[
                ['id'=>'99','type'=>'GROUP','parent_id'=>null,'status'=>'ACTIVE','code'=>'G1'],
                ['id'=>'2','type'=>'COMPANY','parent_id'=>'99','status'=>'ACTIVE'],
                ['id'=>'3','type'=>'COMPANY','parent_id'=>'99','status'=>'ACTIVE'],
                ['id'=>'4','type'=>'COMPANY','parent_id'=>'99','status'=>'INACTIVE'],
                ['id'=>'5','type'=>'COMPANY','parent_id'=>'100','status'=>'ACTIVE'],
            ],
            'provinces'=>[['id'=>'9','status'=>'ACTIVE']],
            'wards'=>[['id'=>'11','province_id'=>'9','status'=>'ACTIVE'],['id'=>'12','province_id'=>'999','status'=>'ACTIVE']],
            'suppliers'=>[], 'supplier_company_usages'=>[], 'business_history_events'=>[],
            'auto_numbering_series'=>$auto ? [[
                'id'=>'11','resource_id'=>'7','series_code'=>'SUP_DEFAULT','status'=>'ACTIVE',
                'is_default'=>1,'scope_type'=>'GROUP', 'pattern'=>'SUP-{RESOURCE}-{SEQ:4}',
                'reset_policy'=>'NEVER','generation_mode'=>'AUTO_IF_EMPTY',
                'start_number'=>1,'increment_by'=>1,
            ]] : [],
            'auto_numbering_counters'=>[],
        ];
        return $db;
    }
    function profileTx(): array {
        return ['name'=>'Alpha','supplier_type'=>'COMPANY','phone'=>'0912345678','address'=>'Street',
            'province_id'=>'9','ward_id'=>'11','all_companies'=>false, 'company_ids'=>['2']];
    }
    function serviceTx(BaseConnection $db): SupplierWriteService { return new SupplierWriteService(new User(), $db); }

    $db = fixtureTx(); $service = serviceTx($db);
    $created = $service->create(profileTx() + ['supplier_code'=>'SUP-01']);
    okTx($created['supplier_code'] === 'SUP-01', 'manual code issued with no default');
    okTx($created['status'] === 'DRAFT', 'dynamic initial DRAFT, never hardcoded ACTIVE');
    okTx((string)$created['row_version'] === '1', 'new Supplier version 1');
    okTx($created['company_ids'] === ['2'], 'company usage returned');
    okTx(count($db->tables['supplier_company_usages']) === 1, 'company usage saved');
    okTx(count($db->tables['business_history_events']) === 1 && $db->tables['business_history_events'][0]['event_type'] === 'CREATE', 'one History CREATE');
    okTx(!array_key_exists('bank_ciphertext', $created) && !array_key_exists('bank_last4', $created), 'no bank data in response');
    okTx($db->transDepth === 0 && end($db->logs) === 'RELEASE_LOCK', 'commit and release advisory lock');
    okTx(count(array_filter($db->logs, static fn(string $s) => $s === 'LOCK:organizations')) === 2,
        'one locking GROUP read and one bulk locking Company read, not N+1');
    $renamed = $service->update('1', ['name'=>'Alpha Two','expected_version'=>1]);
    okTx($renamed['name'] === 'Alpha Two' && (string)$renamed['row_version'] === '2', 'PATCH CAS increments version');
    okTx($renamed['company_ids'] === ['2'] && $renamed['phone'] === '0912345678', 'PATCH preserves usage and other fields');
    okTx(count($db->tables['business_history_events']) === 1, 'DRAFT edit not logged as UPDATE');
    expectErrorTx(fn()=>$service->update('1',['name'=>'stale','expected_version'=>1]), 'SUPPLIER_VERSION_CONFLICT');
    okTx($db->tables['suppliers'][0]['name'] === 'Alpha Two', 'stale PATCH did not overwrite');
    okTx(end($db->logs) === 'RELEASE_LOCK', 'failed PATCH lock released');
    $all = $service->replaceCompanyUsage('1',['all_companies'=>true,'company_ids'=>[],'expected_version'=>2]);
    okTx((string)$all['row_version'] === '3' && (string)$all['all_companies'] === '1', 'replace usage bumps one version');
    okTx($db->tables['supplier_company_usages'] === [], 'all companies has zero allowlist rows');
    expectErrorTx(fn()=>$service->replaceCompanyUsage('1', ['all_companies'=>false,'company_ids'=>['2'],'expected_version'=>2]), 'SUPPLIER_VERSION_CONFLICT');
    $only3 = $service->replaceCompanyUsage('1',['all_companies'=>false,'company_ids'=>['3'],'expected_version'=>3]);
    okTx($only3['company_ids'] === ['3'] && count($db->tables['supplier_company_usages']) === 1, 'list restored atomically');
    $active = $service->setState('1',['status'=>'ACTIVE','expected_version'=>4]);
    okTx($active['status'] === 'ACTIVE' && (string)$active['row_version'] === '5', 'Workflow setstate CAS');
    okTx(count($db->tables['business_history_events']) === 2 && $db->tables['business_history_events'][1]['event_type'] === 'STATE_CHANGE', 'State change History with checkpoint');
    $new = $service->update('1',['phone'=>'0912345678','expected_version'=>5]);
    okTx((string)$new['row_version'] === '6', 'idempotent PATCH still advances version');
    okTx(count($db->tables['business_history_events']) === 2, 'no redundant operational UPDATE');
    $changed = $service->replaceCompanyUsage('1',['all_companies'=>false,'company_ids'=>['2','3'],'expected_version'=>6]);
    okTx((string)$changed['row_version'] === '7', 'operational usage replacement bumps version');
    okTx(end($db->logs) === 'RELEASE_LOCK', 'multi-Company update releases advisory lock');
    okTx($db->tables['business_history_events'][2]['event_type'] === 'UPDATE', 'operational usage event');
    okTx(!str_contains((string)$db->tables['business_history_events'][2]['changes_json'], 'company_organization_id'), 'History does not include Company IDs');
    expectErrorTx(fn()=>$service->setState('1',['status'=>'ACTIVE','expected_version'=>7]), 'SUPPLIER_STATE_TRANSITION_INVALID');
    expectErrorTx(fn()=>$service->setState('1',['status'=>'DRAFT','expected_version'=>7]), 'SUPPLIER_STATE_TRANSITION_INVALID');
    okTx((string)$db->tables['suppliers'][0]['row_version'] === '7', 'illegal workflow is nonmutating');
    $db->branching = true;
    expectErrorTx(fn()=>$service->setState('1',['status'=>'INACTIVE','expected_version'=>7]), 'SUPPLIER_STATE_TRANSITION_INVALID');
    $db->branching = false;
    $inact = $service->setState('1',['status'=>'INACTIVE','expected_version'=>7]);
    okTx($inact['status'] === 'INACTIVE' && (string)$inact['row_version'] === '8', 'unique configured transition works');

    $db = fixtureTx(); $service = serviceTx($db);
    $db->allow = false;
    expectErrorTx(fn()=>$service->create(profileTx()+['supplier_code'=>'SUP-A']), 'AUTH_POLICY_DENIED');
    okTx($db->tables['suppliers'] === [], 'PBAC CREATE deny creates nothing');
    $db->allow = true; $db->view = false;
    try { $service->create(profileTx()+['supplier_code'=>'SUP-A']); throw new RuntimeException('expected view deny'); }
    catch (\CodeIgniter\Exceptions\PageNotFoundException) { okTx($db->tables['suppliers'] === [], 'CREATE without view rolls back'); }
    $db->view = true;
    $service->create(profileTx()+['supplier_code'=>'SUP-A']);
    $db->allow = false;
    expectErrorTx(fn()=>$service->update('1',['name'=>'Denied','expected_version'=>1]), 'AUTH_POLICY_DENIED');
    okTx($db->tables['suppliers'][0]['name'] === 'Alpha', 'PBAC PATCH deny leaves row unchanged');
    $db->allow = true;
    $db->view = false;
    try { $service->update('1',['name'=>'Hidden','expected_version'=>1]); throw new RuntimeException('expected 404'); }
    catch (\CodeIgniter\Exceptions\PageNotFoundException) { okTx($db->tables['suppliers'][0]['name'] === 'Alpha', 'hidden row denied before mutation'); }

    $db = fixtureTx(); $service = serviceTx($db);
    expectErrorTx(fn()=>$service->create(array_replace(profileTx(), ['company_ids'=>['4'],'supplier_code'=>'SUP-A'])), 'SUPPLIER_COMPANY_SCOPE_INVALID');
    expectErrorTx(fn()=>$service->create(array_replace(profileTx(), ['company_ids'=>['5'],'supplier_code'=>'SUP-A'])), 'SUPPLIER_COMPANY_SCOPE_INVALID');
    expectErrorTx(fn()=>$service->create(array_replace(profileTx(), ['ward_id'=>'12','supplier_code'=>'SUP-A'])), 'SUPPLIER_WARD_PROVINCE_MISMATCH');
    okTx($db->tables['suppliers'] === [], 'invalid reference never persists');

    $db = fixtureTx(true); $service = serviceTx($db);
    $auto = $service->create(profileTx());
    okTx($auto['supplier_code'] === 'SUP-SUPPLIER-0001', 'default auto issuer integrated in create tx');
    okTx((int)$db->tables['auto_numbering_counters'][0]['last_value'] === 1, 'issuer counter committed with Supplier');
    expectErrorTx(fn()=>$service->create(profileTx()+['supplier_code'=>'MANUAL-1']), 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
    $db->failUsageInsert = true;
    expectErrorTx(fn()=>$service->create(profileTx()), 'SUPPLIER_PERSISTENCE_FAILED');
    okTx(count($db->tables['suppliers']) === 1, 'child INSERT failure rolls back Supplier');
    okTx((int)$db->tables['auto_numbering_counters'][0]['last_value'] === 1, 'child INSERT failure does not increment counter');
    okTx(count($db->tables['business_history_events']) === 1, 'failed transaction does not emit History');
    $db->failUsageInsert = false; $db->failCounterWrite = true;
    expectErrorTx(fn()=>$service->create(profileTx()), 'NUM_COUNTER_TRANSACTION_FAILED');
    okTx(count($db->tables['suppliers']) === 1 && (int)$db->tables['auto_numbering_counters'][0]['last_value'] === 1, 'counter update failure rolls back Supplier and usage');
    $db->failCounterWrite = false; $db->failHistoryInsert = true;
    try { $service->create(profileTx()); throw new RuntimeException('expected History error'); }
    catch (RuntimeException $e) {
        okTx($e->getMessage() === 'HISTORY_PERSISTENCE_FAILED', 'History failure aborts write');
        okTx(count($db->tables['suppliers']) === 1 && (int)$db->tables['auto_numbering_counters'][0]['last_value'] === 1, 'History failure rolls back counter and Supplier');
    }
    $db->failHistoryInsert = false;
    $db->failSupplierDuplicate = true;
    expectErrorTx(fn()=>$service->create(profileTx()), 'SUPPLIER_CODE_DUPLICATE');
    okTx(count($db->tables['suppliers']) === 1, 'DB 1062 never permits second Supplier');

    $db = fixtureTx(); $service = serviceTx($db);
    $service->create(profileTx()+['supplier_code'=>'SUP-X']);
    $db->failUsageInsert = true;
    expectErrorTx(fn()=>$service->replaceCompanyUsage('1',['all_companies'=>false,'company_ids'=>['3'],'expected_version'=>1]), 'SUPPLIER_PERSISTENCE_FAILED');
    okTx((int)$db->tables['suppliers'][0]['row_version'] === 1, 'failed usage replacement rolls back CAS');
    okTx($db->tables['supplier_company_usages'][0]['company_organization_id'] === '2', 'failed usage replacement restores original rows');
    $db->failUsageInsert = false;
    $db->forceCasZero = true;
    expectErrorTx(fn()=>$service->update('1',['name'=>'Concurrent','expected_version'=>1]), 'SUPPLIER_VERSION_CONFLICT');
    okTx($db->tables['suppliers'][0]['name'] === 'Alpha', 'zero affected rows causes rollback, not success');
    $db->forceCasZero = false;
    $db->initial = 'ACTIVE';
    $db->tables['suppliers'][0]['row_version'] = '4294967295';
    expectErrorTx(fn()=>$service->update('1',['name'=>'Over','expected_version'=>1]), 'SUPPLIER_VERSION_CONFLICT');
    okTx($db->transDepth === 0, 'failed version guard always rolls back');

    // Same phone must be accepted for two independent Suppliers.
    $db = fixtureTx(); $service = serviceTx($db);
    $service->create(profileTx()+['supplier_code'=>'SUP-01']);
    $service->create(profileTx()+['supplier_code'=>'SUP-02']);
    okTx(count($db->tables['suppliers']) === 2 && $db->tables['suppliers'][0]['phone'] === $db->tables['suppliers'][1]['phone'], 'Supplier phone is not UNIQUE');
    expectErrorTx(fn()=>$service->create(profileTx()+['supplier_code'=>'SUP-01']), 'SUPPLIER_CODE_DUPLICATE');
    okTx(count($db->tables['suppliers']) === 2, 'duplicate code does not insert');
    expectErrorTx(fn()=>$service->update('1',['ward_id'=>'12','expected_version'=>1]), 'SUPPLIER_WARD_PROVINCE_MISMATCH');
    okTx((int)$db->tables['suppliers'][0]['row_version'] === 1, 'invalid merged location does not change version');
    $db->allow = false;
    expectErrorTx(fn()=>$service->replaceCompanyUsage('1',['all_companies'=>true,'company_ids'=>[],'expected_version'=>1]), 'AUTH_POLICY_DENIED');
    expectErrorTx(fn()=>$service->setState('1',['status'=>'ACTIVE','expected_version'=>1]), 'AUTH_POLICY_DENIED');
    okTx($db->tables['supplier_company_usages'] !== [], 'denied usage remains persisted');
    $db->allow = true;
    $db->tables['organizations'][1]['status'] = 'INACTIVE';
    expectErrorTx(fn()=>$service->setState('1',['status'=>'ACTIVE','expected_version'=>1]), 'SUPPLIER_COMPANY_SCOPE_INVALID');
    okTx($db->tables['suppliers'][0]['status'] === 'DRAFT', 'inactive Company blocks activation without mutation');

    echo "SUP-T04 TRANSACTION FIXTURE: {$assertions}/{$assertions} PASS (fake DB; NOT MariaDB)\n";
}

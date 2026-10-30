<?php

declare(strict_types=1);

// Standalone deterministic fake connection. No CI4 bootstrap or actual database.
namespace CodeIgniter\Database {
    final class BaseConnection {
        public int $transDepth = 1;
        /** @var array<string,list<array<string,mixed>>> */
        public array $tables = [];
        /** @var list<string> */
        public array $locked = [];
        public bool $failCounterWrite = false;
        public function table(string $name): \NumberFakeBuilder { return new \NumberFakeBuilder($this, $name); }
        public function query(string $sql): \NumberFakeResult|false {
            if (!str_ends_with($sql, ' FOR UPDATE') || !str_starts_with($sql, 'FAKE_SELECT:')) {
                throw new \RuntimeException('Unexpected SQL on fake DB');
            }
            $encoded = substr($sql, strlen('FAKE_SELECT:'), -strlen(' FOR UPDATE'));
            $data = json_decode((string)base64_decode($encoded, true), true);
            if (!is_array($data)) throw new \RuntimeException('Bad fixture query');
            $this->locked[] = $data['table'];
            return new \NumberFakeResult($this->rows($data['table'], $data['where']));
        }
        /** @param array<string,mixed> $where @return list<array<string,mixed>> */
        public function rows(string $table, array $where): array {
            return array_values(array_filter($this->tables[$table] ?? [], static function (array $row) use ($where): bool {
                foreach ($where as $field => $expected) {
                    if ($expected === null) {
                        if (($row[$field] ?? null) !== null) return false;
                    } elseif ((string)($row[$field] ?? '') !== (string)$expected) return false;
                }
                return true;
            }));
        }
    }
}
namespace CodeIgniter\Settings\Config {
    final class Services {
        public static function settings(): object {
            return new class {
                public function get(string $name, ?string $scope = null): string {
                    return 'Asia/Ho_Chi_Minh';
                }
            };
        }
    }
}
namespace {
    require dirname(__DIR__, 2) . '/app/Exceptions/DomainException.php';
    require dirname(__DIR__, 2) . '/app/Modules/Numbering/Configuration/NumberingRules.php';
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierRules.php';
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierNumberingPolicy.php';
    require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierNumberIssuer.php';

    use App\Exceptions\DomainException;
    use App\Modules\Supplier\SupplierNumberIssuer;
    use CodeIgniter\Database\BaseConnection;

    final class NumberFakeResult {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private readonly array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }

    final class NumberFakeBuilder {
        /** @var array<string,mixed> */
        private array $where = [];
        public function __construct(private BaseConnection $db, private string $table) {}
        public function where(string $field, mixed $value): self { $this->where[$field] = $value; return $this; }
        public function select(string $columns): self { return $this; }
        public function get(): NumberFakeResult { return new NumberFakeResult($this->db->rows($this->table, $this->where)); }
        public function getCompiledSelect(): string {
            return 'FAKE_SELECT:' . base64_encode(json_encode(['table' => $this->table, 'where' => $this->where], JSON_THROW_ON_ERROR));
        }
        /** @param array<string,mixed> $data */
        public function insert(array $data): bool {
            if ($this->table === 'auto_numbering_counters' && $this->db->failCounterWrite) return false;
            $rows = $this->db->tables[$this->table] ?? [];
            $data['id'] = (string)(count($rows) + 1);
            $rows[] = $data;
            $this->db->tables[$this->table] = $rows;
            return true;
        }
        /** @param array<string,mixed> $data */
        public function update(array $data): bool {
            if ($this->table === 'auto_numbering_counters' && $this->db->failCounterWrite) return false;
            $rows = $this->db->tables[$this->table] ?? [];
            foreach ($rows as &$row) {
                $same = true;
                foreach ($this->where as $key => $value) {
                    if ((string)($row[$key] ?? '') !== (string)$value) $same = false;
                }
                if ($same) $row = array_replace($row, $data);
            }
            unset($row);
            $this->db->tables[$this->table] = $rows;
            return true;
        }
    }

    $pass = 0;
    function okFixture(bool $condition, string $label): void {
        global $pass;
        if (!$condition) throw new RuntimeException('SUP-T02 FIXTURE FAIL: ' . $label);
        ++$pass;
    }
    function failFixture(callable $callback, string $code): void {
        try { $callback(); } catch (DomainException $e) {
            okFixture($e->getErrorCode() === $code, 'Reject ' . $code . ' got ' . $e->getErrorCode());
            return;
        }
        throw new RuntimeException('SUP-T02 FIXTURE expected ' . $code);
    }
    /** @return array<string,mixed> */
    function numberedSeries(string $code = 'SUP_DEFAULT'): array {
        return [
            'id' => '11', 'resource_id' => '7', 'series_code' => $code,
            'is_default' => 1, 'status' => 'ACTIVE', 'scope_type' => 'GROUP',
            'pattern' => 'SUP-{RESOURCE}-{SEQ:4}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_IF_EMPTY', 'start_number' => 1, 'increment_by' => 1,
        ];
    }
    function dbFixture(?array $series): BaseConnection {
        $db = new BaseConnection();
        $db->tables = [
            'resources' => [['id' => '7', 'type' => 'supplier', 'status' => 'ACTIVE', 'domain' => 'MASTER']],
            'organizations' => [['id' => '99', 'type' => 'GROUP', 'parent_id' => null, 'status' => 'ACTIVE', 'code' => 'G1']],
            'auto_numbering_series' => $series === null ? [] : [$series],
            'auto_numbering_counters' => [], 'suppliers' => [],
        ];
        return $db;
    }
    function persistFixture(BaseConnection $db): callable {
        return static function (string $code, string $group) use ($db): string {
            $id = (string)(count($db->tables['suppliers']) + 1);
            $db->tables['suppliers'][] = [
                'id' => $id, 'supplier_code' => $code, 'group_organization_id' => $group,
            ];
            return $id;
        };
    }

    $db = dbFixture(null);
    $issuer = new SupplierNumberIssuer($db);
    okFixture($issuer->issue(null, ' m-01 ', persistFixture($db)) === '1', 'Manual without configured default');
    okFixture($db->tables['suppliers'][0]['supplier_code'] === 'M-01', 'Manual code uppercased');
    okFixture($db->tables['suppliers'][0]['group_organization_id'] === '99', 'Server supplies trusted Group ID');
    okFixture($db->tables['auto_numbering_counters'] === [], 'Manual mode does not alter counter');
    failFixture(fn() => $issuer->issue(null, 'M-01', persistFixture($db)), 'SUPPLIER_CODE_DUPLICATE');
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'SUPPLIER_CODE_REQUIRED');
    okFixture($db->locked === ['resources','auto_numbering_series','organizations','resources','auto_numbering_series','organizations','resources','auto_numbering_series'],
        'Consistent lock ordering on manual and rejected branches');

    $db = dbFixture(numberedSeries());
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, 'MANUAL-01', persistFixture($db)), 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
    okFixture($issuer->issue(null, null, persistFixture($db)) === '1', 'Default AUTO issuance');
    okFixture($db->tables['suppliers'][0]['supplier_code'] === 'SUP-SUPPLIER-0001', 'Group supplier resource token');
    okFixture((int)$db->tables['auto_numbering_counters'][0]['last_value'] === 1, 'Shared counter issued value');
    okFixture($db->tables['auto_numbering_counters'][0]['scope_organization_id'] === '99', 'Counter Group-scoped');
    okFixture($db->tables['auto_numbering_counters'][0]['period_key'] === 'ALL', 'Never-reset counter period');
    okFixture($issuer->issue(null, null, persistFixture($db)) === '2', 'Next call uses existing counter row');
    okFixture($db->tables['suppliers'][1]['supplier_code'] === 'SUP-SUPPLIER-0002', 'Monotonic code');
    okFixture((int)$db->tables['auto_numbering_counters'][0]['last_value'] === 2, 'Counter updated atomically');
    okFixture(count(array_filter($db->locked, fn($name) => $name === 'auto_numbering_counters')) === 2,
        'Counter row fetched FOR UPDATE for each issuance');

    $db = dbFixture(numberedSeries());
    $db->tables['suppliers'][] = ['id' => '1', 'supplier_code' => 'SUP-SUPPLIER-0001', 'group_organization_id' => '99'];
    $issuer = new SupplierNumberIssuer($db);
    $issuer->issue(null, null, persistFixture($db));
    okFixture($db->tables['suppliers'][1]['supplier_code'] === 'SUP-SUPPLIER-0002', 'Skips pre-existing manual collision');
    okFixture((int)$db->tables['auto_numbering_counters'][0]['last_value'] === 2, 'Counter records skipped candidate');

    $db = dbFixture(numberedSeries());
    $db->tables['auto_numbering_series'][] = array_replace(numberedSeries('SUP_ALT'), [
        'id' => '12', 'is_default' => 0, 'pattern' => 'ALT-{SEQ:3}',
    ]);
    $issuer = new SupplierNumberIssuer($db);
    $issuer->issue('sup_alt', null, persistFixture($db));
    okFixture($db->tables['suppliers'][0]['supplier_code'] === 'ALT-001', 'Explicit alternate Series accepted');
    okFixture((string)$db->tables['auto_numbering_counters'][0]['numbering_series_id'] === '12',
        'Counter belongs to requested Series');

    $db = dbFixture(null);
    $db->tables['auto_numbering_series'][] = array_replace(numberedSeries('SUP_ALT'), [
        'id' => '12', 'is_default' => 0,
    ]);
    $issuer = new SupplierNumberIssuer($db);
    $issuer->issue('SUP_ALT', null, persistFixture($db));
    okFixture($db->tables['suppliers'][0]['supplier_code'] === 'SUP-SUPPLIER-0001',
        'Requested Series accepted without default');

    $db = dbFixture(array_replace(numberedSeries(), ['status' => 'INACTIVE']));
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, 'A-01', persistFixture($db)), 'NUM_IDENTIFIER_SERIES_INACTIVE');
    okFixture($db->tables['suppliers'] === [] && $db->tables['auto_numbering_counters'] === [],
        'Inactive default blocks manual without write');

    $db = dbFixture(numberedSeries());
    $db->tables['auto_numbering_series'][] = array_replace(numberedSeries('SUP_OTHER'), [
        'id' => '12', 'status' => 'INACTIVE',
    ]);
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_IDENTIFIER_DEFAULT_AMBIGUOUS');

    $db = dbFixture(numberedSeries());
    $db->transDepth = 0;
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_COUNTER_TRANSACTION_FAILED');
    okFixture($db->locked === [], 'Missing parent transaction stops before touching DB');

    $db = dbFixture(numberedSeries());
    $db->tables['organizations'][0]['status'] = 'INACTIVE';
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_GROUP_ROOT_INVALID');
    okFixture($db->tables['suppliers'] === [], 'No write on invalid Group');

    $db = dbFixture(numberedSeries());
    $db->tables['resources'][0]['status'] = 'INACTIVE';
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_IDENTIFIER_RESOURCE_NOT_FOUND');

    $db = dbFixture(numberedSeries());
    $db->tables['auto_numbering_counters'][] = [
        'id' => '1', 'numbering_series_id' => '11', 'scope_organization_id' => '99',
        'period_key' => 'ALL', 'last_value' => (string)PHP_INT_MAX,
    ];
    $issuer = new SupplierNumberIssuer($db);
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_COUNTER_OVERFLOW');

    $db = dbFixture(numberedSeries());
    $db->failCounterWrite = true;
    $issuer = new SupplierNumberIssuer($db);
    $preSupplier = $db->tables['suppliers'];
    $preCounter = $db->tables['auto_numbering_counters'];
    failFixture(fn() => $issuer->issue(null, null, persistFixture($db)), 'NUM_COUNTER_TRANSACTION_FAILED');
    // Simulates the owning Service's rollback. Issuer intentionally does not
    // commit, and T04 must supply the real transaction/rollback wrapper.
    $db->tables['suppliers'] = $preSupplier;
    $db->tables['auto_numbering_counters'] = $preCounter;
    okFixture($db->tables['suppliers'] === $preSupplier && $db->tables['auto_numbering_counters'] === $preCounter,
        'Parent rollback restores Supplier and counter after write failure');

    $db = dbFixture(null);
    $db->tables['suppliers'][] = ['id' => '1', 'supplier_code' => 'SAME', 'group_organization_id' => '3'];
    $issuer = new SupplierNumberIssuer($db);
    $issuer->issue(null, 'SAME', persistFixture($db));
    okFixture($db->tables['suppliers'][1]['group_organization_id'] === '99',
        'Existing Supplier in another Group does not block canonical Group uniqueness');

    echo "SUP-T02 NUMBERING FIXTURE: {$pass}/{$pass} PASS (fake DB, NOT MariaDB)\n";
}

<?php

declare(strict_types=1);

// Isolated in-memory fake SQL + PBAC; NO MariaDB, HTTP, actual Purchase module.
namespace CodeIgniter\Database {
    final class BaseConnection
    {
        public int $transDepth = 1;
        public array $tables = [];
        public array $queries = [];
        public bool $queryFails = false;
        public bool $queryThrows = false;
        public bool $viewAllowed = true;
        public string $group = '1';
        public function table(string $table): \SupT05Builder { return new \SupT05Builder($this, $table); }
        public function query(string $sql): \SupT05Result|false {
            if ($this->transDepth < 1) throw new \RuntimeException('FOR UPDATE without transaction');
            if (!str_starts_with($sql, 'T05_SELECT:') || !str_ends_with($sql, ' FOR UPDATE')) {
                throw new \RuntimeException('Query missing lock or unreviewed SQL');
            }
            $this->queries[] = $sql;
            if ($this->queryThrows) throw new \RuntimeException('Fake SQL exception');
            if ($this->queryFails) return false;
            $payload = json_decode(base64_decode(substr($sql, 11, -11), true), true, 512, JSON_THROW_ON_ERROR);
            $rows = array_values(array_filter($this->tables[$payload['table']] ?? [], static function (array $row) use ($payload): bool {
                foreach ($payload['where'] as $key => $value) {
                    if ((string)($row[$key] ?? '') !== (string)$value) return false;
                }
                return true;
            }));
            $rows = array_slice($rows, 0, $payload['limit']);
            $cols = array_map('trim', explode(',', $payload['select']));
            return new \SupT05Result(array_map(static fn (array $r): array => array_intersect_key($r, array_fill_keys($cols, true)), $rows));
        }
    }
}
namespace CodeIgniter\Shield\Entities { class User { public int $id = 7; } }
namespace App\Modules\Access {
    final class UnifiedAccessService
    {
        public function __construct(private readonly \CodeIgniter\Shield\Entities\User $actor, private readonly \CodeIgniter\Database\BaseConnection $db, ?\DateTimeImmutable $time = null) {}
        public function trustedGroupRootId(): string { return $this->db->group; }
        public function canView(string $type, array $row): bool { return $type === 'supplier' && $this->db->viewAllowed; }
    }
}
namespace {
    require_once dirname(__DIR__, 2) . '/app/Exceptions/DomainException.php';
    require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/AuthorizedPurchaseCompanyPort.php';
    require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierUsabilityPolicy.php';
    require_once dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierUsabilityGuard.php';

    use App\Exceptions\DomainException;
    use App\Modules\Supplier\AuthorizedPurchaseCompanyPort;
    use App\Modules\Supplier\SupplierUsabilityGuard;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    final class SupT05Result {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
    }
    final class SupT05Builder {
        private array $where = [];
        private string $columns = '';
        private int $max = 1;
        public function __construct(private readonly BaseConnection $db, private readonly string $table) {}
        public function select(string $columns): self { $this->columns = $columns; return $this; }
        public function where(string $key, string $value): self { $this->where[$key] = $value; return $this; }
        public function limit(int $max): self { $this->max = $max; return $this; }
        public function getCompiledSelect(): string {
            return 'T05_SELECT:' . base64_encode(json_encode([
                'table'=>$this->table,'where'=>$this->where, 'select'=>$this->columns, 'limit'=>$this->max,
            ], JSON_THROW_ON_ERROR));
        }
    }
    final class SupT05PurchasePort implements AuthorizedPurchaseCompanyPort {
        public bool $authorized = true;
        public bool $invoked = false;
        public string $persistedCompany = '2';
        public function resolveAuthorizedLockedCompanyId(string $persistedPurchaseId, User $actor, BaseConnection $db): string {
            $this->invoked = true;
            if ($db->transDepth < 1 || $persistedPurchaseId !== '70' || $actor->id !== 7 || !$this->authorized) {
                throw new DomainException('PURCHASE_PBAC_DENIED');
            }
            return $this->persistedCompany;
        }
    }
    $n = 0;
    $ok = static function (bool $v, string $name) use (&$n): void {
        if (!$v) throw new RuntimeException('SUP-T05 GUARD FIXTURE FAIL: ' . $name);
        ++$n;
    };
    $db = new BaseConnection();
    $db->tables = [
        'suppliers'=>[['id'=>'19','group_organization_id'=>'1','status'=>'ACTIVE','all_companies'=>'1']],
        'organizations'=>[['id'=>'2','type'=>'COMPANY','status'=>'ACTIVE','parent_id'=>'1']],
        'supplier_company_usages'=>[],
    ];
    $actor = new User();
    $port = new SupT05PurchasePort();
    $guard = new SupplierUsabilityGuard($actor, $db);
    $allow = static function () use ($guard, $port, &$db, &$ok): void {
        $db->queries = [];
        $guard->assertUsableForNewPurchase('19','70',$port);
        $ok(count($db->queries) === 3, 'exactly three bounded locked lookups');
        $ok(count(array_filter($db->queries, static fn (string $q): bool => str_ends_with($q,' FOR UPDATE'))) === 3, 'all rows locked');
        $ok($db->transDepth === 1, 'guard did not commit/rollback Purchase transaction');
    };
    $deny = static function (string $code) use ($guard, $port, &$ok): void {
        try { $guard->assertUsableForNewPurchase('19','70',$port); throw new RuntimeException('Expected ' . $code); }
        catch (DomainException $e) { $ok($e->getErrorCode() === $code, $code . ' vs ' . $e->getErrorCode()); }
    };
    $allow();
    $ok($port->invoked, 'Purchase resolver was used');
    $ok(!str_contains(implode(' ', $db->queries), 'bank_'), 'no bank SQL');
    $db->tables['suppliers'][0]['all_companies'] = '0';
    $db->tables['supplier_company_usages'][] = ['supplier_id'=>'19','company_organization_id'=>'2'];
    $allow();
    $db->tables['supplier_company_usages'][0]['company_organization_id'] = '3';
    $deny('SUPPLIER_PURCHASE_COMPANY_NOT_ALLOWED');
    $db->tables['suppliers'][0]['all_companies'] = '1';
    $deny('SUPPLIER_USAGE_MODE_CORRUPT');
    $db->tables['supplier_company_usages'] = [];
    $allow();
    $db->tables['suppliers'][0]['status'] = 'DRAFT'; $deny('SUPPLIER_NOT_OPERATIONAL');
    $db->tables['suppliers'][0]['status'] = 'INACTIVE'; $deny('SUPPLIER_NOT_OPERATIONAL');
    $db->tables['suppliers'][0]['status'] = 'APPROVED'; $deny('SUPPLIER_NOT_OPERATIONAL');
    $db->tables['suppliers'][0]['status'] = 'ACTIVE';
    $db->tables['organizations'][0]['status'] = 'INACTIVE'; $deny('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
    $db->tables['organizations'][0]['status'] = 'ACTIVE';
    $db->tables['organizations'][0]['parent_id'] = '99'; $deny('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
    $db->tables['organizations'][0]['parent_id'] = '1';
    $db->tables['organizations'][0]['type'] = 'BRANCH'; $deny('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
    $db->tables['organizations'][0]['type'] = 'COMPANY';
    $db->tables['suppliers'][0]['group_organization_id'] = '99'; $deny('SUPPLIER_PURCHASE_NOT_AVAILABLE');
    $db->tables['suppliers'][0]['group_organization_id'] = '1';
    $db->viewAllowed = false; $deny('SUPPLIER_PURCHASE_NOT_AVAILABLE');
    $db->viewAllowed = true;
    $port->authorized = false; $deny('PURCHASE_PBAC_DENIED');
    $port->authorized = true;
    $port->persistedCompany = '3'; $deny('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
    $port->persistedCompany = 'invalid'; $deny('SUPPLIER_PURCHASE_TRUSTED_ID_INVALID');
    $port->persistedCompany = '2';
    $db->queryFails = true; $deny('SUPPLIER_PURCHASE_LOOKUP_FAILED');
    $db->queryFails = false;
    $db->queryThrows = true; $deny('SUPPLIER_PURCHASE_LOOKUP_FAILED');
    $db->queryThrows = false;
    $db->transDepth = 0; $deny('SUPPLIER_PURCHASE_TRANSACTION_REQUIRED');
    $db->transDepth = 1;
    foreach (['0','-1','1 OR 1','00019','18446744073709551616'] as $id) {
        try { $guard->assertUsableForNewPurchase($id,'70',$port); throw new RuntimeException('Expected invalid id'); }
        catch (DomainException $e) { $ok($e->getErrorCode() === 'SUPPLIER_PURCHASE_TRUSTED_ID_INVALID','bad Supplier id ' . $id); }
    }
    foreach (['0','00070','1;DROP'] as $id) {
        try { $guard->assertUsableForNewPurchase('19',$id,$port); throw new RuntimeException('Expected invalid id'); }
        catch (DomainException $e) { $ok($e->getErrorCode() === 'SUPPLIER_PURCHASE_TRUSTED_ID_INVALID','bad Purchase id ' . $id); }
    }
    $db->tables['suppliers'] = [];
    $deny('SUPPLIER_PURCHASE_NOT_AVAILABLE');
    $ok($db->transDepth === 1, 'denials did not end caller transaction');

    echo "SUP-T05 USABILITY GUARD FIXTURE: {$n}/{$n} PASS (fake DB/Purchase port, NOT MariaDB)\n";
}

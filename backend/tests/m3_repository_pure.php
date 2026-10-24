<?php

declare(strict_types=1);

// Small stand-in for CI Query Builder; verifies reader operation with known
// fixtures, NOT a replacement for `php spark m3:audit` on real MariaDB.
namespace CodeIgniter\Database {
    class BaseConnection {
        /** @var array<string,list<array<string,mixed>>> */
        public array $fixtures = [];
        public int $queries = 0;
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
        public function error(): array { return ['message' => 'fixture query error']; }
    }
    final class FakeResult {
        public function __construct(private readonly array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class FakeBuilder {
        private array $where = [];
        private array $whereIn = [];
        public function __construct(private readonly BaseConnection $db, private readonly string $table) {}
        public function select(string $cols): self { return $this; }
        public function join(string $table, string $cond): self { return $this; }
        public function orderBy(string $key, string $direction = 'ASC'): self { return $this; }
        public function where(string $column, mixed $value): self {
            $this->where[preg_replace('/^.*\./', '', $column)] = $value; return $this;
        }
        public function whereIn(string $column, array $values): self {
            $this->whereIn[preg_replace('/^.*\./', '', $column)] = $values; return $this;
        }
        public function get(): FakeResult {
            $this->db->queries++;
            $rows = $this->db->fixtures[explode(' ', $this->table)[0]] ?? [];
            $result = array_values(array_filter($rows, function (array $row): bool {
                foreach ($this->where as $column=>$value) {
                    if ((string) ($row[$column] ?? '') !== (string) $value) return false;
                }
                foreach ($this->whereIn as $column=>$values) {
                    if (! in_array($row[$column] ?? null, $values, true)) return false;
                }
                return true;
            }));
            return new FakeResult($result);
        }
    }
}

namespace {
    require __DIR__ . '/../app/Shared/Read/PositiveId.php';
    foreach (['ResourceStateCatalog','ResourceStateValidator','ResourceStateRepository'] as $class) {
        require __DIR__ . '/../app/Modules/Access/' . $class . '.php';
    }
    use App\Modules\Access\ResourceStateRepository;
    $n = 0;
    function checkRepo(string $description, bool $ok): void {
        global $n;
        if (! $ok) { fwrite(STDERR, 'FAIL: ' . $description . "\n"); exit(1); }
        $n++; echo 'PASS: ' . $description . "\n";
    }
    function rejectRepo(string $description, callable $action): void {
        try { $action(); checkRepo($description, false); }
        catch (\InvalidArgumentException | \UnexpectedValueException $e) { checkRepo($description, true); }
    }
    $db = new \CodeIgniter\Database\BaseConnection();
    $db->fixtures = [
        'resources' => [
            ['id'=>'9','type'=>'product','status'=>'ACTIVE','lifecycle_id'=>'10'],
            ['id'=>'10','type'=>'display_setting','status'=>'ACTIVE','lifecycle_id'=>null],
            ['id'=>'11','type'=>'user','status'=>'ACTIVE','lifecycle_id'=>null],
            ['id'=>'12','type'=>'workflow_state','status'=>'ACTIVE','lifecycle_id'=>null],
            ['id'=>'13','type'=>'province','status'=>'ACTIVE','lifecycle_id'=>null],
        ],
        'lifecycles' => [['id'=>'10','status'=>'ACTIVE'], ['id'=>'20','status'=>'ACTIVE']],
        'lifecycle_states' => [
            ['lifecycle_id'=>'10','id'=>'1','code'=>'NEW','status'=>'ACTIVE','is_initial'=>1,'sequence'=>1],
            ['lifecycle_id'=>'10','id'=>'2','code'=>'APPROVED','status'=>'ACTIVE','is_initial'=>0,'sequence'=>2],
            ['lifecycle_id'=>'20','id'=>'5','code'=>'DRAFT','status'=>'ACTIVE','is_initial'=>1,'sequence'=>1],
        ],
        'workflow_states' => [
            ['id'=>'100','code'=>'ACTIVE','status'=>'ACTIVE'],
            ['id'=>'101','code'=>'INACTIVE','status'=>'INACTIVE'],
        ],
        'lifecycle_transitions' => [
            ['lifecycle_id'=>'10','from_state_id'=>'1','to_state_id'=>'2','action_code'=>'approve','status'=>'ACTIVE'],
        ],
        'policies' => [['id'=>'300','resource_id'=>'9','state_id'=>'2','status'=>'INACTIVE']],
    ];
    $repo = new ResourceStateRepository($db);
    $def = $repo->definitionFor('product');
    checkRepo('resource Lifecycle loaded by trusted lookup', $def['mode'] === 'LIFECYCLE' && $def['lifecycle_id'] === '10');
    checkRepo('members read through Lifecycle join', $def['by_code'] === ['NEW'=>'1','APPROVED'=>'2']);
    checkRepo('initial code loaded for create', $repo->initialState('product') === 'NEW');
    checkRepo('persisted product State mapped to canonical ID', $repo->persistedStateId('product', ['status'=>'NEW']) === '1');
    checkRepo('valid workflow transition target resolves', $repo->transitionTarget('product', ['status'=>'NEW'], 'approve') === 'APPROVED');
    rejectRepo('unauthorized/skipped workflow transition rejected', static fn () => $repo->transitionTarget('product', ['status'=>'APPROVED'], 'approve'));
    checkRepo('All States-only policy options contain only sentinel', $repo->policyStateOptions('display_setting') === [['state_id'=>null,'code'=>'ALL_STATES']]);
    checkRepo('All States-only persisted row returns null State', $repo->persistedStateId('display_setting', ['status'=>'ACTIVE']) === null);
    rejectRepo('All States-only policy state rejected', static fn () => $repo->assertPolicyState('display_setting', '100'));
    checkRepo('Shield account with active 0 resolves INACTIVE State (requires active catalog)', $repo->persistedStateId('user', ['status'=>'ACTIVE','active'=>1]) === '100');
    rejectRepo('disabled INACTIVE catalog state cannot be used by user', static fn () => $repo->persistedStateId('user', ['status'=>'inactive','active'=>0]));
    checkRepo('workflow_state can self-manage disabled canonical INACTIVE', $repo->persistedStateId('workflow_state', ['status'=>'INACTIVE']) === '101');
    rejectRepo('business resource without Lifecycle fails closed', static fn () => $repo->definitionFor('province'));
    $q = $db->queries;
    $repo->definitionFor('product');
    checkRepo('definitions cached only within repository instance', $db->queries === $q);
    rejectRepo('cannot switch lifecycle with INACTIVE incompatible policy', static fn () => $repo->assertReassignmentSafe('product', [
        'lifecycle_id'=>'20',
    ]));
    echo "TOTAL: {$n} M3 repository fixture assertions passed.\n";
}

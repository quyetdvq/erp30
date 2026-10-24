<?php

declare(strict_types=1);

/* Real M3.2 classes against an in-memory query builder; no DB/vendor required. */
namespace CodeIgniter\Database {
    class BaseBuilder {}
    class BaseConnection {
        /** @var array<string,list<array<string,mixed>>> */
        public array $data = [];
        public array $calls = [];
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
    }
    final class FakeResult {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class FakeBuilder extends BaseBuilder {
        private array $checks = [];
        private array $orChecks = [];
        private bool $orGroup = false;
        private ?int $limit = null;
        public function __construct(private BaseConnection $db, private string $table) {}
        public function select(string $cols): self { return $this; }
        public function join(string $table, string $on): self { return $this; }
        public function orderBy(string $field, string $direction='ASC'): self { return $this; }
        public function distinct(): self { return $this; }
        public function limit(int $n): self { $this->limit = $n; return $this; }
        public function groupStart(): self { $this->orGroup = true; return $this; }
        public function groupEnd(): self { $this->orGroup = false; return $this; }
        private function key(string $key): string {
            if (str_starts_with($key, 'BINARY ')) { return 'status'; }
            return preg_replace('/^.*\./', '', $key);
        }
        public function where(string $key, mixed $value=null, bool $escape=true): self {
            if (str_starts_with($key, 'BINARY ')) { $value = 'ACTIVE'; }
            $check = [$this->key($key), '=', $value];
            if (preg_match('/^(.+?) (<=|>=)$/', $check[0], $m)) { $check = [$m[1], $m[2], $value]; }
            if ($this->orGroup) $this->orChecks[] = $check;
            else $this->checks[] = $check;
            return $this;
        }
        public function orWhere(string $key, mixed $value): self {
            $this->orChecks[] = [$this->key($key), '=', $value]; return $this;
        }
        public function whereIn(string $key, array $values): self {
            $this->checks[] = [$this->key($key), 'IN', $values]; return $this;
        }
        private function matches(array $row, array $test): bool {
            [$column,$operator,$expected] = $test;
            $value = $row[$column] ?? null;
            return match ($operator) {
                'IN' => in_array((string) $value, array_map('strval', $expected), true),
                '<=' => $value !== null && (string) $value <= (string) $expected,
                '>=' => $value !== null && (string) $value >= (string) $expected,
                default => $value === null ? $expected === null : (string) $value === (string) $expected,
            };
        }
        public function get(): FakeResult {
            $key = explode(' ', $this->table)[0];
            $this->db->calls[$key] = ($this->db->calls[$key] ?? 0) + 1;
            $rows = $this->db->data[$key] ?? [];
            $filtered = array_values(array_filter($rows, function (array $row): bool {
                foreach ($this->checks as $check) if (! $this->matches($row,$check)) return false;
                if ($this->orChecks !== []) {
                    $match = false;
                    foreach ($this->orChecks as $check) if ($this->matches($row,$check)) $match = true;
                    if (! $match) return false;
                }
                return true;
            }));
            return new FakeResult($this->limit === null ? $filtered : array_slice($filtered,0,$this->limit));
        }
    }
}
namespace CodeIgniter\Shield\Entities {
    class User { public mixed $id; public function __construct(array $row) { $this->id = $row['id']; } }
}
namespace {
    use App\Modules\Access\ReadAccessBridge;
    use App\Modules\Access\RuntimeActorReader;
    use App\Modules\Access\RuntimePolicyReader;
    use App\Modules\Access\UnifiedAccessService;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    function config(string $name): object {
        return (object)['tables'=>[
            'users'=>'users','groups_users'=>'auth_groups_users','permissions_users'=>'auth_permissions_users',
        ]];
    }
    function env(string $key, mixed $default=null): mixed { return $default; }
    function log_message(string $level, string $text, array $data=[]): void {}
    function db_connect(): BaseConnection { global $db; return $db; }
    foreach ([
        'Shared/Read/PositiveId.php', 'Shared/Read/UtcBusinessDate.php',
        'Shared/Read/FlatHierarchy.php',
        'Modules/People/PeopleReadRepository.php',
        'Modules/Organization/OrganizationReadRepository.php',
        'Modules/Access/ScopeFactsReader.php', 'Modules/Access/BasePermissionReader.php',
        'Modules/Access/ResourceStateCatalog.php', 'Modules/Access/ResourceStateValidator.php',
        'Modules/Access/ResourceStateRepository.php', 'Modules/Access/PbacDecision.php',
        'Modules/Access/AccessDecisionCore.php', 'Modules/Access/PolicyReadLanes.php',
        'Modules/Access/ReadViewGate.php', 'Modules/Access/RuntimeActorReader.php',
        'Modules/Access/RuntimePolicyReader.php', 'Modules/Access/UnifiedAccessService.php',
        'Modules/Access/ReadAccessBridge.php',
    ] as $file) { require __DIR__ . '/../app/' . $file; }
    $assertions=0;
    function ck32(string $name, bool $ok): void {
        global $assertions;
        if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
        $assertions++; echo "PASS: $name\n";
    }
    function reject32(string $name, callable $cb): void {
        try { $cb(); ck32($name,false); }
        catch (\UnexpectedValueException | \InvalidArgumentException $e) { ck32($name,true); }
    }
    $db = new BaseConnection();
    $today = new \DateTimeImmutable('2026-10-23T12:00:00Z');
    $types = [
        'province'=>'MASTER','ward'=>'MASTER','organization'=>'ORGANIZATION',
        'department'=>'MASTER','position'=>'MASTER','employee'=>'MASTER',
        'employee_assignment'=>'ORGANIZATION',
    ];
    $db->data['resources'] = [];
    $db->data['resource_actions'] = [];
    $db->data['group_permissions'] = [];
    $typeIndex=0;
    foreach ($types as $type=>$domain) {
        $typeIndex++;
        $res=(string) (100+$typeIndex);
        $action=(string)(200+$typeIndex);
        $db->data['resources'][]=['id'=>$res,'type'=>$type,'status'=>'ACTIVE','domain'=>$domain,
            'lifecycle_id'=>$type==='employee_assignment'?null:'10'];
        $db->data['resource_actions'][]=['id'=>$action,'resource_id'=>$res,'code'=>'view',
            'resource_action_id'=>$action,'resource_type'=>$type,'action'=>'view'];
        $db->data['group_permissions'][]=['group_id'=>'5','resource_action_id'=>$action,
            'resource_type'=>$type,'action'=>'view'];
    }
    $db->data['users'] = [
        ['id'=>'9','username'=>'alice','active'=>1,'status'=>'active','deleted_at'=>null],
        ['id'=>'10','username'=>'bob','active'=>1,'status'=>'active','deleted_at'=>null],
    ];
    $db->data['auth_groups_users'] = [['user_id'=>'9','group'=>'admins']];
    $db->data['groups'] = [
        ['id'=>'5','code'=>'admins','status'=>'ACTIVE'],
        ['id'=>'6','code'=>'inactive','status'=>'INACTIVE'],
    ];
    $db->data['auth_permissions_users'] = [];
    $db->data['employees'] = [
        ['id'=>'40','user_id'=>'9','status'=>'ACTIVE'],
        ['id'=>'41','user_id'=>'10','status'=>'ACTIVE'],
    ];
    $db->data['employee_assignments'] = [
        ['id'=>'70','employee_id'=>'40','organization_id'=>'2','department_id'=>'12',
            'position_id'=>'21','status'=>'ACTIVE','effective_from'=>'2026-01-01','effective_to'=>null,'is_primary'=>1],
        ['id'=>'71','employee_id'=>'41','organization_id'=>'4','department_id'=>'12',
            'position_id'=>'21','status'=>'ACTIVE','effective_from'=>'2026-01-01','effective_to'=>null,'is_primary'=>1],
    ];
    $db->data['organizations'] = [
        ['id'=>'1','parent_id'=>null,'type'=>'GROUP','status'=>'ACTIVE'],
        ['id'=>'2','parent_id'=>'1','type'=>'COMPANY','status'=>'ACTIVE'],
        ['id'=>'3','parent_id'=>'2','type'=>'BRANCH','status'=>'ACTIVE'],
        ['id'=>'4','parent_id'=>'1','type'=>'COMPANY','status'=>'ACTIVE'],
    ];
    $db->data['lifecycles'] = [['id'=>'10','status'=>'ACTIVE']];
    $db->data['lifecycle_states'] = [
        ['id'=>'11','lifecycle_id'=>'10','code'=>'ACTIVE','status'=>'ACTIVE','is_initial'=>1],
        ['id'=>'12','lifecycle_id'=>'10','code'=>'INACTIVE','status'=>'ACTIVE','is_initial'=>0],
        ['id'=>'13','lifecycle_id'=>'10','code'=>'CLOSED','status'=>'ACTIVE','is_initial'=>0],
    ];
    $db->data['workflow_states'] = [
        ['id'=>'11','code'=>'ACTIVE','status'=>'ACTIVE'],
        ['id'=>'12','code'=>'INACTIVE','status'=>'ACTIVE'],
        ['id'=>'13','code'=>'CLOSED','status'=>'ACTIVE'],
    ];
    $db->data['policies'] = [];
    $actor = new User(['id'=>'9']);
    $unified = new UnifiedAccessService($actor,$db,$today);
    $legacy = new \App\Modules\Access\ReadViewGate($actor,$db,$today);
    $master = ['id'=>'90','status'=>'ACTIVE'];
    ck32('unrestricted MASTER read agrees with M2.1.1',
        $unified->canView('province',$master) && $legacy->canView('province',$master));
    ck32('descendant ORGANIZATION allowed',
        $unified->canView('organization',['id'=>'3','status'=>'ACTIVE'])
        && $legacy->canView('organization',['id'=>'3','status'=>'ACTIVE']));
    ck32('sibling ORGANIZATION denied by scope',
        ! $unified->canView('organization',['id'=>'4','status'=>'ACTIVE'])
        && ! $legacy->canView('organization',['id'=>'4','status'=>'ACTIVE']));
    ck32('no Base Permission denies second actor',
        ! (new UnifiedAccessService(new User(['id'=>'10']),$db,$today))
            ->canView('province',$master));
    $db->data['employees'][0]['status'] = 'INACTIVE';
    ck32('persisted inactive employee is ineligible even with VIEW base permission',
        ! (new UnifiedAccessService($actor,$db,$today))->canView('province',$master));
    $db->data['employees'][0]['status'] = 'ACTIVE';
    $db->data['resources'][0]['domain'] = 'SYSTEM';
    reject32('approved MASTER Resource cannot be reclassified silently',
        static fn () => (new UnifiedAccessService($actor,$db,$today))->canView('province',$master));
    $db->data['resources'][0]['domain'] = 'MASTER';
    // A direct Shield permission can grant Base Permission only for a registered action.
    $db->data['auth_permissions_users'][] = ['user_id'=>'10','permission'=>'province.view'];
    $db->data['auth_permissions_users'][] = ['user_id'=>'10','permission'=>'not_catalog.view'];
    $direct = (new RuntimeActorReader($db))->snapshot(new User(['id'=>'10']),$today);
    ck32('direct Shield permission only counts if registered in ERP catalog',
        isset($direct['permissions']['province.view'])
        && !isset($direct['permissions']['not_catalog.view']));
    ck32('direct permission alone still cannot pass ORGANIZATION scope for sibling',
        ! (new UnifiedAccessService(new User(['id'=>'10']),$db,$today))
            ->canView('organization',['id'=>'3','status'=>'ACTIVE']));
    $resource = (new RuntimePolicyReader($db))->resourceAction('organization','view');
    ck32('registered Resource VIEW action resolved', $resource['id']==='103' && $resource['action_id']==='203');
    $reader = new RuntimeActorReader($db);
    $facts = $reader->snapshot($actor,$today);
    ck32('actor Shield ID, active group and UTC snapshot',
        $facts['user_id']==='9' && $facts['group_ids']===['5']
        && $facts['employee_active'] && count($facts['effective_assignments'])===1
        && $facts['evaluation_utc']==='2026-10-23T12:00:00Z');
    ck32('no inactive ERP group', !in_array('6',$facts['group_ids'],true));
    // Fresh evaluator to avoid stale in-request caches across fixture mutations.
    $db->data['policies'] = [
        ['id'=>'501','resource_id'=>'103','status'=>'ACTIVE','state_id'=>null,
            'organization_id'=>'2','participant_type'=>'ORGANIZATION','participant_ref_id'=>'2',
            'resource_action_id'=>'203','effect'=>'DENY'],
        ['id'=>'502','resource_id'=>'103','status'=>'ACTIVE','state_id'=>'11',
            'organization_id'=>'2','participant_type'=>'USER','participant_ref_id'=>'9',
            'resource_action_id'=>'203','effect'=>'ALLOW'],
        ['id'=>'503','resource_id'=>'103','status'=>'INACTIVE','state_id'=>null,
            'organization_id'=>'2','participant_type'=>'USER','participant_ref_id'=>'9',
            'resource_action_id'=>'203','effect'=>'ABSOLUTE_DENY'],
        ['id'=>'504','resource_id'=>'103','status'=>'ACTIVE','state_id'=>'12',
            'organization_id'=>'2','participant_type'=>'USER','participant_ref_id'=>'9',
            'resource_action_id'=>'203','effect'=>'ABSOLUTE_DENY'],
    ];
    $u2 = new UnifiedAccessService($actor,$db,$today);
    $l2 = new \App\Modules\Access\ReadViewGate($actor,$db,$today);
    ck32('state ACTIVE ALLOW overrides ordinary DENY in actor lane',
        $u2->canView('organization',['id'=>'3','status'=>'ACTIVE'])
        === $l2->canView('organization',['id'=>'3','status'=>'ACTIVE'])
        && $u2->canView('organization',['id'=>'3','status'=>'ACTIVE']));
    ck32('state INACTIVE ABSOLUTE_DENY vetoes',
        ! $u2->canView('organization',['id'=>'3','status'=>'INACTIVE'])
        && ! $l2->canView('organization',['id'=>'3','status'=>'INACTIVE']));
    $allPolicy = (new RuntimePolicyReader($db))->applicable($resource,['1','2','3'],'11');
    ck32('policy SQL excludes INACTIVE and wrong State',
        array_column($allPolicy,'id')===['501','502']);
    ck32('All States-only policy query excludes State-specific policy',
        array_column((new RuntimePolicyReader($db))->applicable($resource,['1','2','3'],null),'id')===['501']);
    reject32('new engine blocks mutation actions at M3.2',
        static fn () => $u2->decidePersisted('organization','delete',['id'=>'3','status'=>'ACTIVE']));
    reject32('new engine does not trust client-like missing persisted status',
        static fn () => $u2->canView('organization',['id'=>'3']));
    ck32('unset M3.2 mode defaults to accepted legacy evaluator',
        (new ReadAccessBridge($actor,$db,$today))->canView('organization',['id'=>'3','status'=>'INACTIVE'])
        === $l2->canView('organization',['id'=>'3','status'=>'INACTIVE']));
    $bridge = new ReadAccessBridge($actor,$db,$today,'shadow');
    ck32('shadow returns legacy decision and never broadens',
        $bridge->canView('organization',['id'=>'3','status'=>'INACTIVE']) === false);
    reject32('unknown cutover mode fails closed',
        static fn () => new ReadAccessBridge($actor,$db,$today,'open_all'));
    $queriesBefore = array_sum($db->calls);
    $readBridge = new ReadAccessBridge($actor,$db,$today,'enforce');
    ck32('enforce delegates exclusively to new evaluator',
        ! $readBridge->canView('organization',['id'=>'3','status'=>'INACTIVE']));
    ck32('query activity occurs only for authoritative local reads', array_sum($db->calls)>$queriesBefore);
    echo "TOTAL: {$assertions} M3.2 runtime fixture assertions passed.\n";
}

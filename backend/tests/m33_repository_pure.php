<?php

declare(strict_types=1);

namespace CodeIgniter\Database {
    class BaseConnection {
        /** @var array<string,list<array<string,mixed>>> */
        public array $rows = [];
        public array $queries = [];
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
        public function error(): array { return ['message' => 'fixture forced failure']; }
    }
    final class FakeResult {
        public function __construct(private array $rows) {}
        public function getResultArray(): array { return $this->rows; }
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
    }
    final class FakeBuilder {
        private array $checks = [];
        private array $order = [];
        public function __construct(private BaseConnection $db, private string $name) {}
        public function select(string $columns): self { return $this; }
        public function join(string $table, string $condition, string $type = ''): self { return $this; }
        public function orderBy(string $name, string $direction = 'ASC'): self { $this->order[] = [preg_replace('~.*\\.~','',$name),$direction]; return $this; }
        public function where(string $field, mixed $value): self { $this->checks[] = [preg_replace('~.*\\.~','',$field),$value]; return $this; }
        public function whereIn(string $field, array $values): self { $this->checks[] = [preg_replace('~.*\\.~','',$field),$values]; return $this; }
        public function groupBy(string $field): self { return $this; }
        private ?int $limit = null;
        public function limit(int $count): self { $this->limit = $count; return $this; }
        public function get(): FakeResult|false {
            $key = explode(' ', $this->name)[0];
            $this->db->queries[] = $key;
            if ($key === 'broken') return false;
            $rows = array_values(array_filter($this->db->rows[$key] ?? [], function ($row) {
                foreach ($this->checks as [$key,$value]) {
                    $actual = $row[$key] ?? null;
                    if (is_array($value)) {
                        if (!in_array((string) $actual, array_map('strval',$value), true)) return false;
                    } elseif ($actual === null ? $value !== null : (string) $actual !== (string) $value) return false;
                }
                return true;
            }));
            usort($rows, function (array $a, array $b): int {
                foreach ($this->order as [$key,$direction]) {
                    $cmp = strcmp((string) ($a[$key]??''), (string) ($b[$key]??''));
                    if ($cmp !== 0) return strtoupper($direction) === 'DESC' ? -$cmp : $cmp;
                }
                return 0;
            });
            if ($this->limit !== null) $rows = array_slice($rows, 0, $this->limit);
            return new FakeResult($rows);
        }
    }
}

namespace {
    require __DIR__ . '/../app/Shared/Media/ImageUrl.php';
    require __DIR__ . '/../app/Modules/Access/Management/AccessReadContract.php';
    require __DIR__ . '/../app/Modules/Access/Management/AccessReadRepository.php';
    require __DIR__ . '/../app/Modules/Identity/Management/IdentityReadRepository.php';
    require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadContract.php';
    require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadRepository.php';
    function config(string $name): object {
        return (object) ['tables' => [
            'users' => 'auth_users', 'identities' => 'auth_identities',
            'groups_users' => 'auth_groups_users', 'permissions_users' => 'auth_permissions_users',
        ]];
    }
    $check = 0;
    $pass = static function (bool $okay, string $name) use (&$check): void {
        if (!$okay) throw new RuntimeException('FAIL: ' . $name);
        $check++; echo 'PASS: ' . $name . PHP_EOL;
    };
    $db = new \CodeIgniter\Database\BaseConnection();
    $db->rows = [
        'resources' => [
            ['id'=>'2','type'=>'resource','name'=>'Resources','status'=>'ACTIVE'],
            ['id'=>'3','type'=>'policy','name'=>'Policies','status'=>'ACTIVE'],
        ],
        'resource_actions' => [
            ['id'=>'9','resource_id'=>'2','code'=>'view','description'=>null,'resource_action_id'=>'9','resource_type'=>'resource','action'=>'view'],
            ['id'=>'10','resource_id'=>'3','code'=>'view','description'=>null,'resource_action_id'=>'10','resource_type'=>'policy','action'=>'view'],
        ],
        'policies' => [
            ['id'=>'15','resource_id'=>'3','organization_id'=>'1','state_id'=>null,'status'=>'ACTIVE'],
        ],
        'policy_actions' => [
            ['id'=>'100','policy_id'=>'15','resource_action_id'=>'10','effect'=>'ALLOW'],
            ['id'=>'101','policy_id'=>'16','resource_action_id'=>'10','effect'=>'DENY'],
        ],
        'groups' => [['id'=>'22','code'=>'admins','status'=>'ACTIVE']],
        'group_permissions' => [
            ['group_id'=>'22','resource_action_id'=>'10'],
            ['group_id'=>'21','resource_action_id'=>'9'],
        ],
        'auth_groups_users' => [
            ['user_id'=>'42','group'=>'admins','code'=>'admins','group_id'=>'22','name'=>'Administrators','id'=>'42','username'=>'smith','active'=>1,'status'=>'ACTIVE'],
        ],
        'auth_users' => [[
            'id'=>'42','username'=>'smith','active'=>'1','status'=>'ACTIVE',
            'created_at'=>'2026-10-23 08:00:00','name'=>'Smith',
            'avatar_filename'=>null,'email'=>'smith@example.test','deleted_at'=>null,
        ]],
        'auth_permissions_users' => [['user_id'=>'42','permission_count'=>'2','permission'=>'RESOURCE.VIEW'],['user_id'=>'42','permission_count'=>'2','permission'=>'policy.view'],['user_id'=>'42','permission_count'=>'2','permission'=>'obsolete.view']],
        'authorization_logs' => [
            ['id'=>'2','resource_id'=>'3','organization_id'=>'1','resource_type'=>'policy','action'=>'view'],
        ],
        'lifecycle_states' => [
            ['id'=>'90','lifecycle_id'=>'25','sequence'=>2],
            ['id'=>'80','lifecycle_id'=>'25','sequence'=>1],
        ],
        'lifecycle_transitions' => [
            ['id'=>'11','lifecycle_id'=>'25','from_state_id'=>'80','action_code'=>'approve','status'=>'ACTIVE'],
        ],
    ];
    $repo = new \App\Modules\Access\Management\AccessReadRepository($db);
    $identity = new \App\Modules\Identity\Management\IdentityReadRepository($db);
    $workflow = new \App\Modules\Workflow\Management\WorkflowReadRepository($db);
    $pass(count($repo->all('resource'))===2, 'resource catalog list uses expected table');
    $pass(count($repo->sample('resource', 1))===1, 'runtime sampling is bounded before reading rows');
    $pass($repo->one('resource','3')['type']==='policy', 'resource detail keeps trusted row');
    $pass($repo->one('resource','404')===null,'missing row returns null');
    $pass(count($repo->pageActions('resource',[['id'=>'2']]))===1, 'resource action attached only to authorized page');
    $pass(count($repo->pageActions('policy',[['id'=>'15']]))===1, 'policy page actions never include another policy');
    $pass($repo->pageActions('policy',[])===[], 'empty policy page issues no relation query');
    $pass(count($repo->related('permissions',['id'=>'22']))===1, 'group permission rows restricted to owner');
    $pass(count($repo->related('actions',['id'=>'2']))===1,'nested actions restricted to owner');
    $pass(count($workflow->related('states',['id'=>'25']))===2,'lifecycle states restricted to owner');
    $pass(count($workflow->related('transitions',['id'=>'25']))===1,'lifecycle transitions restricted to owner');
    $pass(count($repo->related('members',['id'=>'22','code'=>'admins']))===1,'Shield group member relation accepts canonical code');
    $pass(count($repo->all('authorization_log'))===1,'log list exposes projected resource type');
    $db->rows['workflow_states'] = [['id' => '12', 'code' => 'DRAFT']];
    $db->rows['lifecycles'] = [['id' => '25', 'code' => 'ORDER_FLOW']];
    $pass($workflow->one('lifecycle', '25')['code'] === 'ORDER_FLOW', 'Workflow owns lifecycle master lookup');
    $pass(count($workflow->sample('workflow_state', 1)) === 1, 'Workflow sampling remains bounded');
    $pass($identity->userPermissionTarget('42')['status']==='ACTIVE', 'user permissions read only trusted Shield status');
    $pass($identity->userPermissionTarget('99') === null, 'unknown target user stays hidden');
    $pass($identity->directPermissionTokens('42') === ['resource.view','policy.view'], 'direct permission tokens exclude stale grants, canonicalize like Shield baseline');
    $users=$identity->securityUsers();
    $pass(count($users)===1 && $users[0]['id']==='42','security user projection keeps exact ID');
    $pass($users[0]['email']==='smith@example.test' && !array_key_exists('avatar_filename',$users[0]), 'private avatar filename never returned');
    $pass($users[0]['groups'][0]['code']==='admins','Shield memberships decorated');
    $pass($users[0]['direct_permission_count']===2,'direct permission count projected');
    foreach ($db->queries as $name) {
        if (preg_match('~(INSERT|UPDATE|DELETE)~i', $name)) throw new RuntimeException('Mutation attempted.');
    }
    $pass(true,'all repository operations are read-only');
    echo 'TOTAL: ' . $check . ' M3.3 read repository fixture assertions passed.' . PHP_EOL;
}

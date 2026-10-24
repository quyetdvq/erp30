<?php

declare(strict_types=1);

/** Pure SQL/PBAC collaborators; tests the REAL moved Identity/Workflow read services. */
namespace CodeIgniter\Database {
    class BaseConnection
    {
        /** @var array<string,list<array<string,mixed>>> */
        public array $data = [];
        public array $queries = [];
        public function table(string $table): Builder { return new Builder($this, $table); }
        public function error(): array { return ['message' => 'fixture error']; }
    }
    final class Result
    {
        public function __construct(private readonly array $rows) {}
        public function getResultArray(): array { return $this->rows; }
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
    }
    final class Builder
    {
        private array $conditions = [];
        private ?int $limit = null;
        private array $sort = [];
        public function __construct(private readonly BaseConnection $db, private readonly string $table) {}
        public function select(string $fields): self { return $this; }
        public function join(string $table, string $on, string $how = ''): self { return $this; }
        public function where(string $key, mixed $value): self { $this->conditions[$key] = $value; return $this; }
        public function groupBy(string $field): self { return $this; }
        public function orderBy(string $field, string $dir = 'ASC'): self {
            $this->sort[] = [preg_replace('/.*\./', '', $field), strtoupper($dir)]; return $this;
        }
        public function limit(int $limit): self { $this->limit = $limit; return $this; }
        public function get(): Result
        {
            $table = explode(' ', $this->table)[0];
            $this->db->queries[] = $table;
            $rows = array_values(array_filter($this->db->data[$table] ?? [], function (array $row): bool {
                foreach ($this->conditions as $key => $value) {
                    if ((string) ($row[$key] ?? '') !== (string) ($value ?? '')) {
                        return false;
                    }
                }
                return true;
            }));
            usort($rows, function (array $a, array $b): int {
                foreach ($this->sort as [$key, $direction]) {
                    $result = strcmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
                    if ($result !== 0) {
                        return $direction === 'DESC' ? -$result : $result;
                    }
                }
                return 0;
            });
            return new Result($this->limit === null ? $rows : array_slice($rows, 0, $this->limit));
        }
    }
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace CodeIgniter\Shield\Entities {
    class User
    {
        public mixed $id;
        public function __construct(array $row) { $this->id = $row['id']; }
    }
}
namespace App\Modules\Access {
    final class UnifiedAccessService
    {
        public static array $viewed = [];
        public function __construct($actor, $db) {}
        public function canView(string $type, array $row): bool
        {
            self::$viewed[] = $type . ':' . ($row['id'] ?? '');
            return (string) ($row['id'] ?? '') !== '999';
        }
    }
}
namespace {
    use App\Modules\Identity\Management\IdentityReadService;
    use App\Modules\Workflow\Management\WorkflowReadService;
    use App\Modules\Access\UnifiedAccessService;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    use CodeIgniter\HTTP\Exceptions\BadRequestException;

    function config(string $name): object
    {
        return (object) ['tables' => [
            'users' => 'auth_users', 'identities' => 'auth_identities',
            'groups_users' => 'auth_groups_users', 'permissions_users' => 'auth_permissions_users',
        ]];
    }
    require __DIR__ . '/../app/Shared/Read/PositiveId.php';
    require __DIR__ . '/../app/Shared/Media/ImageUrl.php';
    require __DIR__ . '/../app/Shared/Http/AuthorizedListPage.php';
    require __DIR__ . '/../app/Modules/Identity/Management/IdentityReadRepository.php';
    require __DIR__ . '/../app/Modules/Identity/Management/IdentityReadService.php';
    require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadContract.php';
    require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadRepository.php';
    require __DIR__ . '/../app/Modules/Workflow/Management/WorkflowReadService.php';

    $count = 0;
    function assertRead(string $label, bool $success): void
    {
        global $count;
        if (! $success) { throw new RuntimeException('FAIL: ' . $label); }
        echo 'PASS: ' . $label . PHP_EOL;
        ++$count;
    }
    function rejectedRead(string $label, callable $test): void
    {
        try { $test(); } catch (BadRequestException|\InvalidArgumentException) {
            assertRead($label, true); return;
        }
        assertRead($label, false);
    }
    $db = new BaseConnection();
    $db->data = [
        'auth_users' => [
            ['id'=>'1','username'=>'admin','name'=>'Admin','avatar_filename'=>'secret_avatar.png',
                'email'=>'admin@example.test','active'=>1,'status'=>'ACTIVE','created_at'=>'2026-10-23','deleted_at'=>null],
            ['id'=>'999','username'=>'hidden','name'=>'Hidden','avatar_filename'=>null,
                'email'=>'hidden@example.test','active'=>1,'status'=>'ACTIVE','created_at'=>'2026-10-23','deleted_at'=>null],
        ],
        'auth_groups_users' => [
            ['user_id'=>'1','group'=>'admin','code'=>'admin','group_id'=>'6','name'=>'Administrators','status'=>'ACTIVE'],
        ],
        'auth_permissions_users' => [
            ['user_id'=>'1','permission'=>'resource.view','permission_count'=>2],
            ['user_id'=>'1','permission'=>'old.removed','permission_count'=>2],
        ],
        'resource_actions' => [['id'=>'2','resource_action_id'=>'2', 'resource_type'=>'resource','action'=>'view']],
        'workflow_states' => [
            ['id'=>'2','code'=>'DRAFT','name'=>'Draft','status'=>'ACTIVE'],
            ['id'=>'999','code'=>'HIDDEN','name'=>'Hidden','status'=>'ACTIVE'],
        ],
        'lifecycles' => [
            ['id'=>'10','code'=>'APPROVAL','name'=>'Approval','status'=>'ACTIVE'],
            ['id'=>'999','code'=>'SECRET','name'=>'Hidden','status'=>'ACTIVE'],
        ],
        'lifecycle_states' => [
            ['id'=>'70','lifecycle_id'=>'10','state_id'=>'2','sequence'=>1,'is_initial'=>1],
            ['id'=>'71','lifecycle_id'=>'999','state_id'=>'999','sequence'=>1,'is_initial'=>1],
        ],
        'lifecycle_transitions' => [
            ['id'=>'80','lifecycle_id'=>'10','from_state_id'=>'2','to_state_id'=>'2','action_code'=>'reset','status'=>'ACTIVE'],
            ['id'=>'81','lifecycle_id'=>'999','from_state_id'=>'999','to_state_id'=>'999','action_code'=>'hide','status'=>'ACTIVE'],
        ],
    ];
    $actor = new User(['id'=>'1']);
    $identity = new IdentityReadService($actor, $db);
    $workflow = new WorkflowReadService($actor, $db);

    $users = $identity->securityUsers(['page'=>1,'page_size'=>10]);
    assertRead('Security User list filters persisted PBAC before total', $users['total'] === 1
        && count($users['data']) === 1 && $users['data'][0]['id'] === '1');
    assertRead('User projected response omits private avatar filename',
        !array_key_exists('avatar_filename', $users['data'][0])
        && !array_key_exists('password_hash', $users['data'][0]));
    assertRead('User groups and direct permission count remain projected',
        $users['data'][0]['groups'][0]['code'] === 'admin'
        && $users['data'][0]['direct_permission_count'] === 2);
    assertRead('Shield User direct grants intersect live ERP catalog',
        $identity->userPermissions('1') === ['permissions'=>['resource.view']]);
    assertRead('Unauthorized User ID is indistinguishable from missing User',
        $identity->userPermissions('999') === null && $identity->userPermissions('404') === null);
    assertRead('Malformed User ID returns missing without SQL injection',
        $identity->userPermissions('1 OR 1=1') === null);
    $before = count($db->queries);
    rejectedRead('Identity list validates query before SQL', fn()=> $identity->securityUsers(['rogue'=>1]));
    assertRead('Identity rejected query issued zero SQL', count($db->queries) === $before);

    $states = $workflow->listing('workflow_state', ['page'=>1,'page_size'=>10]);
    assertRead('Workflow State total excludes hidden rows', $states['total'] === 1
        && $states['data'][0]['code'] === 'DRAFT');
    $lifecycles = $workflow->listing('lifecycle', ['page'=>1,'page_size'=>10]);
    assertRead('Lifecycle list total excludes hidden rows', $lifecycles['total'] === 1
        && $lifecycles['data'][0]['code'] === 'APPROVAL');
    assertRead('Lifecycle detail remains PBAC-checked',
        $workflow->detail('lifecycle', '10')['code'] === 'APPROVAL'
        && $workflow->detail('lifecycle', '999') === null);
    assertRead('Workflow State detail remains PBAC-checked',
        $workflow->detail('workflow_state','2')['code'] === 'DRAFT'
        && $workflow->detail('workflow_state','999') === null);
    assertRead('Lifecycle State relation filtered by visible parent',
        count($workflow->relation('lifecycle', 'states', '10')) === 1
        && $workflow->relation('lifecycle', 'states', '999') === null);
    assertRead('Lifecycle Transition relation filtered by visible parent',
        count($workflow->relation('lifecycle', 'transitions', '10')) === 1
        && $workflow->relation('lifecycle', 'transitions', '999') === null);
    $before = count($db->queries);
    rejectedRead('Workflow list validates query before SQL', fn()=> $workflow->listing('lifecycle',['unsafe'=>'*']));
    try {
        $workflow->listing('lifecycle', ['include'=>'states']);
        assertRead('Workflow legacy include rejection stays exact', false);
    } catch (BadRequestException $error) {
        assertRead('Workflow legacy include rejection stays exact', $error->getMessage() === 'Malformed list query: include.');
    }
    assertRead('Workflow rejected query issued zero SQL', count($db->queries) === $before);
    rejectedRead('Workflow table selection is fixed', fn()=> $workflow->listing('authorization_group',[]));
    rejectedRead('Workflow relation selection is fixed', fn()=> $workflow->relation('lifecycle','permissions','10'));
    assertRead('Shared PBAC service independently checks Identity and Workflow',
        in_array('user:999', UnifiedAccessService::$viewed, true)
        && in_array('workflow_state:999', UnifiedAccessService::$viewed, true)
        && in_array('lifecycle:999', UnifiedAccessService::$viewed, true));
    echo 'TOTAL: ', $count, ' M3.4C module read/PBAC assertions passed.', PHP_EOL;
}

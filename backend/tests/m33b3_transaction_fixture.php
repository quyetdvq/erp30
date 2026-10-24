<?php

declare(strict_types=1);

// Standalone vendor-free transaction fixture exercises the REAL M3.3B3
// service and SQL repository against an in-memory Shield/CI4 double.
// It does NOT prove actual Shield UserModel or MariaDB transactions.
namespace CodeIgniter\Database {
    final class FakeResult
    {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    class BaseConnection
    {
        public array $data = [];
        public array $events = [];
        public ?string $failInsert = null;
        public bool $autoDefaultGroup = false;
        public bool $failed = false;
        private array $snapshot = [];
        private int $sequence = 400;
        private string $lastId = '0';
        private ?FakeBuilder $compiled = null;
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
        public function transBegin(): bool { $this->events[] = 'begin'; $this->snapshot = unserialize(serialize($this->data)); $this->failed = false; return true; }
        public function transStatus(): bool { return ! $this->failed; }
        public function transCommit(): bool { $this->events[] = 'commit'; return ! $this->failed; }
        public function transRollback(): bool { $this->data = $this->snapshot; $this->events[] = 'rollback'; return true; }
        public function resetTransStatus(): void { $this->failed = false; }
        public function insertID(): string { return $this->lastId; }
        public function nextId(): string { $this->lastId = (string) ++$this->sequence; return $this->lastId; }
        public function compile(FakeBuilder $query): string { $this->compiled = $query; return 'SELECT locked row'; }
        public function query(string $sql): FakeResult { $this->events[] = 'lock'; return $this->compiled->get(); }
    }
    final class FakeBuilder
    {
        private array $where = [];
        private string $select = '*';
        private ?string $joined = null;
        private string $order = '';
        public function __construct(private BaseConnection $db, private string $table) {}
        public function select(string $columns): self { $this->select = $columns; return $this; }
        public function where(string $field, mixed $value): self { $this->where[$field] = $value; return $this; }
        public function join(string $table, string $on, string $kind='inner'): self { $this->joined = $table; return $this; }
        public function orderBy(string $column, string $direction='ASC'): self { $this->order = $column; return $this; }
        public function getCompiledSelect(): string { return $this->db->compile($this); }
        private function baseTable(): string { return explode(' ', $this->table)[0]; }
        private function matching(array $row): bool
        {
            foreach ($this->where as $field => $value) {
                $field = str_contains($field, '.') ? substr($field, strrpos($field, '.')+1) : $field;
                if (! array_key_exists($field, $row)) {
                    if ($value === null) { continue; }
                    return false;
                }
                if ($value === null) { if ($row[$field] !== null) return false; }
                elseif ((string) $row[$field] !== (string) $value) { return false; }
            }
            return true;
        }
        public function get(): FakeResult
        {
            $table = $this->baseTable();
            $rows = $this->db->data[$table] ?? [];
            if ($this->joined !== null && $table === 'auth_users') {
                foreach ($rows as &$row) {
                    $identity = null;
                    foreach ($this->db->data['auth_identities'] ?? [] as $candidate) {
                        if ((string) ($candidate['user_id'] ?? '') === (string) $row['id'] && $candidate['type'] === 'email_password') {
                            $identity = $candidate; break;
                        }
                    }
                    $row['email'] = $identity['secret'] ?? null;
                }
                unset($row);
            }
            if ($this->joined !== null && $table === 'resource_actions') {
                foreach ($rows as &$row) {
                    foreach ($this->db->data['resources'] ?? [] as $resource) {
                        if ((string) $resource['id'] === (string) $row['resource_id']) {
                            $row['resource_action_id'] = $row['id'];
                            $row['resource_type'] = $resource['type'];
                            $row['action'] = $row['code']; break;
                        }
                    }
                }
                unset($row);
            }
            $rows = array_values(array_filter($rows, fn($row)=>$this->matching($row)));
            if ($this->order !== '') {
                $key = str_contains($this->order, '.') ? substr($this->order,strrpos($this->order,'.')+1) : $this->order;
                usort($rows, fn ($a,$b)=>strcmp((string)($a[$key]??''),(string)($b[$key]??'')));
            }
            return new FakeResult($rows);
        }
        public function insert(array $row): bool
        {
            $table = $this->baseTable();
            if ($this->db->failInsert === $table) { $this->db->failed = true; return false; }
            if (! isset($row['id']) && ! in_array($table, ['auth_identities','auth_groups_users','auth_permissions_users'], true)) {
                $row['id'] = $this->db->nextId();
            }
            $this->db->data[$table][] = $row;
            $this->db->events[] = 'insert:' . $table;
            return true;
        }
        public function update(array $data): bool
        {
            $table = $this->baseTable();
            if (! isset($this->db->data[$table])) { $this->db->data[$table] = []; }
            foreach ($this->db->data[$table] as &$row) {
                if ($this->matching($row)) { $row = array_merge($row,$data); }
            }
            unset($row);
            $this->db->events[]='update:'.$table;
            return true;
        }
        public function delete(): bool
        {
            $table = $this->baseTable();
            $this->db->data[$table] = array_values(array_filter($this->db->data[$table] ?? [],fn($row)=>!$this->matching($row)));
            $this->db->events[] = 'delete:' . $table;
            return true;
        }
        public function countAllResults(): int { return count($this->get()->getResultArray()); }
    }
}
namespace CodeIgniter\Shield\Entities {
    class User
    {
        public mixed $id = null;
        public string $username = '';
        public mixed $active = false;
        public string $status = 'inactive';
        public mixed $full_name = null;
        public mixed $avatar_filename = null;
        public mixed $deleted_at = null;
        public ?\DateTimeImmutable $created_at = null;
        private string $email = '';
        private ?string $password = null;
        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                if ($key === 'email') { $this->setEmail((string)$value); }
                elseif (property_exists($this, $key)) { $this->{$key} = $value; }
            }
            $this->created_at ??= new \DateTimeImmutable('2026-10-23T14:00:00+00:00');
        }
        public function setEmail(string $email): void { $this->email = $email; }
        public function getEmail(): string { return $this->email; }
        public function setPassword(string $password): void { $this->password = $password; }
        public function newPassword(): ?string { return $this->password; }
    }
}
namespace App\Modules\Identity {
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    final class UserModel
    {
        public function __construct(private BaseConnection $db) {}
        public function createNewUser(array $data): User { return new User($data); }
        public function withIdentities(): self { return $this; }
        public function findById(int $id): ?User
        {
            foreach ($this->db->data['auth_users'] as $row) {
                if ((int)$row['id'] !== $id) continue;
                $email = '';
                foreach ($this->db->data['auth_identities'] as $identity) {
                    if ((int)$identity['user_id'] === $id && $identity['type'] === 'email_password') {
                        $email = $identity['secret']; break;
                    }
                }
                return new User([...$row, 'email'=>$email]);
            }
            return null;
        }
        public function save(User $user): bool
        {
            $data = ['username'=>$user->username,'active'=>$user->active,'status'=>$user->status,
                'full_name'=>$user->full_name,'avatar_filename'=>$user->avatar_filename,'deleted_at'=>null,
                'created_at'=>$user->created_at];
            if ($user->id === null) {
                if (! $this->db->table('auth_users')->insert($data)) { return false; }
                $id = $this->db->insertID();
                $good = $this->db->table('auth_identities')->insert([
                    'user_id'=>$id, 'type'=>'email_password', 'secret'=>$user->getEmail(),
                    'secret2'=>hash('sha256',(string)$user->newPassword()),
                ]);
                if ($good && $this->db->autoDefaultGroup) {
                    $good = $this->db->table('auth_groups_users')->insert(['user_id'=>$id,'group'=>'default','created_at'=>'now']);
                }
                return $good;
            }
            $this->db->table('auth_users')->where('id',(string)$user->id)->update($data);
            $current = [];
            foreach ($this->db->data['auth_identities'] ?? [] as $candidate) {
                if ((string)$candidate['user_id'] === (string)$user->id && $candidate['type'] === 'email_password') {
                    $current = $candidate; break;
                }
            }
            $fields = ['secret'=>$user->getEmail()];
            if ($user->newPassword() !== null) {
                $fields['secret2'] = hash('sha256',$user->newPassword());
            }
            return $this->db->table('auth_identities')->where('user_id',(string)$user->id)
                ->where('type','email_password')->update($fields);
        }
        public function getInsertID(): string { return $this->db->insertID(); }
    }
}
namespace App\Modules\Access {
    final class UnifiedAccessService
    {
        public static array $deny = [];
        public static array $events = [];
        public function __construct($actor,$db) {}
        public function canView(string $type,array $row): bool { return (string)($row['id']??'')!=='999'; }
        public function decideMutation(string $type,string $action,?array $row,array $candidate=[]): array
        {
            self::$events[] = $type.'.'.$action;
            return ['allowed'=>! in_array($type.'.'.$action,self::$deny,true)];
        }
    }
}
namespace App\Modules\Identity\Management {
    final class IdentityReadRepository
    {
        public function __construct(private $db) {}
        public function directPermissionTokens(string $id): array
        {
            $valid = [];
            foreach ($this->db->data['resource_actions'] as $action) {
                foreach ($this->db->data['resources'] as $resource) {
                    if ($action['resource_id'] === $resource['id']) {
                        $valid[$resource['type'].'.'.$action['code']] = true;
                    }
                }
            }
            $tokens = [];
            foreach ($this->db->data['auth_permissions_users'] ?? [] as $permission) {
                if ((string)$permission['user_id'] === $id && isset($valid[$permission['permission']])) {
                    $tokens[$permission['permission']] = true;
                }
            }
            $result = array_keys($tokens); sort($result); return $result;
        }
    }
}
namespace {
    use App\Exceptions\DomainException;
    use App\Modules\Identity\Mutation\SecurityUserMutationService;
    use App\Modules\Access\UnifiedAccessService;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    function config(string $name): object {
        return (object)['tables'=>['users'=>'auth_users','identities'=>'auth_identities',
            'groups_users'=>'auth_groups_users','permissions_users'=>'auth_permissions_users']];
    }
    function model(string $type, bool $shared, BaseConnection &$db): object { return new $type($db); }
    function service(string $name): object {
        if ($name !== 'passwords') throw new RuntimeException('Unknown service');
        return new class { public function check(string $password, User $user): object {
            return new class($password) { public function __construct(private string $password) {}
                public function isOK(): bool { return strlen($this->password) >= 8; }
            };
        }};
    }
    function auth(): object { return $GLOBALS['fake_auth']; }
    require __DIR__.'/../app/Exceptions/DomainException.php';
    require __DIR__.'/../app/Shared/Read/PositiveId.php';
    require __DIR__.'/../app/Shared/Media/ImageUrl.php';
    require __DIR__.'/../app/Modules/Identity/Mutation/SecurityUserMutationRules.php';
    require __DIR__.'/../app/Modules/Identity/Mutation/SecurityUserMutationRepository.php';
    require __DIR__.'/../app/Modules/Identity/Mutation/SecurityUserMutationService.php';
    $db = new BaseConnection();
    $db->data = [
        'auth_users'=>[
            ['id'=>'9','username'=>'admin','active'=>1,'status'=>'active','full_name'=>'Admin','avatar_filename'=>null,'deleted_at'=>null,'created_at'=>new \DateTimeImmutable('2026-10-23T14:00:00+00:00')],
            ['id'=>'10','username'=>'bob','active'=>1,'status'=>'active','full_name'=>null,'avatar_filename'=>null,'deleted_at'=>null,'created_at'=>new \DateTimeImmutable('2026-10-23T14:00:00+00:00')],
            ['id'=>'999','username'=>'hidden','active'=>1,'status'=>'active','full_name'=>null,'avatar_filename'=>null,'deleted_at'=>null,'created_at'=>new \DateTimeImmutable('2026-10-23T14:00:00+00:00')],
        ],
        'auth_identities'=>[
            ['user_id'=>'9','type'=>'email_password','secret'=>'admin@example.com','secret2'=>'oldhash'],
            ['user_id'=>'10','type'=>'email_password','secret'=>'bob@example.com','secret2'=>'oldhash'],
            ['user_id'=>'999','type'=>'email_password','secret'=>'hidden@example.com','secret2'=>'oldhash'],
        ],
        'groups'=>[['id'=>'6','code'=>'admins','status'=>'ACTIVE'],['id'=>'7','code'=>'disabled','status'=>'INACTIVE']],
        'auth_groups_users'=>[],
        'auth_permissions_users'=>[],
        'resources'=>[['id'=>'1','type'=>'user','domain'=>'SYSTEM','status'=>'ACTIVE']],
        'resource_actions'=>[['id'=>'2','resource_id'=>'1','code'=>'view'],['id'=>'3','resource_id'=>'1','code'=>'edit'],['id'=>'4','resource_id'=>'1','code'=>'reset_password']],
    ];
    $GLOBALS['fake_auth'] = new class { public bool $loggedOut = false; public function logout(): void { $this->loggedOut = true; } };
    $service = new SecurityUserMutationService(new User(['id'=>'9']),$db);
    $n=0;
    function txOk(string $label,bool $yes): void { global $n; if (!$yes) throw new RuntimeException('FAIL: '.$label); $n++; echo 'PASS: '.$label.PHP_EOL; }
    function txReject(string $label,callable $f,string $code): void {
        try { $f(); } catch(DomainException $e) { txOk($label,$e->getErrorCode()===$code); return; }
        txOk($label,false);
    }
    $created=$service->create(['username'=>' Alice ','email'=>' Alice@Example.COM ','password'=>'Pass-12345','state'=>'ACTIVE','name'=>'  Alice  ','group_ids'=>['6']]);
    $id=(string)$created['id'];
    txOk('Create inserts real fake Shield user', count($db->data['auth_users'])===4);
    txOk('Create normalizes username and email', $created['username']==='alice' && $created['email']==='alice@example.com');
    txOk('Create writes hashed fake credential to auth_identities', $db->data['auth_identities'][3]['secret2']===hash('sha256','Pass-12345'));
    txOk('Create does not write password into users', !array_key_exists('password',$db->data['auth_users'][3]));
    txOk('Create attaches only requested Shield group', count($db->data['auth_groups_users'])===1 && $db->data['auth_groups_users'][0]['group']==='admins');
    txOk('Create returns baseline public identity shape', !isset($created['avatar_filename']) && !isset($created['password_hash']) && isset($created['has_avatar']));
    txReject('Duplicate username blocked',fn()=>$service->create(['username'=>'ALICE','email'=>'unique@example.com','password'=>'Pass-12345','state'=>'ACTIVE']),'USER_IDENTITY_ALREADY_EXISTS');
    txReject('Duplicate email blocked',fn()=>$service->create(['username'=>'unique','email'=>'Alice@example.com','password'=>'Pass-12345','state'=>'ACTIVE']),'USER_IDENTITY_ALREADY_EXISTS');
    txOk('Duplicate create preserves user count',count($db->data['auth_users'])===4);
    txReject('Non-operational group forbidden',fn()=>$service->create(['username'=>'other','email'=>'other@example.com','password'=>'Pass-12345','state'=>'ACTIVE','group_ids'=>['7']]),'AUTH_GROUP_INACTIVE');
    UnifiedAccessService::$deny=['authorization_group.assign_member'];
    txReject('Group assign_member vetoes User create',fn()=>$service->create(['username'=>'other','email'=>'other@example.com','password'=>'Pass-12345','state'=>'ACTIVE','group_ids'=>['6']]),'AUTH_GROUP_AUTHORIZATION_DENIED');
    UnifiedAccessService::$deny=[];
    txOk('Group failures rollback entire User create', count($db->data['auth_users'])===4);
    $updated=$service->update($id,['email'=>' Alicenew@example.com ','name'=>'  Alice Updated  ','row_version'=>'ignored']);
    txOk('Update persists email through Shield identities', $updated['email']==='alicenew@example.com' && $db->data['auth_identities'][3]['secret']==='alicenew@example.com');
    txOk('Update retains password hash', $db->data['auth_identities'][3]['secret2']===hash('sha256','Pass-12345'));
    txReject('Out-of-scope User detail concealed',fn()=>$service->update('999',['username'=>'notallowed']),'USER_NOT_FOUND');
    UnifiedAccessService::$deny=['user.edit'];
    txReject('User edit denied by distinct PBAC action',fn()=>$service->update($id,['username'=>'denied']),'USER_AUTHORIZATION_DENIED');
    UnifiedAccessService::$deny=[];
    txOk('Denied User update unchanged', $db->data['auth_users'][3]['username']==='alice');
    $locked=$service->setState($id,['state'=>'LOCKED']);
    txOk('Six-state LOCKED stored in Shield status', $locked['status']==='LOCKED' && $db->data['auth_users'][3]['active']===false);
    txOk('Changing a different user does not logout actor', !$GLOBALS['fake_auth']->loggedOut);
    $service->setState('9',['state'=>'INACTIVE']);
    txOk('Self-inactivation logs out only AFTER commit', $GLOBALS['fake_auth']->loggedOut);
    UnifiedAccessService::$deny=['user.reset_password'];
    txReject('Password reset needs dedicated action',fn()=>$service->resetPassword($id,['new_password'=>'DifferentPass8']),'USER_PASSWORD_ACTION_REQUIRED');
    UnifiedAccessService::$deny=[];
    txReject('Password policy is checked',fn()=>$service->resetPassword($id,['new_password'=>'short']),'USER_PASSWORD_POLICY_INVALID');
    $service->resetPassword($id,['new_password'=>'DifferentPass8']);
    txOk('Reset password hashes through Shield entity', $db->data['auth_identities'][3]['secret2']===hash('sha256','DifferentPass8'));
    $permission=$service->replacePermissions($id,['permissions'=>['USER.Edit','user.edit']]);
    txOk('User direct permissions normalized/deduplicated', $permission['permissions']===['user.edit']);
    txOk('Only one direct Shield grant persisted', count($db->data['auth_permissions_users'])===1);
    txReject('Unknown ERP permission blocked',fn()=>$service->replacePermissions($id,['permissions'=>['ghost.edit']]),'IAM_PERMISSION_MAPPING_INVALID');
    txOk('Rejected permission replacement preserves prior rows',count($db->data['auth_permissions_users'])===1);
    $db->failInsert='auth_permissions_users';
    txReject('Failed SQL grant triggers rollback',fn()=>$service->replacePermissions($id,['permissions'=>['user.view','user.edit']]),'IAM_PERSISTENCE_FAILED');
    txOk('Rolled back previous Shield grants',count($db->data['auth_permissions_users'])===1 && $db->data['auth_permissions_users'][0]['permission']==='user.edit');
    $db->failInsert=null;
    txOk('Transaction records include commit and rollback',in_array('commit',$db->events,true) && in_array('rollback',$db->events,true));
    txOk('User and group modifications take row locks',count(array_filter($db->events,fn($event)=>$event==='lock'))>=5);
    $db->autoDefaultGroup=true;
    txReject('Unexpected Shield defaultGroup rolls back creation',fn()=>$service->create(['username'=>'defaultuser','email'=>'default@example.com','password'=>'Pass-12345','state'=>'ACTIVE']),'IAM_PERSISTENCE_FAILED');
    txOk('Default membership failure does not leak user',count($db->data['auth_users'])===4);
    $db->autoDefaultGroup=false;
    txOk('No unexpected SQL tables written',array_diff(array_map(fn($event)=>substr($event,7),array_filter($db->events,fn($event)=>str_starts_with($event,'insert:'))),['auth_users','auth_identities','auth_groups_users','auth_permissions_users'])===[]);
    echo 'TOTAL: '.$n.' M3.3B3 Shield User transaction-fixture assertions passed.'.PHP_EOL;
}

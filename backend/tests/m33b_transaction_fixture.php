<?php

declare(strict_types=1);

/* M3.3B1 in-memory DB fixture: exercise the REAL write service + repository.
 * Only PBAC/state/read-side collaborators are stubs; no vendor or DB needed. */
namespace CodeIgniter\Database {
    class BaseConnection
    {
        /** @var array<string,list<array<string,mixed>>> */
        public array $data = [];
        public array $events = [];
        public ?string $failInsert = null;
        public bool $failed = false;
        private array $before = [];
        private int $idSequence = 1000;
        public int $affected = 0;
        public function affectedRows(): int { return $this->affected; }
        private string $lastId = '';
        private ?FakeBuilder $compiled = null;
        public function table(string $name): FakeBuilder { return new FakeBuilder($this,$name); }
        public function transBegin(): bool { $this->events[]='begin'; $this->before=$this->data; $this->failed=false; return true; }
        public function transStatus(): bool { return ! $this->failed; }
        public function transCommit(): bool { $this->events[]='commit'; return true; }
        public function transRollback(): bool { $this->data=$this->before; $this->events[]='rollback'; return true; }
        public function resetTransStatus(): void { $this->failed=false; }
        public function insertID(): string { return $this->lastId; }
        public function error(): array { return ['code' => 0, 'message' => '']; }
        public function nextId(): string { return $this->lastId=(string)++$this->idSequence; }
        public function compile(FakeBuilder $builder): string { $this->compiled=$builder; return 'SELECT ... FOR UPDATE'; }
        public function query(string $sql): FakeResult { $this->events[]='lock'; return $this->compiled->get(); }
    }
    final class FakeResult
    {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class FakeBuilder
    {
        private array $wheres=[];
        public function __construct(private BaseConnection $db, private string $table) {}
        public function where(string $key, mixed $value): self { $this->wheres[$key]=$value; return $this; }
        public function select(string $cols): self { return $this; }
        public function orderBy(string $column, string $order='ASC'): self { return $this; }
        public function join(string $table, string $on): self { return $this; }
        public function getCompiledSelect(): string { return $this->db->compile($this); }
        private function matches(array $row): bool {
            foreach ($this->wheres as $key=>$value) {
                if ($key==='id !=') { if ((string)($row['id']??'')===(string)$value) return false; continue; }
                if ($key==='deleted_at') { if (($row[$key]??null)!==null) return false; continue; }
                if ((string)($row[$key]??'')!==(string)($value??'')) return false;
            }
            return true;
        }
        public function get(): FakeResult {
            return new FakeResult(array_values(array_filter($this->db->data[$this->table]??[], fn(array $row):bool=>$this->matches($row))));
        }
        public function insert(array $row): bool {
            if ($this->db->failInsert===$this->table) { $this->db->failed=true; $this->db->affected=0; return false; }
            if (!array_key_exists('id',$row) && !in_array($this->table,['group_permissions','auth_groups_users'],true)) $row['id']=$this->db->nextId();
            $this->db->data[$this->table][]=$row; $this->db->affected=1; $this->db->events[]='insert:'.$this->table; return true;
        }
        public function update(array $values): bool {
            if (! isset($this->db->data[$this->table])) { $this->db->data[$this->table]=[]; }
            $this->db->affected=0;
            foreach ($this->db->data[$this->table] as &$row) if ($this->matches($row)) {
                if (array_merge($row,$values)!==$row) { $row=array_merge($row,$values); $this->db->affected++; }
            }
            unset($row); $this->db->events[]='update:'.$this->table; return true;
        }
        public function delete(): bool {
            $this->db->data[$this->table]=array_values(array_filter($this->db->data[$this->table]??[],fn($row)=>!$this->matches($row)));
            $this->db->events[]='delete:'.$this->table; return true;
        }
    }
}
namespace CodeIgniter\Shield\Entities {
    class User { public mixed $id; public function __construct(array $data) { $this->id=$data['id']; } }
}
namespace App\Modules\Access {
    final class UnifiedAccessService
    {
        public static array $deny = [];
        public static array $events = [];
        public function __construct($actor,$db) {}
        public function decideMutation(string $type,string $action,?array $row,array $candidate=[]): array {
            self::$events[]=$type.'.'.$action;
            return ['allowed'=>!in_array($type.'.'.$action,self::$deny,true),'reason'=>'FIXTURE',
                'matched_policy_ids'=>[],'decisive_policy_ids'=>[]];
        }
    }
    final class ResourceStateRepository
    {
        public static int $reassignmentCalls=0;
        public function assertPolicyState(string $type, mixed $stateId): void {
            if ($stateId !== null && !in_array((string)$stateId, ['2','3'], true)) {
                throw new \App\Exceptions\DomainException('POLICY_INVALID_PARTICIPANT',['field'=>'state_id']);
            }
        }
        public function __construct($db) {}
        public function buildDefinition(array $row): array { return $row; }
        public function assertReassignmentSafe(string $type,array $row): void { self::$reassignmentCalls++; }
    }
}
namespace App\Modules\Access\Management {
    final class AccessReadService
    {
        public function __construct($actor,private $db) {}
        public function detail(string $type,string $id): ?array {
            $table=AccessReadContract::table($type);
            foreach ($this->db->data[$table]??[] as $row) if ((string)($row['id']??'')===$id) return $row;
            return null;
        }
    }
}
namespace App\Modules\Workflow\Management {
    // Read-only PBAC-checked projection double used by the Workflow transaction fixture.
    final class WorkflowReadService
    {
        public function __construct($actor, private $db) {}
        public function detail(string $type, string $id): ?array
        {
            $table = ['workflow_state' => 'workflow_states', 'lifecycle' => 'lifecycles'][$type] ?? null;
            if ($table === null) {
                throw new \InvalidArgumentException('Invalid Workflow fixture read type');
            }
            foreach ($this->db->data[$table] ?? [] as $row) {
                if ((string) ($row['id'] ?? '') === $id) {
                    return $row;
                }
            }
            return null;
        }
    }
}
namespace {
    use App\Exceptions\DomainException;
    use App\Modules\Access\Mutation\AccessMutationService;
    use App\Modules\Access\ResourceStateRepository;
    use App\Modules\Access\UnifiedAccessService;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    function config(string $key): object { return (object)['tables'=>[
        'users'=>'users','groups_users'=>'auth_groups_users','permissions_users'=>'auth_permissions_users',
    ]]; }
    require __DIR__.'/../app/Exceptions/DomainException.php';
    require __DIR__.'/../app/Shared/Read/PositiveId.php';
    require __DIR__.'/../app/Modules/Access/ResourceStateCatalog.php';
    require __DIR__.'/../app/Modules/Access/Management/AccessReadContract.php';
    require __DIR__.'/../app/Modules/Access/Mutation/MutationRules.php';
    require __DIR__.'/../app/Modules/Access/Mutation/AccessMutationRepository.php';
    require __DIR__.'/../app/Modules/Access/Mutation/PolicyMutationRepository.php';
    require __DIR__.'/../app/Modules/Access/Mutation/PolicyMutationRules.php';
    require __DIR__.'/../app/Modules/Access/Mutation/AccessMutationService.php';

    $pass=0;
    function assertB(string $label,bool $yes): void {
        global $pass;
        if (! $yes) { fwrite(STDERR,'FAIL: '.$label.PHP_EOL); exit(1); }
        $pass++; echo 'PASS: '.$label.PHP_EOL;
    }
    function expectB(string $label,callable $fn,string $error): void {
        try { $fn(); } catch (DomainException $e) { assertB($label,$e->getErrorCode()===$error); return; }
        assertB($label,false);
    }
    $db=new BaseConnection();
    $db->data=[
        'groups'=>[['id'=>'3','code'=>'ADMIN','name'=>'Admin','status'=>'ACTIVE'],
            ['id'=>'4','code'=>'GUEST','name'=>'Guest','status'=>'INACTIVE']],
        'resources'=>[['id'=>'10','type'=>'resource','name'=>'resource','status'=>'ACTIVE','domain'=>'SYSTEM','lifecycle_id'=>null]],
        'resource_actions'=>[['id'=>'12','resource_id'=>'10','code'=>'view','description'=>null]],
        'users'=>[['id'=>'8','username'=>'demo','deleted_at'=>null]],
        'auth_groups_users'=>[['user_id'=>'8','group'=>'admin','created_at'=>'2026-10-01 00:00:00']],
        'group_permissions'=>[['group_id'=>'3','resource_action_id'=>'12','created_at'=>'2026-10-01 00:00:00']],
    ];
    $actor=new User(['id'=>7]);
    $service=new AccessMutationService($actor,$db);
    $one=$service->createGroup(['code'=>'new','name'=>'  New  ']);
    assertB('Group created with baseline normalized code',$one['code']==='NEW');
    assertB('Group name whitespace baseline retained',$one['name']==='  New  ');
    assertB('Group creation audits actor',$one['created_by']==='7');
    assertB('Group create committed',str_contains(implode(',',$db->events),'commit'));
    expectB('Duplicate group code denied',fn()=>$service->createGroup(['code'=>'new','name'=>'Another']),'AUTH_GROUP_INVALID');
    assertB('Duplicate group did not write',count($db->data['groups'])===3);
    $updated=$service->updateGroup('3',['name'=>'Changed']);
    assertB('Group PATCH applies inside transaction',$updated['name']==='Changed');
    assertB('Group PATCH writes actor audit',$updated['updated_by']==='7');
    expectB('Group update cannot replace nonexistent ID',fn()=>$service->updateGroup('500',['name'=>'X']),'AUTH_GROUP_NOT_FOUND');
    UnifiedAccessService::$deny=['authorization_group.edit'];
    expectB('Denied Group edit is blocked by PBAC',fn()=>$service->updateGroup('3',['name'=>'Blocked']),'AUTH_POLICY_DENIED');
    assertB('Denied Group edit makes no change',$db->data['groups'][0]['name']==='Changed');
    UnifiedAccessService::$deny=[];
    $member=$service->replaceGroupMembers('3',['user_ids'=>[8,8]]);
    assertB('Member replacement writes Shield membership',count($db->data['auth_groups_users'])===1 && $db->data['auth_groups_users'][0]['group']==='admin');
    expectB('Missing target user aborts member replacement',fn()=>$service->replaceGroupMembers('3',['user_ids'=>[999]]),'AUTH_GROUP_TARGET_NOT_FOUND');
    assertB('Missing user leaves original membership',count($db->data['auth_groups_users'])===1);
    $grants=$service->replaceGroupPermissions('3',['resource_action_ids'=>[12,12]]);
    assertB('Permissions deduplicated',count($grants)===1 && (string)$grants[0]['resource_action_id']==='12');
    expectB('Missing resource action aborts grants',fn()=>$service->replaceGroupPermissions('3',['resource_action_ids'=>[999]]),'AUTH_GROUP_RESOURCE_ACTION_NOT_FOUND');
    assertB('Missing action leaves original group permission',count($db->data['group_permissions'])===1);
    expectB('Inactive group may not receive permissions',fn()=>$service->replaceGroupPermissions('4',['resource_action_ids'=>[12]]),'AUTH_GROUP_INACTIVE');
    $created=$service->createResource(['type'=>'user','name'=>'Users','domain'=>'SYSTEM']);
    assertB('Resource creation uses native non-Lifecycle state',$created['type']==='user' && $created['lifecycle_id']===null);
    expectB('Resource type uniqueness enforced',fn()=>$service->createResource(['type'=>'user','name'=>'Users2','domain'=>'SYSTEM']),'RESOURCE_TYPE_ALREADY_EXISTS');
    expectB('Resource domain immutable',fn()=>$service->updateResource('10',['domain'=>'MASTER']),'RESOURCE_DOMAIN_IMMUTABLE');
    $newName=$service->updateResource('10',['name'=>'  CAPTION  ']);
    assertB('Resource name normalized like baseline',$newName['name']==='caption');
    $state=$service->resourceState('10','inactive');
    assertB('Resource set-state returns minimal result',$state===['id'=>'10','status'=>'INACTIVE']);
    $act=$service->createResourceAction('10',['code'=>' VIEW2 ','description'=>'View 2']);
    assertB('Resource Action belongs to correct parent',$act['resource_id']==='10' && $act['code']==='view2');
    expectB('Duplicate Resource Action prevented',fn()=>$service->createResourceAction('10',['code'=>'view2']),'RESOURCE_ACTION_ALREADY_EXISTS');
    expectB('Resource Action code immutable',fn()=>$service->updateResourceAction('10',(string)$act['id'],['code'=>'override']),'RESOURCE_ACTION_CODE_IMMUTABLE');
    $newAct=$service->updateResourceAction('10',(string)$act['id'],['description'=>'Updated']);
    assertB('Resource Action updates description only',$newAct['description']==='Updated');
    UnifiedAccessService::$deny=['resource.delete'];
    expectB('Resource delete PBAC veto',fn()=>$service->deleteResource('10'),'AUTH_POLICY_DENIED');
    assertB('Denied Resource delete preserves record',count($db->data['resources'])===2);
    UnifiedAccessService::$deny=[];
    $service->deleteResourceAction('10',(string)$act['id']);
    assertB('Delete Action removes only target',count($db->data['resource_actions'])===1 && $db->data['resource_actions'][0]['code']==='view');
    $db->failInsert='resource_actions';
    try { $service->createResourceAction('10',['code'=>'broken']); assertB('Inserted despite DB failure',false); }
    catch (\RuntimeException) { assertB('DB INSERT exception propagates without commit',true); }
    assertB('Failed insert rolled back',count($db->data['resource_actions'])===1);
    assertB('Transaction rollback recorded',in_array('rollback',$db->events,true));
    assertB('All attempted writes originate from known catalog tables',!array_filter($db->events,fn($e)=>str_starts_with($e,'insert:') && !in_array(substr($e,7),['groups','resources','resource_actions','auth_groups_users','group_permissions'],true)));
    echo 'TOTAL: '.$pass.' M3.3B1 transaction-fixture assertions passed.'.PHP_EOL;
}

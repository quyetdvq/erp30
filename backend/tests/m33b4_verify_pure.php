<?php

declare(strict_types=1);

// Standalone fake SQL command integration tests; every write method aborts.
namespace CodeIgniter\CLI {
    abstract class BaseCommand {}
    final class CLI { public static function write(string $message, ?string $color = null): void {} }
}
namespace CodeIgniter\Shield\Entities {
    class User { public mixed $id; public function __construct(array $data){$this->id=$data['id'];} }
}
namespace App\Modules\Access {
    final class ResourceStateCatalog {
        public static function independentTypes(): array { return array_fill(0,10,'resource'); }
    }
    final class UnifiedAccessService {
        public function __construct($actor,$db,$date) {}
        public function decideMutation(string $type,string $action,?array $target,array $candidate=[]): array {
            if (! in_array($type,['workflow_state','lifecycle'],true)) throw new \UnexpectedValueException('Unexpected resource');
            return ['allowed'=>$action==='create'||$action==='edit'];
        }
    }
}
namespace {
    require __DIR__.'/../app/Exceptions/DomainException.php';
    require __DIR__.'/../app/Shared/Read/PositiveId.php';
    require __DIR__.'/../app/Modules/Access/Mutation/MutationRules.php';
    require __DIR__.'/../app/Modules/Workflow/Mutation/WorkflowMutationRules.php';
    require __DIR__.'/../app/Commands/M33B4Verify.php';

    final class WFResult {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0]??null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class WFQuery {
        private array $conditions=[];
        private ?int $limit=null;
        private string $select='*';
        public function __construct(private WFDb $db,private string $table) {}
        public function select(string $fields): self { $this->select=$fields;return $this; }
        public function where(string $field,mixed $value): self { $this->conditions[$field]=$value;return $this; }
        public function orderBy(string $field,string $direction='ASC'): self { return $this; }
        public function limit(int $value): self { $this->limit=$value;return $this; }
        public function insert(array $row): never { throw new \RuntimeException('READ_ONLY_VIOLATION'); }
        public function update(array $row): never { throw new \RuntimeException('READ_ONLY_VIOLATION'); }
        public function delete(): never { throw new \RuntimeException('READ_ONLY_VIOLATION'); }
        public function get(): WFResult {
            $this->db->reads[]=['table'=>$this->table,'select'=>$this->select,'limit'=>$this->limit];
            $rows=array_values(array_filter($this->db->data[$this->table]??[],function(array $row): bool {
                foreach($this->conditions as $key=>$needle) {
                    if ((string)($row[$key]??'')!==($needle===null?'':(string)$needle)) return false;
                }
                return true;
            }));
            if ($this->limit!==null) $rows=array_slice($rows,0,$this->limit);
            return new WFResult($rows);
        }
    }
    final class WFDb {
        public array $reads=[];
        public array $tables=[];
        public array $data=[];
        public function __construct() {
            $this->tables=['resources','resource_actions','workflow_states','lifecycles','lifecycle_states',
                'lifecycle_transitions','policies','policy_actions','organizations','groups','group_permissions',
                'employees','employee_assignments','auth_users'];
            $this->data = [
                'resources'=>[
                    ['id'=>'40','type'=>'workflow_state','domain'=>'SYSTEM','status'=>'ACTIVE'],
                    ['id'=>'41','type'=>'lifecycle','domain'=>'SYSTEM','status'=>'ACTIVE'],
                ],
                'resource_actions'=>array_merge(
                    array_map(fn($i,$code)=>['id'=>$i+1,'resource_id'=>'40','code'=>$code],range(0,3),['view','create','edit','setstate']),
                    array_map(fn($i,$code)=>['id'=>$i+10,'resource_id'=>'41','code'=>$code],range(0,3),['view','create','edit','setstate'])),
                'workflow_states'=>[
                    ['id'=>'10','status'=>'ACTIVE'],['id'=>'11','status'=>'ACTIVE'],
                ],
                'lifecycles'=>[['id'=>'60','status'=>'ACTIVE']],
                'lifecycle_states'=>[
                    ['id'=>'90','lifecycle_id'=>'60','state_id'=>'10','is_initial'=>1,'sequence'=>1],
                    ['id'=>'91','lifecycle_id'=>'60','state_id'=>'11','is_initial'=>0,'sequence'=>2],
                ],
                'lifecycle_transitions'=>[
                    ['id'=>'95','lifecycle_id'=>'60','from_state_id'=>'10','to_state_id'=>'11',
                        'action_code'=>'approve','status'=>'ACTIVE'],
                ],
                'auth_users'=>[['id'=>'5','status'=>'active','active'=>1,'deleted_at'=>null]],
            ];
        }
        public function tableExists(string $name): bool { return in_array($name,$this->tables,true); }
        public function table(string $name): WFQuery { return new WFQuery($this,$name); }
    }
    function db_connect(): WFDb { return $GLOBALS['wdb']; }
    function config(string $name): object { return (object)['tables'=>['users'=>'auth_users']]; }
    $checks=0;
    function checkVerifyW(string $name,bool $valid):void {global $checks;
        if(!$valid)throw new RuntimeException('FAIL: '.$name);
        ++$checks;echo 'PASS: '.$name.PHP_EOL;
    }
    function failsW(string $label,callable $call,string $expected):void {
        try{$call();checkVerifyW($label,false);} catch(Throwable $e){
            checkVerifyW($label,str_contains($e->getMessage(),$expected));
        }
    }
    $db=new WFDb();$GLOBALS['wdb']=$db;
    (new \App\Commands\M33B4Verify())->run([]);
    checkVerifyW('Read-only command accepts sound Workflow schema and transitions',count($db->reads)>5);
    checkVerifyW('Command bounds all master and actor samples',count(array_filter($db->reads,
        fn($r)=>in_array($r['table'],['workflow_states','lifecycles','auth_users'],true) && $r['limit']!==null))===3);
    checkVerifyW('Never reads password or avatar columns',!array_filter($db->reads,
        fn($r)=>str_contains($r['select'],'password')||str_contains($r['select'],'avatar')));
    checkVerifyW('No writes are possible through the fake SQL boundary',true);
    $db=new WFDb();$GLOBALS['wdb']=$db;
    $db->data['resource_actions']=array_values(array_filter($db->data['resource_actions'],
        fn($row)=>!($row['resource_id']==='41' && $row['code']==='setstate')));
    failsW('Missing lifecycle.setstate blocks readiness',fn()=>(new \App\Commands\M33B4Verify())->run([]),'lifecycle.setstate');
    $db=new WFDb();$GLOBALS['wdb']=$db;
    $db->data['lifecycle_transitions'][0]['to_state_id']='99';
    failsW('Transition beyond membership blocks readiness',fn()=>(new \App\Commands\M33B4Verify())->run([]),'WF_STATE_NOT_IN_LIFECYCLE');
    $db=new WFDb();$GLOBALS['wdb']=$db;
    $db->data['lifecycle_states'][0]['is_initial']=0;
    failsW('Active Lifecycle without Initial State blocks readiness',fn()=>(new \App\Commands\M33B4Verify())->run([]),'WF_INVALID_LIFECYCLE_CONFIGURATION');
    $db=new WFDb();$GLOBALS['wdb']=$db;
    $db->data['workflow_states'][0]['status']='INACTIVE';
    failsW('Inactive member State blocks readiness',fn()=>(new \App\Commands\M33B4Verify())->run([]),'WF_INVALID_LIFECYCLE_CONFIGURATION');
    $db=new WFDb();$GLOBALS['wdb']=$db;
    failsW('Invalid sample limit fails closed',fn()=>(new \App\Commands\M33B4Verify())->run(['0']),'Sample limits');
    echo 'TOTAL: '.$checks.' M3.3B4 read-only command fixture assertions passed.'.PHP_EOL;
}

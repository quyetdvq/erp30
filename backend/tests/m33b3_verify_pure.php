<?php

declare(strict_types=1);

// Vendor-free command integration double. Any attempted write is a hard failure.
namespace CodeIgniter\CLI {
    abstract class BaseCommand {}
    final class CLI { public static function write(string $text, ?string $color=null): void {} }
}
namespace CodeIgniter\Shield\Entities {
    class User { public mixed $id; public function __construct(array $data) { $this->id=$data['id']; } }
}
namespace App\Modules\Access {
    final class UnifiedAccessService {
        public function __construct($actor,$db,$at) {}
        public function decideMutation(string $type,string $action,?array $target,array $candidate=[]): array {
            if ($type!=='user') throw new \UnexpectedValueException('Wrong Resource');
            return ['allowed' => $action==='create' || $action==='edit'];
        }
    }
}
namespace {
    require __DIR__.'/../app/Commands/M33B3Verify.php';
    final class FakeVerifyResult {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0]??null; }
        public function getResultArray(): array { return $this->rows; }
    }
    final class FakeVerifyBuilder {
        private array $wheres=[];
        private ?int $limit=null;
        private string $select='*';
        public function __construct(private FakeVerifyDb $db,private string $table) {}
        public function select(string $fields): self { $this->select=$fields; return $this; }
        public function where(string $field,mixed $value): self { $this->wheres[$field]=$value; return $this; }
        public function orderBy(string $name,string $direction='ASC'): self { return $this; }
        public function limit(int $amount): self { $this->limit=$amount; return $this; }
        public function insert(array $data): never { throw new \RuntimeException('READ_ONLY_VIOLATED'); }
        public function update(array $data): never { throw new \RuntimeException('READ_ONLY_VIOLATED'); }
        public function delete(): never { throw new \RuntimeException('READ_ONLY_VIOLATED'); }
        public function get(): FakeVerifyResult {
            $this->db->reads[]=['table'=>$this->table,'selected'=>$this->select];
            $rows=array_values(array_filter($this->db->data[$this->table]??[],function($row):bool {
                foreach($this->wheres as $field=>$value) {
                    if (($row[$field]??null)!==$value && (string)($row[$field]??'')!==(string)$value) return false;
                }
                return true;
            }));
            if($this->limit!==null) $rows=array_slice($rows,0,$this->limit);
            return new FakeVerifyResult($rows);
        }
    }
    final class FakeVerifyDb {
        public array $reads=[];
        public array $data=[];
        public array $knownTables=[];
        public function __construct() {
            $this->data=[
                'resources'=>[['id'=>'1','type'=>'user','domain'=>'SYSTEM','status'=>'ACTIVE']],
                'resource_actions'=>array_map(fn($i,$a)=>['id'=>(string)(10+$i),'resource_id'=>'1','code'=>$a],
                    range(0,5),
                    ['view','create','edit','setstate','reset_password','assign_permission']),
                'auth_users'=>[['id'=>'9','status'=>'active','active'=>1,'deleted_at'=>null],['id'=>'10','status'=>'locked','active'=>0,'deleted_at'=>null]],
            ];
            $this->knownTables=['resources','resource_actions','groups','group_permissions','organizations',
                'workflow_states','policies','policy_actions','auth_users','auth_identities','auth_groups_users','auth_permissions_users'];
        }
        public function tableExists(string $table): bool { return in_array($table,$this->knownTables,true); }
        public function table(string $name): FakeVerifyBuilder { return new FakeVerifyBuilder($this,$name); }
    }
    function db_connect(): FakeVerifyDb { return $GLOBALS['verifyDb']; }
    function config(string $what): object { return (object)['tables'=>[
        'users'=>'auth_users','identities'=>'auth_identities','groups_users'=>'auth_groups_users',
        'permissions_users'=>'auth_permissions_users']];
    }
    $n=0;
    function verifyU(bool $yes,string $label):void {global $n;if(!$yes)throw new \RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
    $db=new FakeVerifyDb();$GLOBALS['verifyDb']=$db;
    (new \App\Commands\M33B3Verify())->run([]);
    verifyU(count($db->reads)===3,'Only three bounded catalog/target SQL reads');
    verifyU($db->reads[2]===['table'=>'auth_users','selected'=>'id, active, status'],'Never selects password or private avatar metadata');
    verifyU(!array_filter($db->reads,fn($r)=>str_contains($r['selected'],'secret2') || str_contains($r['selected'],'password_hash')),'Password hashes never queried');
    $db=new FakeVerifyDb();$db->data['resource_actions']=array_values(array_filter($db->data['resource_actions'],fn($r)=>$r['code']!=='reset_password'));
    $GLOBALS['verifyDb']=$db;
    try{(new \App\Commands\M33B3Verify())->run([]);verifyU(false,'Missing reset_password must fail closed');}
    catch(\UnexpectedValueException $error){verifyU(str_contains($error->getMessage(),'reset_password'),'Missing reset_password must fail closed');}
    $db=new FakeVerifyDb();$db->data['auth_users'][0]['status']='deleted';$GLOBALS['verifyDb']=$db;
    try{(new \App\Commands\M33B3Verify())->run([]);verifyU(false,'Unsupported persisted status blocked');}
    catch(\UnexpectedValueException $error){verifyU(str_contains($error->getMessage(),'Invalid persisted Shield User State'),'Unsupported persisted status blocked');}
    $db=new FakeVerifyDb();$db->knownTables=array_values(array_diff($db->knownTables,['auth_identities']));$GLOBALS['verifyDb']=$db;
    try{(new \App\Commands\M33B3Verify())->run([]);verifyU(false,'Missing Shield identity schema blocked');}
    catch(\UnexpectedValueException $error){verifyU(str_contains($error->getMessage(),'auth_identities'),'Missing Shield identity schema blocked');}
    $db=new FakeVerifyDb();$GLOBALS['verifyDb']=$db;
    try{(new \App\Commands\M33B3Verify())->run(['0']);verifyU(false,'Invalid sample limits blocked');}
    catch(\UnexpectedValueException $error){verifyU(str_contains($error->getMessage(),'sample limits'),'Invalid sample limits blocked');}
    echo 'TOTAL: '.$n.' M3.3B3 read-only command fixtures passed.'.PHP_EOL;
}

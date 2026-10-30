<?php

declare(strict_types=1);

namespace CodeIgniter\Database {
    class BaseConnection
    {
        public int $transDepth=0;
        public bool $writeFails=false;
        /** @var list<array<string,mixed>> */
        public array $rows=[];
        public function table(string $name): object
        {
            if ($name !== 'business_history_events') throw new \RuntimeException('Unexpected table.');
            return new class($this) {
                public function __construct(private readonly BaseConnection $db) {}
                public function insert(array $record): bool
                {
                    if ($this->db->writeFails) return false;
                    foreach ($this->db->rows as $previous) {
                        if ($record['create_marker'] === 'CREATE' && $previous['create_marker'] === 'CREATE'
                            && $record['resource_type'] === $previous['resource_type']
                            && $record['resource_id'] === $previous['resource_id']) {
                            throw new \RuntimeException('SQL UNIQUE violation');
                        }
                    }
                    $this->db->rows[]=$record;
                    return true;
                }
            };
        }
        public function transStatus(): bool { return !$this->writeFails; }
    }
}
namespace App\Shared\Http {
    final class RequestMetadata { public static function id(): string {return 'his01-request';} }
}
namespace {
    require_once __DIR__ . '/../../app/Modules/History/HistoryPolicy.php';
    require_once __DIR__ . '/../../app/Modules/History/HistoryRecorder.php';
    use App\Modules\History\HistoryRecorder;
    use CodeIgniter\Database\BaseConnection;

    $db=new BaseConnection();$rec=new HistoryRecorder($db);$n=0;
    $ok=static function(bool $condition,string $why)use(&$n):void{
        if(!$condition) throw new \RuntimeException('HIS RECORDER: '.$why);$n++;
    };
    try{$rec->created('customer','17','1','DRAFT');throw new \RuntimeException('Committed outside transaction');}
    catch(\RuntimeException $error){$ok($error->getMessage()==='HISTORY_REQUIRES_OWNING_TRANSACTION','reject outside transaction');}
    $db->transDepth=1;
    $rec->created('customer','17','1','DRAFT');
    $ok(count($db->rows)===1,'one create');
    $ok($db->rows[0]['create_marker']==='CREATE','unique marker set');
    $ok($db->rows[0]['request_id']==='his01-request','server request ID');
    try{$rec->created('customer','17','1','DRAFT');throw new \RuntimeException('Duplicate create accepted');}
    catch(\RuntimeException $error){$ok($error->getMessage()==='HISTORY_PERSISTENCE_FAILED','duplicate CREATE rejected');}
    $old=['status'=>'DRAFT','name'=>'A','phone'=>'0912345678','company_ids'=>['1']];
    $draft=[...$old,'name'=>'B'];
    $rec->updated('customer','17','1',$old,$draft);
    $ok(count($db->rows)===1,'draft edits not inserted');
    $rec->transitioned('customer','17','1','DRAFT','ACTIVE','setstate',
        ['status'=>'ACTIVE','customer_code'=>'CUS-17','name'=>'B','phone'=>'0912345678','company_ids'=>['1']]);
    $ok(count($db->rows)===2,'state transition captured');
    $ok($db->rows[1]['checkpoint_json']!==null,'activation checkpoint');
    $ok(!str_contains($db->rows[1]['checkpoint_json'],'0912345678'),'checkpoint PII redacted');
    $active=['status'=>'ACTIVE','name'=>'B','phone'=>'0912345678','company_ids'=>['1']];
    $next=[...$active,'name'=>'Company PII','phone'=>'+84912345678'];
    $rec->updated('customer','17','1',$active,$next);
    $ok(count($db->rows)===3,'active edit captured');
    $ok(!str_contains($db->rows[2]['changes_json'],'Company PII'),'changed name redacted');
    $ok(!str_contains($db->rows[2]['changes_json'],'0912345678'),'changed phone redacted');
    $rec->updated('customer','17','1',$next,$next);
    $ok(count($db->rows)===3,'no-op ignored');
    $rec->decision('supplier','21','1','APPROVE','approve','PENDING','ACTIVE',['status'=>'ACTIVE','supplier_code'=>'SUP-21']);
    $ok(count($db->rows)===4,'explicit approve');
    $rec->sensitiveChange('supplier','21','1','bank_account','bank_replace','ACTIVE');
    $ok(count($db->rows)===5,'bank change captured');
    $ok(!str_contains($db->rows[4]['changes_json'],'123456'),'no bank data');
    $ok($db->rows[2]['changes_json']==='{"name":{},"phone":{}}', 'compact redacted UPDATE stored');
    $ok($db->rows[1]['changes_json']==='{"status":["DRAFT","ACTIVE"]}', 'compact workflow state diff');
    $ok($db->rows[4]['changes_json']==='{"bank_account":{}}', 'compact bank marker');
    try{$rec->sensitiveChange('customer','17','1','phone','bank_replace','ACTIVE');throw new \RuntimeException('Unsafe sensitive field accepted');}
    catch(\InvalidArgumentException){$ok(true,'unapproved sensitive event rejected');}
    try{$rec->decision('customer','17','1','APPROVE','setstate','DRAFT','ACTIVE',[]);throw new \RuntimeException('Generic setstate mislabeled');}
    catch(\InvalidArgumentException){$ok(true,'generic setstate cannot impersonate approval');}
    $db->writeFails=true;
    try{$rec->created('customer','18','1','DRAFT');throw new \RuntimeException('Insert failure ignored');}
    catch(\RuntimeException $error){$ok($error->getMessage()==='HISTORY_PERSISTENCE_FAILED','failed audit is fatal');}
    echo "HIS-01 RECORDER FIXTURE: {$n}/{$n} PASS\n";
}

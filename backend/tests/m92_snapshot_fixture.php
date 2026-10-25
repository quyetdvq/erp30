<?php

declare(strict_types=1);

// Offline SQL integration fixture. No vendor, network, MariaDB or CodeIgniter
// bootstrap is needed: the minimal connection stub rejects every DML verb.
namespace CodeIgniter\Database {
    class BaseConnection
    {
        public string $DBPrefix = '';
        public function query(string $sql, array $binds = []): mixed { throw new \LogicException('Abstract fake connection.'); }
    }
}

namespace {
    require_once __DIR__.'/../tools/m92/M92DbAudit.php';

    class M92FakeResult
    {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }

    class M92FakeConnection extends \CodeIgniter\Database\BaseConnection
    {
        public array $statements = [];
        public string $actualDatabase = 'erp';
        public string $isolation = 'REPEATABLE-READ';
        public array $engines = [
            ['TABLE_NAME'=>'dinktech_import_jobs','ENGINE'=>'InnoDB'],
            ['TABLE_NAME'=>'dinktech_import_job_rows','ENGINE'=>'InnoDB'],
        ];
        public array $jobs = [];
        public array $groups = [];
        public int $orphanRows = 0;
        public int $sourceJobCount = -1;
        public int $sourceRowCount = -1;
        public bool $failAggregation = false;
        public function __construct() { $this->DBPrefix='dinktech_'; }
        public function query(string $sql, array $binds=[]): M92FakeResult
        {
            $this->statements[] = [$sql,$binds];
            $text=strtoupper(trim($sql));
            if ($text==='START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY' || $text==='ROLLBACK') {
                return new M92FakeResult([]);
            }
            if (str_starts_with($text,'INSERT') || str_starts_with($text,'UPDATE') || str_starts_with($text,'DELETE')
                || str_starts_with($text,'DROP') || str_starts_with($text,'ALTER') || str_starts_with($text,'TRUNCATE')) {
                throw new \RuntimeException('Write attempted during READ ONLY fixture.');
            }
            if ($text==='SELECT DATABASE() AS DB') return new M92FakeResult([['db'=>$this->actualDatabase]]);
            if (str_contains($text, 'INFORMATION_SCHEMA.TABLES')) {
                if ($binds !== ['dinktech_import_jobs','dinktech_import_job_rows']) throw new \RuntimeException('Unsafe metadata binds');
                return new M92FakeResult($this->engines);
            }
            if ($text==='SELECT @@TX_ISOLATION AS ISOLATION') return new M92FakeResult([['isolation'=>$this->isolation]]);
            if (str_contains($text,'COUNT(*) AS N') && str_contains($text,'LEFT JOIN')) {
                return new M92FakeResult([['n'=>(string)$this->orphanRows]]);
            }
            if (str_contains($text,'COUNT(*) AS N') && str_contains($text,'JOIN')) {
                return new M92FakeResult([['n'=>(string)($this->sourceRowCount < 0 ? array_sum(array_column($this->groups, 'recorded_rows')) : $this->sourceRowCount)]]);
            }
            if (str_contains($text,'COUNT(*) AS N')) {
                if ($binds !== ['PRODUCT']) throw new \RuntimeException('Missing Product scope bind');
                return new M92FakeResult([['n'=>(string)($this->sourceJobCount < 0 ? count($this->jobs) : $this->sourceJobCount)]]);
            }
            if (str_contains($text,'SELECT ID, ENTITY_TYPE') && str_contains($text,'FROM `DINKTECH_IMPORT_JOBS`')) {
                if ($binds !== ['PRODUCT'] || !str_contains($text, 'ORDER BY ID ASC LIMIT')) {
                    throw new \RuntimeException('Unbounded list read');
                }
                return new M92FakeResult($this->jobs);
            }
            if (str_contains($text,'COUNT(DISTINCT R.ROW_NUMBER)')) {
                if ($binds !== ['PRODUCT'] || !str_contains($text, 'GROUP BY R.IMPORT_JOB_ID')
                    || !str_contains($text, "BINARY R.ROW_STATUS = 'SUCCESS'")) {
                    throw new \RuntimeException('Unsafe SQL journal aggregation');
                }
                if ($this->failAggregation) throw new \RuntimeException('Fake database outage during snapshot');
                return new M92FakeResult($this->groups);
            }
            throw new \RuntimeException('Unexpected SQL statement in M9.2: '.$sql);
        }
    }

    $n=0;
    $ok=static function(bool $value,string $name)use(&$n):void {
        if (!$value) throw new \RuntimeException('M9.2 SNAPSHOT FAILED: '.$name);
        $n++;
    };
    $options=M92Plan::args(['erp','DEV_CLONE_ONLY','DEV_READ_ONLY','10','100']);
    $job=['id'=>'5','entity_type'=>'PRODUCT','mode'=>'UPSERT','source_format'=>'CSV','status'=>'COMPLETED',
        'total_rows'=>'1','success_rows'=>'1','error_rows'=>'0'];
    $group=['import_job_id'=>'5','recorded_rows'=>'1','distinct_rows'=>'1','min_row_number'=>'2',
        'success_rows'=>'1','error_rows'=>'0','skipped_rows'=>'0','invalid_status_rows'=>'0','invalid_payload_rows'=>'0'];
    $db=new M92FakeConnection();
    $db->jobs=[$job]; $db->groups=[$group];
    $status=(new M92DbAudit($db))->run($options);
    $ok($status['status']==='PASS_DEV_IMPORT_JOURNAL_SNAPSHOT','fully valid Product job can PASS');
    $sql=array_column($db->statements,0);
    $ok(in_array('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', $sql, true),'read-only snapshot issued');
    $ok(end($sql)==='ROLLBACK','snapshot always rolled back');
    $ok(!str_contains(json_encode($status,JSON_THROW_ON_ERROR),'"id":"5"'), 'no raw job IDs in report');
    $db=new M92FakeConnection();
    $db->actualDatabase='erp_prod';
    try { (new M92DbAudit($db))->run($options); throw new \RuntimeException('accepted wrong DB'); }
    catch (\RuntimeException $e) { $ok(str_contains($e->getMessage(),'Connected DB'), 'wrong DB rejected before snapshot'); }
    $ok(count($db->statements)===1,'wrong DB cannot access journal tables');
    $db=new M92FakeConnection();
    $db->engines[1]['ENGINE']='MyISAM';
    try { (new M92DbAudit($db))->run($options); throw new \RuntimeException('accepted MyISAM'); }
    catch (\RuntimeException $e) { $ok(str_contains($e->getMessage(),'InnoDB'), 'nontransactional table rejected'); }
    $db=new M92FakeConnection();
    $db->isolation='READ-COMMITTED';
    try { (new M92DbAudit($db))->run($options); throw new \RuntimeException('accepted read committed'); }
    catch (\RuntimeException $e) { $ok(str_contains($e->getMessage(),'REPEATABLE-READ'), 'snapshot isolation pinned'); }
    $db=new M92FakeConnection();
    $db->sourceJobCount=11;
    $status=(new M92DbAudit($db))->run($options);
    $ok($status['status']==='INCONCLUSIVE_AUDIT_BOUND','max job bound fails closed');
    $ok(end($db->statements)[0]==='ROLLBACK','bounded early return rolls back');
    $db=new M92FakeConnection();
    $db->sourceRowCount=101;
    $status=(new M92DbAudit($db))->run($options);
    $ok($status['status']==='INCONCLUSIVE_AUDIT_BOUND','max row bound fails closed');
    $ok(end($db->statements)[0]==='ROLLBACK','bounded row count rolls back');
    $db=new M92FakeConnection();
    $db->jobs=[$job];$db->groups=[$group];$db->failAggregation=true;
    try { (new M92DbAudit($db))->run($options); throw new \RuntimeException('accepted SQL failure'); }
    catch (\RuntimeException $e) { $ok(str_contains($e->getMessage(),'Fake database outage'),'SQL failure surfaced'); }
    $ok(end($db->statements)[0]==='ROLLBACK','failed query also rolls back');
    $db=new M92FakeConnection();
    $db->jobs=[$job];$db->groups=[$group];$db->orphanRows=3;
    $status=(new M92DbAudit($db))->run($options);
    $ok($status['status']==='FAIL_DEV_IMPORT_JOURNAL_INTEGRITY', 'orphan rows fail snapshot');
    echo 'M9.2 SQL SNAPSHOT OFFLINE FIXTURE: '.$n.'/'.$n." PASS\n";
}

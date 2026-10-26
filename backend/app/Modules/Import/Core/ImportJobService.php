<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Notifications\NotificationFlags;
use App\Modules\Notifications\NotificationOutboxProducer;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/** Shared import orchestration: one bounded job, one durable result per row. */
final class ImportJobService
{
    private readonly UnifiedAccessService $access;

    public function __construct(private readonly User $actor,private readonly BaseConnection $db)
    { $this->access=new UnifiedAccessService($actor,$db); }

    /** @return array<string,mixed> */
    public function start(ImportAdapter $adapter,string $path,string $name,string $mode):array
    {
        if (!in_array($mode,['CREATE_ONLY','UPDATE_ONLY','UPSERT'],true)) {
            throw new DomainException('IMPORT_INVALID_MODE');
        }
        // Both gates happen BEFORE any SQL writes.
        $adapter->authorizeStart();
        $template=$adapter->template();
        $parsed=(new ImportFileReader())->read($path,$name,$template);
        $work=ImportRowPlanner::order($parsed['rows'],$template['record_types']);
        $this->assertInnoDb($adapter);
        $now=gmdate('Y-m-d H:i:s');
        $this->write('create_job',fn()=> $this->db->table('import_jobs')->insert([
            'entity_type'=>$adapter->entityType(),'mode'=>$mode,'source_format'=>$parsed['format'],
            'source_file_name'=>$name,'source_file_hash'=>hash_file('sha256',$path),
            'status'=>'PENDING','total_rows'=>count($work),'success_rows'=>0,'error_rows'=>0,
            'created_by'=>(string)$this->actor->id,'created_at'=>$now,
        ]));
        $id=(string)$this->db->insertID();
        if (!preg_match('/\A[1-9][0-9]*\z/D',$id)) throw new DomainException('IMPORT_JOB_FAILED');
        $this->setJob($id,['status'=>'PROCESSING','started_at'=>gmdate('Y-m-d H:i:s')]);
        $results=[];$sources=[];$fatal=false;$success=0;$errors=0;
        try {
            // Journal each row as soon as its business transaction completes.
            // Business services own their transactions; this is NOT a whole-job
            // transaction. Persisted row numbers retain original input positions.
            foreach ($work as $entry) {
                $this->processRow($entry,$mode,$adapter,$sources,$results,$fatal);
                $number=$entry['number'];$result=$results[$number];
                $this->write('record_row',fn()=> $this->db->table('import_job_rows')->insert([
                    'import_job_id'=>$id,'row_number'=>$number,'row_status'=>$result['status'],
                    'entity_key'=>$result['key'],'target_ref_id'=>$result['id'],
                    'error_code'=>$result['error'],'error_message'=>$result['error'],
                    'created_at'=>gmdate('Y-m-d H:i:s'),
                ]));
                if ($result['status']==='SUCCESS') $success++;
                if ($result['status']==='ERROR') $errors++;
                $this->setJob($id,['success_rows'=>$success,'error_rows'=>$errors]);
            }
        } catch (Throwable $e) {
            // An earlier business mutation may already have committed.
            // Never auto-retry; preserve the partial journal for manual review.
            try {
                $this->finalizeTerminal($id,'FAILED',$success,$errors);
            } catch (Throwable $finalizeError) {
                log_message('error', 'M11 Import terminal FAILED finalize failed; job={job}: {error}', [
                    'job' => $id, 'error' => $finalizeError->getMessage(),
                ]);
                // Database may be unavailable. The PROCESSING journal remains
                // visible for an operator to investigate and reconcile.
            }
            throw new DomainException('IMPORT_JOB_FAILED',['phase'=>'journal'], $e);
        }
        // NEVER put successful finalize inside the row-processing catch block.
        // If the outbox INSERT fails, terminal UPDATE rolls back to PROCESSING.
        $status=$fatal?'FAILED':($errors>0?'COMPLETED_WITH_ERRORS':'COMPLETED');
        try {
            $this->finalizeTerminal($id,$status,$success,$errors);
        } catch (Throwable $e) {
            throw new DomainException('IMPORT_JOB_FAILED',['phase'=>'finalize'], $e);
        }
        // The creator is entitled to the 201 job envelope by product.import;
        // import_job.view is independently required by the three GET routes.
        $created=$this->db->table('import_jobs')->where('id',$id)->get()->getRowArray();
        if ($created===null) throw new DomainException('IMPORT_JOB_FAILED');
        return $this->projectJob($created);
    }

    /** @param array{number:int,cells:array<string,string|null>} $entry
     *  @param array<string,array{id:string,code:string}> $sources
     *  @param array<int,array{status:string,id:?string,key:?string,error:?string}> $results
     */
    private function processRow(array $entry,string $mode,ImportAdapter $adapter,array &$sources,array &$results,bool &$fatal):void
    {
        $number=$entry['number'];$cells=$entry['cells'];
        $key=$cells['source_key']??$cells['product_code']??null;
        if ($fatal) {
            $results[$number]=['status'=>'SKIPPED','id'=>null,'key'=>$key,'error'=>'IMPORT_PREVIOUS_FATAL_ERROR'];
            return;
        }
        try {
            $result=$adapter->execute($cells,$mode,$sources);
            $results[$number]=['status'=>'SUCCESS','id'=>$result['target_ref_id'],'key'=>$result['entity_key'],'error'=>null];
        } catch (DomainException $e) {
            $results[$number]=['status'=>'ERROR','id'=>null,'key'=>$key,
                'error'=>substr($e->getErrorCode(),0,100)];
            // An authorization failure is a job-stopping security event; never
            // continue after permissions or assignments have been revoked.
            if (str_ends_with($e->getErrorCode(),'AUTHORIZATION_DENIED')) $fatal=true;
        } catch (Throwable) {
            $results[$number]=['status'=>'ERROR','id'=>null,'key'=>$key,'error'=>'IMPORT_ROW_FAILED'];
            $fatal=true;
        }
    }

    /** @param array<string,mixed> $raw @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function listJobs(array $raw,ImportAdapter $adapter):array
    {
        $query=ImportListQuery::parse($raw);
        $statuses=$this->requireViewScope($adapter);
        if ($statuses===[]) return ['data'=>[],'page'=>$query['page'],'page_size'=>$query['page_size'],'total'=>0];
        $sql=ImportJobSql::listJobs($statuses,$query,(string)$this->db->DBPrefix,$adapter->entityType());
        $total=(int)$this->select($sql['count_sql'],$sql['count_binds'])->getRowArray()['total'];
        $result=[];
        foreach ($this->select($sql['rows_sql'],$sql['rows_binds'])->getResultArray() as $row) {
            if (!$adapter->canViewJob($this->access,$row)) {
                throw new UnexpectedValueException('Import Job SQL/PBAC scope mismatch.');
            }
            $result[]=$this->projectJob($row);
        }
        return ['data'=>$result,'page'=>$query['page'],'page_size'=>$query['page_size'],'total'=>$total];
    }

    /** @return array<string,mixed> */
    public function findVisible(string $rawId,ImportAdapter $adapter):array
    {
        $id=$this->id($rawId);
        $this->requireViewScope($adapter);
        $job=$this->db->table('import_jobs')->where('id',$id)->where('entity_type',$adapter->entityType())
            ->get()->getRowArray();
        if ($job===null || !$adapter->canViewJob($this->access,$job)) {
            throw new PageNotFoundException('Import job is not available.');
        }
        return $this->projectJob($job);
    }

    /** @param array<string,mixed> $raw @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function rows(string $jobId,array $raw,ImportAdapter $adapter):array
    {
        $id=$this->id($jobId);
        $this->findVisible($id,$adapter);
        $query=ImportListQuery::parse($raw,true);
        $sql=ImportJobSql::listRows($id,$query,(string)$this->db->DBPrefix);
        $total=(int)$this->select($sql['count_sql'],$sql['count_binds'])->getRowArray()['total'];
        $data=[];
        foreach ($this->select($sql['rows_sql'],$sql['rows_binds'])->getResultArray() as $row) {
            $data[]=$this->projectRow($row);
        }
        return ['data'=>$data,'page'=>$query['page'],'page_size'=>$query['page_size'],'total'=>$total];
    }

    /** @return list<string> */
    private function requireViewScope(ImportAdapter $adapter):array
    {
        if (!$this->access->hasBaseViewPermission('import_job')) {
            throw new DomainException('IMPORT_JOB_AUTHORIZATION_DENIED');
        }
        return $adapter->viewableJobStatuses($this->access);
    }

    private function id(string $value):string
    {
        try { return PositiveId::value($value); }
        catch (\InvalidArgumentException $e) { throw new DomainException('IMPORT_JOB_NOT_FOUND',[], $e); }
    }

    /** Atomic terminal transition and outbox creation; does NOT wrap individual row business commits. */
    private function finalizeTerminal(string $id, string $terminal, int $success, int $errors): void
    {
        if (! in_array($terminal, ['COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'], true)) {
            throw new DomainException('IMPORT_JOB_FAILED');
        }
        $table = $this->db->prefixTable('import_jobs');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $table) !== 1) {
            throw new DomainException('IMPORT_JOB_FAILED');
        }
        $this->db->transBegin();
        try {
            $result = $this->db->query("SELECT id,status,created_by FROM `{$table}` WHERE id = ? FOR UPDATE", [$id]);
            $row = $result === false ? null : $result->getRowArray();
            if ($row === null || $row['status'] !== 'PROCESSING') {
                throw new DomainException('IMPORT_JOB_FAILED', ['phase' => 'terminal_state']);
            }
            $ok = $this->db->table('import_jobs')->where('id', $id)->where('status', 'PROCESSING')
                ->set('completed_at', 'UTC_TIMESTAMP(6)', false)
                ->set('updated_at', 'UTC_TIMESTAMP(6)', false)
                ->update(['status' => $terminal, 'success_rows' => $success, 'error_rows' => $errors,
                    'updated_by' => (string) $this->actor->id]);
            if (! $ok || $this->db->affectedRows() !== 1) {
                throw new DomainException('IMPORT_JOB_FAILED', ['phase' => 'terminal_update']);
            }
            NotificationOutboxProducer::enqueueImportTerminal($this->db, $id, $terminal);
            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new DomainException('IMPORT_JOB_FAILED', ['phase' => 'terminal_commit']);
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string,mixed> $changes */
    private function setJob(string $id,array $changes):void
    {
        $this->write('update_job',fn()=> $this->db->table('import_jobs')->where('id',$id)->update([
            ...$changes,'updated_by'=>(string)$this->actor->id,'updated_at'=>gmdate('Y-m-d H:i:s'),
        ]));
    }

    /** @param callable():bool $callback */
    private function write(string $phase,callable $callback):void
    {
        try { if (!$callback()) throw new \RuntimeException('DML failed.'); }
        catch (Throwable $e) { throw new DomainException('IMPORT_JOB_FAILED',['phase'=>$phase],$e); }
    }

    /** @param list<mixed> $binds */
    private function select(string $sql,array $binds):object
    {
        $result=$this->db->query($sql,$binds);
        if ($result===false) throw new DomainException('IMPORT_JOB_FAILED');
        return $result;
    }

    /** Fail closed if any import/business/numbering table cannot roll back. */
    private function assertInnoDb(ImportAdapter $adapter):void
    {
        $names=array_values(array_unique(['import_jobs','import_job_rows',...$adapter->transactionalTables(),
            ...(NotificationFlags::producerEnabled($this->db) ? ['notification_outbox'] : [])]));
        if (count($names)<3 || count($names)>40) throw new DomainException('IMPORT_STORAGE_NOT_TRANSACTIONAL');
        foreach ($names as $name) {
            if (!is_string($name) || !preg_match('/\A[a-z][a-z0-9_]*\z/D',$name)) {
                throw new DomainException('IMPORT_STORAGE_NOT_TRANSACTIONAL');
            }
        }
        $prefix=(string)$this->db->DBPrefix;
        if ($prefix!=='' && !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$prefix)) {
            throw new DomainException('IMPORT_STORAGE_NOT_TRANSACTIONAL');
        }
        $names=array_map(static fn(string $name):string=>$prefix.$name,$names);
        $result=$this->db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE '
            .'TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')',$names);
        if ($result===false) throw new DomainException('IMPORT_STORAGE_NOT_TRANSACTIONAL');
        $engines=[];
        foreach ($result->getResultArray() as $row) {
            $engines[(string)$row['TABLE_NAME']]=strtoupper((string)$row['ENGINE']);
        }
        foreach ($names as $name) {
            if (($engines[$name]??null)!=='INNODB') throw new DomainException('IMPORT_STORAGE_NOT_TRANSACTIONAL');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function projectJob(array $row):array
    {
        $keys=['id','entity_type','mode','source_format','source_file_name','source_file_hash',
            'status','total_rows','success_rows','error_rows','created_by','updated_by',
            'started_at','completed_at','created_at','updated_at'];
        $out=[];
        foreach ($keys as $key) {
            $value=$row[$key]??null;
            if (in_array($key,['total_rows','success_rows','error_rows'],true)) $value=(int)$value;
            if (in_array($key,['id','created_by','updated_by'],true) && $value!==null) $value=(string)$value;
            if (in_array($key,['started_at','completed_at','created_at','updated_at'],true) && $value!==null) {
                $value=gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$value.' UTC'));
            }
            $out[$key]=$value;
        }
        return $out;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function projectRow(array $row):array
    {
        return ['row_number'=>(int)$row['row_number'],'row_status'=>(string)$row['row_status'],
            'entity_key'=>$row['entity_key']??null,
            'target_ref_id'=>isset($row['target_ref_id'])?(string)$row['target_ref_id']:null,
            'error_code'=>$row['error_code']??null,'error_message'=>$row['error_message']??null,
            'created_at'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$row['created_at'].' UTC'))];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\People\Structure\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\ReadAccessBridge;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/**
 * M5.4 protected Department/Position mutations: persisted VIEW + PBAC,
 * serialized code/hierarchy checks, Lifecycle and audit in one SQL transaction.
 */
final class StructureMutationService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly ReadAccessBridge $read;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $connection=null)
    {
        $this->db=$connection ?? db_connect();
        $this->access=new UnifiedAccessService($actor,$this->db);
        $this->states=new ResourceStateRepository($this->db);
        $this->read=new ReadAccessBridge($actor,$this->db);
        $this->actorId=StructureMutationRules::id((string)$actor->id,'department','user_id');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string $type,array $input): array
    {
        $table=self::table($type);
        $payload=StructureMutationRules::payload($type,$input,true);
        $initial=$this->states->initialState($type);
        if ($initial===null) throw new DomainException('WF_INITIAL_STATE_REQUIRED',['resource'=>$type]);
        if(isset($payload['status']) && $payload['status']!==$initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID',['resource'=>$type]);
        }
        $payload['status']=$initial;
        return $this->transaction($type,function() use($type,$table,$payload):array {
            if($type==='department') {
                StructureMutationRules::assertDepartmentParent($this->departmentSnapshot(),null,$payload['parent_id']);
            }
            $this->permit($type,'create',null,$payload);
            $this->assertUniqueCode($table,(string)$payload['code']);
            $id=$this->insert($type,$table,[...$payload,...$this->createdAudit()]);
            return $this->visible($type,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $type,string $id,array $input): array
    {
        $table=self::table($type);
        $id=StructureMutationRules::id($id,$type);
        return $this->transaction($type,function() use($type,$table,$id,$input):array {
            $before=$this->target($type,$id,true);
            $this->assertVisible($type,$before);
            $this->permit($type,'edit',$before);
            if(array_key_exists('status',$input)) {
                throw new DomainException('WF_TRANSITION_REQUIRED',['resource'=>$type]);
            }
            $patch=StructureMutationRules::payload($type,$input,false);
            if($type==='department' && array_key_exists('parent_id',$patch)) {
                StructureMutationRules::assertDepartmentParent($this->departmentSnapshot(),$id,$patch['parent_id']);
            }
            if(array_key_exists('code',$patch)) $this->assertUniqueCode($table,$patch['code'],$id);
            $this->writeUpdate($type,$table,$id,[...$patch,...$this->updatedAudit()]);
            return $this->visible($type,$id);
        });
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function setState(string $type,string $id,array $request): array
    {
        $table=self::table($type);
        $id=StructureMutationRules::id($id,$type);
        $requested=StructureMutationRules::requestedState($type,$request);
        return $this->transaction($type,function() use($type,$table,$id,$requested):array {
            $before=$this->target($type,$id,true);
            $this->assertVisible($type,$before);
            $this->permit($type,'setstate',$before);
            try { $next=$this->states->transitionTarget($type,$before,'setstate'); }
            catch (UnexpectedValueException $error) {
                throw new DomainException('WF_INVALID_TRANSITION',['resource'=>$type],$error);
            }
            if($requested!==$next) {
                throw new DomainException('WF_INVALID_TRANSITION',['field'=>'status']);
            }
            $this->permit($type,'setstate',$before,[],true);
            try {
                $ok=$this->db->table($table)->where('id',$id)->where('status',(string)$before['status'])
                    ->update(['status'=>$next,...$this->updatedAudit()]);
            } catch(Throwable $error) { $this->writeFailure($type,$error); }
            if(!$ok) $this->writeFailure($type);
            if($next!==(string)$before['status'] && $this->db->affectedRows()!==1) {
                throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            }
            return $this->visible($type,$id);
        });
    }

    /** @return list<array<string,mixed>> */
    private function departmentSnapshot():array
    {
        $query=$this->db->query('SELECT id,parent_id FROM '.$this->db->prefixTable('departments').' ORDER BY id FOR UPDATE');
        if($query===false) throw new DomainException('DEPARTMENT_PERSISTENCE_FAILED');
        return $query->getResultArray();
    }

    private function assertUniqueCode(string $table,string $code,?string $except=null):void
    {
        $builder=$this->db->table($table)->select('id')->where('code',$code);
        if($except!==null) $builder->where('id !=',$except);
        if($builder->limit(1)->get()->getRowArray()!==null) {
            throw new DomainException(strtoupper(rtrim($table,'s')).'_CODE_DUPLICATE',['field'=>'code']);
        }
    }

    /** @return array<string,mixed> */
    private function target(string $type,string $id,bool $lock=false):array
    {
        $builder=$this->db->table(self::table($type))->where('id',$id);
        $result=$lock ? $this->db->query($builder->getCompiledSelect().' FOR UPDATE') : $builder->get();
        if($result===false) throw new DomainException(strtoupper($type).'_PERSISTENCE_FAILED');
        $row=$result->getRowArray();
        if($row===null) throw new PageNotFoundException('Employee structure record not found.');
        return $row;
    }

    /** @return array<string,mixed> */
    private function visible(string $type,string $id):array
    {
        $row=$this->target($type,$id);
        $this->assertVisible($type,$row);
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(string $type,array $row):void
    {
        if(!$this->read->canView($type,$row)) {
            throw new PageNotFoundException('Employee structure record not found.');
        }
    }

    /** @param array<string,mixed>|null $old @param array<string,mixed> $candidate */
    private function permit(string $type,string $action,?array $old=null,array $candidate=[],bool $transition=false):void
    {
        $decision=$transition
            ? $this->access->decideTransitionAction($type,$action,$old??[])
            : $this->access->decideMutation($type,$action,$old,$candidate);
        if(!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED',['action'=>$action]);
    }

    private static function table(string $type):string
    {
        return match($type) {
            'department'=>'departments','position'=>'positions',
            default=>throw new UnexpectedValueException('UNREVIEWED_STRUCTURE_TYPE'),
        };
    }

    /** @param array<string,mixed> $payload */
    private function insert(string $type,string $table,array $payload):string
    {
        try { $ok=$this->db->table($table)->insert($payload); }
        catch(Throwable $e) { $this->writeFailure($type,$e); }
        if(!$ok) $this->writeFailure($type);
        return (string)$this->db->insertID();
    }

    /** @param array<string,mixed> $patch */
    private function writeUpdate(string $type,string $table,string $id,array $patch):void
    {
        try { $ok=$this->db->table($table)->where('id',$id)->update($patch); }
        catch(Throwable $e) { $this->writeFailure($type,$e); }
        if(!$ok) $this->writeFailure($type);
    }

    /** @return array<string,string> */
    private function createdAudit():array
    { return ['created_by'=>$this->actorId,'created_at'=>gmdate('Y-m-d H:i:s')]; }

    /** @return array<string,string> */
    private function updatedAudit():array
    { return ['updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')]; }

    private function writeFailure(string $type,?Throwable $cause=null):never
    {
        $dbCode=(int)($this->db->error()['code'] ?? 0);
        if($dbCode===1062 || (int)($cause?->getCode()??0)===1062) {
            throw new DomainException(strtoupper($type).'_CODE_DUPLICATE',['field'=>'code'],$cause);
        }
        throw new DomainException(strtoupper($type).'_PERSISTENCE_FAILED',[],$cause);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(string $type,callable $work):mixed
    {
        // Serialize Department hierarchy and code checks, and Position code
        // checks across independent HTTP workers. The acquired lock is released
        // on every path even if authorization or a DB write fails.
        $lockName='dinkerp.m54.'.$type;
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($lockName).',10) AS acquired');
        if($lock===false || (int)($lock->getRowArray()['acquired']??0)!==1) {
            throw new DomainException(strtoupper($type).'_PERSISTENCE_FAILED');
        }
        try {
            if(!$this->db->transBegin()) throw new DomainException(strtoupper($type).'_PERSISTENCE_FAILED');
            try {
                $result=$work();
                if(!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException(strtoupper($type).'_PERSISTENCE_FAILED');
                }
                return $result;
            } catch(Throwable $e) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($lockName).')');
        }
    }
}

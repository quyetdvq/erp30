<?php

declare(strict_types=1);

namespace App\Modules\People\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\ReadAccessBridge;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * M5.3: guarded Employee and Employee Assignment writes.
 * A named lock serializes the manager DAG and primary assignment intervals.
 * Data checks, PBAC, SQL and response VIEW live in one rollback-able transaction.
 */
final class PeopleMutationService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->actorId = PeopleMutationRules::id((string)$actor->id, 'user_id', 'EMP_MANAGER_REFERENCE_INVALID');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createEmployee(array $input): array
    {
        $payload=PeopleMutationRules::employee($input, true);
        $initial=$this->states->initialState('employee');
        if ($initial === null) throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource'=>'employee']);
        if (isset($payload['status']) && $payload['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['resource'=>'employee']);
        }
        $payload['status']=$initial;
        return $this->transaction('employee_tree', function () use ($payload): array {
            $this->permit('employee','create',null,$payload);
            $this->assertUniqueEmployee($payload);
            PeopleMutationRules::assertManagerChain($this->employeeSnapshot(), null, $payload['manager_employee_id'] ?? null);
            $id=$this->insert('employees', [...$payload, ...$this->createdAudit()]);
            return $this->visible('employee', $id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateEmployee(string $id, array $input): array
    {
        $id=PeopleMutationRules::id($id, 'employee_id', 'EMP_MANAGER_REFERENCE_INVALID');
        return $this->transaction('employee_tree', function () use ($id,$input): array {
            $old=$this->target('employee',$id,true);
            $this->assertVisible('employee',$old);
            $this->permit('employee','edit',$old);
            if (array_key_exists('status',$input)) {
                throw new DomainException('WF_TRANSITION_REQUIRED', ['resource'=>'employee']);
            }
            $patch=PeopleMutationRules::employee($input, false);
            $this->assertUniqueEmployee($patch, $id);
            if (array_key_exists('manager_employee_id',$patch)) {
                PeopleMutationRules::assertManagerChain($this->employeeSnapshot(), $id, $patch['manager_employee_id']);
            }
            $this->update('employees',$id,[...$patch,...$this->updatedAudit()]);
            return $this->visible('employee',$id);
        });
    }

    /** @return array<string,mixed> */
    public function setEmployeeState(string $id, mixed $status): array
    {
        $id=PeopleMutationRules::id($id, 'employee_id', 'EMP_MANAGER_REFERENCE_INVALID');
        return $this->transaction('employee_tree', function () use ($id,$status): array {
            $row=$this->target('employee',$id,true);
            $this->assertVisible('employee',$row);
            $this->permit('employee','setstate',$row);
            $requested=PeopleMutationRules::employee(['status'=>$status], false)['status'];
            $definition=$this->states->definitionFor('employee');
            $transitions=$this->db->table('lifecycle_transitions')
                ->where('lifecycle_id',$definition['lifecycle_id'])
                ->where('status','ACTIVE')->where('action_code','setstate')->get()->getResultArray();
            $next=PeopleTransitionRules::destination($definition,$row,$transitions,$requested);
            $this->permit('employee','setstate',$row,[],true);
            $this->updateConditionalState('employees',$id,(string)$row['status'],$next);
            return $this->visible('employee',$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createAssignment(array $input): array
    {
        $payload=PeopleMutationRules::assignment($input,true);
        PeopleMutationRules::assignmentPeriod($payload);
        return $this->transaction('assignment_primary', function () use ($payload): array {
            $this->assertAssignmentReferences($payload,true);
            // CREATE PBAC uses the verified persisted Organization scope,
            // not an unchecked organization_id/body policy override.
            $this->permit('employee_assignment','create',null,
                ['organization_id'=>(string)$payload['organization_id'], 'status'=>(string)$payload['status']]);
            $this->assertPrimaryAvailable($payload);
            $id=$this->insert('employee_assignments',[...$payload,...$this->createdAudit()]);
            return $this->visible('employee_assignment',$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateAssignment(string $id, array $input): array
    {
        $id=PeopleMutationRules::id($id,'assignment_id');
        return $this->transaction('assignment_primary', function () use ($id,$input): array {
            $old=$this->target('employee_assignment',$id,true);
            $this->assertVisible('employee_assignment',$old);
            $this->permit('employee_assignment','edit',$old);
            if (array_key_exists('status',$input)) {
                throw new DomainException('WF_TRANSITION_REQUIRED', ['resource'=>'employee_assignment']);
            }
            $patch=PeopleMutationRules::assignment($input,false);
            if (array_key_exists('employee_id',$patch)
                && (string)$patch['employee_id'] !== (string)$old['employee_id']) {
                throw new DomainException('EMP_ASSIGNMENT_EMPLOYEE_IMMUTABLE', ['field'=>'employee_id']);
            }
            $candidate=array_replace($old,$patch);
            PeopleMutationRules::assignmentPeriod($candidate);
            $referenceChanged=array_intersect(array_keys($patch),
                ['employee_id','organization_id','department_id','position_id']) !== [];
            $this->assertAssignmentReferences($candidate,$referenceChanged);
            if (array_key_exists('organization_id',$patch)
                && (string)$patch['organization_id'] !== (string)$old['organization_id']) {
                // Scope migration needs old-record EDIT and new-scope CREATE.
                $this->permit('employee_assignment','create',null,
                    ['organization_id'=>(string)$candidate['organization_id'], 'status'=>(string)$candidate['status']]);
            }
            $this->assertPrimaryAvailable($candidate,$id);
            $this->update('employee_assignments',$id,[...$patch,...$this->updatedAudit()]);
            return $this->visible('employee_assignment',$id);
        });
    }

    /** @return array<string,mixed> */
    public function setAssignmentState(string $id, mixed $status): array
    {
        $id=PeopleMutationRules::id($id,'assignment_id');
        return $this->transaction('assignment_primary', function () use ($id,$status): array {
            $row=$this->target('employee_assignment',$id,true);
            $this->assertVisible('employee_assignment',$row);
            $this->permit('employee_assignment','setstate',$row);
            $next=PeopleMutationRules::assignment(['status'=>$status],false)['status'];
            // Assignment is Native State (ACTIVE/INACTIVE), not Lifecycle.
            $state=$this->states->definitionFor('employee_assignment');
            if (($state['mode'] ?? '') !== 'NATIVE' || !isset($state['by_code'][$next])) {
                throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>'status']);
            }
            $candidate=array_replace($row,['status'=>$next]);
            PeopleMutationRules::assignmentPeriod($candidate);
            // Historical assignments may be ended even if their references
            // have since become inactive; activation requires active refs.
            $this->assertAssignmentReferences($candidate,$next==='ACTIVE' && (string)$row['status']!=='ACTIVE');
            $this->assertPrimaryAvailable($candidate,$id);
            $this->updateConditionalState('employee_assignments',$id,(string)$row['status'],$next);
            return $this->visible('employee_assignment',$id);
        });
    }

    /** @return list<array<string,mixed>> */
    private function employeeSnapshot(): array
    {
        return $this->db->table('employees')->select('id,manager_employee_id,status')
            ->orderBy('id','ASC')->get()->getResultArray();
    }

    /** @param array<string,mixed> $row */
    private function assertUniqueEmployee(array $row, ?string $except=null): void
    {
        foreach (['employee_code'=>'EMPLOYEE_CODE_DUPLICATE', 'user_id'=>'EMPLOYEE_USER_DUPLICATE'] as $field=>$error) {
            if (!array_key_exists($field,$row) || $row[$field] === null) continue;
            $query=$this->db->table('employees')->select('id')->where($field,$row[$field]);
            if ($except !== null) $query->where('id !=',$except);
            if ($query->limit(1)->get()->getRowArray() !== null) {
                throw new DomainException($error, ['field'=>$field]);
            }
        }
    }

    /** @param array<string,mixed> $candidate */
    private function assertAssignmentReferences(array $candidate, bool $requireActive): void
    {
        $relations=['employee_id'=>'employees','organization_id'=>'organizations',
            'department_id'=>'departments','position_id'=>'positions'];
        foreach ($relations as $field=>$table) {
            $id=$candidate[$field] ?? null;
            if ($id === null) {
                if (in_array($field,['employee_id','organization_id'],true)) {
                    throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>$field]);
                }
                continue;
            }
            $row=$this->db->table($table)->select('id,status')->where('id',$id)->get()->getRowArray();
            if ($row === null) throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>$field]);
            if ($requireActive && ($row['status'] ?? null)!=='ACTIVE') {
                throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INACTIVE', ['field'=>$field]);
            }
        }
    }

    /** @param array<string,mixed> $candidate */
    private function assertPrimaryAvailable(array $candidate, ?string $except=null): void
    {
        if (($candidate['status'] ?? null) !== 'ACTIVE' || (int)($candidate['is_primary'] ?? 0)!==1) return;
        $rows=$this->db->table('employee_assignments')->select('id,employee_id,status,is_primary,effective_from,effective_to')
            ->where('employee_id',$candidate['employee_id'])->where('status','ACTIVE')
            ->where('is_primary',1)->get()->getResultArray();
        foreach ($rows as $row) {
            if ($except !== null && (string)$row['id']===$except) continue;
            if (PeopleMutationRules::overlaps($candidate,$row)) {
                throw new DomainException('EMP_PRIMARY_ASSIGNMENT_OVERLAP', ['field'=>'effective_from']);
            }
        }
    }

    /** @return array<string,mixed> */
    private function target(string $type, string $id, bool $lock=false): array
    {
        $table=$this->table($type);
        $query=$this->db->table($table)->where('id',$id);
        $result=$lock ? $this->db->query($query->getCompiledSelect().' FOR UPDATE') : $query->get();
        if ($result===false) throw new DomainException('EMP_ASSIGNMENT_MUTATION_FAILED');
        $row=$result->getRowArray();
        if ($row===null) throw new PageNotFoundException('Employee structure target not found.');
        return $row;
    }

    /** @return array<string,mixed> */
    private function visible(string $type, string $id): array
    {
        $row=$this->target($type,$id);
        $this->assertVisible($type,$row);
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(string $type, array $row): void
    {
        if (!(new ReadAccessBridge($this->actor,$this->db))->canView($type,$row)) {
            throw new PageNotFoundException('Employee structure target not found.');
        }
    }

    /** @param array<string,mixed>|null $old @param array<string,mixed> $candidate */
    private function permit(string $type, string $action, ?array $old=null, array $candidate=[], bool $transition=false): void
    {
        $decision=$transition
            ? $this->access->decideTransitionAction($type,$action,$old ?? [])
            : $this->access->decideMutation($type,$action,$old,$candidate);
        if (!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED', ['action'=>$action]);
    }

    private function table(string $type): string
    {
        return match($type) {
            'employee'=>'employees', 'employee_assignment'=>'employee_assignments',
            default=>throw new \InvalidArgumentException('Unapproved People mutation type'),
        };
    }

    /** @param array<string,mixed> $payload */
    private function insert(string $table, array $payload): string
    {
        try { $ok=$this->db->table($table)->insert($payload); }
        catch(Throwable $e) { $this->writeFailure($table,$e); }
        if (!$ok) $this->writeFailure($table);
        return (string)$this->db->insertID();
    }

    /** @param array<string,mixed> $payload */
    private function update(string $table, string $id, array $payload): void
    {
        try { $ok=$this->db->table($table)->where('id',$id)->update($payload); }
        catch(Throwable $e) { $this->writeFailure($table,$e); }
        if (!$ok) $this->writeFailure($table);
    }

    private function updateConditionalState(string $table, string $id, string $old, string $next): void
    {
        try {
            $ok=$this->db->table($table)->where('id',$id)->where('status',$old)
                ->update(['status'=>$next,...$this->updatedAudit()]);
        } catch(Throwable $e) { $this->writeFailure($table,$e); }
        if (!$ok) $this->writeFailure($table);
        // A no-op setstate may update only audit columns, so exactly one row
        // should still be affected in the baseline schema.
        if ($this->db->affectedRows()!==1 && $old !== $next) {
            throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
        }
        // When the requested Native State is unchanged and updated_at has
        // second precision, MariaDB may legitimately report zero changed rows.
    }

    /** @return array<string,mixed> */
    private function createdAudit(): array
    {
        return ['created_by'=>$this->actorId,'created_at'=>gmdate('Y-m-d H:i:s')];
    }

    /** @return array<string,mixed> */
    private function updatedAudit(): array
    {
        return ['updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')];
    }

    private function writeFailure(string $table, ?Throwable $previous=null): never
    {
        $code=(int)($this->db->error()['code'] ?? 0);
        if ($table==='employees' && ($code===1062 || (int)($previous?->getCode() ?? 0)===1062)) {
            $message=strtolower((string)($previous?->getMessage() ?? $this->db->error()['message'] ?? ''));
            throw new DomainException(str_contains($message,'user_id') ? 'EMPLOYEE_USER_DUPLICATE':'EMPLOYEE_CODE_DUPLICATE',[], $previous);
        }
        throw new DomainException($table==='employees'?'EMP_PERSISTENCE_FAILED':'EMP_ASSIGNMENT_PERSISTENCE_FAILED',[], $previous);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(string $lane, callable $work): mixed
    {
        // Both lanes use a per-server MariaDB lock. Assignment operations
        // serialize overlap checks even when HTTP writers race.
        $lockName='dinkerp.m53.'.$lane;
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($lockName).', 10) AS acquired');
        if ($lock===false || (int)($lock->getRowArray()['acquired'] ?? 0)!==1) {
            throw new DomainException('EMP_ASSIGNMENT_MUTATION_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('EMP_ASSIGNMENT_MUTATION_FAILED');
            try {
                $result=$work();
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('EMP_ASSIGNMENT_MUTATION_FAILED');
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

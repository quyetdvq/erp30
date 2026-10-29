<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Execution;

use App\Exceptions\DomainException;
use App\Modules\Access\BasePermissionReader;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Customer\CustomerService;
use App\Modules\Notifications\NotificationOutboxProducer;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** Reads, previews and executes only persisted Lifecycle transitions. */
final class BusinessWorkflowService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly User $actor;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->actor = $actor;
        $this->access = new UnifiedAccessService($actor,$this->db);
        $this->states = new ResourceStateRepository($this->db);
    }

    /** @return array{mode:string,initial:?string,states:list<string>} */
    public function states(string $type): array
    {
        BusinessWorkflowRules::table($type);
        $resource = $this->resource($type);
        $base = (new BasePermissionReader($this->db))->forUser($this->actor);
        if ((string) ($resource['status'] ?? '') !== 'ACTIVE' || !in_array($type.'.view',$base,true)) {
            throw new DomainException('AUTH_POLICY_DENIED',['action'=>'view']);
        }
        $definition = $this->states->definitionFor($type);
        $codes = array_keys($definition['by_code']);
        $lifecycle = (string) ($definition['lifecycle_id'] ?? '');
        if ($lifecycle === '') throw new DomainException('WF_TRANSITION_REQUIRED',['resource'=>$type]);
        $initial = $definition['initial'];
        return ['mode'=>'LIFECYCLE','initial'=>$initial,'states'=>$codes];
    }

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>,record_display?:array{name:?string,code:?string}} */
    public function options(string $type,string $id,bool $includeDisplay=false): array
    {
        $row = $this->target($type,$id,false);
        $current = strtoupper((string) $row['status']);
        if (!$this->access->canView($type,$row)) throw new DomainException('AUTH_POLICY_DENIED',['action'=>'view']);
        $allowed = $this->access->decideMutation($type,'setstate',$row)['allowed'];
        $options = [];
        if ($allowed) {
            $transitions = $this->transitions($type,$row);
            $counts = array_count_values(array_column($transitions, 'action_code'));
            foreach ($transitions as $transition) {
                if (($counts[$transition['action_code']] ?? 0) !== 1) continue;
                if (!$this->transitionAuthorized($type,(string) $transition['action_code'],$row)) continue;
                $options[] = ['action_code'=>(string) $transition['action_code'], 'status'=>(string) $transition['destination']];
            }
        }
        $result = ['mode'=>'LIFECYCLE','current'=>$current,'options'=>$options];
        if ($includeDisplay) {
            // Only the own-user /my-work read model requests additive display
            // fields; keep the existing Workflow options HTTP contract unchanged.
            $result['record_display'] = BusinessWorkflowDisplay::fromAuthorizedRow($type,$row);
        }
        return $result;
    }

    /** @return array{status:string,action_code:string} */
    public function execute(string $type,string $id,string $action): array
    {
        $action = BusinessWorkflowRules::action($action);
        BusinessWorkflowRules::table($type);
        if ($type === 'customer') {
            // Customer owns extra Company Usage reactivation invariants and
            // an advisory master lock. Never bypass CustomerService::setState()
            // with the generic SQL UPDATE (even for a direct HTTP caller).
            $preview = $this->options($type, $id);
            $chosen = BusinessWorkflowRules::transitionForAction($preview['options'], $action);
            if ($action !== 'setstate') {
                throw new DomainException('WF_INVALID_TRANSITION', ['action_code' => $action]);
            }
            $saved = (new CustomerService($this->actor, $this->db))
                ->setState($id, ['status' => $chosen['status']]);
            return ['status' => (string) $saved['status'], 'action_code' => $action];
        }
        if (!$this->db->transBegin()) throw new RuntimeException('Workflow transition transaction failed');
        try {
            // Serialize transition on the actual business row and reassess PBAC
            // against its CURRENT persisted status. No client-controlled state ID.
            $row = $this->target($type,$id,true);
            if (!$this->access->decideMutation($type,'setstate',$row)['allowed']) {
                throw new DomainException('AUTH_POLICY_DENIED',['action'=>'setstate']);
            }
            $chosen = BusinessWorkflowRules::transitionForAction($this->transitions($type,$row), $action);
            if (!$this->transitionAuthorized($type,$action,$row)) {
                throw new DomainException('AUTH_POLICY_DENIED',['action'=>$action]);
            }
            $table = BusinessWorkflowRules::table($type);
            $old = (string) $row['status'];
            $new = (string) $chosen['destination'];
            $ok = $this->db->table($table)->where('id',$id)->where('status',$old)->update([
                'status'=>$new, 'updated_by'=>(string) $this->actor->id,
                'updated_at'=>gmdate('Y-m-d H:i:s'),
            ]);
            if (!$ok || $this->db->affectedRows()!==1) throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            // The transition and its immutable recipient snapshot share one transaction.
            // No notification is emitted without explicit record-level assignments.
            NotificationOutboxProducer::enqueueWorkflowTransition($this->db, $type, $id, $old, $new, $action);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Workflow transaction commit failed');
            }
            return ['status'=>$new,'action_code'=>$action];
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function resource(string $type): array
    {
        $row = $this->db->table('resources')->where('type',$type)->get()->getRowArray();
        if ($row===null) throw new DomainException('WF_TARGET_ADAPTER_UNAVAILABLE');
        return $row;
    }

    /** @return array<string,mixed> */
    private function target(string $type,string $id,bool $lock): array
    {
        $table = BusinessWorkflowRules::table($type);
        $id = BusinessWorkflowRules::id($id);
        $builder = $this->db->table($table)->where('id',$id);
        $result = $lock ? $this->db->query($builder->getCompiledSelect().' FOR UPDATE') : $builder->get();
        if ($result===false) throw new RuntimeException('Workflow target query failed');
        $row = $result->getRowArray();
        if ($row===null) throw new DomainException('WF_TARGET_NOT_FOUND');
        if (!BusinessWorkflowRules::matchesTargetOwner($type, $row)) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        // Customer Contacts are visible only together with their parent Customer.
        // The Contact table itself is shared and must not bypass parent PBAC.
        if ($type === 'customer_contact') {
            $parent = $this->db->table('customers')->where('id', $row['contact_ref_id'])->get()->getRowArray();
            if ($parent === null) {
                throw new DomainException('WF_TARGET_NOT_FOUND');
            }
            if (!$this->access->canView('customer', $parent)) {
                throw new DomainException('AUTH_POLICY_DENIED', ['action' => 'view']);
            }
        }
        // Unlike Customer Contacts (MASTER), Organization/Employee Contacts
        // require an owner-derived ORGANIZATION ID before any PBAC check.
        // The verified scope is also re-resolved after locking for execute().
        return BusinessWorkflowContactScope::fromPersisted($type, $row, $this->db);
    }

    /**
     * @param array<string,mixed> $row
     * @return list<array{action_code:string,destination:string}>
     */
    private function transitions(string $type,array $row): array
    {
        $definition = $this->states->definitionFor($type);
        $lifecycle = $definition['lifecycle_id'] ?? null;
        if ($lifecycle===null) throw new DomainException('WF_TRANSITION_REQUIRED',['resource'=>$type]);
        // The current State ID is always derived from the locked/read row.
        $stateId = $this->states->persistedStateId($type,$row);
        if ($stateId===null) throw new DomainException('WF_STATE_NOT_MAPPED');
        $list = $this->db->table('lifecycle_transitions')
            ->where('lifecycle_id',$lifecycle)->where('from_state_id',$stateId)
            ->where('status','ACTIVE')->orderBy('id','ASC')->get()->getResultArray();
        $byId = array_flip($definition['by_code']);
        $results = [];
        foreach ($list as $t) {
            $to = (string) ($t['to_state_id'] ?? '');
            if ($to === (string) $stateId || !isset($byId[$to])) continue;
            $active = $this->db->table('workflow_states')->select('id')
                ->where('id',$to)->where('status','ACTIVE')->get()->getRowArray();
            if ($active===null) continue;
            $action = strtolower((string) ($t['action_code'] ?? ''));
            try { $action = BusinessWorkflowRules::action($action); }
            catch (DomainException) { continue; }
            $results[] = ['action_code'=>$action,'destination'=>(string) $byId[$to]];
        }
        return $results;
    }

    /** @param array<string,mixed> $row */
    private function transitionAuthorized(string $type,string $action,array $row): bool
    {
        try {
            return $this->access->decideTransitionAction($type,$action,$row)['allowed'];
        } catch (Throwable) {
            // Missing/inactive or unregistered action is never an option.
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Exceptions\DomainException;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Contact\ContactService;
use App\Modules\History\HistoryRecorder;
use App\Modules\History\HistoryPolicy;
use App\Modules\Notifications\NotificationOutboxProducer;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/** P2-CUS-G1. Group master + Company-local interactions; no SAL/FIN financial data. */
final class CustomerService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;
    private readonly DateTimeImmutable $instant;

    public function __construct(private readonly User $actor, ?BaseConnection $connection=null)
    {
        $this->db=$connection??db_connect();
        $this->instant=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $this->access=new UnifiedAccessService($actor,$this->db,$this->instant);
        $this->states=new ResourceStateRepository($this->db);
        $this->actorId=CustomerRules::id((string)$actor->id,'actor_user_id');
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    public function list(?array $query): array
    {
        $params = CustomerSql::params($query ?? []);
        return $this->snapshot(function (UnifiedAccessService $access) use ($params): array {
            $page = $params['page'];
            $size = $params['page_size'];
            if (!$access->hasBaseViewPermission('customer')) {
                return ['data'=>[], 'page'=>$page, 'page_size'=>$size, 'total'=>0];
            }
            // Canonical Lifecycle membership is distinct from the client-side
            // syntax whitelist. Reject stale/unconfigured State filters before SQL.
            $status = $params['filter']['status'] ?? null;
            if ($status !== null && !isset($this->states->definitionFor('customer')['by_code'][$status])) {
                throw new BadRequestException('Unknown Customer Lifecycle status.');
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new CustomerScopeProvider());
            $plan = CustomerSql::collection($params, $scope, (string)$this->db->DBPrefix);
            $countResult = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countResult === false) throw new UnexpectedValueException('CUS_SCOPED_COUNT_FAILED');
            $total = self::validCount($countResult->getRowArray()['total'] ?? null);
            if ($total === 0) {
                return ['data'=>[], 'page'=>$page, 'page_size'=>$size, 'total'=>0];
            }
            $rowsResult = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($rowsResult === false) throw new UnexpectedValueException('CUS_SCOPED_PAGE_FAILED');
            $rows = array_values($rowsResult->getResultArray());
            if (count($rows) > $size) throw new UnexpectedValueException('CUS_PAGE_EXCEEDS_LIMIT');
            $byState = [];
            foreach ($rows as &$row) {
                if (!$access->canView('customer', $row)) {
                    // Never post-filter a page: the count and page must describe
                    // the exact same authorized SQL scope.
                    throw new UnexpectedValueException('CUS_SQL_SCOPE_PBAC_MISMATCH');
                }
                // Customer MASTER decisions depend on actor + GROUP + Lifecycle
                // state, not Customer ID. Cache only within this read snapshot.
                $state = strtoupper(trim((string)$row['status']));
                $byState[$state] ??= $this->actionHints($access, $row);
                $row['allowed_actions'] = $byState[$state];
            }
            unset($row);
            return ['data'=>$rows, 'page'=>$page, 'page_size'=>$size, 'total'=>$total];
        });
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        $id = CustomerRules::id($id);
        return $this->snapshot(function (UnifiedAccessService $access) use ($id): array {
            if (!$access->hasBaseViewPermission('customer')) {
                throw new PageNotFoundException('Customer not available.');
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new CustomerScopeProvider());
            $plan = CustomerSql::detail($id, $scope, (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('CUS_SCOPED_DETAIL_FAILED');
            $row = $result->getRowArray();
            if ($row === null) throw new PageNotFoundException('Customer not available.');
            if (!$access->canView('customer', $row)) {
                throw new UnexpectedValueException('CUS_SQL_SCOPE_PBAC_MISMATCH');
            }
            $row['company_ids'] = array_map('strval', array_column($this->db->table('customer_company_usages')
                ->select('company_organization_id')->where('customer_id', $row['id'])
                ->orderBy('company_organization_id', 'ASC')->get()->getResultArray(), 'company_organization_id'));
            unset($row['phone_key']);
            $row['allowed_actions'] = $this->actionHints($access, $row);
            return $row;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array
    {
        $parsed=CustomerRules::customer($input,true);
        $fields=$parsed['fields'];
        if (($fields['primary_contact_id']??null)!==null) throw new DomainException('CUSTOMER_PRIMARY_CONTACT_MISMATCH');
        $fields['phone_key']=CustomerRules::requiredProfile($fields);
        return $this->transaction(function() use ($fields,$parsed): array {
            $this->permit('customer','create',null,[]);
            $this->validateScope((int)$fields['all_companies'],$parsed['companies'],true);
            $this->validateAddress($fields);
            $this->assertPhoneAvailable((string)$fields['phone_key']);
            $manual=$fields['customer_code']??null;
            unset($fields['customer_code']);
            $initial=$this->states->initialState('customer');
            // The Lifecycle, not CustomerService, chooses the initial State.
            // Fail before numbering/INSERT if its code cannot fit VARCHAR(20).
            if ($initial===null || strlen($initial)>20)
                throw new DomainException('CUSTOMER_INITIAL_STATE_INVALID');
            $now=gmdate('Y-m-d H:i:s');
            $issuer=new CustomerNumberIssuer($this->db);
            $id=$issuer->issue($parsed['series'],$manual,function(string $code) use ($fields,$now,$parsed,$initial): string {
                $code=CustomerRules::text(strtoupper(trim($code)),'customer_code',50);
                if (!is_string($code)||preg_match('/\A[A-Z0-9][A-Z0-9_.-]{0,49}\z/D',$code)!==1)
                    throw new DomainException('CUSTOMER_CODE_INVALID');
                try {
                    $saved=$this->db->table('customers')->insert([
                        ...$fields,'customer_code'=>$code,'status'=>$initial,
                        'created_by'=>$this->actorId,'created_at'=>$now,
                    ]);
                } catch (Throwable $cause) {
                    $this->insertFailure($cause);
                }
                if (!$saved) $this->insertFailure();
                $newId=(string)$this->db->insertID();
                $this->replaceCompanyList($newId,(int)$fields['all_companies'],$parsed['companies']??[]);
                return $newId;
            });
            // A new INDIVIDUAL owns one main Contact from the same POST data.
            // Both rows + Numbering counter share this physical transaction;
            // Contact PBAC/Workflow failure rolls the entire Customer back.
            $contactInput=CustomerRules::initialContact($id,$fields);
            if ($contactInput!==null) {
                $contactId=(new ContactService($this->actor,$this->db))
                    ->createCustomerInOwningTransaction($contactInput);
                if (!$this->db->table('customers')->where('id',$id)->where('primary_contact_id',null)
                    ->update(['primary_contact_id'=>$contactId,
                        'updated_by'=>$this->actorId,'updated_at'=>$now])
                    ||$this->db->affectedRows()!==1) {
                    throw new DomainException('CUSTOMER_PRIMARY_CONTACT_PERSISTENCE_FAILED');
                }
            }
            $result = $this->visible($this->target($id));
            // HIS-01: one CREATE for the Customer master, after Contact/Numbering
            // have succeeded but before the owning transaction commits.
            (new HistoryRecorder($this->db))->created('customer', $id, $this->actorId, $initial);
            return $result;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id,array $input): array
    {
        $id=CustomerRules::id($id);
        $parsed=CustomerRules::customer($input,false);
        if (isset($parsed['fields']['customer_code'])) throw new DomainException('CUSTOMER_CODE_IMMUTABLE');
        return $this->transaction(function() use ($id,$parsed): array {
            $old=$this->target($id,true);
            $this->assertVisible($old);
            $this->permit('customer','edit',$old);
            $fields=$parsed['fields'];
            $all=(int)($fields['all_companies']??$old['all_companies']);
            $oldCompanyIds=array_map('strval',array_column($this->db->table('customer_company_usages')
                ->select('company_organization_id')->where('customer_id',$id)->get()->getResultArray(),'company_organization_id'));
            $companies=$parsed['companies'];
            if ($companies===null) $companies=$oldCompanyIds;
            if (array_key_exists('all_companies',$fields)&&$all===1&&$parsed['companies']===null) $companies=[];
            if (array_key_exists('all_companies',$fields)&&$all===0&&$parsed['companies']===null&&((int)$old['all_companies'])===1)
                throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
            $this->validateScope($all,$companies,(string)$old['status']==='ACTIVE');
            $next=array_replace($old,$fields);
            $phoneKey=CustomerRules::requiredProfile($next);
            if (array_key_exists('phone',$fields)) $fields['phone_key']=$phoneKey;
            $this->assertPhoneAvailable($phoneKey,$id);
            $this->validateAddress($next);
            $this->validatePrimaryContact($id,$next['primary_contact_id']??null);
            if ($fields!==[]||$parsed['companies']!==null) {
                try {
                    $saved=$this->db->table('customers')->where('id',$id)->update([
                        ...$fields,'updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable $cause) {
                    $this->insertFailure($cause);
                }
                if (!$saved) $this->insertFailure();
            }
            if ($parsed['companies']!==null||array_key_exists('all_companies',$fields)) {
                $this->replaceCompanyList($id,$all,$companies);
            }
            $result = $this->visible($this->target($id));
            // Selective audit: DRAFT edits and idempotent field submissions
            // generate no History. The policy also redacts all profile PII.
            (new HistoryRecorder($this->db))->updated('customer', $id, $this->actorId,
                [...$old, 'company_ids'=>$oldCompanyIds],
                [...$result, 'company_ids'=>$companies]);
            return $result;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function setState(string $id,array $input): array
    {
        $id=CustomerRules::id($id);
        if (array_keys($input)!==['status']) throw new DomainException('CUSTOMER_STATE_INVALID');
        $state=CustomerRules::stateCode($input['status']);
        return $this->transaction(function() use ($id,$state): array {
            $old=$this->target($id,true);
            $this->assertVisible($old);
            $this->permit('customer','setstate',$old);
            if ($state===(string)$old['status']) throw new DomainException('CUSTOMER_STATE_TRANSITION_INVALID');
            try {
                $next=$this->states->transitionTarget('customer',$old,'setstate');
            } catch (UnexpectedValueException $cause) {
                // A valid configured Lifecycle may have no setstate edge from
                // this State. Report a domain rejection, not an HTTP 500.
                if ($cause->getMessage()!=='Transition not allowed or ambiguous for current State.') throw $cause;
                throw new DomainException('CUSTOMER_STATE_TRANSITION_INVALID',[], $cause);
            }
            if ($next!==$state) throw new DomainException('CUSTOMER_STATE_TRANSITION_INVALID');
            if ($state==='ACTIVE') {
                $companies=array_map('strval',array_column($this->db->table('customer_company_usages')
                    ->select('company_organization_id')->where('customer_id',$id)->get()->getResultArray(),'company_organization_id'));
                $this->validateScope((int)$old['all_companies'],$companies,true);
            }
            $this->permit('customer','setstate',$old,[],true);
            if (!$this->db->table('customers')->where('id',$id)->where('status',$old['status'])
                ->update(['status'=>$state,'updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')])
                ||$this->db->affectedRows()!==1) throw new DomainException('CUSTOMER_CONCURRENT_STATE_CHANGE');
            // Honor M11's existing opt-in producer, with immutable recipients
            // snapshotted inside the same Customer state transaction.
            NotificationOutboxProducer::enqueueWorkflowTransition(
                $this->db, 'customer', $id, (string) $old['status'], $state, 'setstate'
            );
            $result = $this->visible($this->target($id));
            $checkpointRow = $result;
            if (HistoryPolicy::isOperational('customer', $state)
                && !HistoryPolicy::isOperational('customer', (string)$old['status'])) {
                $checkpointRow['company_ids'] = array_map('strval', array_column(
                    $this->db->table('customer_company_usages')->select('company_organization_id')
                        ->where('customer_id', $id)->get()->getResultArray(),
                    'company_organization_id'
                ));
            }
            (new HistoryRecorder($this->db))->transitioned('customer', $id, $this->actorId,
                (string)$old['status'], $state, 'setstate', $checkpointRow);
            return $result;
        });
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    public function interactions(string $id,?array $query): array
    {
        $id=CustomerRules::id($id);
        $params=CustomerSql::params($query??[],true);
        return $this->snapshot(function(UnifiedAccessService $access) use ($id,$params): array {
            $customer=$this->target($id);
            if (!$access->canView('customer',$customer)) throw new PageNotFoundException('Customer unavailable.');
            $company=$this->company($params['company_id'],false);
            if (!$access->canView('customer_interaction',['organization_id'=>$params['company_id'],'status'=>'ACTIVE']))
                throw new PageNotFoundException('Customer interactions unavailable.');
            $builder=$this->db->table('customer_interactions')
                ->where('customer_id',$id)->where('company_organization_id',$company['id']);
            if ($params['activity_type']!==null) $builder->where('activity_type',$params['activity_type']);
            if ($params['from']!==null) $builder->where('happened_at >=',$params['from']);
            if ($params['to']!==null) $builder->where('happened_at <',$params['to']);
            $total=$builder->countAllResults(false);
            $query=$builder->orderBy('happened_at','DESC')->orderBy('id','DESC')
                ->limit($params['page_size'],($params['page']-1)*$params['page_size'])->get();
            if ($query===false) throw new UnexpectedValueException('CUS_INTERACTIONS_QUERY_FAILED');
            return ['data'=>$query->getResultArray(),'total'=>(int)$total,
                'page'=>$params['page'],'page_size'=>$params['page_size']];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function addInteraction(string $id,array $input): array
    {
        $id=CustomerRules::id($id);$data=CustomerRules::interaction($input);
        return $this->transaction(function() use ($id,$data): array {
            $customer=$this->target($id,true);
            $this->assertVisible($customer);
            $company=$this->company($data['company_organization_id'],true);
            if (!$this->eligible($customer,(string)$company['id'])) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
            $this->permit('customer_interaction','create',null,['organization_id'=>$company['id']]);
            if ($data['corrects_interaction_id']!==null) {
                $original=$this->db->table('customer_interactions')
                    ->where('id',$data['corrects_interaction_id'])->get()->getRowArray();
                if ($original===null || (string)$original['customer_id']!==$id
                    || (string)$original['company_organization_id']!==(string)$company['id']
                    ||$original['activity_type']==='CORRECTION'
                    ||strcmp($original['happened_at'],$data['happened_at'])>0) {
                    throw new DomainException('CUSTOMER_INTERACTION_CORRECTION_INVALID');
                }
            }
            $now=gmdate('Y-m-d H:i:s');
            if (!$this->db->table('customer_interactions')->insert([
                ...$data,'customer_id'=>$id,'created_by'=>$this->actorId,'created_at'=>$now,
            ])) throw new DomainException('CUSTOMER_INTERACTION_PERSISTENCE_FAILED');
            $newId=(string)$this->db->insertID();
            $row=$this->db->table('customer_interactions')->where('id',$newId)->get()->getRowArray();
            if ($row===null) throw new DomainException('CUSTOMER_INTERACTION_PERSISTENCE_FAILED');
            return $row;
        });
    }

    /** UI hints are advisory; every mutation rechecks persisted PBAC. */
    private function actionHints(UnifiedAccessService $access, array $row): array
    {
        return EffectiveRowActionHints::forViewedRow($access, 'customer', $row);
    }

    private function visible(array $row): array
    {
        $this->assertVisible($row);
        unset($row['phone_key']); // Internal unique index key, never part of public API.
        $row['allowed_actions'] = $this->actionHints($this->access, $row);
        return $row;
    }

    private static function validCount(mixed $raw): int
    {
        $text = is_int($raw) || is_string($raw) ? (string)$raw : '';
        $limit = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $text) !== 1 || strlen($text) > strlen($limit)
            || (strlen($text) === strlen($limit) && strcmp($text, $limit) > 0)) {
            throw new UnexpectedValueException('CUS_INVALID_SCOPED_COUNT');
        }
        return (int)$text;
    }

    private function assertVisible(array $row): void
    {
        if (!$this->access->canView('customer',$row)) throw new PageNotFoundException('Customer not available.');
    }

    /** @return array<string,mixed> */
    private function target(string $id,bool $lock=false): array
    {
        $builder=$this->db->table('customers')->where('id',$id);
        $query=$lock?$this->db->query($builder->getCompiledSelect().' FOR UPDATE'):$builder->get();
        if ($query===false) throw new DomainException('CUSTOMER_PERSISTENCE_FAILED');
        $row=$query->getRowArray();
        if ($row===null) throw new PageNotFoundException('Customer not found.');
        return $row;
    }

    private function permit(string $resource,string $action,?array $old,array $candidate=[],bool $transition=false): void
    {
        $decision=$transition?$this->access->decideTransitionAction($resource,$action,$old??[]):
            $this->access->decideMutation($resource,$action,$old,$candidate);
        if (!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED',['action'=>$action]);
    }

    private function validateScope(int $all,?array $companies,bool $active): void
    {
        if ($all===1) {
            if ($companies!==null&&$companies!==[]) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
            return;
        }
        if ($all!==0 || ($active && (!$companies || count($companies)>512))) throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
        foreach ($companies??[] as $id) $this->company($id,true);
    }

    /** @return array<string,mixed> */
    private function company(string $id,bool $active): array
    {
        $row=$this->db->table('organizations')->where('id',$id)->get()->getRowArray();
        if ($row===null ||(string)$row['type']!=='COMPANY'||($active&&(string)$row['status']!=='ACTIVE'))
            throw new DomainException('CUSTOMER_COMPANY_SCOPE_INVALID');
        return $row;
    }
    /** @param array<string,mixed> $row */
    private function eligible(array $row,string $companyId): bool
    {
        return (int)$row['all_companies']===1 || $this->db->table('customer_company_usages')
            ->where('customer_id',$row['id'])->where('company_organization_id',$companyId)->countAllResults()===1;
    }
    /** @param list<string> $companies */
    private function replaceCompanyList(string $id,int $all,array $companies): void
    {
        if (!$this->db->table('customer_company_usages')->where('customer_id',$id)->delete())
            throw new DomainException('CUSTOMER_COMPANY_SCOPE_PERSISTENCE_FAILED');
        if ($all===1) return;
        foreach ($companies as $companyId) {
            if (!$this->db->table('customer_company_usages')->insert([
                'customer_id'=>$id,'company_organization_id'=>$companyId,
            ])) throw new DomainException('CUSTOMER_COMPANY_SCOPE_PERSISTENCE_FAILED');
        }
    }

    /** @param array<string,mixed> $row */
    private function validateAddress(array $row): void
    {
        $province=$row['province_id']??null;$ward=$row['ward_id']??null;
        if ($province!==null && $this->db->table('provinces')->where('id',$province)->countAllResults()!==1)
            throw new DomainException('CUSTOMER_WARD_PROVINCE_MISMATCH');
        if ($ward!==null) {
            $w=$this->db->table('wards')->where('id',$ward)->get()->getRowArray();
            if ($province===null||$w===null||(string)$w['province_id']!==(string)$province)
                throw new DomainException('CUSTOMER_WARD_PROVINCE_MISMATCH');
        }
    }
    /** Never scope the uniqueness check to an actor's VIEW privileges. */
    private function assertPhoneAvailable(string $phoneKey, ?string $selfId=null): void
    {
        $builder=$this->db->table('customers')->select('id')->where('phone_key',$phoneKey);
        if ($selfId!==null) $builder->where('id !=',$selfId);
        if ($builder->limit(1)->get()->getRowArray()!==null)
            throw new DomainException('CUSTOMER_PHONE_DUPLICATE',['field'=>'phone']);
    }

    private function validatePrimaryContact(string $customerId,?string $contactId): void
    {
        if ($contactId===null) return;
        $row=$this->db->table('contacts')->where('id',$contactId)->get()->getRowArray();
        if ($row===null||$row['contact_type']!=='CUSTOMER'||(string)$row['contact_ref_id']!==$customerId)
            throw new DomainException('CUSTOMER_PRIMARY_CONTACT_MISMATCH');
    }

    /** @template T @param callable(UnifiedAccessService):T $task @return T */
    private function snapshot(callable $task): mixed
    {
        if ($this->db->transDepth!==0) throw new UnexpectedValueException('CUS_READ_CANNOT_BORROW_WRITE');
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||!$this->db->transBegin())
            throw new UnexpectedValueException('CUS_READ_TRANSACTION_FAILED');
        try {
            $access=new UnifiedAccessService($this->actor,$this->db,$this->instant);
            $value=$task($access);
            if (!$this->db->transStatus()||!$this->db->transCommit()) throw new UnexpectedValueException('CUS_READ_TRANSACTION_FAILED');
            return $value;
        } catch (Throwable $cause) {
            $this->db->transRollback();$this->db->resetTransStatus();throw $cause;
        }
    }
    private function insertFailure(?Throwable $cause=null): never
    {
        $error=$this->db->error();
        if ((int)($error['code']??0)===1062 || (int)($cause?->getCode()??0)===1062) {
            // Database UNIQUE is authoritative even if an independent writer
            // bypasses the advisory Customer mutation lock.
            $diagnostic=(string)($error['message']??'').' '.($cause?->getMessage()??'');
            if (str_contains($diagnostic,'uq_customers_phone_key'))
                throw new DomainException('CUSTOMER_PHONE_DUPLICATE',['field'=>'phone'],$cause);
            throw new DomainException('CUSTOMER_CODE_DUPLICATE',['field'=>'customer_code'],$cause);
        }
        throw new DomainException('CUSTOMER_PERSISTENCE_FAILED',[],$cause);
    }

    /** @template T @param callable():T $task @return T */
    private function transaction(callable $task): mixed
    {
        if ($this->db->transDepth!==0) throw new DomainException('CUSTOMER_NESTED_TRANSACTION_UNSUPPORTED');
        // The P1 Product pattern serializes counter issuance with an advisory lock.
        // Customer company-scope replacements and status writes share this mutex.
        $name='dinkerp:'.(defined('ENVIRONMENT')?ENVIRONMENT:'development')
            .':'.$this->db->getDatabase().':customer-master';
        if (strlen($name)>64) $name=substr($name,0,30).':'.substr(hash('sha256',$name),0,32);
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($name).',10) AS acquired');
        if ($lock===false||(int)($lock->getRowArray()['acquired']??0)!==1)
            throw new DomainException('CUSTOMER_MUTATION_LOCK_FAILED');
        try {
            if (!$this->db->transBegin()) throw new DomainException('CUSTOMER_PERSISTENCE_FAILED');
            try {
                $value=$task();
                if (!$this->db->transStatus()||!$this->db->transCommit())
                    throw new DomainException('CUSTOMER_PERSISTENCE_FAILED');
                return $value;
            } catch (Throwable $cause) {
                $this->db->transRollback();$this->db->resetTransStatus();throw $cause;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($name).')');
        }
    }
}

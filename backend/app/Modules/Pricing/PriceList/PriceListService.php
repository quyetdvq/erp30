<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/**
 * M7.3: Pricing-owned CRUD + item-set replacement.
 * Organization+exact-State PBAC is compiled into SQL BEFORE COUNT/page;
 * mutations always authorize the locked persisted row, never a client hint.
 */
final class PriceListService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $connection=null)
    {
        $this->db=$connection??db_connect();
        $this->access=new UnifiedAccessService($actor,$this->db);
        $this->states=new ResourceStateRepository($this->db);
        $this->actorId=(string)$actor->id;
    }

    /** @param array<string,mixed> $request @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int|string} */
    public function list(array $request):array
    {
        $query=PriceListRules::query($request);
        $this->requireBaseView();
        // The bucket compiler, COUNT, page, and per-row recheck share the same
        // REPEATABLE READ snapshot. Otherwise a policy/owner change can shift
        // one row across a page and leak an inconsistent total or result.
        return $this->snapshot(function () use ($query):array {
            $access=new UnifiedAccessService($this->actor,$this->db);
            $buckets=$this->authorizedBuckets($access);
            if ($buckets===[]) return ['data'=>[],'page'=>$query['page'],'page_size'=>$query['page_size'],'total'=>0];
            $sql=PriceListSql::collection($buckets,$query,(string)$this->db->DBPrefix);
            $counter=$this->db->query($sql['count_sql'],$sql['count_binds']);
            if ($counter===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
            $total=(string)($counter->getRowArray()['total']??'0');
            if (!preg_match('/\A[0-9]+\z/D',$total)
                || strlen($total)>strlen((string)PHP_INT_MAX)
                || (strlen($total)===strlen((string)PHP_INT_MAX) && strcmp($total,(string)PHP_INT_MAX)>0)) {
                throw new UnexpectedValueException('Invalid or out-of-range Pricing COUNT');
            }
            $result=$this->db->query($sql['rows_sql'],$sql['rows_binds']);
            if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
            $data=[];
            foreach ($result->getResultArray() as $row) {
                if (!$access->canView('price_list',$row)) {
                    throw new UnexpectedValueException('Pricing SQL/PBAC scope disagreement.');
                }
                $data[]=[...$this->project($row), 'allowed_actions'=>
                    EffectiveRowActionHints::forViewedRow($access,'price_list',$row)];
            }
            return ['data'=>$data,'page'=>$query['page'],'page_size'=>$query['page_size'],'total'=>(int)$total];
        });
    }

    public function show(string $rawId):array
    {
        $this->requireBaseView();
        $row=$this->row(PriceListRules::id($rawId));
        $this->assertVisible($row);
        return [...$this->project($row), 'allowed_actions'=>
            EffectiveRowActionHints::forViewedRow($this->access,'price_list',$row)];
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function create(array $request):array
    {
        $fields=PriceListRules::master($request,true);
        // Initial lifecycle State is configured server-side, never from input.
        return $this->transaction(function () use ($fields):array {
            $initial=$this->states->initialState('price_list');
            if ($initial===null) throw new DomainException('WF_INITIAL_STATE_REQUIRED',['resource'=>'price_list']);
            $this->organization($fields['organization_id'],true);
            $decision=$this->access->decideMutation('price_list','create',null,
                ['organization_id'=>$fields['organization_id'],'status'=>$initial]);
            if (!$decision['allowed']) throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>'create']);
            $now=gmdate('Y-m-d H:i:s');
            $this->write('create',fn()=> $this->db->table('price_lists')->insert([
                ...$fields,'status'=>$initial,'created_by'=>$this->actorId,'created_at'=>$now,
            ]));
            $id=(string)$this->db->insertID();
            if ($id==='' || $id==='0') throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>'insert_id']);
            $row=$this->row($id);
            $this->assertVisible($row);
            return $this->project($row);
        });
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function update(string $rawId,array $request):array
    {
        $id=PriceListRules::id($rawId);
        $patch=PriceListRules::master($request,false);
        return $this->transaction(function () use ($id,$patch):array {
            $old=$this->row($id,true);
            $this->assertVisible($old);
            $this->permit('edit',$old);
            $candidate=array_replace($old,$patch);
            PriceListRules::interval($candidate);
            if (array_key_exists('organization_id',$patch)
                && (string)$patch['organization_id']!==(string)$old['organization_id']) {
                // Editing an old-scope row cannot grant permission to move it
                // into a previously unauthorized destination organization.
                $this->organization($patch['organization_id'],true);
                $decision=$this->access->decideMutation('price_list','create',null,
                    ['organization_id'=>$patch['organization_id']]);
                if (!$decision['allowed']) throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>'create']);
            }
            $delta=[];
            foreach ($patch as $field=>$value) {
                if ((string)($old[$field]??'')!==(string)($value??'')) $delta[$field]=$value;
            }
            if ($delta!==[]) $this->write('update',fn()=> $this->db->table('price_lists')->where('id',$id)
                ->update([...$delta,...$this->edited()]));
            $row=$this->row($id);
            $this->assertVisible($row);
            return $this->project($row);
        });
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function setState(string $rawId,array $body):array
    {
        $id=PriceListRules::id($rawId);
        $requested=PriceListRules::requestedState($body);
        return $this->transaction(function () use ($id,$requested):array {
            $old=$this->row($id,true);
            $this->assertVisible($old);
            $this->permit('setstate',$old);
            try { $next=$this->states->transitionTarget('price_list',$old,'setstate'); }
            catch (UnexpectedValueException $e) { throw new DomainException('WF_INVALID_TRANSITION',['field'=>'status'],$e); }
            if ($next!==$requested) throw new DomainException('WF_INVALID_TRANSITION',['field'=>'status']);
            $decision=$this->access->decideTransitionAction('price_list','setstate',$old);
            if (!$decision['allowed']) throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>'setstate']);
            $this->write('setstate',fn()=> $this->db->table('price_lists')->where('id',$id)
                ->where('status',$old['status'])->update(['status'=>$next,...$this->edited()]));
            if ($next!==(string)$old['status'] && $this->db->affectedRows()!==1) {
                throw new DomainException('PRICE_LIST_CONCURRENT_MODIFICATION');
            }
            $row=$this->row($id);
            $this->assertVisible($row);
            return $this->project($row);
        });
    }

    /** @return list<array<string,mixed>> */
    public function listItems(string $rawId):array
    {
        $this->requireBaseView();
        $id=PriceListRules::id($rawId);
        $owner=$this->row($id);
        $this->assertVisible($owner);
        return $this->byOwner($id);
    }

    /** @param array<mixed> $request @return list<array<string,mixed>> */
    public function replaceItems(string $rawId,array $request):array
    {
        $id=PriceListRules::id($rawId);
        $items=PriceListRules::items($request);
        return $this->transaction(function () use ($id,$items):array {
            $owner=$this->row($id,true);
            $this->assertVisible($owner);
            $this->permit('edit',$owner);
            $old=$this->lockedItems($id);
            $plan=PriceListItemPlan::build($old,$items);
            $changes=[...$plan['creates'],...array_map(static fn(array $step):array=>$step['new'],$plan['updates'])];
            $this->validateNewItems($changes);
            $now=gmdate('Y-m-d H:i:s');
            foreach ($plan['deletes'] as $row) {
                $this->write('delete_item',fn()=> $this->db->table('product_price_list_items')
                    ->where('price_list_id',$id)->where('id',$row['id'])->delete());
            }
            foreach ($plan['updates'] as $step) {
                $this->write('update_item',fn()=> $this->db->table('product_price_list_items')
                    ->where('price_list_id',$id)->where('id',$step['old']['id'])
                    ->update(['price'=>$step['new']['price'],...$this->edited()]));
            }
            foreach ($plan['creates'] as $item) {
                $this->write('insert_item',fn()=> $this->db->table('product_price_list_items')->insert([
                    'price_list_id'=>$id,...$item,'created_by'=>$this->actorId,'created_at'=>$now,
                ]));
            }
            return $this->byOwner($id);
        });
    }

    private function requireBaseView():void
    {
        if (!$this->access->hasBaseViewPermission('price_list')) {
            throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>'view']);
        }
    }

    /** Exact persisted (org, State) buckets, not a lossy ALLOW-policy JOIN.
     *  @return list<array{organization_id:string,status:string}>
     */
    private function authorizedBuckets(UnifiedAccessService $access):array
    {
        $result=$this->db->query('SELECT MIN(id) AS sample_id, organization_id, HEX(status) AS state_hex FROM '
            .PriceListSql::table('price_lists',(string)$this->db->DBPrefix)
            .' GROUP BY organization_id, HEX(status) ORDER BY sample_id ASC LIMIT '
            .(PriceListRules::MAX_BUCKETS+1));
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $rows=$result->getResultArray();
        if (count($rows)>PriceListRules::MAX_BUCKETS) {
            throw new DomainException('PRICE_LIST_SCOPE_TOO_COMPLEX');
        }
        $out=[];
        foreach ($rows as $bucket) {
            $id=(string)($bucket['sample_id']??'');
            $org=(string)($bucket['organization_id']??'');
            $hex=$bucket['state_hex']??null;
            if (!preg_match('/\A[1-9][0-9]*\z/D',$id) || !preg_match('/\A[1-9][0-9]*\z/D',$org)
                || !is_string($hex) || $hex==='' || preg_match('/\A(?:[A-Fa-f0-9]{2})+\z/D',$hex)!==1) {
                throw new UnexpectedValueException('Malformed persisted Pricing PBAC bucket.');
            }
            $status=hex2bin($hex);
            if (!is_string($status)) throw new UnexpectedValueException('Invalid persisted Pricing State.');
            $sample=$this->row($id);
            if ((string)$sample['organization_id']!==$org || (string)$sample['status']!==$status) {
                throw new UnexpectedValueException('Pricing PBAC bucket representative changed.');
            }
            if ($access->canView('price_list',$sample)) {
                $out[]=['organization_id'=>$org,'status'=>$status];
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function row(string $id,bool $lock=false):array
    {
        $sql=$this->db->table('price_lists')->where('id',$id)->getCompiledSelect();
        $result=$lock?$this->db->query($sql.' FOR UPDATE'):$this->db->query($sql);
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $row=$result->getRowArray();
        if ($row===null) throw new PageNotFoundException('Price List is not available.');
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(array $row):void
    {
        if (!$this->access->canView('price_list',$row)) {
            throw new PageNotFoundException('Price List is not available.');
        }
    }

    /** @param array<string,mixed> $row */
    private function permit(string $action,array $row):void
    {
        if (!$this->access->decideMutation('price_list',$action,$row)['allowed']) {
            throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>$action]);
        }
    }

    /** @return array<string,mixed> */
    private function organization(string $id,bool $lock):array
    {
        $sql=$this->db->table('organizations')->where('id',$id)->getCompiledSelect();
        $result=$this->db->query($lock?$sql.' FOR UPDATE':$sql);
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $row=$result->getRowArray();
        if ($row===null || !in_array((string)($row['type']??''),['GROUP','COMPANY'],true)) {
            throw new DomainException('PRICE_LIST_INVALID_ORGANIZATION_SCOPE',['field'=>'organization_id']);
        }
        return $row;
    }

    /** @param list<array{product_id:string,uom_id:string,price:string}> $changes */
    private function validateNewItems(array $changes):void
    {
        if ($changes===[]) return;
        $productIds=[];$uomIds=[];
        foreach ($changes as $item) {
            $productIds[$item['product_id']]=true;
            $uomIds[$item['uom_id']]=true;
        }
        $products=$this->lockedReferenceRows('products',array_keys($productIds));
        $uoms=$this->lockedReferenceRows('product_uoms',array_keys($uomIds));
        $children=$this->db->table('products')->select('parent_product_id')->whereIn('parent_product_id',array_keys($productIds))->get();
        if ($children===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $parents=[];
        foreach ($children->getResultArray() as $row) $parents[(string)$row['parent_product_id']]=true;
        $conversionPairs=[];
        foreach ($changes as $item) {
            $pid=$item['product_id'];$uid=$item['uom_id'];
            $product=$products[$pid]??null;
            $uom=$uoms[$uid]??null;
            if ($product===null || $uom===null) throw new DomainException('PRICE_LIST_INVALID_REFERENCE');
            if (!$this->access->canView('product',$product) || !$this->access->canView('product_uom',$uom)) {
                throw new DomainException('PRICE_LIST_AUTHORIZATION_DENIED',['action'=>'view']);
            }
            if ((string)$product['status']!=='ACTIVE' || (string)$uom['status']!=='ACTIVE') {
                throw new DomainException('PRICE_LIST_INACTIVE_REFERENCE');
            }
            if (($product['base_uom_id']??null)===null || isset($parents[$pid])
                || !in_array((string)$product['product_type'],['COMBO','STANDARD'],true)
                || ((string)$product['product_type']==='COMBO' && $product['parent_product_id']!==null)) {
                throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'product_id']);
            }
            $base=(string)$product['base_uom_id'];
            if ((string)$product['product_type']==='COMBO') {
                if ($uid!==$base) throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'uom_id']);
            } elseif ($uid!==$base) {
                $conversionPairs[$pid.':'.$uid]=['product_id'=>$pid,'uom_id'=>$uid];
            }
        }
        if ($conversionPairs!==[]) {
            // Bounded indexed lookup, never a full conversion-catalog fetch.
            $pairs=array_values($conversionPairs);
            $ids=array_values(array_unique(array_column($pairs,'product_id')));
            $units=array_values(array_unique(array_column($pairs,'uom_id')));
            $result=$this->db->table('product_uom_conversions')
                ->select('product_id,uom_id,status,usage_type')->whereIn('product_id',$ids)
                ->whereIn('uom_id',$units)->get();
            if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
            $valid=[];
            foreach ($result->getResultArray() as $row) {
                if ((string)$row['status']==='ACTIVE' && in_array((string)$row['usage_type'],['SALES','BOTH'],true)) {
                    $valid[(string)$row['product_id'].':'.(string)$row['uom_id']]=true;
                }
            }
            foreach (array_keys($conversionPairs) as $pair) if (!isset($valid[$pair])) {
                throw new DomainException('PRICE_LIST_INVALID_ITEM',['field'=>'uom_id']);
            }
        }
    }

    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    private function lockedReferenceRows(string $table,array $ids):array
    {
        if ($ids===[]) return [];
        sort($ids,SORT_STRING);
        $sql=$this->db->table($table)->whereIn('id',$ids)->orderBy('id','ASC')->getCompiledSelect().' FOR UPDATE';
        $result=$this->db->query($sql);
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $out=[];
        foreach ($result->getResultArray() as $row) $out[(string)$row['id']]=$row;
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function byOwner(string $id):array
    {
        $result=$this->db->table('product_price_list_items')->where('price_list_id',$id)
            ->orderBy('product_id','ASC')->orderBy('uom_id','ASC')->get();
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        return array_map($this->projectItem(...),array_values($result->getResultArray()));
    }

    /** @return list<array<string,mixed>> */
    private function lockedItems(string $id):array
    {
        $sql=$this->db->table('product_price_list_items')->where('price_list_id',$id)
            ->orderBy('product_id','ASC')->orderBy('uom_id','ASC')->getCompiledSelect().' FOR UPDATE';
        $result=$this->db->query($sql);
        if ($result===false) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        return array_values($result->getResultArray());
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function project(array $row):array
    {
        return ['id'=>(string)$row['id'],'code'=>(string)$row['code'],'name'=>(string)$row['name'],
            'organization_id'=>(string)$row['organization_id'],
            'effective_from'=>PriceListRules::apiDateTime((string)$row['effective_from']),
            'effective_to'=>PriceListRules::apiDateTime($row['effective_to']===null?null:(string)$row['effective_to']),
            'priority'=>(int)$row['priority'],'status'=>(string)$row['status'],
            'description'=>$row['description']===null?null:(string)$row['description']];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function projectItem(array $row):array
    {
        return ['id'=>(string)$row['id'],'price_list_id'=>(string)$row['price_list_id'],
            'product_id'=>(string)$row['product_id'],'uom_id'=>(string)$row['uom_id'],
            'price'=>(string)$row['price']];
    }

    /** @return array{updated_by:string,updated_at:string} */
    private function edited():array { return ['updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')]; }

    /** @param callable():bool $execute */
    private function write(string $operation,callable $execute):void
    {
        try { if (!$execute()) throw new UnexpectedValueException('SQL write returned false.'); }
        catch (Throwable $e) {
            $error=(int)($this->db->error()['code']??0);
            if ($error===1062 || (int)$e->getCode()===1062) {
                throw new DomainException('PRICE_LIST_DUPLICATE',['field'=>'code'],$e);
            }
            throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>$operation],$e);
        }
    }

    /** Read-only check; never ALTER tables to make the QA pass. */
    private function assertTransactionEngines():void
    {
        $prefix=(string)$this->db->DBPrefix;
        $tables=array_map(static fn(string $name):string=>$prefix.$name,
            ['price_lists','product_price_list_items','organizations','products','product_uoms','product_uom_conversions']);
        try {
            $placeholders=implode(', ',array_fill(0,count($tables),'?'));
            $result=$this->db->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES'
                .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.$placeholders.')',$tables);
            if ($result===false) throw new UnexpectedValueException('Cannot inspect engines.');
            $found=[];
            foreach ($result->getResultArray() as $row) $found[strtolower((string)$row['TABLE_NAME'])]=strtoupper((string)$row['ENGINE']);
            foreach ($tables as $table) if (($found[strtolower($table)]??null)!=='INNODB') {
                throw new UnexpectedValueException('Transactional table engine missing: '.$table);
            }
        } catch (Throwable $e) { throw new DomainException('PRICE_LIST_CONNECTION_INVALID',[],$e); }
    }

    private function lockName():string
    {
        $name='dinkerp:'.(defined('ENVIRONMENT')?ENVIRONMENT:'development').':'.$this->db->getDatabase().':pricing-master';
        return strlen($name)<=64?$name:substr($name,0,30).':'.substr(hash('sha256',$name),0,32);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(callable $work):mixed
    {
        if ($this->db->transDepth!==0) throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>'nested_transaction']);
        $this->assertTransactionEngines();
        $name=$this->lockName();
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($name).',10) AS acquired');
        if ($lock===false || (int)($lock->getRowArray()['acquired']??0)!==1) {
            throw new DomainException('PRICE_LIST_LOCK_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>'begin']);
            try {
                $result=$work();
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('PRICE_LIST_MUTATION_FAILED',['operation'=>'commit']);
                }
                return $result;
            } catch (Throwable $e) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($name).')');
        }
    }

    /** @template T @param callable():T $work @return T */
    private function snapshot(callable $work):mixed
    {
        if ($this->db->transDepth!==0) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        $this->assertTransactionEngines();
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false
            || !$this->db->transBegin()) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
        try {
            $result=$work();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('PRICE_LIST_CONNECTION_INVALID');
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }
}

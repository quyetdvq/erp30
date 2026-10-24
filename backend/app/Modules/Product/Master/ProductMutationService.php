<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/** API-050 Product mutations. One product-wide lock + one physical transaction per command. */
final class ProductMutationService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db=$connection ?? db_connect();
        $this->access=new UnifiedAccessService($actor,$this->db);
        $this->states=new ResourceStateRepository($this->db);
        $this->actorId=(string)$actor->id;
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function create(array $request): array
    {
        $this->assertEvaluationHint($request);
        $parsed=ProductMutationRules::normalize($request,true);
        $fields=$parsed['fields'];
        $initial=$this->states->initialState('product');
        if ($initial===null) throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource'=>'product']);
        if (isset($fields['status']) && $fields['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['field'=>'status']);
        }
        $fields['status']=$initial;
        return $this->transaction(function() use ($fields,$parsed): array {
            $this->permit('create',null,$fields);
            $this->assertReferences($fields,null);
            $parentId=$fields['parent_product_id'] ?? null;
            if ($parentId!==null) {
                $parent=$this->row($parentId,true);
                if (($parent['product_type']??null)!=='STANDARD') throw new DomainException('PRODUCT_PARENT_IS_COMBO');
                if (($parent['parent_product_id']??null)!==null) throw new DomainException('PRODUCT_PARENT_IS_CHILD');
                if (! $this->hasChildren($parentId)) {
                    $this->assertConversionEligible($parentId);
                    $this->persistUpdate($parentId, ['price'=>null,'serial_management_enabled'=>0, ...$this->edited()]);
                }
            }
            $id=(new ProductNumberIssuer($this->db))->issue($parsed['series'],$fields['code'],function(string $code)use($fields):string {
                $this->assertUnique('code',$code);
                foreach (['sku','barcode'] as $unique) if ($fields[$unique]!==null) $this->assertUnique($unique,(string)$fields[$unique]);
                $row=$fields;
                $row['code']=$code;
                $row['serial_management_enabled']=(int)$row['serial_management_enabled'];
                unset($row['evaluation_organization_id']);
                try { $ok=$this->db->table('products')->insert([...$row,...$this->created()]); }
                catch (Throwable $cause) { $this->writeFailure('create',$cause); }
                if (! $ok) $this->writeFailure('create');
                return (string)$this->db->insertID();
            });
            $new=$this->row($id,true);
            if ($parsed['combo']!==null) {
                $this->permit('edit',$new); // CREATE never implicitly grants COMBO edit.
                $this->insertCombo($id,$parsed['combo']);
            }
            $this->assertVisible($new);
            return $this->visible($id);
        });
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function update(string $rawId,array $request): array
    {
        $id=ProductMutationRules::id($rawId);
        $this->assertEvaluationHint($request);
        $parsed=ProductMutationRules::normalize($request,false);
        $patch=$parsed['fields'];
        return $this->transaction(function()use($id,$patch):array {
            $old=$this->row($id,true);
            $this->assertVisible($old);
            $this->permit('edit',$old);
            $merged=array_replace($old,$patch);
            $semantic=self::semanticChanged($old,$merged);
            ProductMutationRules::assertEditBoundary($old,$merged,$semantic && $this->hasTransactionHistory($id));
            $children=$this->hasChildren($id);
            ProductMutationRules::assertStructure($merged,$children);
            if ($children && ($merged['product_type']??null)!=='STANDARD') throw new DomainException('PRODUCT_TYPE_CHANGE_INVALID');
            if (($old['product_type']??null)==='COMBO' && ($merged['product_type']??null)!=='COMBO'
                && $this->db->table('product_combo_items')->select('id')->where('combo_product_id',$id)->get()->getRowArray()!==null) {
                throw new DomainException('PRODUCT_TYPE_CHANGE_INVALID');
            }
            $this->assertReferences($merged,$old);
            foreach (['code','sku','barcode'] as $field) if (array_key_exists($field,$patch) && $patch[$field]!==null) {
                $this->assertUnique($field,(string)$patch[$field],$id);
            }
            if (array_key_exists('serial_management_enabled', $patch)) {
                $patch['serial_management_enabled'] = (int) $patch['serial_management_enabled'];
            }
            $this->persistUpdate($id,[...$patch,...$this->edited()]);
            $this->assertVisible($this->row($id));
            return $this->visible($id);
        });
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function setState(string $rawId,array $request): array
    {
        $id=ProductMutationRules::id($rawId);
        $requested=ProductMutationRules::requestedState($request);
        return $this->transaction(function()use($id,$requested):array {
            $old=$this->row($id,true);
            $this->assertVisible($old);
            $this->permit('setstate',$old);
            try { $next=$this->states->transitionTarget('product',$old,'setstate'); }
            catch (UnexpectedValueException $e) { throw new DomainException('WF_INVALID_TRANSITION',['field'=>'status'],$e); }
            if ($next!==$requested) throw new DomainException('WF_INVALID_TRANSITION',['field'=>'status']);
            $this->permit('setstate',$old,[],true);
            $ok=$this->db->table('products')->where('id',$id)->where('status',$old['status'])
                ->update(['status'=>$next,...$this->edited()]);
            if (! $ok) $this->writeFailure('setstate');
            if ($next!==(string)$old['status'] && $this->db->affectedRows()!==1) {
                throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            }
            return $this->visible($id);
        });
    }

    /** @param array<string,mixed> $candidate @param ?array<string,mixed> $old */
    private function assertReferences(array $candidate,?array $old): void
    {
        if (($candidate['product_group_id']??null)!==null && ($old===null || (string)($old['product_group_id']??'') !== (string)$candidate['product_group_id'])) {
            $this->assertGroupActive((string)$candidate['product_group_id']);
        }
        if (($candidate['base_uom_id']??null)!==null && ($old===null || (string)($old['base_uom_id']??'') !== (string)$candidate['base_uom_id'])) {
            $uom=$this->db->table('product_uoms')->select('id,status')->where('id',$candidate['base_uom_id'])->get()->getRowArray();
            if ($uom===null || $uom['status']!=='ACTIVE') throw new DomainException('PRODUCT_BASE_UOM_INACTIVE', ['field'=>'base_uom_id']);
        }
    }

    private function assertGroupActive(string $groupId): void
    {
        $seen=[];
        while (true) {
            if (isset($seen[$groupId])) throw new DomainException('PRODUCT_GROUP_NOT_EFFECTIVELY_ACTIVE');
            $seen[$groupId]=true;
            $group=$this->db->table('product_groups')->select('id,parent_id,status')->where('id',$groupId)->get()->getRowArray();
            if ($group===null || $group['status']!=='ACTIVE') throw new DomainException('PRODUCT_GROUP_NOT_EFFECTIVELY_ACTIVE',['field'=>'product_group_id']);
            if ($group['parent_id']===null) return;
            $groupId=(string)$group['parent_id'];
        }
    }

    private function hasChildren(string $id): bool
    { return $this->db->table('products')->select('id')->where('parent_product_id',$id)->get()->getRowArray()!==null; }

    private function hasTransactionHistory(string $id): bool
    {
        // The accepted baseline currently injects NoProductHistoryProvider until the
        // transaction module exists. Fail closed for known persisted usage edges.
        return $this->exists('product_price_list_items','product_id',$id)
            || $this->exists('product_uom_conversions','product_id',$id)
            || $this->exists('product_combo_items','component_product_id',$id);
    }

    private function assertConversionEligible(string $id): void
    {
        if ($this->hasTransactionHistory($id)) {
            $errors=['product_price_list_items'=>'PRODUCT_CONVERSION_PRICE_LIST_BLOCKED',
                'product_uom_conversions'=>'PRODUCT_CONVERSION_UOM_BLOCKED',
                'product_combo_items'=>'PRODUCT_CONVERSION_COMBO_BLOCKED'];
            foreach ($errors as $table=>$code) {
                $col=$table==='product_combo_items'?'component_product_id':'product_id';
                if ($this->exists($table,$col,$id)) throw new DomainException($code);
            }
        }
    }

    private function exists(string $table,string $column,string $id): bool
    { return $this->db->table($table)->select('id')->where($column,$id)->limit(1)->get()->getRowArray()!==null; }

    /** @param array<string,mixed> $old @param array<string,mixed> $merged */
    private static function semanticChanged(array $old,array $merged): bool
    {
        foreach (['base_uom_id','parent_product_id','product_type','serial_management_enabled'] as $field) {
            if ($field === 'serial_management_enabled') {
                if ((bool) ($old[$field] ?? false) !== (bool) ($merged[$field] ?? false)) return true;
            } elseif ((string) ($old[$field] ?? '') !== (string) ($merged[$field] ?? '')) {
                return true;
            }
        }
        return false;
    }

    /** @param list<array{component_product_id:string,quantity:string}> $items */
    private function insertCombo(string $ownerId,array $items): void
    {
        foreach ($items as $item) {
            $id=$item['component_product_id'];
            if ($id===$ownerId) throw new DomainException('COMBO_SELF_REFERENCE');
            $row=$this->row($id,true);
            if ($row['status']!=='ACTIVE' || ! $this->access->canView('product',$row)) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED');
            }
            if ($this->hasChildren($id)) throw new DomainException('PRODUCT_INVALID_ROLE');
            $uom=$this->db->table('product_uoms')->select('status')->where('id',$row['base_uom_id'])->get()->getRowArray();
            if ($uom===null || $uom['status']!=='ACTIVE') throw new DomainException('PRODUCT_BASE_UOM_INACTIVE');
            try {
                $ok=$this->db->table('product_combo_items')->insert([
                    'combo_product_id'=>$ownerId,'component_product_id'=>$id,'quantity'=>$item['quantity'],...$this->created(),
                ]);
            } catch (Throwable $cause) { $this->writeFailure('combo',$cause); }
            if (! $ok) $this->writeFailure('combo');
        }
    }

    private function assertUnique(string $column,string $value,?string $except=null): void
    {
        $query=$this->db->table('products')->select('id')->where($column,$value);
        if ($except!==null) $query->where('id !=',$except);
        if ($query->get()->getRowArray()!==null) {
            throw new DomainException('PRODUCT_IDENTIFIER_DUPLICATE', ['field'=>$column]);
        }
    }

    /** @return array<string,mixed> */
    private function row(string $id,bool $lock=false): array
    {
        $builder=$this->db->table('products')->where('id',$id);
        $result=$lock ? $this->db->query($builder->getCompiledSelect().' FOR UPDATE') : $builder->get();
        if ($result===false) $this->writeFailure('select');
        $row=$result->getRowArray();
        if ($row===null) throw new DomainException('PRODUCT_NOT_FOUND');
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(array $row): void
    { if (! $this->access->canView('product',$row)) throw new PageNotFoundException('Product is not available.'); }

    /** @param ?array<string,mixed> $old @param array<string,mixed> $candidate */
    private function permit(string $action,?array $old,array $candidate=[],bool $transition=false): void
    {
        $decision=$transition ? $this->access->decideTransitionAction('product',$action,$old??[])
            : $this->access->decideMutation('product',$action,$old,$candidate);
        if (! $decision['allowed']) throw new DomainException('PRODUCT_AUTHORIZATION_DENIED',['action'=>$action]);
    }

    /** @return array<string,mixed> */
    private function visible(string $id): array
    {
        return (new ProductReadService($this->actor,$this->db))->show($id);
    }

    /** @param array<string,mixed> $payload */
    private function persistUpdate(string $id,array $payload): void
    {
        try { $ok=$this->db->table('products')->where('id',$id)->update($payload); }
        catch (Throwable $cause) { $this->writeFailure('update',$cause); }
        if (! $ok) $this->writeFailure('update');
    }

    /** @return array<string,string> */
    private function created(): array
    { return ['created_by'=>$this->actorId,'created_at'=>gmdate('Y-m-d H:i:s')]; }

    /** @return array<string,string> */
    private function edited(): array
    { return ['updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')]; }

    private function writeFailure(string $operation,?Throwable $cause=null): never
    {
        $dbError=$this->db->error();
        if ((int)($dbError['code']??0)===1062 || (int)($cause?->getCode()??0)===1062) {
            throw new DomainException('PRODUCT_IDENTIFIER_DUPLICATE', [], $cause);
        }
        throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>$operation], $cause);
    }

    /** @param array<string,mixed> $request */
    private function assertEvaluationHint(array $request): void
    {
        if (!array_key_exists('evaluation_organization_id',$request) || $request['evaluation_organization_id']===null) return;
        $hint=$request['evaluation_organization_id'];
        try {
            if (!is_int($hint) && !is_string($hint)) throw new \InvalidArgumentException();
            $hint = \App\Shared\Read\PositiveId::value($hint);
        } catch (\InvalidArgumentException $cause) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_INVALID', [], $cause);
        }
        $roots=$this->db->table('organizations')->select('id')->where('type','GROUP')->where('parent_id',null)->get()->getResultArray();
        if (count($roots)!==1 || (string)$hint!==(string)$roots[0]['id']) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_MISMATCH');
        }
    }

    /** @template T @param callable():T $task @return T */
    private function transaction(callable $task): mixed
    {
        if ($this->db->transDepth!==0) throw new DomainException('PRODUCT_MUTATION_FAILED',['operation'=>'nested_transaction']);
        $name='dinkerp:'.(defined('ENVIRONMENT')?ENVIRONMENT:'development').':'.$this->db->getDatabase().':product-master';
        if (strlen($name)>64) $name=substr($name,0,30).':'.substr(hash('sha256',$name),0,32);
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($name).', 10) AS acquired');
        if ($lock===false || (int)($lock->getRowArray()['acquired']??0)!==1) {
            throw new DomainException('PRODUCT_MUTATION_LOCK_FAILED');
        }
        try {
            if (! $this->db->transBegin()) $this->writeFailure('begin');
            try {
                $value=$task();
                if (! $this->db->transStatus() || ! $this->db->transCommit()) $this->writeFailure('commit');
                return $value;
            } catch (Throwable $cause) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $cause;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($name).')');
        }
    }
}

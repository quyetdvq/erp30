<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/**
 * M7.2: authorized direct COMBO item reads and atomic replacement.
 * Uses the Product Master advisory mutex to serialize graph/structure edits;
 * reads only direct COMBO edges and the bounded, reachable graph (never all Products).
 */
final class ProductComboService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db=$db ?? db_connect();
        $this->access=new UnifiedAccessService($actor,$this->db);
        $this->actorId=(string)$actor->id;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $rawId): array
    {
        $id=ProductComboRules::productId($rawId);
        if (!$this->access->hasBaseViewPermission('product')) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED',['action'=>'view']);
        }
        $statuses=$this->access->viewableMasterLifecycleStatuses('product');
        $plan=ProductComboSql::authorizedOwner($id,$statuses,(string)$this->db->DBPrefix);
        $result=$this->db->query($plan['sql'],$plan['binds']);
        if ($result===false) throw new DomainException('COMBO_CONNECTION_INVALID');
        $owner=$result->getRowArray();
        if ($owner===null) throw new PageNotFoundException('Product is not available.');
        if (!$this->access->canView('product',$owner)) {
            throw new UnexpectedValueException('COMBO SQL/PBAC scope disagreement.');
        }
        $this->assertOwner($owner,$id);
        return $this->byOwner($id);
    }

    /** @param array<mixed> $request @return list<array<string,mixed>> */
    public function replace(string $rawId,array $request): array
    {
        $id=ProductComboRules::productId($rawId);
        $parsed=ProductComboRules::request($request);
        foreach ($parsed['items'] as $item) {
            if ($item['component_product_id']===$id) throw new DomainException('COMBO_SELF_REFERENCE');
        }
        return $this->transaction(function() use($id,$parsed):array {
            $owner=$this->lockedProduct($id);
            if (!$this->access->canView('product',$owner)) throw new PageNotFoundException('Product is not available.');
            if (!$this->access->decideMutation('product','edit',$owner)['allowed']) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED',['action'=>'edit']);
            }
            $this->assertEvaluationHint($parsed['hint']);
            $this->assertOwner($owner,$id);
            $old=$this->lockedItems($id);
            $oldIds=[];
            foreach ($old as $row) $oldIds[(string)$row['component_product_id']]=true;
            $componentIds=array_column($parsed['items'],'component_product_id');
            $components=$this->lockedComponents($componentIds);
            $this->validateComponents($owner,$parsed['items'],$components,$oldIds);

            // All graph-reading and graph-changing writers share the Product
            // advisory mutex. Only descendant edges of requested COMBO items
            // are loaded, in indexed batches, and cycle checking is pure.
            $reach=$this->reachableEdges($id,$parsed['items'],$components);
            ProductComboGraph::assertAcyclic($id,$parsed['items'],$reach);

            $plan=ProductComboPlan::build($old,$parsed['items']);
            $now=gmdate('Y-m-d H:i:s');
            foreach ($plan['deletes'] as $row) {
                $this->perform('delete',fn()=> $this->db->table('product_combo_items')
                    ->where('combo_product_id',$id)->where('id',$row['id'])->delete());
            }
            foreach ($plan['updates'] as $item) {
                $this->perform('update',fn()=> $this->db->table('product_combo_items')
                    ->where('combo_product_id',$id)->where('id',$item['old']['id'])
                    ->update(['quantity'=>$item['new']['quantity'], 'updated_by'=>$this->actorId,
                        'updated_at'=>$now]));
            }
            foreach ($plan['creates'] as $item) {
                $this->perform('insert',fn()=> $this->db->table('product_combo_items')->insert([
                    'combo_product_id'=>$id, 'component_product_id'=>$item['component_product_id'],
                    'quantity'=>$item['quantity'], 'created_by'=>$this->actorId, 'created_at'=>$now,
                ]));
            }
            return $this->byOwner($id);
        });
    }

    /** @param array<string,mixed> $owner */
    private function assertOwner(array $owner,string $id): void
    {
        if (strtoupper((string)($owner['product_type']??''))!=='COMBO'
            || $owner['parent_product_id']!==null || $this->hasChildren([$id])!==[]) {
            throw new DomainException('PRODUCT_INVALID_ROLE',['field'=>'product_type']);
        }
    }

    /** @param array<string,mixed> $owner
     *  @param list<array{component_product_id:string,quantity:string}> $items
     *  @param array<string,array<string,mixed>> $components
     *  @param array<string,bool> $retained
     */
    private function validateComponents(array $owner,array $items,array $components,array $retained):void
    {
        $ids=array_column($items,'component_product_id');
        $children=$this->hasChildren($ids);
        $parents=[]; $uomIds=[];
        if (($owner['base_uom_id']??null)===null) throw new DomainException('PRODUCT_BASE_UOM_REQUIRED');
        $uomIds[]=(string)$owner['base_uom_id'];
        foreach ($items as $item) {
            $id=$item['component_product_id'];
            $component=$components[$id]??null;
            if ($component===null) throw new DomainException('COMBO_INVALID_REFERENCE',['field'=>'component_product_id']);
            if (!$this->access->canView('product',$component)) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED',['action'=>'view']);
            }
            $type=strtoupper((string)($component['product_type']??''));
            if (!in_array($type,['STANDARD','COMBO'],true) || isset($children[$id])
                || ($type==='COMBO' && $component['parent_product_id']!==null)) {
                throw new DomainException('PRODUCT_INVALID_ROLE',['field'=>'component_product_id']);
            }
            if ($type==='STANDARD' && $component['parent_product_id']!==null) {
                $parentId=(string)$component['parent_product_id'];
                if ($parentId===$id) throw new DomainException('PRODUCT_INVALID_ROLE');
                $parents[$parentId]=true;
            }
            if (($component['base_uom_id']??null)===null) throw new DomainException('PRODUCT_BASE_UOM_REQUIRED');
            $uomIds[]=(string)$component['base_uom_id'];
            if (!isset($retained[$id]) && strtoupper((string)$component['status'])!=='ACTIVE') {
                throw new DomainException('COMBO_INACTIVE_REFERENCE',['field'=>'component_product_id']);
            }
        }
        if ($parents!==[]) {
            $parentRows=$this->lockedProducts(array_keys($parents));
            foreach (array_keys($parents) as $parentId) {
                $p=$parentRows[$parentId]??null;
                if ($p===null || strtoupper((string)$p['product_type'])!=='STANDARD'
                    || $p['parent_product_id']!==null) {
                    throw new DomainException('PRODUCT_INVALID_ROLE',['field'=>'component_product_id']);
                }
            }
        }
        $uoms=$this->lockedUoms(array_values(array_unique($uomIds)));
        if (($uoms[(string)$owner['base_uom_id']]??null)!=='ACTIVE') {
            throw new DomainException('PRODUCT_BASE_UOM_INACTIVE');
        }
        foreach ($items as $item) {
            $id=$item['component_product_id'];
            $uom=(string)$components[$id]['base_uom_id'];
            if (!isset($uoms[$uom]) || (!isset($retained[$id]) && $uoms[$uom]!=='ACTIVE')) {
                throw new DomainException('PRODUCT_BASE_UOM_INACTIVE',['field'=>'component_product_id']);
            }
        }
    }

    /** @param list<array{component_product_id:string,quantity:string}> $items
     *  @param array<string,array<string,mixed>> $components
     *  @return list<array{combo_product_id:string,component_product_id:string}>
     */
    private function reachableEdges(string $owner,array $items,array $components):array
    {
        $front=[];
        foreach ($items as $item) {
            $id=$item['component_product_id'];
            if (strtoupper((string)$components[$id]['product_type'])==='COMBO') $front[$id]=true;
        }
        $seen=[]; $edges=[];
        while ($front!==[]) {
            $ids=[];
            foreach (array_keys($front) as $id) {
                $id=(string)$id;
                if (!isset($seen[$id])) {
                    $seen[$id]=true;
                    // A path back to the owner is a cycle. Keep the incoming
                    // edge for the graph validator; never traverse the old
                    // owner edges, which are about to be replaced atomically.
                    if ($id!==$owner) $ids[]=$id;
                }
            }
            if ($ids===[]) break;
            if (count($seen)>ProductComboGraph::MAX_NODES) throw new DomainException('COMBO_GRAPH_LIMIT');
            $front=[];
            foreach (array_chunk($ids,250) as $batch) {
                $result=$this->db->table('product_combo_items')
                    ->select('combo_product_id, component_product_id')->whereIn('combo_product_id',$batch)->get();
                if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'graph_query']);
                foreach ($result->getResultArray() as $row) {
                    $a=(string)$row['combo_product_id']; $b=(string)$row['component_product_id'];
                    $edges[]=['combo_product_id'=>$a,'component_product_id'=>$b];
                    if (count($edges)>ProductComboGraph::MAX_EDGES) throw new DomainException('COMBO_GRAPH_LIMIT');
                    if (!isset($seen[$b])) $front[$b]=true;
                }
            }
        }
        return $edges;
    }

    /** @return array<string,mixed> */
    private function lockedProduct(string $id):array
    {
        $row=$this->db->query($this->db->table('products')->where('id',$id)->getCompiledSelect().' FOR UPDATE');
        if ($row===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'select_owner']);
        $product=$row->getRowArray();
        if ($product===null) throw new DomainException('PRODUCT_NOT_FOUND');
        return $product;
    }

    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    private function lockedComponents(array $ids):array
    {
        return $this->lockedProducts($ids);
    }

    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    private function lockedProducts(array $ids):array
    {
        if ($ids===[]) return [];
        sort($ids,SORT_STRING);
        $sql=$this->db->table('products')->whereIn('id',$ids)->orderBy('id','ASC')->getCompiledSelect().' FOR UPDATE';
        $result=$this->db->query($sql);
        if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'select_components']);
        $out=[];
        foreach ($result->getResultArray() as $row) $out[(string)$row['id']]=$row;
        return $out;
    }

    /** @param list<string> $ids @return array<string,bool> */
    private function hasChildren(array $ids):array
    {
        if ($ids===[]) return [];
        $result=$this->db->table('products')->select('parent_product_id')->whereIn('parent_product_id',$ids)->get();
        if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'children_query']);
        $out=[];
        foreach ($result->getResultArray() as $row) $out[(string)$row['parent_product_id']]=true;
        return $out;
    }

    /** @param list<string> $ids @return array<string,string> */
    private function lockedUoms(array $ids):array
    {
        if ($ids===[]) return [];
        sort($ids,SORT_STRING);
        $sql=$this->db->table('product_uoms')->select('id,status')->whereIn('id',$ids)
            ->orderBy('id','ASC')->getCompiledSelect().' FOR UPDATE';
        $result=$this->db->query($sql);
        if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'select_uoms']);
        $out=[];
        foreach ($result->getResultArray() as $row) $out[(string)$row['id']]=(string)$row['status'];
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function lockedItems(string $id):array
    {
        $sql=$this->db->table('product_combo_items')->where('combo_product_id',$id)
            ->orderBy('component_product_id','ASC')->orderBy('id','ASC')->getCompiledSelect().' FOR UPDATE';
        $result=$this->db->query($sql);
        if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'select_items']);
        return array_values($result->getResultArray());
    }

    /** @return list<array<string,mixed>> */
    private function byOwner(string $id):array
    {
        $result=$this->db->table('product_combo_items')->where('combo_product_id',$id)
            ->orderBy('component_product_id','ASC')->orderBy('id','ASC')->get();
        if ($result===false) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'readback']);
        return array_values($result->getResultArray());
    }

    private function assertEvaluationHint(mixed $hint):void
    {
        if ($hint===null) return;
        if (!is_string($hint) && !is_int($hint)) throw new DomainException('EVALUATION_ORGANIZATION_HINT_INVALID');
        try {$id=PositiveId::value($hint);}
        catch(InvalidArgumentException $e){throw new DomainException('EVALUATION_ORGANIZATION_HINT_INVALID',[],$e);}
        $roots=$this->db->table('organizations')->select('id')->where('type','GROUP')
            ->where('parent_id',null)->get()->getResultArray();
        if (count($roots)!==1 || (string)$roots[0]['id']!==$id) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_MISMATCH');
        }
    }

    /** Refuse irreversible bulk writes on MyISAM or uninspectable engines. */
    private function assertTransactionEngines():void
    {
        $prefix=(string)$this->db->DBPrefix;
        if ($prefix!=='' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$prefix)!==1) {
            throw new DomainException('COMBO_CONNECTION_INVALID');
        }
        $tables=array_map(static fn(string $name):string=>$prefix.$name,
            ['products','product_uoms','product_combo_items']);
        try {
            $result=$this->db->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES'
                .' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)',$tables);
            if ($result===false) throw new UnexpectedValueException('Unavailable table engines');
            $found=[];
            foreach ($result->getResultArray() as $row) {
                $found[strtolower((string)$row['TABLE_NAME'])]=strtoupper((string)$row['ENGINE']);
            }
            foreach ($tables as $table) {
                if (($found[strtolower($table)]??null)!=='INNODB') throw new UnexpectedValueException('Non-InnoDB table');
            }
        }catch(Throwable $e){throw new DomainException('COMBO_CONNECTION_INVALID',[],$e);}
    }

    /** @param callable():bool $write */
    private function perform(string $operation,callable $write):void
    {
        try {if (!$write()) throw new UnexpectedValueException('SQL rejected write');}
        catch(Throwable $e) {
            $number=(int)($this->db->error()['code']??0);
            if ($number===1062 || (int)$e->getCode()===1062) throw new DomainException('COMBO_DUPLICATE',[],$e);
            if ($number===1452 || (int)$e->getCode()===1452) throw new DomainException('COMBO_INVALID_REFERENCE',[],$e);
            throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>$operation],$e);
        }
    }

    private function lockName():string
    {
        $name='dinkerp:'.(defined('ENVIRONMENT')?ENVIRONMENT:'development')
            .':'.$this->db->getDatabase().':product-master';
        return strlen($name)<=64?$name:substr($name,0,30).':'.substr(hash('sha256',$name),0,32);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(callable $work):mixed
    {
        if ($this->db->transDepth!==0) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'nested_transaction']);
        $this->assertTransactionEngines();
        $lockName=$this->lockName();
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($lockName).',10) AS acquired');
        if ($lock===false || (int)($lock->getRowArray()['acquired']??0)!==1) {
            throw new DomainException('PRODUCT_MUTATION_LOCK_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'begin']);
            try {
                $result=$work();
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('COMBO_MUTATION_FAILED',['operation'=>'commit']);
                }
                return $result;
            }catch(Throwable $e){
                $this->db->transRollback(); $this->db->resetTransStatus(); throw $e;
            }
        }finally{$this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($lockName).')');}
    }
}

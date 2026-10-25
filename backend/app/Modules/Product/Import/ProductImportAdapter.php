<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;
use App\Modules\Import\Core\ImportAdapter;
use App\Modules\Import\Core\ImportTemplateCatalog;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Product\Master\ProductMutationService;
use App\Modules\Product\Conversion\ProductConversionService;
use App\Modules\Product\Combo\ProductComboService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/** M8 Product-specific references, PBAC and established business commands. */
final class ProductImportAdapter implements ImportAdapter
{
    public function __construct(private readonly User $actor, private readonly BaseConnection $db)
    {
    }

    public function entityType(): string { return 'PRODUCT'; }
    public function template(): array { return ImportTemplateCatalog::product(); }

    public function transactionalTables(): array
    {
        return ['products','product_groups','product_uoms','product_uom_conversions',
            'product_combo_items','auto_numbering_series','auto_numbering_counters'];
    }

    public function viewableJobStatuses(UnifiedAccessService $access): array
    {
        return $access->viewableProductImportStatuses();
    }

    public function canViewJob(UnifiedAccessService $access, array $row): bool
    {
        return ($row['entity_type'] ?? null) === $this->entityType() && $access->canView('import_job',$row);
    }

    public function authorizeStart(): void
    {
        // Re-read active Shield/ERP assignment and policy snapshot per row.
        if (!(new UnifiedAccessService($this->actor,$this->db))->canStartProductImport()) {
            throw new DomainException('PRODUCT_IMPORT_AUTHORIZATION_DENIED');
        }
    }

    /** @param array<string,string|null> $row @param array<string,array{id:string,code:string}> $sourceMap
     *  @return array{target_ref_id:?string,entity_key:?string}
     */
    public function execute(array $row, string $mode, array &$sourceMap): array
    {
        $this->authorizeStart();
        return match (strtoupper((string)($row['record_type']??''))) {
            'PRODUCT' => $this->product($row,$mode,$sourceMap),
            'UOM_CONVERSION' => $this->conversion($row,$mode,$sourceMap),
            'COMBO_ITEM' => $this->combo($row,$mode,$sourceMap),
            default => throw new DomainException('IMPORT_INVALID_RECORD_TYPE'),
        };
    }

    /** @param array<string,string|null> $row @param array<string,array{id:string,code:string}> $sources
     *  @return array{target_ref_id:string,entity_key:string}
     */
    private function product(array $row, string $mode, array &$sources): array
    {
        $source=self::required($row,'source_key');
        $code=self::optional($row,'product_code');
        $old=$code===null?null:$this->db->table('products')->select('id,code,status')->where('code',$code)->get()->getRowArray();
        // A caller with product.import must not learn or modify a hidden
        // Product merely by guessing its code. The business service enforces
        // mutation PBAC again inside its own transaction.
        if ($old!==null && !(new UnifiedAccessService($this->actor,$this->db))->canView('product',$old)) {
            throw new DomainException('IMPORT_REFERENCE_NOT_FOUND',['field'=>'product_code']);
        }
        $create=$this->createTarget($mode,$old!==null);
        $payload=[];
        foreach (['sku','barcode','product_name','product_child_name','product_type','price',
            'net_weight_g','package_gross_weight_g','package_length_mm','package_width_mm',
            'package_height_mm','description','manual_standard_cost','serial_management_enabled'] as $name) {
            if (array_key_exists($name,$row) && ($row[$name]!=='' || $row[$name]===null)) {
                $payload[$name]=$row[$name];
            }
        }
        // CSV/XLSX cells arrive as strings, whereas ProductMutationRules uses
        // typed bools or '1'/'0'. Never weaken the Product service rules.
        if (is_string($payload['serial_management_enabled']??null)) {
            $flag=strtolower(trim($payload['serial_management_enabled']));
            if ($flag==='true') $payload['serial_management_enabled']='1';
            if ($flag==='false') $payload['serial_management_enabled']='0';
        }
        // A CSV status cell is NEVER permission to bypass Workflow.setState().
        if (self::optional($row,'status')!==null) throw new DomainException('IMPORT_STATUS_REQUIRES_SETSTATE');
        if ($create && $code!==null) $payload['code']=$code;
        $parentKey=self::optional($row,'parent_source_key');
        $parentCode=self::optional($row,'parent_product_code');
        if ($parentKey!==null && $parentCode!==null) throw new DomainException('IMPORT_REFERENCE_AMBIGUOUS');
        if ($parentKey!==null || $parentCode!==null) {
            $payload['parent_product_id']=$this->resolve($parentKey,$parentCode,$sources);
        }
        foreach (['product_group_code'=>'product_groups', 'base_uom_code'=>'product_uoms'] as $field=>$table) {
            $catalogCode=self::optional($row,$field);
            if ($catalogCode===null) continue;
            $record=$this->db->table($table)->select('id,status')->where('code',$catalogCode)->get()->getRowArray();
            if ($record===null || strtoupper((string)$record['status'])!=='ACTIVE'
                || !(new UnifiedAccessService($this->actor,$this->db))->canView(
                    $table==='product_groups'?'product_group':'product_uom', $record)) {
                throw new DomainException('IMPORT_REFERENCE_NOT_FOUND',['field'=>$field]);
            }
            $payload[$field==='product_group_code'?'product_group_id':'base_uom_id']=(string)$record['id'];
        }
        $service=new ProductMutationService($this->actor,$this->db);
        if ($create) {
            $result=$service->create($payload);
        } else {
            if (count($payload)===0) throw new DomainException('IMPORT_EMPTY_UPDATE');
            $result=$service->update((string)$old['id'],$payload);
        }
        $id=(string)($result['id']??($old['id']??''));
        $persisted=$this->db->table('products')->select('id,code')->where('id',$id)->get()->getRowArray();
        if ($persisted===null) throw new DomainException('IMPORT_ROW_FAILED');
        $realCode=(string)$persisted['code'];
        $sources[$source]=['id'=>$id,'code'=>$realCode];
        return ['target_ref_id'=>$id,'entity_key'=>$realCode];
    }

    /** @param array<string,string|null> $row @param array<string,array{id:string,code:string}> $sources
     *  @return array{target_ref_id:?string,entity_key:?string}
     */
    private function conversion(array $row,string $mode,array $sources):array
    {
        $owner=$this->resolve(self::optional($row,'product_source_key'),self::optional($row,'product_code'),$sources);
        $uomCode=self::required($row,'uom_code');
        $uom=$this->db->table('product_uoms')->select('id,status')->where('code',$uomCode)->get()->getRowArray();
        if ($uom===null || !(new UnifiedAccessService($this->actor,$this->db))->canView('product_uom',$uom)) throw new DomainException('IMPORT_REFERENCE_NOT_FOUND',['field'=>'uom_code']);
        $id=(string)$uom['id'];
        $service=new ProductConversionService($this->actor,$this->db);
        return $this->mergeLocked(function ()use($service,$owner,$id,$row,$mode,$uomCode):array {
            $items=$service->list($owner);
            $found=false;
            $updated=[];
            foreach ($items as $item) {
                $itemId=(string)$item['uom_id'];
                $normalized=self::conversionFields($item);
                if ($itemId===$id) {
                    $found=true;
                    if ($mode==='CREATE_ONLY') throw new DomainException('IMPORT_TARGET_EXISTS');
                    $normalized=self::conversionFields(array_replace($normalized,self::conversionChanges($row)));
                }
                $updated[]=$normalized;
            }
            if (!$found) {
                if ($mode==='UPDATE_ONLY') throw new DomainException('IMPORT_TARGET_NOT_FOUND');
                $updated[]=self::conversionFields(['uom_id'=>$id,...self::conversionChanges($row)]);
            }
            $result=$service->replace($owner,['items'=>$updated]);
            foreach ($result as $item) {
                if ((string)$item['uom_id']===$id) {
                    return ['target_ref_id'=>(string)$item['id'],'entity_key'=>$uomCode];
                }
            }
            throw new DomainException('IMPORT_ROW_FAILED');
        });
    }

    /** @param array<string,string|null> $row @param array<string,array{id:string,code:string}> $sources
     *  @return array{target_ref_id:?string,entity_key:?string}
     */
    private function combo(array $row,string $mode,array $sources):array
    {
        $owner=$this->resolve(self::optional($row,'combo_source_key'),self::optional($row,'combo_product_code'),$sources);
        $component=$this->resolve(self::optional($row,'component_source_key'),self::optional($row,'component_product_code'),$sources);
        $quantity=self::required($row,'quantity');
        $service=new ProductComboService($this->actor,$this->db);
        return $this->mergeLocked(function ()use($service,$owner,$component,$quantity,$mode):array {
            $items=$service->list($owner);
            $updated=[];$found=false;
            foreach ($items as $item) {
                $itemId=(string)$item['component_product_id'];
                $qty=(string)$item['quantity'];
                if ($itemId===$component) {
                    $found=true;
                    if ($mode==='CREATE_ONLY') throw new DomainException('IMPORT_TARGET_EXISTS');
                    $qty=$quantity;
                }
                $updated[]=['component_product_id'=>$itemId,'quantity'=>$qty];
            }
            if (!$found) {
                if ($mode==='UPDATE_ONLY') throw new DomainException('IMPORT_TARGET_NOT_FOUND');
                $updated[]=['component_product_id'=>$component,'quantity'=>$quantity];
            }
            $result=$service->replace($owner,['items'=>$updated]);
            foreach ($result as $item) {
                if ((string)$item['component_product_id']===$component) {
                    return ['target_ref_id'=>(string)$item['id'],'entity_key'=>$component];
                }
            }
            throw new DomainException('IMPORT_ROW_FAILED');
        });
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private static function conversionFields(array $values):array
    {
        $keys=['uom_id','conversion_factor','price','package_gross_weight_g',
            'package_length_mm','package_width_mm','package_height_mm','usage_type','status'];
        $result=[];
        foreach ($keys as $key) if (array_key_exists($key,$values)) $result[$key]=$values[$key];
        return $result;
    }

    /** @param array<string,string|null> $row @return array<string,mixed> */
    private static function conversionChanges(array $row):array
    {
        $changes=[];
        foreach (['conversion_factor','price','package_gross_weight_g',
            'package_length_mm','package_width_mm','package_height_mm','usage_type','status'] as $field) {
            if (array_key_exists($field,$row) && ($row[$field]!=='' || $row[$field]===null)) {
                $changes[$field]=$row[$field];
            }
        }
        return $changes;
    }

    /** @param array<string,array{id:string,code:string}> $sources */
    private function resolve(?string $source,?string $code,array $sources):string
    {
        if (($source===null) === ($code===null)) throw new DomainException('IMPORT_REFERENCE_INVALID');
        if ($source!==null) {
            if (!isset($sources[$source])) throw new DomainException('IMPORT_DEPENDENCY_FAILED');
            return $sources[$source]['id'];
        }
        $product=$this->db->table('products')->select('id,status')->where('code',$code)->get()->getRowArray();
        if ($product===null || !(new UnifiedAccessService($this->actor,$this->db))->canView('product',$product)) {
            throw new DomainException('IMPORT_REFERENCE_NOT_FOUND');
        }
        return (string)$product['id'];
    }

    /** @param array<string,string|null> $row */
    private static function optional(array $row,string $field):?string
    {
        $value=$row[$field]??null;
        return $value===null || $value===''?null:trim($value);
    }

    /** @param array<string,string|null> $row */
    private static function required(array $row,string $field):string
    {
        $value=self::optional($row,$field);
        if ($value===null) throw new DomainException('IMPORT_REQUIRED_FIELD',['field'=>$field]);
        return $value;
    }

    private function createTarget(string $mode,bool $exists):bool
    {
        return match($mode) {
            'CREATE_ONLY' => !$exists ? true : throw new DomainException('IMPORT_TARGET_EXISTS'),
            'UPDATE_ONLY' => $exists ? false : throw new DomainException('IMPORT_TARGET_NOT_FOUND'),
            'UPSERT' => !$exists,
            default => throw new DomainException('IMPORT_INVALID_MODE'),
        };
    }

    /**
     * Serialize read-merge-replace using the SAME Product advisory mutex.
     * The conversion/combo services acquire that mutex reentrantly on the
     * same connection; this outer reference survives their RELEASE_LOCK().
     * Do not open an outer transaction (those services reject nesting).
     * @template T @param callable():T $work @return T
     */
    private function mergeLocked(callable $work):mixed
    {
        $name='dinkerp:'.(defined('ENVIRONMENT')?ENVIRONMENT:'development').':'.$this->db->getDatabase().':product-master';
        if (strlen($name)>64) $name=substr($name,0,30).':'.substr(hash('sha256',$name),0,32);
        $lock=$this->db->query('SELECT GET_LOCK('.$this->db->escape($name).', 10) AS acquired');
        if ($lock===false || (int)($lock->getRowArray()['acquired']??0)!==1) {
            throw new DomainException('PRODUCT_MUTATION_LOCK_FAILED');
        }
        try { return $work(); }
        finally { $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($name).')'); }
    }
}

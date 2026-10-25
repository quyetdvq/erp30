<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use UnexpectedValueException;

/** M7.4: Product details/relations/sales UOM read projections with SQL PBAC pushdown. */
final class ProductRelationshipService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    /** @var list<string>|null */
    private ?array $productViewStates = null;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
    }

    /** @param array<string,mixed> $body @return list<array<string,mixed>> */
    public function resolveDetails(array $body): array
    {
        $ids = $this->ids($body, ProductRelationshipRules::MAX_DETAILS);
        $this->requireProductView();
        if ($ids === []) return [];
        return ProductRelationshipRules::resolvedDetails($ids, $this->visibleProducts($ids));
    }

    /** @param array<string,mixed> $body
     * @return list<array{product_id:string,total:int,products:list<array<string,mixed>>}>
     */
    public function relationPreviews(array $body): array
    {
        $ids = $this->ids($body, ProductRelationshipRules::MAX_RELATIONS);
        try { $limit = ProductRelationshipRules::limitPerProduct($body); }
        catch (InvalidArgumentException $e) { throw new BadRequestException('Invalid relation preview limit.', 0, $e); }
        $this->requireProductView();
        if ($ids === []) return [];
        $visible = $this->visibleProducts($ids);
        $roles = []; $parentIds = []; $comboIds = [];
        foreach ($visible as $id => $product) {
            $role = (string)$product['role'];
            if (!in_array($role, ['PARENT', 'COMBO'], true)) continue;
            $roles[$id] = $role;
            if ($role === 'PARENT') $parentIds[] = $id;
            else $comboIds[] = $id;
        }
        if ($roles === []) return [];
        $viewStates = $this->productStates();
        if ($viewStates === []) return ProductRelationshipRules::relationPreviews($ids, $roles, []);
        $sql = ProductRelationshipSql::relationWindows($parentIds, $comboIds, $viewStates,
            $limit, (string)$this->db->DBPrefix);
        $rows = $this->query($sql);
        $childIds = [];
        foreach ($rows as $row) $childIds[] = (string)$row['product_id'];
        $children = $childIds === [] ? [] : $this->visibleProducts(array_values(array_unique($childIds)));
        $byOwner = [];
        foreach ($rows as $row) {
            $ownerId = (string)$row['owner_id'];
            $childId = (string)$row['product_id'];
            if (!isset($roles[$ownerId]) || !isset($children[$childId])) {
                throw new UnexpectedValueException('PBAC relation preview changed during read.');
            }
            $total = (int)$row['total'];
            if (!isset($byOwner[$ownerId])) $byOwner[$ownerId] = ['total'=>$total,'products'=>[]];
            if ($byOwner[$ownerId]['total'] !== $total) {
                throw new UnexpectedValueException('Inconsistent authorized relation count.');
            }
            $byOwner[$ownerId]['products'][] = $children[$childId];
        }
        return ProductRelationshipRules::relationPreviews($ids, $roles, $byOwner);
    }

    /** @param array<string,mixed> $body @return list<array{product_id:string,options:list<array{id:string,code:string}>}> */
    public function salesUomOptions(array $body): array
    {
        $ids = $this->ids($body, ProductRelationshipRules::MAX_SALES_UOMS);
        $this->requireProductView();
        if ($ids === []) return [];
        $visible = $this->visibleProducts($ids);
        if ($visible === []) return [];
        $viewable = $this->access->viewableMasterLifecycleStatuses('product_uom');
        if ($viewable === [] || !in_array('ACTIVE', $viewable, true)) {
            return ProductRelationshipRules::salesOptions($ids, array_fill_keys(array_keys($visible), true), []);
        }
        $nonCombo = [];
        foreach ($visible as $id => $product) {
            if ($product['role'] !== 'COMBO') $nonCombo[] = $id;
        }
        $sql = ProductRelationshipSql::salesUoms(array_keys($visible), $nonCombo, $viewable,
            (string)$this->db->DBPrefix);
        $options = []; $seen = [];
        foreach ($this->query($sql) as $row) {
            if (!$this->access->canView('product_uom', [
                'id'=>$row['uom_id'], 'code'=>$row['code'], 'status'=>$row['status'],
            ])) {
                throw new UnexpectedValueException('SALES UOM SQL/PBAC scope disagreement.');
            }
            $owner = (string)$row['product_id'];
            $uom = (string)$row['uom_id'];
            if (!isset($visible[$owner])) throw new UnexpectedValueException('Unrequested Product returned by SQL.');
            if (isset($seen[$owner][$uom])) continue;
            $seen[$owner][$uom] = true;
            $options[$owner][] = ['id'=>$uom, 'code'=>(string)$row['code']];
        }
        return ProductRelationshipRules::salesOptions($ids, array_fill_keys(array_keys($visible), true), $options);
    }

    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    private function visibleProducts(array $ids): array
    {
        if ($ids === []) return [];
        $states = $this->productStates();
        if ($states === []) return [];
        $plan = ProductRelationshipSql::productsByIds($ids, $states, (string)$this->db->DBPrefix);
        $byId = [];
        foreach ($this->query($plan) as $row) {
            if (!$this->access->canView('product', $row)) {
                throw new UnexpectedValueException('Product relationship SQL/PBAC scope disagreement.');
            }
            $id = (string)$row['id'];
            $byId[$id] = ProductRelationshipProjection::detail($row);
        }
        return $byId;
    }

    /** @param array{sql:string,binds:list<mixed>} $plan @return list<array<string,mixed>> */
    private function query(array $plan): array
    {
        $result = $this->db->query($plan['sql'], $plan['binds']);
        if (!is_object($result)) throw new UnexpectedValueException('Product relationship SQL failed.');
        return $result->getResultArray();
    }

    /** @param array<string,mixed> $body @return list<string> */
    private function ids(array $body, int $max): array
    {
        try { return ProductRelationshipRules::ids($body, $max); }
        catch (InvalidArgumentException $e) { throw new BadRequestException('Invalid Product relationship ID batch.', 0, $e); }
    }

    /** @return list<string> One canonical PBAC snapshot for this request, reused across owner/child reads. */
    private function productStates(): array
    {
        return $this->productViewStates ??= $this->access->viewableMasterLifecycleStatuses('product');
    }

    private function requireProductView(): void
    {
        if (!$this->access->hasBaseViewPermission('product')) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'view']);
        }
    }
}

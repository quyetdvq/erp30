<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Exceptions\PersistenceException;
use CodeIgniter\Database\ConnectionInterface;

/** Bounded read-only projections; no Product/Combo business rules live here. */
final class ProductLookupRepository
{
    /** @param ConnectionInterface<mixed,mixed> $db */
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /**
     * Filter derived Product roles BEFORE count/offset/limit. Never filter a
     * generic /products page in PHP: that would silently omit valid choices.
     *
     * @return array{total:int,rows:list<array<string,mixed>>}
     */
    public function options(string $purpose, string $q, int $page, int $pageSize): array
    {
        $productsTable = $this->quoted('products');
        $builder = $this->db->table('products p')
            ->select('p.*')
            ->join('products parent', 'parent.id = p.parent_product_id', 'left')
            ->where("HEX(p.status) = '414354495645'", null, false);

        // ProductRoleService is authoritative: STANDARD root with children is
        // PARENT; STANDARD root without children is SIMPLE; STANDARD with a
        // parent is CHILD. COMBO is selectable if its graph is well-formed.
        $typeStandard = "BINARY UPPER(p.product_type) = BINARY 'STANDARD'";
        $typeCombo = "BINARY UPPER(p.product_type) = BINARY 'COMBO'";
        $hasChildren = "EXISTS (SELECT 1 FROM {$productsTable} AS child WHERE child.parent_product_id = p.id)";
        if ($purpose === 'parent_product') {
            $eligible = "({$typeStandard} AND p.parent_product_id IS NULL)";
        } else {
            $eligible = "({$typeCombo} OR ({$typeStandard} AND (p.parent_product_id IS NOT NULL OR NOT {$hasChildren})))";
        }
        $builder->where($eligible, null, false);
        if ($q !== '') {
            $builder->groupStart()
                ->like('p.code', $q)
                ->orLike('p.sku', $q)
                ->orLike('p.product_name', $q)
                ->orLike('p.product_child_name', $q)
                ->orLike('parent.product_name', $q)
                ->groupEnd();
        }
        try {
            // countAllResults() with its default reset=true clears table aliases
            // on the shared DB connection, including when called on a clone.
            // Preserve this builder's aliases for the subsequent paged SELECT.
            $total = (int) $builder->countAllResults(false);
            $result = $builder->orderBy('p.id', 'ASC')->limit($pageSize, ($page - 1) * $pageSize)->get();
            if ($result === false) {
                throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'lookupOptions');
            }
            return ['total' => $total, 'rows' => array_values($result->getResultArray())];
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'lookupOptions', $exception);
        }
    }

    /**
     * One SQL windowed batch, not one request or full child scan per owner.
     * Scope/State-aware authorized status codes are supplied by application
     * service and enforced before COUNT(*) OVER or ROW_NUMBER().
     *
     * @param list<int> $ownerIds
     * @param list<string> $allowedStatusHex
     * @return list<array{owner_id:string,product_id:string,total:string}>
     */
    public function relationPreview(array $ownerIds, array $allowedStatusHex, int $limit): array
    {
        if ($ownerIds === [] || $allowedStatusHex === []) {
            return [];
        }
        $productTable = $this->quoted('products');
        $itemTable = $this->quoted('product_combo_items');
        $owners = implode(', ', array_fill(0, count($ownerIds), '?'));
        $statuses = implode(', ', array_fill(0, count($allowedStatusHex), '?'));
        $sql = <<<SQL
SELECT ranked.owner_id, ranked.product_id, ranked.total
FROM (
    SELECT rel.owner_id, rel.product_id,
           ROW_NUMBER() OVER (PARTITION BY rel.owner_id ORDER BY rel.product_id ASC) AS relation_rank,
           COUNT(*) OVER (PARTITION BY rel.owner_id) AS total
    FROM (
        SELECT child.parent_product_id AS owner_id, child.id AS product_id
        FROM {$productTable} child WHERE child.parent_product_id IN ({$owners})
        UNION ALL
        SELECT item.combo_product_id AS owner_id, item.component_product_id AS product_id
        FROM {$itemTable} item WHERE item.combo_product_id IN ({$owners})
    ) rel
    JOIN {$productTable} child ON child.id = rel.product_id
    WHERE HEX(child.status) IN ({$statuses})
) ranked
WHERE ranked.relation_rank <= ?
ORDER BY ranked.owner_id, ranked.product_id
SQL;
        try {
            $result = $this->db->query($sql, [...$ownerIds, ...$ownerIds, ...$allowedStatusHex, $limit]);
            if (! $result instanceof \CodeIgniter\Database\ResultInterface) {
                throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'relationPreview');
            }
            return array_values(array_map(static fn (array $row): array => [
                'owner_id' => (string) $row['owner_id'],
                'product_id' => (string) $row['product_id'],
                'total' => (string) $row['total'],
            ], $result->getResultArray()));
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'relationPreview', $exception);
        }
    }

    /**
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    public function uoms(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return $this->read('product_uoms', fn () => $this->db->table('product_uoms')->whereIn('id', $ids)->get());
    }

    /**
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    public function conversions(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return $this->read('product_uom_conversions', fn () => $this->db->table('product_uom_conversions')->whereIn('product_id', $ids)->get());
    }

    /** @return list<array<string,mixed>> */
    private function read(string $name, callable $call): array
    {
        try {
            $result = $call();
            if ($result === false) {
                throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'lookup_' . $name);
            }
            return array_values($result->getResultArray());
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'lookup_' . $name, $exception);
        }
    }

    private function quoted(string $table): string
    {
        if (! $this->db instanceof \CodeIgniter\Database\BaseConnection) {
            throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'lookupQuoted');
        }
        return '`' . str_replace('`', '``', $this->db->prefixTable($table)) . '`';
    }
}

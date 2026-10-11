<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use CodeIgniter\Database\ConnectionInterface;

/** Canonical read-only Product role and capability derivation. */
final class ProductRoleService
{
    public const ROLE_SIMPLE = 'SIMPLE';
    public const ROLE_PARENT = 'PARENT';
    public const ROLE_CHILD = 'CHILD';
    public const ROLE_COMBO = 'COMBO';
    public const ERROR_NOT_FOUND = 'PRODUCT_NOT_FOUND';
    public const ERROR_INVALID_ROLE = 'PRODUCT_INVALID_ROLE';

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductRepository $repository = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @var ConnectionInterface<mixed, mixed> */
    private readonly ConnectionInterface $db;

    /** @param mixed $product Product id or persisted Product row. */
    public function getRole(mixed $product): string
    {
        return $this->resolve($product)['role'];
    }

    /**
     * Derive list-only Product fields from a complete Product snapshot.
     *
     * Reuses the same role validation as single-record reads while avoiding
     * per-Product parent/child SQL queries. Call with the unpaginated list:
     * structural checks require parents and descendants outside the page.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function describeList(array $rows): array
    {
        $byId = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(int) $row['id']] = $row;
            }
            if (($row['parent_product_id'] ?? null) !== null) {
                $childrenByParent[(int) $row['parent_product_id']][] = $row;
            }
        }
        foreach ($childrenByParent as $parentId => $children) {
            usort($children, static fn (array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
            $childrenByParent[$parentId] = $children;
        }

        $result = [];
        foreach ($rows as $row) {
            $resolved = $this->resolve($row, $byId, $childrenByParent);
            $role = $resolved['role'];
            $row['role'] = $role;
            $row['full_name'] = $this->fullName($resolved);
            $row['selectable'] = strtoupper((string) ($row['status'] ?? '')) === 'ACTIVE'
                && in_array($role, [self::ROLE_SIMPLE, self::ROLE_CHILD, self::ROLE_COMBO], true);
            $row['leaf_transactionable'] = in_array($role, [self::ROLE_SIMPLE, self::ROLE_CHILD], true);
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Describe only requested Product rows using bounded relationship queries.
     * A page may contain CHILD rows without their parent, or a PARENT without
     * its children. Both relationships must be loaded before deriving roles.
     * Grandchild existence is queried as IDs, not fetched as full rows.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function describePage(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $byId = [];
        $parentIds = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(int) $row['id']] = $row;
            }
            if (($row['parent_product_id'] ?? null) !== null) {
                $parentIds[] = (int) $row['parent_product_id'];
            }
        }
        $pageIds = array_keys($byId);
        foreach ($this->repo()->findByIds($parentIds) as $parent) {
            $byId[(int) $parent['id']] = $parent;
        }

        $childrenByParent = [];
        $hasChildrenIds = [];
        $childIds = [];
        foreach ($this->repo()->listByParentIds($pageIds) as $child) {
            $parentId = (int) $child['parent_product_id'];
            $childrenByParent[$parentId][] = $child;
            $hasChildrenIds[$parentId] = true;
            $childIds[] = (int) $child['id'];
        }
        // The rows returned by listByParentIds include direct children of
        // page rows only; fetching siblings of a page CHILD is unnecessary.
        // A child with descendants is invalid; detect it without N+1 SQL.
        foreach ($this->repo()->parentIdsWithChildren($childIds) as $id) {
            $hasChildrenIds[$id] = true;
        }

        $result = [];
        foreach ($rows as $row) {
            $resolved = $this->resolve($row, $byId, $childrenByParent, $hasChildrenIds);
            $role = $resolved['role'];
            $row['role'] = $role;
            $row['full_name'] = $this->fullName($resolved);
            $row['selectable'] = strtoupper((string) ($row['status'] ?? '')) === 'ACTIVE'
                && in_array($role, [self::ROLE_SIMPLE, self::ROLE_CHILD, self::ROLE_COMBO], true);
            $row['leaf_transactionable'] = in_array($role, [self::ROLE_SIMPLE, self::ROLE_CHILD], true);
            $result[] = $row;
        }
        return $result;
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $productId): ?array
    {
        return $this->repo()->findById($productId);
    }

    public function isParent(mixed $product): bool
    {
        return $this->getRole($product) === self::ROLE_PARENT;
    }

    public function isChild(mixed $product): bool
    {
        return $this->getRole($product) === self::ROLE_CHILD;
    }

    public function isSimple(mixed $product): bool
    {
        return $this->getRole($product) === self::ROLE_SIMPLE;
    }

    public function isCombo(mixed $product): bool
    {
        return $this->getRole($product) === self::ROLE_COMBO;
    }

    public function isSelectable(mixed $product): bool
    {
        $resolved = $this->resolve($product);
        if (strtoupper((string) ($resolved['row']['status'] ?? '')) !== 'ACTIVE') {
            return false;
        }

        return in_array($resolved['role'], [self::ROLE_SIMPLE, self::ROLE_CHILD, self::ROLE_COMBO], true);
    }

    public function isLeafTransactionable(mixed $product): bool
    {
        return in_array($this->getRole($product), [self::ROLE_SIMPLE, self::ROLE_CHILD], true);
    }

    public function requiresComboExpansion(mixed $product): bool
    {
        return $this->getRole($product) === self::ROLE_COMBO;
    }

    public function getFullName(mixed $product): string
    {
        return $this->fullName($this->resolve($product));
    }

    /** @param array{row:array<string,mixed>,role:string,parent?:array<string,mixed>} $resolved */
    private function fullName(array $resolved): string
    {
        $row = $resolved['row'];
        if ($resolved['role'] !== self::ROLE_CHILD) {
            return $this->requiredName($row['product_name'] ?? null, 'product_name');
        }

        $childName = $this->requiredName($row['product_child_name'] ?? null, 'product_child_name');
        $parent = $resolved['parent'] ?? null;
        if ($parent === null) {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'parent_not_found', 'product_id' => $row['id'] ?? null]);
        }
        $this->assertRootStandard($parent, 'parent');

        return $this->requiredName($parent['product_name'] ?? null, 'parent.product_name') . ' - ' . $childName;
    }

    /**
     * @param array<int, array<string,mixed>>|null $byId
     * @param array<int, list<array<string,mixed>>>|null $childrenByParent
     * @return array{row:array<string,mixed>,role:string,parent?:array<string,mixed>}
     */
    private function resolve(mixed $product, ?array $byId = null, ?array $childrenByParent = null, ?array $hasChildrenIds = null): array
    {
        $row = is_array($product)
            ? $product
            : ($byId !== null ? ($byId[(int) $product] ?? null) : $this->repo()->findById($product));
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['product_id' => $product]);
        }
        if (! isset($row['id'])) {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'missing_id']);
        }

        $type = strtoupper((string) ($row['product_type'] ?? ''));
        $parentId = $row['parent_product_id'] ?? null;
        if ($type === self::ROLE_COMBO) {
            if ($parentId !== null || $this->hasChildren($row['id'], $childrenByParent, $hasChildrenIds)) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'combo_structure', 'product_id' => $row['id']]);
            }
            return ['row' => $row, 'role' => self::ROLE_COMBO];
        }
        if ($type !== 'STANDARD') {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'product_type', 'product_id' => $row['id']]);
        }
        if ($parentId !== null) {
            $parentId = PositiveId::normalize($parentId, 'parent_product_id', self::ERROR_INVALID_ROLE);
            if ((int) $row['id'] === $parentId) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'self_parent', 'product_id' => $row['id']]);
            }
            $parent = $byId !== null ? ($byId[$parentId] ?? null) : $this->repo()->findById($parentId);
            if ($parent === null) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'parent_not_found', 'product_id' => $row['id']]);
            }
            $this->assertRootStandard($parent, 'parent');
            if ($this->hasChildren($row['id'], $childrenByParent, $hasChildrenIds)) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'child_has_children', 'product_id' => $row['id']]);
            }
            return ['row' => $row, 'role' => self::ROLE_CHILD, 'parent' => $parent];
        }

        $children = $childrenByParent !== null
            ? ($childrenByParent[(int) $row['id']] ?? [])
            : $this->repo()->listChildren($row['id']);
        foreach ($children as $child) {
            if (strtoupper((string) ($child['product_type'] ?? '')) !== 'STANDARD'
                || ($child['parent_product_id'] ?? null) === null
                || (int) $child['parent_product_id'] !== (int) $row['id']
                || $this->hasChildren($child['id'], $childrenByParent, $hasChildrenIds)) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'child_structure', 'product_id' => $row['id']]);
            }
        }

        return ['row' => $row, 'role' => $children === [] ? self::ROLE_SIMPLE : self::ROLE_PARENT];
    }

    /** @param array<int, list<array<string,mixed>>>|null $childrenByParent */
    private function hasChildren(mixed $id, ?array $childrenByParent, ?array $hasChildrenIds = null): bool
    {
        if ($hasChildrenIds !== null) {
            return isset($hasChildrenIds[(int) $id]);
        }
        if ($childrenByParent !== null) {
            return ($childrenByParent[(int) $id] ?? []) !== [];
        }

        return $this->repo()->hasChildren($id);
    }

    /** @param array<string,mixed> $parent */
    private function assertRootStandard(array $parent, string $field): void
    {
        if (strtoupper((string) ($parent['product_type'] ?? '')) !== 'STANDARD' || ($parent['parent_product_id'] ?? null) !== null) {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => $field . '_not_root_standard', 'product_id' => $parent['id'] ?? null]);
        }
    }

    private function requiredName(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'required_name', 'field' => $field]);
        }

        return trim($value);
    }

    private function repo(): ProductRepository
    {
        return $this->repository ?? new ProductRepository($this->db);
    }
}

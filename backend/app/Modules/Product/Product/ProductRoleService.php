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
        $resolved = $this->resolve($product);
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

    /** @return array{row:array<string,mixed>,role:string,parent?:array<string,mixed>} */
    private function resolve(mixed $product): array
    {
        $row = is_array($product) ? $product : $this->repo()->findById($product);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['product_id' => $product]);
        }
        if (! isset($row['id'])) {
            throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'missing_id']);
        }

        $type = strtoupper((string) ($row['product_type'] ?? ''));
        $parentId = $row['parent_product_id'] ?? null;
        if ($type === self::ROLE_COMBO) {
            if ($parentId !== null || $this->repo()->hasChildren($row['id'])) {
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
            $parent = $this->repo()->findById($parentId);
            if ($parent === null) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'parent_not_found', 'product_id' => $row['id']]);
            }
            $this->assertRootStandard($parent, 'parent');
            if ($this->repo()->hasChildren($row['id'])) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'child_has_children', 'product_id' => $row['id']]);
            }
            return ['row' => $row, 'role' => self::ROLE_CHILD, 'parent' => $parent];
        }

        $children = $this->repo()->listChildren($row['id']);
        foreach ($children as $child) {
            if (strtoupper((string) ($child['product_type'] ?? '')) !== 'STANDARD'
                || ($child['parent_product_id'] ?? null) === null
                || (int) $child['parent_product_id'] !== (int) $row['id']
                || $this->repo()->hasChildren($child['id'])) {
                throw new DomainException(self::ERROR_INVALID_ROLE, ['reason' => 'child_structure', 'product_id' => $row['id']]);
            }
        }

        return ['row' => $row, 'role' => $children === [] ? self::ROLE_SIMPLE : self::ROLE_PARENT];
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

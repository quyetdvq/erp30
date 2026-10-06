<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Id\PositiveId;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Database\ConnectionInterface;

/** Read-only structural expansion of a COMBO into aggregated leaf quantities. */
final class ComboExpansionService
{
    public const ERROR_INVALID_QUANTITY = 'COMBO_EXPANSION_INVALID_QUANTITY';
    public const ERROR_CYCLE = 'COMBO_EXPANSION_CYCLE';
    public const ERROR_INACTIVE_REFERENCE = 'INACTIVE_REFERENCE';

    /** @var ConnectionInterface<mixed, mixed> */
    private readonly ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ComboCompositionRepository $repository = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $productUoms = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /**
     * Expand one active COMBO root into deterministic aggregated leaf rows.
     *
     * @return list<array{product_id:int,base_uom_id:int,quantity:string}>
     */
    public function expand(mixed $rootComboProductId, mixed $rootQuantity): array
    {
        $rootId = PositiveId::normalize($rootComboProductId, 'combo_product_id', ProductRoleService::ERROR_NOT_FOUND);
        $quantity = $this->positiveQuantity($rootQuantity, 'root_quantity');
        $root = $this->product($rootId);
        $this->assertOperationalCombo($root);
        $this->requireBaseUom($root, $rootId);

        $edgesByCombo = $this->edgesByCombo();
        $products = [$rootId => $root];
        $roles = [$rootId => ProductRoleService::ROLE_COMBO];
        $aggregate = [];
        $this->visitCombo($rootId, $quantity, $edgesByCombo, $products, $roles, [], $aggregate);

        $result = [];
        foreach ($aggregate as $leaf) {
            $result[] = [
                'product_id' => $leaf['product_id'],
                'base_uom_id' => $leaf['base_uom_id'],
                'quantity' => $this->canonicalDecimal($leaf['quantity']),
            ];
        }
        usort($result, static function (array $left, array $right): int {
            return [$left['product_id'], $left['base_uom_id']] <=> [$right['product_id'], $right['base_uom_id']];
        });

        return $result;
    }

    /** @return array<int, list<array{component_product_id:int,quantity:string}>> */
    private function edgesByCombo(): array
    {
        $edges = [];
        foreach ($this->repo()->listEdges() as $edge) {
            $comboId = PositiveId::normalize($edge['combo_product_id'] ?? null, 'combo_product_id', 'INVALID_REFERENCE');
            $componentId = PositiveId::normalize($edge['component_product_id'] ?? null, 'component_product_id', 'INVALID_REFERENCE');
            $quantity = $this->positiveQuantity($edge['quantity'] ?? null, 'quantity', 6);
            $edges[$comboId][] = [
                'component_product_id' => $componentId,
                'quantity' => $quantity,
            ];
        }

        return $edges;
    }

    /**
     * @param array<int, list<array{component_product_id:int,quantity:string}>> $edgesByCombo
     * @param array<int, array<string,mixed>> $products
     * @param array<int, string> $roles
     * @param array<int, bool> $activePath
     * @param array<string, array{product_id:int,base_uom_id:int,quantity:string}> $aggregate
     */
    private function visitCombo(
        int $comboId,
        string $pathQuantity,
        array $edgesByCombo,
        array &$products,
        array &$roles,
        array $activePath,
        array &$aggregate,
    ): void {
        if (($activePath[$comboId] ?? false) === true) {
            throw new DomainException(self::ERROR_CYCLE, ['product_id' => $comboId]);
        }
        $activePath[$comboId] = true;

        foreach ($edgesByCombo[$comboId] ?? [] as $edge) {
            $componentId = $edge['component_product_id'];
            $component = $products[$componentId] ??= $this->product($componentId);
            $role = $roles[$componentId] ??= $this->roles()->getRole($component);
            $edgeQuantity = $edge['quantity'];
            $componentQuantity = $this->multiplyExact($pathQuantity, $edgeQuantity);

            if ($role === ProductRoleService::ROLE_COMBO) {
                $this->assertOperationalCombo($component);
                $this->requireBaseUom($component, $componentId);
                if (isset($edgesByCombo[$componentId]) && ($activePath[$componentId] ?? false)) {
                    throw new DomainException(self::ERROR_CYCLE, ['product_id' => $componentId]);
                }
                $this->visitCombo($componentId, $componentQuantity, $edgesByCombo, $products, $roles, $activePath, $aggregate);
                continue;
            }

            if (! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_CHILD], true)) {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                    'product_id' => $componentId,
                    'role' => $role,
                ]);
            }
            if (isset($edgesByCombo[$componentId])) {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                    'product_id' => $componentId,
                    'reason' => 'leaf_has_composition',
                ]);
            }
            if (! $this->roles()->isSelectable($component)) {
                throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['product_id' => $componentId]);
            }
            $baseUomId = $this->requireBaseUom($component, $componentId);
            $key = $componentId . ':' . $baseUomId;
            if (! isset($aggregate[$key])) {
                $aggregate[$key] = [
                    'product_id' => $componentId,
                    'base_uom_id' => $baseUomId,
                    'quantity' => $componentQuantity,
                ];
            } else {
                $aggregate[$key]['quantity'] = $this->addExact($aggregate[$key]['quantity'], $componentQuantity);
            }
        }
    }

    /** @param array<string,mixed> $product */
    private function assertOperationalCombo(array $product): void
    {
        if ($this->roles()->getRole($product) !== ProductRoleService::ROLE_COMBO) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                'product_id' => $product['id'] ?? null,
            ]);
        }
        if (! $this->roles()->isSelectable($product)) {
            throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['product_id' => $product['id'] ?? null]);
        }
    }

    /** @param array<string,mixed> $product */
    private function requireBaseUom(array $product, int $productId): int
    {
        $baseUomId = PositiveId::normalize($product['base_uom_id'] ?? null, 'base_uom_id', ProductUomService::ERROR_INVALID_REFERENCE);
        $this->productUoms()->requireActiveBaseUom($baseUomId);

        return $baseUomId;
    }

    /** @return array<string,mixed> */
    private function product(int $productId): array
    {
        $product = $this->roles()->findById($productId);
        if ($product === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $productId]);
        }

        return $product;
    }

    private function positiveQuantity(mixed $value, string $field, ?int $maximumFractionScale = null): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_QUANTITY, ['field' => $field]);
        }
        if ($maximumFractionScale !== null) {
            $integerDigits = strlen(explode('.', $value, 2)[0]);
            if ($this->fractionScale($value) > $maximumFractionScale || $integerDigits > 12) {
                throw new DomainException(self::ERROR_INVALID_QUANTITY, ['field' => $field]);
            }
        }
        if ((new Decimal($value))->compare('0', $this->fractionScale($value)) <= 0) {
            throw new DomainException(self::ERROR_INVALID_QUANTITY, ['field' => $field]);
        }

        return $value;
    }

    private function multiplyExact(string $left, string $right): string
    {
        return (new Decimal($left))->multiply($right, $this->fractionScale($left) + $this->fractionScale($right))->value;
    }

    private function addExact(string $left, string $right): string
    {
        return (new Decimal($left))->add($right, max($this->fractionScale($left), $this->fractionScale($right)))->value;
    }

    private function fractionScale(string $value): int
    {
        $position = strpos($value, '.');

        return $position === false ? 0 : strlen($value) - $position - 1;
    }

    private function canonicalDecimal(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        return $integer . ($fraction === '' ? '' : '.' . $fraction);
    }

    private function repo(): ComboCompositionRepository
    {
        return $this->repository ?? new ComboCompositionRepository($this->db);
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(db: $this->db);
    }

    private function productUoms(): ProductUomService
    {
        return $this->productUoms ?? new ProductUomService(db: $this->db);
    }
}

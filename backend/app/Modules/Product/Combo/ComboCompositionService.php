<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized atomic mutation of normalized direct COMBO components. */
final class ComboCompositionService
{
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_AUTHORIZATION_DENIED';
    public const ERROR_INVALID_ITEM = 'VALIDATION_ERROR';
    public const ERROR_SELF_REFERENCE = 'COMBO_SELF_REFERENCE';
    public const ERROR_CYCLE = 'COMBO_CYCLE';
    public const ERROR_CONCURRENT_MODIFICATION = 'CONCURRENT_MODIFICATION';
    public const ERROR_CONNECTION_INVALID = 'COMBO_CONNECTION_INVALID';
    public const ERROR_MUTATION_FAILED = 'COMBO_MUTATION_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ComboCompositionRepository $repository = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $productUoms = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        private readonly ?DomainLockService $domainLock = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @return list<array<string, mixed>> */
    public function listItems(mixed $comboProductId, ?OperationContext $operation = null, mixed $clientHint = null): array
    {
        $operation ??= $this->operation();
        $comboProductId = PositiveId::normalize($comboProductId, 'combo_product_id', ProductRoleService::ERROR_NOT_FOUND);
        $owner = $this->owner($comboProductId, false);
        $this->authorize($operation, 'view', $owner, [], $clientHint);

        return $this->repo()->listByComboProductId($comboProductId);
    }

    /** @return array<string, mixed>|null */
    public function findItem(mixed $comboProductId, mixed $componentProductId): ?array
    {
        $comboProductId = PositiveId::normalize($comboProductId, 'combo_product_id', ProductRoleService::ERROR_NOT_FOUND);
        $componentProductId = PositiveId::normalize($componentProductId, 'component_product_id', ProductRoleService::ERROR_NOT_FOUND);
        foreach ($this->repo()->listByComboProductId($comboProductId) as $row) {
            if ((int) ($row['component_product_id'] ?? 0) === $componentProductId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Replace the complete direct component set atomically.
     *
     * @param list<array<string, mixed>> $submittedItems
     * @return list<array<string, mixed>>
     */
    public function replaceItems(
        mixed $comboProductId,
        array $submittedItems,
        ?OperationContext $operation = null,
        mixed $clientHint = null,
    ): array {
        $operation ??= $this->operation();
        $comboProductId = PositiveId::normalize($comboProductId, 'combo_product_id', ProductRoleService::ERROR_NOT_FOUND);
        $owner = $this->owner($comboProductId, true);
        $items = $this->normalizeItems($submittedItems);
        $decision = $this->authorize($operation, 'edit', $owner, ['combo_product_id' => $comboProductId], $clientHint);

        return $this->withComboGraphLock(function () use ($comboProductId, $items, $operation, $decision): array {
            return $this->withStructureLocks($comboProductId, $items, function () use ($comboProductId, $items, $operation, $decision): array {
                return $this->transaction(function () use ($comboProductId, $items, $operation, $decision): array {
                    $lockedOwner = $this->owner($comboProductId, true);
                    $existingRows = $this->repo()->listByComboProductId($comboProductId);
                    $this->validateSubmittedItems($lockedOwner, $items, $existingRows);
                    $this->validateResultingGraph($comboProductId, $items);
                    $this->persistReplacement($comboProductId, $items, $existingRows, $operation);
                    $this->audit()->recordIfRequired($decision);

                    return $this->repo()->listByComboProductId($comboProductId);
                }, 'combo_items_replace');
            });
        });
    }

    /**
     * Merge one direct component into a COMBO without replacing other items.
     *
     * This is the canonical single-item mutation boundary used by Product
     * Import. It intentionally reuses the same graph/structure locks,
     * validation, authorization, and transaction as complete replacement.
     *
     * @return list<array<string, mixed>>
     */
    public function mergeItem(
        mixed $comboProductId,
        mixed $componentProductId,
        mixed $quantity,
        ?OperationContext $operation = null,
        mixed $clientHint = null,
    ): array {
        $operation ??= $this->operation();
        $comboProductId = PositiveId::normalize($comboProductId, 'combo_product_id', ProductRoleService::ERROR_NOT_FOUND);
        $owner = $this->owner($comboProductId, true);
        $items = $this->normalizeItems([[
            'component_product_id' => $componentProductId,
            'quantity' => $quantity,
        ]]);
        $decision = $this->authorize($operation, 'edit', $owner, ['combo_product_id' => $comboProductId], $clientHint);

        return $this->withComboGraphLock(function () use ($comboProductId, $items, $operation, $decision): array {
            return $this->withStructureLocks($comboProductId, $items, function () use ($comboProductId, $items, $operation, $decision): array {
                return $this->transaction(function () use ($comboProductId, $items, $operation, $decision): array {
                    $lockedOwner = $this->owner($comboProductId, true);
                    $existingRows = $this->repo()->listByComboProductId($comboProductId);
                    $this->validateSubmittedItems($lockedOwner, $items, $existingRows);

                    $resultingItems = [];
                    $merged = false;
                    foreach ($existingRows as $row) {
                        if ((int) $row['component_product_id'] === $items[0]['component_product_id']) {
                            $resultingItems[] = $items[0];
                            $merged = true;
                            continue;
                        }
                        $resultingItems[] = [
                            'component_product_id' => (int) $row['component_product_id'],
                            'quantity' => (string) $row['quantity'],
                        ];
                    }
                    if (! $merged) {
                        $resultingItems[] = $items[0];
                    }

                    $this->validateResultingGraph($comboProductId, $resultingItems);
                    $this->persistMergedItem($comboProductId, $items[0], $existingRows, $operation);
                    $this->audit()->recordIfRequired($decision);

                    return $this->repo()->listByComboProductId($comboProductId);
                }, 'combo_item_merge');
            });
        });
    }

    /** @return list<array<string, mixed>> */
    public function upsertItem(
        mixed $comboProductId,
        mixed $componentProductId,
        mixed $quantity,
        ?OperationContext $operation = null,
        mixed $clientHint = null,
    ): array {
        return $this->mergeItem($comboProductId, $componentProductId, $quantity, $operation, $clientHint);
    }

    /** @param array<string,mixed> $owner
     *  @param list<array<string,mixed>> $existingRows
     *  @param list<array{component_product_id:int,quantity:string}> $items
     */
    private function validateSubmittedItems(array $owner, array $items, array $existingRows): void
    {
        $this->productUoms()->requireActiveBaseUom($owner['base_uom_id'] ?? null);
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[(int) $row['component_product_id']] = $row;
        }

        foreach ($items as $item) {
            $componentId = $item['component_product_id'];
            if ($componentId === (int) ($owner['id'] ?? 0)) {
                throw new DomainException(self::ERROR_SELF_REFERENCE, ['product_id' => $componentId]);
            }
            if (isset($existing[$componentId])) {
                $component = $this->existingProduct($componentId);
                $role = $this->roles()->getRole($component);
                if ($role === ProductRoleService::ROLE_PARENT) {
                    throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                        'product_id' => $componentId,
                        'role' => $role,
                    ]);
                }
                $this->assertAllowedRole($role, $componentId);
                $this->assertBaseUomReference($component, $componentId);
                continue;
            }

            $this->validateNewComponent($componentId);
        }
    }

    private function validateNewComponent(int $componentId): void
    {
        $component = $this->existingProduct($componentId);
        if (strtoupper((string) ($component['status'] ?? '')) !== 'ACTIVE') {
            throw new DomainException('INACTIVE_REFERENCE', ['product_id' => $componentId]);
        }
        if (! $this->roles()->isSelectable($component)) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['product_id' => $componentId]);
        }
        $role = $this->roles()->getRole($component);
        $this->assertAllowedRole($role, $componentId);
        $this->productUoms()->requireActiveBaseUom($component['base_uom_id'] ?? null);
    }

    private function assertAllowedRole(string $role, int $productId): void
    {
        if (! in_array($role, [
            ProductRoleService::ROLE_SIMPLE,
            ProductRoleService::ROLE_CHILD,
            ProductRoleService::ROLE_COMBO,
        ], true)) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                'product_id' => $productId,
                'role' => $role,
            ]);
        }
    }

    /** @param list<array{component_product_id:int,quantity:string}> $items */
    private function validateResultingGraph(int $ownerId, array $items): void
    {
        $edges = $this->repo()->listEdges();
        $adjacency = [];
        $ids = [$ownerId => true];
        foreach ($edges as $edge) {
            $edgeOwner = PositiveId::normalize($edge['combo_product_id'] ?? null, 'combo_product_id', 'INVALID_REFERENCE');
            $component = PositiveId::normalize($edge['component_product_id'] ?? null, 'component_product_id', 'INVALID_REFERENCE');
            if ($edgeOwner === $ownerId) {
                continue;
            }
            $adjacency[$edgeOwner][] = $component;
            $ids[$edgeOwner] = true;
            $ids[$component] = true;
        }
        foreach ($items as $item) {
            $component = $item['component_product_id'];
            $adjacency[$ownerId][] = $component;
            $ids[$component] = true;
        }

        $roles = [];
        foreach (array_keys($ids) as $id) {
            $product = $this->existingProduct((int) $id);
            $role = $this->roles()->getRole($product);
            $roles[(int) $id] = $role;
        }
        foreach ($adjacency as $edgeOwner => $components) {
            if (($roles[(int) $edgeOwner] ?? null) !== ProductRoleService::ROLE_COMBO) {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                    'product_id' => $edgeOwner,
                    'role' => $roles[(int) $edgeOwner] ?? null,
                ]);
            }
            foreach ($components as $component) {
                $role = $roles[$component] ?? null;
                $this->assertAllowedRole((string) $role, $component);
            }
        }

        $visited = [];
        $active = [];
        foreach (array_keys($adjacency) as $node) {
            $this->visitComboNode((int) $node, $adjacency, $roles, $visited, $active);
        }
    }

    /** @param array<int, list<int>> $adjacency
     *  @param array<int, string> $roles
     *  @param array<int, bool> $visited
     *  @param array<int, bool> $active
     */
    private function visitComboNode(int $node, array $adjacency, array $roles, array &$visited, array &$active): void
    {
        if (($active[$node] ?? false) === true) {
            throw new DomainException(self::ERROR_CYCLE, ['product_id' => $node]);
        }
        if (($visited[$node] ?? false) === true) {
            return;
        }
        $active[$node] = true;
        foreach ($adjacency[$node] ?? [] as $component) {
            if (($roles[$component] ?? null) === ProductRoleService::ROLE_COMBO) {
                $this->visitComboNode($component, $adjacency, $roles, $visited, $active);
            }
        }
        unset($active[$node]);
        $visited[$node] = true;
    }

    /** @param list<array{component_product_id:int,quantity:string}> $items
     *  @param list<array<string,mixed>> $existingRows
     */
    private function persistReplacement(int $ownerId, array $items, array $existingRows, OperationContext $operation): void
    {
        $existing = [];
        $submitted = $this->itemMap($items);
        foreach ($existingRows as $row) {
            $existing[(int) $row['component_product_id']] = $row;
            if (! isset($submitted[(int) $row['component_product_id']])) {
                $this->repo()->delete((int) $row['id']);
            }
        }
        foreach ($items as $item) {
            $componentId = $item['component_product_id'];
            $row = $existing[$componentId] ?? null;
            if ($row === null) {
                $this->repo()->insert([
                    'combo_product_id' => $ownerId,
                    'component_product_id' => $componentId,
                    'quantity' => $item['quantity'],
                ], $operation);
                continue;
            }
            if (! $this->sameQuantity((string) $row['quantity'], $item['quantity'])) {
                $this->repo()->update((int) $row['id'], ['quantity' => $item['quantity']], $operation);
            }
        }
    }

    /**
     * @param array{component_product_id:int,quantity:string} $item
     * @param list<array<string,mixed>> $existingRows
     */
    private function persistMergedItem(int $ownerId, array $item, array $existingRows, OperationContext $operation): void
    {
        foreach ($existingRows as $row) {
            if ((int) $row['component_product_id'] !== $item['component_product_id']) {
                continue;
            }
            if (! $this->sameQuantity((string) $row['quantity'], $item['quantity'])) {
                $this->repo()->update((int) $row['id'], ['quantity' => $item['quantity']], $operation);
            }

            return;
        }

        $this->repo()->insert([
            'combo_product_id' => $ownerId,
            'component_product_id' => $item['component_product_id'],
            'quantity' => $item['quantity'],
        ], $operation);
    }

    /** @param list<array{component_product_id:int,quantity:string}> $items
     *  @return array<int, bool>
     */
    private function itemMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $map[$item['component_product_id']] = true;
        }

        return $map;
    }

    /** @param array<mixed> $submittedItems
     *  @return list<array{component_product_id:int,quantity:string}>
     */
    private function normalizeItems(array $submittedItems): array
    {
        if (! array_is_list($submittedItems)) {
            throw new DomainException(self::ERROR_INVALID_ITEM, ['field' => 'items']);
        }
        $items = [];
        $seen = [];
        foreach ($submittedItems as $item) {
            if (! is_array($item)) {
                throw new DomainException(self::ERROR_INVALID_ITEM, ['field' => 'items']);
            }
            foreach ($item as $field => $_value) {
                if (! in_array($field, ['component_product_id', 'quantity'], true)) {
                    throw new DomainException(self::ERROR_INVALID_ITEM, ['field' => $field]);
                }
            }
            $componentId = PositiveId::normalize($item['component_product_id'] ?? null, 'component_product_id', 'INVALID_REFERENCE');
            if (isset($seen[$componentId])) {
                throw new DomainException('DUPLICATE_KEY', ['component_product_id' => $componentId]);
            }
            $seen[$componentId] = true;
            $items[] = [
                'component_product_id' => $componentId,
                'quantity' => $this->quantity($item['quantity'] ?? null),
            ];
        }

        return $items;
    }

    private function quantity(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_ITEM, ['field' => 'quantity']);
        }
        if ((new Decimal($value))->compare('0', 6) <= 0) {
            throw new DomainException(self::ERROR_INVALID_ITEM, ['field' => 'quantity']);
        }

        return $value;
    }

    private function sameQuantity(string $left, string $right): bool
    {
        return (new Decimal($left))->compare($right, 6) === 0;
    }

    /** @return array<string,mixed> */
    private function owner(int $id, bool $requireActive): array
    {
        $product = $this->existingProduct($id);
        $role = $this->roles()->getRole($product);
        if ($role !== ProductRoleService::ROLE_COMBO) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['product_id' => $id, 'role' => $role]);
        }
        if ($requireActive && ! $this->roles()->isSelectable($product)) {
            throw new DomainException('INACTIVE_REFERENCE', ['product_id' => $id]);
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function existingProduct(int $id): array
    {
        $product = $this->roles()->findById($id);
        if ($product === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $id]);
        }

        return $product;
    }

    /** @param array<string,mixed> $product */
    private function assertBaseUomReference(array $product, int $id): void
    {
        $base = $product['base_uom_id'] ?? null;
        if (! is_int($base) && ! (is_string($base) && preg_match('/^[1-9][0-9]*$/', $base) === 1)) {
            throw new DomainException(ProductUomService::ERROR_INVALID_REFERENCE, ['product_id' => $id]);
        }
    }

    /** @param array<string,mixed>|null $target
     *  @param array<string,mixed> $input
     */
    private function authorize(OperationContext $operation, string $action, ?array $target, array $input, mixed $clientHint): AuthorizationDecision
    {
        $decision = $this->auth()->authorize($operation, 'product', $action, new EvaluationOrganizationRequest(
            persistedTarget: $target,
            input: $input,
            clientEvaluationOrganizationId: $clientHint,
        ));
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $action]);
        }

        return $decision;
    }

    /** @param callable(): mixed $callback */
    private function withComboGraphLock(callable $callback): mixed
    {
        $lock = $this->locks();
        try {
            return $lock->synchronized('combo-graph', $callback);
        } catch (ConcurrencyException $exception) {
            throw new DomainException(self::ERROR_CONCURRENT_MODIFICATION, ['lock' => 'combo-graph'], $exception);
        }
    }

    /** @param list<array{component_product_id:int,quantity:string}> $items
     *  @param callable(): mixed $callback
     */
    private function withStructureLocks(int $ownerId, array $items, callable $callback): mixed
    {
        $roots = $this->requiredStructureRoots($ownerId, $items);
        $lock = $this->locks();
        $acquired = [];
        try {
            foreach ($roots as $rootId) {
                $lock->acquire('product-structure:' . $rootId);
                $acquired[] = $rootId;
            }

            return $callback();
        } catch (ConcurrencyException $exception) {
            throw new DomainException(self::ERROR_CONCURRENT_MODIFICATION, ['lock' => 'product-structure'], $exception);
        } finally {
            foreach (array_reverse($acquired) as $rootId) {
                $lock->release('product-structure:' . $rootId);
            }
        }
    }

    /** @param list<array{component_product_id:int,quantity:string}> $items
     *  @return list<int>
     */
    private function requiredStructureRoots(int $ownerId, array $items): array
    {
        $ids = [$ownerId => true];
        foreach ($items as $item) {
            $ids[$item['component_product_id']] = true;
        }
        foreach ($this->repo()->listEdges() as $edge) {
            $ids[(int) $edge['combo_product_id']] = true;
            $ids[(int) $edge['component_product_id']] = true;
        }

        $roots = [];
        foreach (array_keys($ids) as $id) {
            $product = $this->existingProduct((int) $id);
            $roots[$this->structureRoot($product)] = true;
        }
        $rootIds = array_map('intval', array_keys($roots));
        sort($rootIds, SORT_NUMERIC);

        return $rootIds;
    }

    /** @param array<string,mixed> $product */
    private function structureRoot(array $product): int
    {
        $id = PositiveId::normalize($product['id'] ?? null, 'product_id', ProductRoleService::ERROR_NOT_FOUND);
        if ($this->roles()->getRole($product) === ProductRoleService::ROLE_CHILD) {
            return PositiveId::normalize($product['parent_product_id'] ?? null, 'parent_product_id', ProductRoleService::ERROR_INVALID_ROLE);
        }

        return $id;
    }

    /** @param callable(): mixed $callback */
    private function transaction(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        try {
            $result = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation]);
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation], $exception);
        }
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

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): AuthorizationAuditService
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function locks(): DomainLockService
    {
        return $this->domainLock ?? new MariaDbDomainLockService($this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}

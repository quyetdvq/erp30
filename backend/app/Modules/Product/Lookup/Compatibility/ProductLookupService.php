<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup\Compatibility;

use App\Services\ResourceWorkflowStateResolver;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Product\Lookup\ProductLookupRepository;
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;

/**
 * Read-only, owner-independent MASTER Product lookups for selector/preview UI.
 * All list memberships and counts are filtered by the real PBAC decision
 * BEFORE pagination. Never grant Product view through Price List permissions.
 */
final class ProductLookupService
{
    private ?AuthorizationService $runtimeAuthorization = null;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        private readonly ?ConnectionInterface $db = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?ProductRepository $products = null,
        private readonly ?ProductLookupRepository $lookups = null,
    ) {
    }

    /** @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function options(array $query): array
    {
        $purpose = $query['purpose'] ?? 'price_list_item';
        if (! is_string($purpose) || ! in_array($purpose, ['price_list_item', 'combo_component', 'parent_product'], true)) {
            throw new BadRequestException('Invalid lookup purpose.');
        }
        $q = $query['q'] ?? '';
        if (! is_string($q) || mb_strlen(trim($q)) > 100) {
            throw new BadRequestException('Invalid Product search text.');
        }
        $page = $this->boundedInteger($query['page'] ?? 1, 1, 100000, 'page');
        $pageSize = $this->boundedInteger($query['page_size'] ?? 20, 1, 50, 'page_size');

        return $this->read(function (OperationContext $operation) use ($purpose, $q, $page, $pageSize): array {
            $this->assertCatalogStructure();
            // ACTIVE is the sole State of eligible selection results. A
            // synthetic target here cannot authorize any other State.
            $this->requireView($operation, 'product', ['status' => 'ACTIVE']);
            $result = $this->lookup()->options($purpose, trim($q), $page, $pageSize);
            $described = $this->roles()->describePage($result['rows']);
            $items = [];
            foreach ($described as $row) {
                $this->requireView($operation, 'product', $row);
                $items[] = $this->option($row);
            }
            return ['items' => $items, 'page' => $page, 'page_size' => $pageSize, 'total' => $result['total']];
        });
    }

    /**
     * Known-but-invisible ID is silently omitted: this batch endpoint must
     * never distinguish a missing Product from an unauthorized Product.
     * Inactive/previously selected Product labels remain resolvable when VIEW
     * is permitted, but never become newly selectable.
     *
     * @param mixed $input
     * @return list<array<string,mixed>>
     */
    public function resolve(mixed $input): array
    {
        $ids = $this->ids($input, 100);
        if ($ids === []) {
            return [];
        }
        return $this->read(function (OperationContext $operation) use ($ids): array {
            $this->assertCatalogStructure();
            $byId = [];
            foreach ($this->roles()->describePage($this->repo()->findByIds($ids)) as $row) {
                if ($this->mayView($operation, 'product', $row)) {
                    $byId[(int) $row['id']] = $this->option($row);
                }
            }
            $result = [];
            foreach ($ids as $id) {
                if (isset($byId[$id])) {
                    $result[] = $byId[$id];
                }
            }
            return $result;
        });
    }

    /**
     * Full Product representations for an explicitly expanded Combo. Limited
     * and independently checked by product.view; invisible IDs are omitted.
     *
     * @param mixed $input
     * @return list<array<string,mixed>>
     */
    public function resolveDetails(mixed $input): array
    {
        $ids = $this->ids($input, 100);
        if ($ids === []) {
            return [];
        }
        return $this->read(function (OperationContext $operation) use ($ids): array {
            $this->assertCatalogStructure();
            $byId = [];
            foreach ($this->roles()->describePage($this->repo()->findByIds($ids)) as $row) {
                if ($this->mayView($operation, 'product', $row)) {
                    $byId[(int) $row['id']] = $this->productForApi($row);
                }
            }
            $result = [];
            foreach ($ids as $id) {
                if (isset($byId[$id])) {
                    $result[] = $byId[$id];
                }
            }
            return $result;
        });
    }

    /**
     * @param mixed $input
     * @return list<array{product_id:string,total:int,products:list<array<string,mixed>>}>
     */
    public function relationPreviews(mixed $input, mixed $limitInput = 2): array
    {
        $ids = $this->ids($input, 50);
        $limit = $this->boundedInteger($limitInput, 1, 5, 'limit_per_product');
        if ($ids === []) {
            return [];
        }
        return $this->read(function (OperationContext $operation) use ($ids, $limit): array {
            $this->assertCatalogStructure();
            $owners = [];
            foreach ($this->roles()->describePage($this->repo()->findByIds($ids)) as $row) {
                if (in_array($row['role'], ['PARENT', 'COMBO'], true) && $this->mayView($operation, 'product', $row)) {
                    $owners[(int) $row['id']] = $row;
                }
            }
            if ($owners === []) {
                return [];
            }
            // Count only Product rows whose Lifecycle State is currently
            // viewable by this operation. Hard-coded ACTIVE/INACTIVE would
            // silently omit DRAFT/CLOSED children after Lifecycle Unified v2.
            // The SQL applies this predicate BEFORE windowed totals/LIMIT;
            // the per-row authorization check below remains mandatory.
            $statuses = [];
            $stateCodes = (new ResourceWorkflowStateResolver($this->connection()))
                ->allowedPolicyStateCodes('product');
            foreach (array_unique($stateCodes) as $status) {
                if ($this->mayView($operation, 'product', ['status' => $status])) {
                    $statuses[] = strtoupper(bin2hex($status));
                }
            }
            $previews = $this->lookup()->relationPreview(array_keys($owners), $statuses, $limit);
            $childrenById = [];
            $previewIds = array_map(static fn (array $row): int => (int) $row['product_id'], $previews);
            foreach ($this->roles()->describePage($this->repo()->findByIds($previewIds)) as $row) {
                // If PBAC disagrees with the State predicate (e.g. a policy
                // race), reject the whole response rather than leak counts.
                $this->requireView($operation, 'product', $row);
                $childrenById[(int) $row['id']] = $this->productForApi($row);
            }
            $byOwner = [];
            foreach ($previews as $preview) {
                $child = $childrenById[(int) $preview['product_id']] ?? null;
                if ($child === null) {
                    throw new DomainException('PRODUCT_AUTHORIZATION_DENIED');
                }
                $ownerId = (int) $preview['owner_id'];
                $byOwner[$ownerId]['total'] = (int) $preview['total'];
                $byOwner[$ownerId]['products'][] = $child;
            }
            $result = [];
            foreach ($ids as $id) {
                if (isset($owners[$id])) {
                    $result[] = [
                        'product_id' => (string) $id,
                        'total' => $byOwner[$id]['total'] ?? 0,
                        'products' => $byOwner[$id]['products'] ?? [],
                    ];
                }
            }
            return $result;
        });
    }

    /**
     * Resolve labels for existing Price List UOM values, including INACTIVE
     * records, without making those UOMs eligible for a new selection.
     *
     * @param mixed $input
     * @return list<array{id:string,code:string,status:string}>
     */
    public function resolveUomLabels(mixed $input): array
    {
        $ids = $this->ids($input, 100);
        if ($ids === []) {
            return [];
        }
        return $this->read(function (OperationContext $operation) use ($ids): array {
            $visible = [];
            foreach ($this->lookup()->uoms($ids) as $row) {
                if ($this->mayView($operation, 'product_uom', $row)) {
                    $visible[(int) $row['id']] = [
                        'id' => (string) $row['id'], 'code' => (string) $row['code'],
                        'status' => (string) $row['status'],
                    ];
                }
            }
            $result = [];
            foreach ($ids as $id) {
                if (isset($visible[$id])) {
                    $result[] = $visible[$id];
                }
            }
            return $result;
        });
    }

    /**
     * Return only eligible SALES UOMs of visible Products, with UOM.view
     * independently required. COMBO offers base UOM only. This is a read
     * projection, not an alternative to server-side Price List validation.
     *
     * @param mixed $input
     * @return list<array{product_id:string,options:list<array{id:string,code:string}>}>
     */
    public function salesUomOptions(mixed $input): array
    {
        $ids = $this->ids($input, 50);
        if ($ids === []) {
            return [];
        }
        return $this->read(function (OperationContext $operation) use ($ids): array {
            $this->assertCatalogStructure();
            $visible = [];
            foreach ($this->roles()->describePage($this->repo()->findByIds($ids)) as $row) {
                if ($this->mayView($operation, 'product', $row)) {
                    $visible[(int) $row['id']] = $row;
                }
            }
            if ($visible === []) {
                return [];
            }
            $conversions = $this->lookup()->conversions(array_keys($visible));
            $uomIds = [];
            foreach ($visible as $row) {
                if (isset($row['base_uom_id'])) {
                    $uomIds[] = (int) $row['base_uom_id'];
                }
            }
            foreach ($conversions as $conversion) {
                $product = $visible[(int) $conversion['product_id']] ?? null;
                if ($product !== null && $product['role'] !== 'COMBO'
                    && $conversion['status'] === 'ACTIVE'
                    && in_array($conversion['usage_type'], ['SALES', 'BOTH'], true)) {
                    $uomIds[] = (int) $conversion['uom_id'];
                }
            }
            $uoms = [];
            foreach ($this->lookup()->uoms(array_values(array_unique($uomIds))) as $row) {
                if ($row['status'] === 'ACTIVE' && $this->mayView($operation, 'product_uom', $row)) {
                    $uoms[(int) $row['id']] = ['id' => (string) $row['id'], 'code' => (string) $row['code']];
                }
            }
            $result = [];
            foreach ($ids as $id) {
                $product = $visible[$id] ?? null;
                if ($product === null) {
                    continue;
                }
                $orderedIds = [(int) ($product['base_uom_id'] ?? 0)];
                if ($product['role'] !== 'COMBO') {
                    foreach ($conversions as $conversion) {
                        if ((int) $conversion['product_id'] === $id && $conversion['status'] === 'ACTIVE'
                            && in_array($conversion['usage_type'], ['SALES', 'BOTH'], true)) {
                            $orderedIds[] = (int) $conversion['uom_id'];
                        }
                    }
                }
                $options = [];
                foreach (array_unique($orderedIds) as $uomId) {
                    if (isset($uoms[$uomId])) {
                        $options[] = $uoms[$uomId];
                    }
                }
                $result[] = ['product_id' => (string) $id, 'options' => $options];
            }
            return $result;
        });
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function option(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'product_code' => isset($row['code']) ? (string) $row['code'] : null,
            'full_name' => (string) $row['full_name'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'base_uom_id' => isset($row['base_uom_id']) ? (string) $row['base_uom_id'] : null,
            'selectable' => (bool) $row['selectable'],
        ];
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function productForApi(array $row): array
    {
        if (array_key_exists('image_filename', $row)) {
            $row['image_available'] = is_string($row['image_filename']) && $row['image_filename'] !== '';
            $row['image_url'] = \App\Infrastructure\Media\YearlyImageStorage::publicUrl($row['image_filename'], 'product');
        }
        unset($row['image_filename'], $row['image_mime'], $row['image_size_bytes']);
        if (array_key_exists('code', $row)) {
            $row['product_code'] = $row['code'];
            unset($row['code']);
        }
        return $row;
    }

    private function assertCatalogStructure(): void
    {
        $invalid = $this->repo()->firstInvalidListStructure();
        if ($invalid !== null) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                'product_id' => $invalid['id'], 'reason' => $invalid['reason'],
            ]);
        }
    }

    /** @param array<string,mixed> $target */
    private function mayView(OperationContext $operation, string $resource, array $target): bool
    {
        return $this->auth()->authorize($operation, $resource, 'view',
            new EvaluationOrganizationRequest(persistedTarget: $target))->isAllowed();
    }

    /** @param array<string,mixed> $target */
    private function requireView(OperationContext $operation, string $resource, array $target): void
    {
        if (! $this->mayView($operation, $resource, $target)) {
            throw new DomainException(strtoupper($resource) . '_AUTHORIZATION_DENIED');
        }
    }

    /**
     * @template T
     * @param callable(OperationContext):T $callback
     * @return T
     */
    private function read(callable $callback): mixed
    {
        $operation = $this->operation();
        return $this->repo()->withConsistentListSnapshot(fn (): mixed =>
            $this->auth()->withReadOnlyBatch($operation, fn (): mixed => $callback($operation)));
    }

    /** @return list<int> */
    private function ids(mixed $input, int $max): array
    {
        if (! is_array($input) || ! array_is_list($input) || count($input) > $max) {
            throw new BadRequestException('Invalid Product ID list.');
        }
        $ids = [];
        foreach ($input as $raw) {
            if ((! is_string($raw) && ! is_int($raw)) || ! preg_match('/\A[1-9][0-9]*\z/', (string) $raw)
                || strlen((string) $raw) > strlen((string) PHP_INT_MAX)
                || (strlen((string) $raw) === strlen((string) PHP_INT_MAX) && strcmp((string) $raw, (string) PHP_INT_MAX) > 0)) {
                throw new BadRequestException('Invalid Product ID.');
            }
            $ids[(int) $raw] = (int) $raw;
        }
        return array_values($ids);
    }

    private function boundedInteger(mixed $value, int $min, int $max, string $field): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[0-9]+\z/', (string) $value)
            || strlen((string) $value) > 10 || (int) $value < $min || (int) $value > $max) {
            throw new BadRequestException('Invalid ' . $field . '.');
        }
        return (int) $value;
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }

    /** @return ConnectionInterface<mixed,mixed> */
    private function connection(): ConnectionInterface
    {
        return $this->db ?? db_connect();
    }

    private function repo(): ProductRepository
    {
        return $this->products ?? new ProductRepository($this->connection());
    }

    private function roles(): ProductRoleService
    {
        return new ProductRoleService(repository: $this->repo(), db: $this->connection());
    }

    private function lookup(): ProductLookupRepository
    {
        return $this->lookups ?? new ProductLookupRepository($this->connection());
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? ($this->runtimeAuthorization ??= new AuthorizationService(db: $this->connection()));
    }
}

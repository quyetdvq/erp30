<?php

declare(strict_types=1);

namespace App\Modules\Product\Product\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Modules\Product\Product\Application\Ports\ProductListAuthorizationPort;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Product-owned orchestration for the existing GET /api/v1/products contract.
 *
 * Query parsing, authorized COUNT/page, snapshot, hydration and fallback
 * remain identical to API-050's original listProducts(). This service never
 * writes Product, Pricing or technical tables and never opens a write txn.
 */
final class ProductListQueryService
{
    /**
     * @param ConnectionInterface<mixed,mixed> $db
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ProductListAuthorizationPort $authorization,
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?ProductRepository $productRepository = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductMasterService $products = null,
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function list(array $query = []): array
    {
        $query = self::canonicalSortQuery($query);
        $allowlist = new CommonListQueryAllowlist(
            search: ['product_code', 'sku', 'barcode', 'product_name', 'product_child_name'],
            filters: ['base_uom_id', 'parent_product_id', 'product_group_id', 'product_type', 'status'],
            sort: ['id', 'product_id', 'product_code', 'sku', 'product_name', 'full_name', 'price', 'product_type', 'status'],
        );
        $adapter = $this->queryAdapter ?? new CommonListQueryAdapter();
        $repository = $this->productRepository ?? new ProductRepository($this->db);
        if ($this->authorization->requiresRowStateAuthorization()) {
            // MASTER resources have GROUP scope; only persisted State affects
            // per-row PBAC. Compile the effective decision through the
            // established Authorization engine for each byte-exact bucket.
            // Include State samples, COUNT, page and relationship hydration
            // in one read snapshot, never after pagination.
            $parsed = $adapter->parse($query, $allowlist);
            if ($repository->supportsSqlProjection($parsed)) {
                $operation = $this->authorization->operation();
                $optimized = $repository->withConsistentListSnapshot(function () use ($repository, $operation, $adapter, $query, $allowlist): ?array {
                    $authorized = $this->authorization->withReadOnlyBatch($operation, function () use ($repository, $operation, $adapter, $query, $allowlist): ?array {
                        $statuses = $this->authorization->authorizedStatuses($operation);
                        if ($statuses === null) {
                            return null;
                        }
                        $roles = new ProductRoleService(repository: $repository, db: $this->db);
                        $result = $adapter->paginate($query, $allowlist,
                            new SqlProductListSource($repository, $roles, $statuses));
                        $items = [];
                        foreach ($result->data as $row) {
                            // Preserve fail-closed recheck, including its
                            // established exception code (no HTTP remapping).
                            if (! $this->authorization->recheckRowVisible($operation, $row)) {
                                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED');
                            }
                            $items[] = AuthorizedProductReadService::projectForApi($row);
                        }
                        return ['items' => $items, 'page' => $result->query->page,
                            'page_size' => $result->query->pageSize, 'total' => $result->total];
                    });
                    // Mutation actions are read-only previews, but the PBAC
                    // engine's VIEW-only batch deliberately excludes them.
                    // Compute them after it closes, inside the same SQL snapshot.
                    return $authorized === null ? null : $this->withAllowedActions($authorized, $operation);
                });
                if ($optimized !== null) {
                    return $optimized;
                }
            }
            // Unsafe buckets, noncanonical numeric filters and custom
            // resource metadata continue through the original per-row path.
            $rows = array_values(array_filter(
                $this->roles()->describeList($this->products()->listAll()),
                fn (array $row): bool => $this->authorization->rowVisible($row),
            ));
            return $this->withAllowedActions($this->paginate(array_map(
                static fn (array $row): array => AuthorizedProductReadService::projectForApi($row),
                $rows,
            ), $query, $allowlist));
        }
        $this->authorization->authorizeBaseView();
        // Only page after product.view. Use existing SQL/projection source
        // when equivalent; preserve PHP comparison for Unicode and sorting.
        if ($repository->supportsSqlProjection($adapter->parse($query, $allowlist))) {
            return $repository->withConsistentListSnapshot(function () use ($adapter, $query, $allowlist, $repository): array {
                $roles = new ProductRoleService(repository: $repository, db: $this->db);
                $result = $adapter->paginate($query, $allowlist, new SqlProductListSource($repository, $roles));
                return $this->withAllowedActions([
                    'items' => array_map(
                        static fn (array $row): array => AuthorizedProductReadService::projectForApi($row),
                        array_values(iterator_to_array($result->data)),
                    ),
                    'page' => $result->query->page,
                    'page_size' => $result->query->pageSize,
                    'total' => $result->total,
                ]);
            });
        }

        // Exact legacy fallback for noncanonical filters ("01" != "1").
        $rows = array_map(
            static fn (array $row): array => AuthorizedProductReadService::projectForApi($row),
            $this->roles()->describeList($this->products()->listAll()),
        );
        return $this->withAllowedActions($this->paginate($rows, $query, $allowlist));
    }

    /**
     * Only rows returned by the already authorized page receive action hints.
     * COUNT/filter and page boundaries are never changed by UI action denials.
     * Empty pages require no extra PBAC reads or operation capture.
     *
     * @param array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string} $response
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    private function withAllowedActions(array $response, ?\App\Infrastructure\Mutation\OperationContext $operation = null): array
    {
        if ($response['items'] !== []) {
            $operation ??= $this->authorization->operation();
            $response['items'] = $this->authorization->withAllowedActions($operation, $response['items']);
        }
        return $response;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    private function paginate(array $rows, array $query, CommonListQueryAllowlist $allowlist): array
    {
        $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query, $allowlist, new AuthorizedArrayListSource($rows, ProductListSort::compareField(...)),
        );
        return [
            'items' => array_values(iterator_to_array($result->data)),
            'page' => $result->query->page,
            'page_size' => $result->query->pageSize,
            'total' => $result->total,
        ];
    }

    /**
     * Public `product_id` is the Product table's `id`. Normalize only complete
     * sort tokens before canonical parsing; duplicate aliases remain invalid.
     * With no sort supplied the shared adapter already defaults to id ASC.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function canonicalSortQuery(array $query): array
    {
        
        if (! isset($query['sort']) || ! is_string($query['sort'])) {
            $query['sort'] = '-id';
            return $query;
        }
        $query['sort'] = implode(',', array_map(
            static fn (string $term): string => match ($term) {
                'product_id' => 'id',
                '-product_id' => '-id',
                default => $term,
            },
            explode(',', $query['sort']),
        ));
        return $query;
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(db: $this->db);
    }

    private function products(): ProductMasterService
    {
        return $this->products ?? new ProductMasterService(db: $this->db);
    }
}

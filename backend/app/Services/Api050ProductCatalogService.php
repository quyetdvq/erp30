<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Pricing\PriceList\PriceListService;
use App\Modules\Product\Combo\ComboCompositionService;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\ProductGroup\ProductGroupService;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the 26 CONTRACT_READY P1-API-050 operations. */
final class Api050ProductCatalogService
{
    public function __construct(
        /** @var ConnectionInterface<mixed, mixed>|null */
        private readonly ?ConnectionInterface $db = null,
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?ProductGroupService $groups = null,
        private readonly ?ProductUomMasterService $uoms = null,
        private readonly ?ProductMasterService $products = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $conversions = null,
        private readonly ?PriceListService $priceLists = null,
        private readonly ?ComboCompositionService $combos = null,
    ) {
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listGroups(array $query = []): array
    {
        $this->authorize('product_group', 'view');
        return $this->paginate(
            $this->groups()->listAll(),
            $query,
            new CommonListQueryAllowlist(
                search: ['code', 'name'],
                filters: ['code', 'name', 'parent_id', 'status'],
                sort: ['id', 'code', 'name', 'parent_id', 'status'],
            ),
        );
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createGroup(array $payload): array
    {
        $id = $this->groups()->create($payload, $this->operation());
        return $this->requireRow($this->groups()->findById($id), ProductGroupService::ERROR_NOT_FOUND);
    }

    /** @return array<string,mixed> */
    public function getGroup(int $id): array
    {
        $row = $this->requireRow($this->groups()->findById($id), ProductGroupService::ERROR_NOT_FOUND);
        $this->authorize('product_group', 'view', $row);
        return $row;
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateGroup(int $id, array $payload): array
    {
        $this->groups()->update($id, $payload, $this->operation());
        return $this->getGroup($id);
    }

    /** @return array<string,mixed> */
    public function setGroupState(int $id, string $status): array
    {
        $this->groups()->setState($id, $status, $this->operation());
        return $this->getGroup($id);
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listUoms(array $query = []): array
    {
        $this->authorize('product_uom', 'view');
        return $this->paginate(
            $this->uoms()->listAll(),
            $query,
            new CommonListQueryAllowlist(
                search: ['code', 'name'],
                filters: ['code', 'name', 'status'],
                sort: ['id', 'code', 'name', 'status'],
            ),
        );
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createUom(array $payload): array
    {
        $id = $this->uoms()->create($payload, $this->operation());
        return $this->requireRow($this->uoms()->findById($id), ProductUomMasterService::ERROR_NOT_FOUND);
    }

    /** @return array<string,mixed> */
    public function getUom(int $id): array
    {
        $row = $this->requireRow($this->uoms()->findById($id), ProductUomMasterService::ERROR_NOT_FOUND);
        $this->authorize('product_uom', 'view', $row);
        return $row;
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateUom(int $id, array $payload): array
    {
        $this->uoms()->update($id, $payload, $this->operation());
        return $this->getUom($id);
    }

    /** @return array<string,mixed> */
    public function setUomState(int $id, string $status): array
    {
        $this->uoms()->setState($id, $status, $this->operation());
        return $this->getUom($id);
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listProducts(array $query = []): array
    {
        $this->authorize('product', 'view');
        // Derive relationship-dependent fields in one in-memory pass. Per-row
        // role resolution would otherwise issue SQL for each Product.
        $rows = array_map(
            fn (array $row): array => $this->productCodeForApi($row),
            $this->roles()->describeList($this->products()->listAll()),
        );
        return $this->paginate(
            $rows,
            $query,
            new CommonListQueryAllowlist(
                search: ['product_code', 'sku', 'barcode', 'product_name', 'product_child_name'],
                filters: ['base_uom_id', 'parent_product_id', 'product_group_id', 'product_type', 'status'],
                sort: ['id', 'product_code', 'product_name', 'product_type', 'status'],
            ),
        );
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createProduct(array $payload): array
    {
        $id = $this->products()->create($payload, $this->operation());
        return $this->getProduct($id);
    }

    /** @return array<string,mixed> */
    public function getProduct(int $id): array
    {
        $row = $this->requireRow($this->products()->findById($id), ProductMasterService::ERROR_NOT_FOUND);
        $this->authorize('product', 'view', $row);
        return $this->decorateProduct($row);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        $this->products()->update($id, $payload, $this->operation());
        return $this->getProduct($id);
    }

    /** @return array<string,mixed> */
    public function setProductState(int $id, string $status): array
    {
        $this->products()->setState($id, $status, $this->operation());
        return $this->getProduct($id);
    }

    /** @return list<array<string,mixed>> */
    public function getConversions(int $productId): array
    {
        $product = $this->requireRow($this->products()->findById($productId), ProductMasterService::ERROR_NOT_FOUND);
        $this->authorize('product', 'view', $product);
        return $this->conversions()->listByProduct($productId);
    }

    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public function replaceConversions(int $productId, array $items, mixed $clientHint = null): array
    {
        return $this->conversions()->replaceByProduct($productId, $items, $this->operation(), $clientHint);
    }

    /** @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string} */
    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listPriceLists(array $query = []): array
    {
        $visible = [];
        foreach ($this->priceLists()->listAll() as $row) {
            $decision = $this->decision('price_list', 'view', $row);
            if ($decision->isAllowed()) {
                $visible[] = $row;
            }
        }
        return $this->paginate(
            $visible,
            $query,
            new CommonListQueryAllowlist(
                search: ['code', 'name'],
                filters: ['code', 'name', 'organization_id', 'status'],
                sort: ['id', 'code', 'effective_from', 'name', 'organization_id', 'priority', 'status'],
            ),
        );
    }

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createPriceList(array $payload): array
    {
        $id = $this->priceLists()->create($payload, $this->operation());
        return $this->getPriceList($id);
    }

    /** @return array<string,mixed> */
    public function getPriceList(int $id): array
    {
        $row = $this->requireRow($this->priceLists()->findById($id), PriceListService::ERROR_NOT_FOUND);
        $this->authorize('price_list', 'view', $row);
        return $row;
    }

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updatePriceList(int $id, array $payload): array
    {
        $this->priceLists()->update($id, $payload, $this->operation());
        return $this->getPriceList($id);
    }

    /** @return array<string,mixed> */
    public function setPriceListState(int $id, string $status): array
    {
        $this->priceLists()->setState($id, $status, $this->operation());
        return $this->getPriceList($id);
    }

    /** @return list<array<string,mixed>> */
    public function getPriceListItems(int $id): array
    {
        return $this->priceLists()->listItems($id, $this->operation());
    }

    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public function replacePriceListItems(int $id, array $items): array
    {
        return $this->priceLists()->replaceItems($id, $items, $this->operation());
    }

    /** @return list<array<string,mixed>> */
    public function getComboItems(int $id): array
    {
        return $this->combos()->listItems($id, $this->operation());
    }

    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public function replaceComboItems(int $id, array $items): array
    {
        return $this->combos()->replaceItems($id, $items, $this->operation());
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function decorateProduct(array $row): array
    {
        $role = $this->roles()->getRole($row);
        $row['role'] = $role;
        $row['full_name'] = $this->roles()->getFullName($row);
        $row['selectable'] = $this->roles()->isSelectable($row);
        $row['leaf_transactionable'] = $this->roles()->isLeafTransactionable($row);
        return $this->productCodeForApi($row);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function productCodeForApi(array $row): array
    {
        if (array_key_exists('code', $row)) {
            $row['product_code'] = $row['code'];
            unset($row['code']);
        }

        return $row;
    }

    /** @param array<string,mixed>|null $row
     *  @return array<string,mixed>
     */
    private function requireRow(?array $row, string $error): array
    {
        if ($row === null) {
            throw new DomainException($error);
        }

        return $row;
    }

    /** @param array<string,mixed>|null $target
     *  @param array<string,mixed> $input
     */
    private function authorize(string $resource, string $action, ?array $target = null, array $input = [], mixed $clientHint = null): void
    {
        $decision = $this->decision($resource, $action, $target, $input, $clientHint);
        if (! $decision->isAllowed()) {
            throw new DomainException(strtoupper($resource) . '_AUTHORIZATION_DENIED', ['action' => $action]);
        }
    }

    /** @param array<string,mixed>|null $target
     *  @param array<string,mixed> $input
     */
    private function decision(string $resource, string $action, ?array $target = null, array $input = [], mixed $clientHint = null): \App\Authorization\AuthorizationDecision
    {
        return $this->authorization()->authorize(
            $this->operation(),
            $resource,
            $action,
            new EvaluationOrganizationRequest(
                persistedTarget: $target,
                input: $input,
                clientEvaluationOrganizationId: $clientHint,
            ),
        );
    }

    /** @param list<array<string,mixed>> $rows
     *  @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    private function paginate(array $rows, array $query, CommonListQueryAllowlist $allowlist): array
    {
        $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $allowlist,
            new AuthorizedArrayListSource($rows),
        );

        return [
            'items' => array_values(iterator_to_array($result->data)),
            'page' => $result->query->page,
            'page_size' => $result->query->pageSize,
            'total' => $result->total,
        ];
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

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->connection());
    }

    private function groups(): ProductGroupService
    {
        return $this->groups ?? new ProductGroupService(db: $this->connection());
    }

    private function uoms(): ProductUomMasterService
    {
        return $this->uoms ?? new ProductUomMasterService(db: $this->connection());
    }

    private function products(): ProductMasterService
    {
        return $this->products ?? new ProductMasterService(db: $this->connection());
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(db: $this->connection());
    }

    private function conversions(): ProductUomService
    {
        return $this->conversions ?? new ProductUomService(productUoms: $this->uoms(), db: $this->connection());
    }

    private function priceLists(): PriceListService
    {
        return $this->priceLists ?? new PriceListService(db: $this->connection());
    }

    private function combos(): ComboCompositionService
    {
        return $this->combos ?? new ComboCompositionService(db: $this->connection());
    }
}

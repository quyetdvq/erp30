<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Pricing\PriceList\PriceListService;
use App\Modules\Pricing\PriceList\Application\PriceListQueryService;
use App\Bootstrap\PriceListListAuthorizationAdapter;
use App\Modules\Product\Combo\ComboCompositionService;
use App\Modules\Product\Product\Application\AuthorizedProductReadService;
use App\Modules\Product\Product\Application\ProductListQueryService;
use App\Bootstrap\ProductListAuthorizationAdapter;
use App\Bootstrap\ProductUomListAuthorizationAdapter;
use App\Bootstrap\ProductGroupListAuthorizationAdapter;
use App\Bootstrap\ProductMasterListSqlAuthorizationAdapter;
use App\Modules\Product\Lookup\Application\ProductMasterListSqlFastPath;
use App\Modules\Product\ProductGroup\Application\ProductGroupListQueryService;
use App\Modules\Product\Uom\Application\ProductUomListQueryService;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRepository;
use App\Repositories\PolicyRepository;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\ProductGroup\ProductGroupService;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the 26 CONTRACT_READY P1-API-050 operations. */
final class Api050ProductCatalogService
{
    private ?AuthorizationService $runtimeAuthorization = null;
    private ?ResourceWorkflowStateResolver $runtimeWorkflowStates = null;

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
        private readonly ?ProductRepository $productRepository = null,
    ) {
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listGroups(array $query = []): array
    {
        // A trusted Product master SQL plan is optional: injected Group
        // collaborators keep their existing full-PBAC characterization path.
        $requiresState = fn (): bool => $this->requiresRowStateAuthorization('product_group');
        $authorizeBase = function (): void { $this->authorize('product_group', 'view'); };
        return (new ProductGroupListQueryService(
            $this->groups(),
            new ProductGroupListAuthorizationAdapter(
                $requiresState, $authorizeBase,
                fn (array $row): bool => $this->decision('product_group', 'view', $row)->isAllowed(),
            ),
            $this->queryAdapter,
            $this->groups === null ? $this->masterListSqlFastPath('product_group', $requiresState, $authorizeBase) : null,
        ))->list($query);
    }

    /**
     * Factory-only seam for two existing Product MASTER read operations.
     * No public API or state-policy change; runtime SQL eligibility is checked
     * after composition, and the fallback uses the original facade callbacks.
     * @param \Closure():bool $requiresState
     * @param \Closure():void $authorizeBase
     */
    private function masterListSqlFastPath(string $resource, \Closure $requiresState, \Closure $authorizeBase): ?ProductMasterListSqlFastPath
    {
        // Runtime rollout is explicitly opt-in: source-only tests do not
        // certify MariaDB/Shield parity or representative PBAC latency.
        // Without this exact value, both existing lists retain 014F/014G.
        if ($this->authorization !== null || getenv('ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN') !== '1') {
            return null;
        }
        $db = $this->connection();
        if (! $db instanceof BaseConnection) {
            return null;
        }
        return new ProductMasterListSqlFastPath(
            $db,
            $resource,
            new ProductMasterListSqlAuthorizationAdapter(
                $db, $resource,
                fn (): OperationContext => $this->operation(),
                fn (): AuthorizationService => $this->authorization(),
            ),
            $requiresState,
            $authorizeBase,
            $this->queryAdapter,
        );
    }

    private function stateResolver(): ResourceWorkflowStateResolver
    {
        return $this->runtimeWorkflowStates ??= new ResourceWorkflowStateResolver($this->connection());
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createGroup(array $payload): array
    {
        $payload = (new InitialCreateStateNormalizer($this->stateResolver()))->normalize('product_group', $payload);
        $id = $this->groups()->create($payload, $this->operation());
        return $this->requireRow($this->groups()->findById($id), ProductGroupService::ERROR_NOT_FOUND);
    }

    /** @return array<string,mixed> */
    public function getGroup(int $id): array
    {
        return $this->getGroupWithOperation($id, $this->operation());
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateGroup(int $id, array $payload): array
    {
        $operation = $this->operation();
        $this->groups()->update($id, $payload, $operation);
        return $this->getGroupWithOperation($id, $operation);
    }

    /** @return array<string,mixed> */
    public function setGroupState(int $id, string $status): array
    {
        $operation = $this->operation();
        $this->groups()->setState($id, $status, $operation);
        return $this->getGroupWithOperation($id, $operation);
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listUoms(array $query = []): array
    {
        $requiresState = fn (): bool => $this->requiresRowStateAuthorization('product_uom');
        $authorizeBase = function (): void { $this->authorize('product_uom', 'view'); };
        return (new ProductUomListQueryService(
            $this->uoms(),
            new ProductUomListAuthorizationAdapter(
                $requiresState, $authorizeBase,
                fn (array $row): bool => $this->decision('product_uom', 'view', $row)->isAllowed(),
            ),
            $this->queryAdapter,
            $this->uoms === null ? $this->masterListSqlFastPath('product_uom', $requiresState, $authorizeBase) : null,
        ))->list($query);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createUom(array $payload): array
    {
        $payload = (new InitialCreateStateNormalizer($this->stateResolver()))->normalize('product_uom', $payload);
        $id = $this->uoms()->create($payload, $this->operation());
        return $this->requireRow($this->uoms()->findById($id), ProductUomMasterService::ERROR_NOT_FOUND);
    }

    /** @return array<string,mixed> */
    public function getUom(int $id): array
    {
        return $this->getUomWithOperation($id, $this->operation());
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateUom(int $id, array $payload): array
    {
        $operation = $this->operation();
        $this->uoms()->update($id, $payload, $operation);
        return $this->getUomWithOperation($id, $operation);
    }

    /** @return array<string,mixed> */
    public function setUomState(int $id, string $status): array
    {
        $operation = $this->operation();
        $this->uoms()->setState($id, $status, $operation);
        return $this->getUomWithOperation($id, $operation);
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function listProducts(array $query = []): array
    {
        // Transitional API-050 facade: Product owns list query orchestration.
        // Retain the exact lazy operation, Authorization instance and injected
        // collaborators of this facade while migrating its HTTP consumers.
        $db = $this->connection();
        $security = new ProductListAuthorizationAdapter(
            $db,
            fn (): OperationContext => $this->operation(),
            fn (): AuthorizationService => $this->authorization(),
            fn (): bool => $this->requiresRowStateAuthorization('product'),
        );
        return (new ProductListQueryService(
            $db,
            $security,
            $this->queryAdapter,
            $this->productRepository,
            $this->roles,
            $this->products,
        ))->list($query);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createProduct(array $payload): array
    {
        // The COMBO composition belongs to the CREATE operation. A second PUT
        // after POST previously left an orphaned DRAFT COMBO if edit/PBAC or
        // domain validation rejected its component set.
        $hasItems = array_key_exists('combo_items', $payload);
        $items = $payload['combo_items'] ?? null;
        unset($payload['combo_items']);
        if ($hasItems && (! is_array($items) || ! array_is_list($items)
            || strtoupper((string) ($payload['product_type'] ?? '')) !== 'COMBO')) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'combo_items']);
        }
        $payload = (new InitialCreateStateNormalizer($this->stateResolver()))->normalize('product', $payload);
        $operation = $this->operation();
        if (! $hasItems) {
            $id = $this->products()->create($payload, $operation);
            return $this->getProductWithOperation($id, $operation);
        }

        $db = $this->connection();
        if (! $db instanceof BaseConnection || ! $db->transBegin()) {
            throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation' => 'combo_create']);
        }
        try {
            $id = $this->products()->create($payload, $operation);
            // Always apply normal product.edit PBAC in the new record's trusted
            // Initial State. No create->edit privilege escalation is allowed.
            $this->combos()->replaceItems($id, $items, $operation);
            $created = $this->getProductWithOperation($id, $operation);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation' => 'combo_create']);
            }
            return $created;
        } catch (\Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /**
     * Shared Product read capability. When the catalog was injected with an
     * authorization service, mutation context, or Product collaborators, the
     * returned reader uses those exact objects and the same lazy operation.
     *
     * This is a narrow composition seam, not a new HTTP operation.
     */
    public function productReadAccess(): AuthorizedProductReadService
    {
        return new AuthorizedProductReadService(
            $this->products(),
            $this->roles(),
            $this->authorization(),
            fn (): OperationContext => $this->operation(),
        );
    }

    /** @return array<string,mixed> */
    public function getProduct(int $id): array
    {
        $operation = $this->operation();
        $row = $this->getProductWithOperation($id, $operation);
        // A direct Product detail GET also powers the editor. Return the same
        // advisory PBAC actions as the list; mutation readbacks stay unchanged.
        $security = new ProductListAuthorizationAdapter(
            $this->connection(),
            static fn (): OperationContext => $operation,
            fn (): AuthorizationService => $this->authorization(),
            fn (): bool => $this->requiresRowStateAuthorization('product'),
        );
        return $security->withAllowedActions($operation, [$row])[0];
    }

    /**
     * Authorizes the persisted row; never trusts multipart status/owner hints.
     * @return array<string,mixed>
     */
    public function productForImage(int $id, string $action): array
    {
        return $this->productReadAccess()->authorizedRow($id, $action);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        $operation = $this->operation();
        $this->products()->update($id, $payload, $operation);
        return $this->getProductWithOperation($id, $operation);
    }

    /** @return array<string,mixed> */
    public function setProductState(int $id, string $status): array
    {
        $operation = $this->operation();
        $this->products()->setState($id, $status, $operation);
        return $this->getProductWithOperation($id, $operation);
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
        // Transitional API-050 facade: Pricing owns Price List list/query
        // orchestration. Preserve lazy operation/PBAC and injected collaborators;
        // query validation still precedes the first DB connection resolution.
        return (new PriceListQueryService(
            fn (): ConnectionInterface => $this->connection(),
            new PriceListListAuthorizationAdapter(
                fn (): OperationContext => $this->operation(),
                fn (): AuthorizationService => $this->authorization(),
            ),
            $this->queryAdapter,
            $this->priceLists,
        ))->list($query);
    }

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createPriceList(array $payload): array
    {
        $payload = (new InitialCreateStateNormalizer($this->stateResolver()))->normalize('price_list', $payload);
        $operation = $this->operation();
        $id = $this->priceLists()->create($payload, $operation);
        return $this->getPriceListWithOperation($id, $operation);
    }

    /** @return array<string,mixed> */
    public function getPriceList(int $id): array
    {
        return $this->getPriceListWithOperation($id, $this->operation());
    }

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function updatePriceList(int $id, array $payload): array
    {
        $operation = $this->operation();
        $this->priceLists()->update($id, $payload, $operation);
        return $this->getPriceListWithOperation($id, $operation);
    }

    /** @return array<string,mixed> */
    public function setPriceListState(int $id, string $status): array
    {
        $operation = $this->operation();
        $this->priceLists()->setState($id, $status, $operation);
        return $this->getPriceListWithOperation($id, $operation);
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

    /** @return array<string,mixed> */
    private function getGroupWithOperation(int $id, OperationContext $operation): array
    {
        $row = $this->requireRow($this->groups()->findById($id), ProductGroupService::ERROR_NOT_FOUND);
        $this->authorize('product_group', 'view', $row, operation: $operation);
        return $row;
    }

    /** @return array<string,mixed> */
    private function getUomWithOperation(int $id, OperationContext $operation): array
    {
        $row = $this->requireRow($this->uoms()->findById($id), ProductUomMasterService::ERROR_NOT_FOUND);
        $this->authorize('product_uom', 'view', $row, operation: $operation);
        return $row;
    }

    /** @return array<string,mixed> */
    private function getProductWithOperation(int $id, OperationContext $operation): array
    {
        // Keep the shared Product read capability while freezing the command's
        // actor/evaluation time/request id for mutation readback.
        $reader = new AuthorizedProductReadService(
            $this->products(),
            $this->roles(),
            $this->authorization(),
            static fn (): OperationContext => $operation,
        );
        return $this->decorateProduct($reader->authorizedRow($id, 'view'));
    }

    /** @return array<string,mixed> */
    private function getPriceListWithOperation(int $id, OperationContext $operation): array
    {
        $row = $this->requireRow($this->priceLists()->findById($id), PriceListService::ERROR_NOT_FOUND);
        $this->authorize('price_list', 'view', $row, operation: $operation);
        return $row;
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
        // Database TINYINT(1) reads are driver-dependent (0, '0', false).
        // The public OpenAPI contract promises a JSON boolean.
        // Never expose internal storage filenames or server paths in API JSON.
        if (array_key_exists('image_filename', $row)) {
            $row['image_available'] = is_string($row['image_filename']) && $row['image_filename'] !== '';
            $row['image_url'] = \App\Infrastructure\Media\YearlyImageStorage::publicUrl($row['image_filename'], 'product');
        }
        unset($row['image_filename'], $row['image_mime'], $row['image_size_bytes']);
        if (array_key_exists('serial_management_enabled', $row)) {
            $row['serial_management_enabled'] = in_array($row['serial_management_enabled'], [true, 1, '1'], true);
        }
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
    private function authorize(
        string $resource,
        string $action,
        ?array $target = null,
        array $input = [],
        mixed $clientHint = null,
        ?OperationContext $operation = null,
    ): void {
        $decision = $this->decision($resource, $action, $target, $input, $clientHint, $operation);
        if (! $decision->isAllowed()) {
            throw new DomainException(strtoupper($resource) . '_AUTHORIZATION_DENIED', ['action' => $action]);
        }
    }

    /** @param array<string,mixed>|null $target
     *  @param array<string,mixed> $input
     */
    private function decision(
        string $resource,
        string $action,
        ?array $target = null,
        array $input = [],
        mixed $clientHint = null,
        ?OperationContext $operation = null,
    ): \App\Authorization\AuthorizationDecision {
        return $this->authorization()->authorize(
            $operation ?? $this->operation(),
            $resource,
            $action,
            new EvaluationOrganizationRequest(
                persistedTarget: $target,
                input: $input,
                clientEvaluationOrganizationId: $clientHint,
            ),
        );
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
        return $this->authorization ?? ($this->runtimeAuthorization ??= new AuthorizationService(db: $this->connection()));
    }

    private function requiresRowStateAuthorization(string $resource): bool
    {
        $this->runtimeWorkflowStates ??= new ResourceWorkflowStateResolver($this->connection());
        if ($this->runtimeWorkflowStates->lifecycleId($resource) !== null) {
            return true;
        }
        $resourceRow = $this->runtimeWorkflowStates->resource($resource);
        return (new PolicyRepository($this->connection()))->hasActiveStateScopedPolicies((int) $resourceRow['id']);
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

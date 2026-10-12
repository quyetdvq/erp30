<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationContext;
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
use App\Modules\Product\ProductGroup\ProductGroupService;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomService;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Repositories\OrganizationRepository;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\NumberingScopePeriodContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized Product master mutations for Product Model V2. */
final class ProductMasterService
{
    public const ERROR_NOT_FOUND = 'PRODUCT_NOT_FOUND';
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_AUTHORIZATION_DENIED';
    public const ERROR_REQUIRED_FIELD = 'PRODUCT_REQUIRED_FIELD';
    public const ERROR_INVALID_FIELD = 'PRODUCT_INVALID_FIELD';
    public const ERROR_INVALID_TYPE = 'PRODUCT_INVALID_TYPE';
    public const ERROR_INVALID_STATUS = 'PRODUCT_INVALID_STATUS';
    public const ERROR_INVALID_DECIMAL = 'PRODUCT_INVALID_DECIMAL';
    public const ERROR_NEGATIVE_PRICE = 'PRODUCT_PRICE_NEGATIVE';
    public const ERROR_NEGATIVE_COST = 'PRODUCT_STANDARD_COST_NEGATIVE';
    public const ERROR_ROOT_CHILD_NAME = 'PRODUCT_ROOT_CHILD_NAME_INVALID';
    public const ERROR_CHILD_NAME_REQUIRED = 'PRODUCT_CHILD_NAME_REQUIRED';
    public const ERROR_CHILD_PRODUCT_NAME = 'PRODUCT_CHILD_PRODUCT_NAME_INVALID';
    public const ERROR_PRODUCT_NAME_REQUIRED = 'PRODUCT_NAME_REQUIRED';
    public const ERROR_SKU_REQUIRED = 'PRODUCT_SKU_REQUIRED';
    public const ERROR_UOM_REQUIRED = 'PRODUCT_BASE_UOM_REQUIRED';
    public const ERROR_PRICE_REQUIRED = 'PRODUCT_PRICE_REQUIRED';
    public const ERROR_INACTIVE_UOM = 'PRODUCT_BASE_UOM_INACTIVE';
    public const ERROR_INACTIVE_GROUP = 'PRODUCT_GROUP_INACTIVE';
    public const ERROR_PARENT_NOT_FOUND = 'PRODUCT_PARENT_NOT_FOUND';
    public const ERROR_PARENT_INVALID = 'PRODUCT_PARENT_INVALID';
    public const ERROR_PARENT_IS_CHILD = 'PRODUCT_PARENT_IS_CHILD';
    public const ERROR_PARENT_IS_COMBO = 'PRODUCT_PARENT_IS_COMBO';
    public const ERROR_DEPTH_EXCEEDED = 'PRODUCT_HIERARCHY_DEPTH_EXCEEDED';
    public const ERROR_COMBO_PARENT = 'PRODUCT_COMBO_PARENT_INVALID';
    public const ERROR_COMBO_SERIAL = 'PRODUCT_COMBO_SERIAL_INVALID';
    public const ERROR_PARENT_PRICE = 'PRODUCT_PARENT_PRICE_INVALID';
    public const ERROR_PARENT_SERIAL = 'PRODUCT_PARENT_SERIAL_INVALID';
    public const ERROR_CONVERSION_HISTORY = 'PRODUCT_CONVERSION_HISTORY_BLOCKED';
    public const ERROR_CONVERSION_PRICE_LIST = 'PRODUCT_CONVERSION_PRICE_LIST_BLOCKED';
    public const ERROR_CONVERSION_UOM = 'PRODUCT_CONVERSION_UOM_BLOCKED';
    public const ERROR_CONVERSION_COMBO = 'PRODUCT_CONVERSION_COMBO_BLOCKED';
    public const ERROR_SEMANTIC_HISTORY = 'PRODUCT_SEMANTIC_HISTORY_LOCKED';
    public const ERROR_REPARENT_UNSUPPORTED = 'PRODUCT_CHILD_REPARENT_UNSUPPORTED';
    public const ERROR_TYPE_CHANGE_INVALID = 'PRODUCT_TYPE_CHANGE_INVALID';
    public const ERROR_STATUS_REQUIRES_SETSTATE = 'PRODUCT_STATUS_REQUIRES_SETSTATE';
    public const ERROR_MUTATION_FAILED = 'PRODUCT_MUTATION_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_CONNECTION_INVALID';
    public const ERROR_CONCURRENT_MODIFICATION = 'CONCURRENT_MODIFICATION';

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductRepository $repository = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        private readonly ?ProductGroupService $productGroups = null,
        private readonly ?ProductUomMasterService $productUoms = null,
        private readonly ?ProductHistoryProvider $history = null,
        private readonly ?AutoNumberService $autoNumber = null,
        private readonly ?AutoNumberingSeriesRepository $numberingSeries = null,
        private readonly ?DomainLockService $domainLock = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $productUomService = null,
        private readonly ?OrganizationRepository $organizations = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $clientHint = $data['evaluation_organization_id'] ?? null;
        // Also covers Import: it calls the domain service directly instead of
        // entering through Api050ProductCatalogService's HTTP normalizer.
        $data = (new \App\Services\InitialCreateStateNormalizer(
            new \App\Services\ResourceWorkflowStateResolver($this->db),
        ))->normalize('product', $data);
        $payload = $this->normalizeCreate($data);
        $decision = $this->authorize($operation, 'create', null, $payload, $clientHint);
        $this->validateCreateReferences($payload);
        $context = $this->numberingContext($decision->evaluationOrganizationId);

        $parentId = $payload['parent_product_id'] ?? null;
        if ($parentId !== null) {
            return $this->withStructureLock($parentId, function () use ($payload, $operation, $context): int {
                return $this->issueAndPersist($payload, $operation, $context);
            });
        }

        return $this->issueAndPersist($payload, $operation, $context);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'product_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        if (array_key_exists('status', $data)) {
            throw new DomainException(self::ERROR_STATUS_REQUIRES_SETSTATE, ['field' => 'status']);
        }
        $payload = $this->normalizeUpdate($data);
        $this->authorize($operation, 'edit', $current, $payload, $clientHint);
        $merged = array_merge($current, $payload);
        $this->assertReparentBoundary($current, $merged);
        $this->validateReferenceChanges($current, $payload, $merged);

        $semanticChanged = $this->semanticChanged($current, $merged);
        $structureAffected = $semanticChanged || $this->typeChanged($current, $merged);
        $rootId = $this->rootId($current);
        if ($structureAffected) {
            return $this->withStructureLock($rootId, function () use ($targetId, $payload, $operation): bool {
                return $this->transaction(function () use ($targetId, $payload, $operation): bool {
                    $locked = $this->repo()->findForUpdate($targetId);
                    if ($locked === null) {
                        throw new DomainException(self::ERROR_NOT_FOUND, ['product_id' => $targetId]);
                    }
                    $mergedLocked = array_merge($locked, $payload);
                    $this->assertReparentBoundary($locked, $mergedLocked);
                    $this->validateMerged($locked, $mergedLocked);
                    $this->assertHistoryLock($locked, $mergedLocked);

                    return $this->repo()->updateMutable($targetId, $this->persistenceUpdate($payload), $operation);
                }, 'product_update');
            });
        }

        $this->validateMerged($current, $merged);

        return $this->repo()->updateMutable($targetId, $this->persistenceUpdate($payload), $operation);
    }

    public function setState(int $id, string $status, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        if (\App\Services\BusinessLifecycleTransitions::applyIfBound(
            'product', $id, $status, $operation, $this->db,
        )) {
            return true;
        }
        $targetId = PositiveId::normalize($id, 'product_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $normalizedStatus = $this->normalizeStatus($status);
        $decision = $this->authorize($operation, 'setstate', $current, []);

        return $this->transaction(function () use ($targetId, $normalizedStatus, $operation, $decision): bool {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['product_id' => $targetId]);
            }
            $updated = $this->repo()->updateStatus($targetId, $normalizedStatus, $operation);
            $this->audit()->recordIfRequired($decision);

            return $updated;
        }, 'product_setstate');
    }

    public function activate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'ACTIVE', $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'INACTIVE', $operation);
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->repo()->findById($id);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->repo()->listAll();
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->repo()->findByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findBySku(string $sku): ?array
    {
        return $this->repo()->findBySku($sku);
    }

    /** @return array<string, mixed>|null */
    public function findByBarcode(string $barcode): ?array
    {
        return $this->repo()->findByBarcode($barcode);
    }

    /** @param array<string, mixed> $payload */
    private function issueAndPersist(array $payload, OperationContext $operation, EvaluationOrganizationContext $context): int
    {
        $createdId = null;
        $mutation = new class($this->repo(), function (string $identifier) use (&$createdId, $payload, $operation): void {
            $createdId = $this->persistCreate($payload, $identifier, $operation);
        }) implements AutoNumberingBusinessMutation {
            /** @param \Closure(string): void $persist */
            public function __construct(private readonly ProductRepository $repository, private readonly \Closure $persist)
            {
            }

            public function isIdentifierOccupied(string $identifier): bool
            {
                return $this->repository->findByCode($identifier) !== null;
            }

            public function persist(string $identifier): void
            {
                ($this->persist)($identifier);
            }
        };

        $seriesCode = $payload['numbering_series_code'] ?? null;
        if ($this->hasNumberingSeries($seriesCode)) {
            $this->autoNumber()->issue('product', $context, $operation, $mutation, $seriesCode, $payload['code'] ?? null);
        } else {
            if (($payload['code'] ?? null) === null || trim((string) $payload['code']) === '') {
                throw new DomainException(ProductRepository::ERROR_FIELD_REQUIRED, ['field' => 'code']);
            }
            $this->transaction(function () use ($mutation, $payload): void {
                $mutation->persist((string) $payload['code']);
            }, 'product_create');
        }

        if ($createdId === null) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'product_create']);
        }

        return $createdId;
    }

    /** @param array<string, mixed> $payload */
    private function persistCreate(array $payload, string $identifier, OperationContext $operation): int
    {
        $data = $payload;
        unset($data['numbering_series_code'], $data['evaluation_organization_id'], $data['product_code']);
        $data['code'] = $identifier;
        $parentId = $data['parent_product_id'] ?? null;
        if ($parentId !== null) {
            $parent = $this->repo()->findForUpdate($parentId);
            if ($parent === null) {
                throw new DomainException(self::ERROR_PARENT_NOT_FOUND, ['parent_product_id' => $parentId]);
            }
            $this->assertParentCandidate($parent);
            if (! $this->repo()->hasChildren($parentId)) {
                $this->assertConversionEligible($parent);
                $this->repo()->update($parentId, [
                    'price' => null,
                    'serial_management_enabled' => 0,
                ], $operation);
            }
        }

        return $this->repo()->insert($data, $operation);
    }

    /** @param array<string, mixed> $payload */
    private function validateCreateReferences(array $payload): void
    {
        if (($payload['product_group_id'] ?? null) !== null) {
            $this->groups()->requireSelectableProductGroup((int) $payload['product_group_id']);
        }
        if (($payload['base_uom_id'] ?? null) !== null) {
            $this->baseUoms()->requireActiveBaseUom((int) $payload['base_uom_id']);
        }
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $payload
     *  @param array<string, mixed> $merged
     */
    private function validateReferenceChanges(array $current, array $payload, array $merged): void
    {
        if (array_key_exists('product_group_id', $payload)
            && $this->nullableId($current['product_group_id'] ?? null) !== $this->nullableId($merged['product_group_id'] ?? null)
            && ($merged['product_group_id'] ?? null) !== null) {
            $this->groups()->requireSelectableProductGroup((int) $merged['product_group_id']);
        }
        if (array_key_exists('base_uom_id', $payload)
            && $this->nullableId($current['base_uom_id'] ?? null) !== $this->nullableId($merged['base_uom_id'] ?? null)
            && ($merged['base_uom_id'] ?? null) !== null) {
            $this->baseUoms()->requireActiveBaseUom((int) $merged['base_uom_id']);
        }
    }

    /** @param array<string, mixed> $parent */
    private function assertParentCandidate(array $parent): void
    {
        if (($parent['product_type'] ?? null) === 'COMBO') {
            throw new DomainException(self::ERROR_PARENT_IS_COMBO, ['parent_product_id' => $parent['id'] ?? null]);
        }
        if (($parent['product_type'] ?? null) !== 'STANDARD') {
            throw new DomainException(self::ERROR_PARENT_INVALID, ['parent_product_id' => $parent['id'] ?? null]);
        }
        if (($parent['parent_product_id'] ?? null) !== null) {
            throw new DomainException(self::ERROR_PARENT_IS_CHILD, ['parent_product_id' => $parent['id'] ?? null]);
        }
    }

    /** @param array<string, mixed> $parent */
    private function assertConversionEligible(array $parent): void
    {
        $id = PositiveId::normalize($parent['id'] ?? null, 'product_id', self::ERROR_PARENT_NOT_FOUND);
        if ($this->history()->hasTransactionHistory($id)) {
            throw new DomainException(self::ERROR_CONVERSION_HISTORY, ['product_id' => $id]);
        }
        if ($this->repo()->hasPriceListReference($id)) {
            throw new DomainException(self::ERROR_CONVERSION_PRICE_LIST, ['product_id' => $id]);
        }
        if ($this->repo()->hasUomConversion($id)) {
            throw new DomainException(self::ERROR_CONVERSION_UOM, ['product_id' => $id]);
        }
        if ($this->repo()->isComboComponent($id)) {
            throw new DomainException(self::ERROR_CONVERSION_COMBO, ['product_id' => $id]);
        }
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function validateMerged(array $current, array $merged): void
    {
        $type = strtoupper((string) ($merged['product_type'] ?? ''));
        $parentId = $merged['parent_product_id'] ?? null;
        $hasChildren = $this->roles()->isParent($current);
        if (($merged['manual_standard_cost'] ?? null) !== null) {
            $this->assertNonNegative($merged['manual_standard_cost'], self::ERROR_NEGATIVE_COST);
        }
        if ($type === 'COMBO') {
            if ($parentId !== null || $hasChildren) {
                throw new DomainException(self::ERROR_TYPE_CHANGE_INVALID, ['product_type' => 'COMBO']);
            }
            if (($merged['serial_management_enabled'] ?? false) === true || (int) ($merged['serial_management_enabled'] ?? 0) === 1) {
                throw new DomainException(self::ERROR_COMBO_SERIAL);
            }
            $this->assertRootFields($merged, true);
            return;
        }
        if ($type !== 'STANDARD') {
            throw new DomainException(self::ERROR_INVALID_TYPE, ['field' => 'product_type']);
        }
        if (strtoupper((string) ($current['product_type'] ?? '')) === 'COMBO'
            && $this->repo()->hasComboComposition($current['id'] ?? null)) {
            throw new DomainException(self::ERROR_TYPE_CHANGE_INVALID, [
                'product_type' => 'STANDARD',
                'reason' => 'combo_composition_exists',
            ]);
        }
        if ($parentId !== null) {
            $this->assertChildFields($merged);
            return;
        }
        if ($hasChildren) {
            if (($merged['price'] ?? null) !== null) {
                throw new DomainException(self::ERROR_PARENT_PRICE);
            }
            if ((int) ($merged['serial_management_enabled'] ?? 0) !== 0) {
                throw new DomainException(self::ERROR_PARENT_SERIAL);
            }
            if (($merged['product_name'] ?? null) === null || trim((string) $merged['product_name']) === '') {
                throw new DomainException(self::ERROR_PRODUCT_NAME_REQUIRED);
            }
            return;
        }
        $this->assertRootFields($merged, false);
    }

    /** @param array<string, mixed> $row */
    private function assertRootFields(array $row, bool $combo): void
    {
        if (($row['product_name'] ?? null) === null || trim((string) $row['product_name']) === '') {
            throw new DomainException(self::ERROR_PRODUCT_NAME_REQUIRED);
        }
        if (($row['product_child_name'] ?? null) !== null) {
            throw new DomainException(self::ERROR_ROOT_CHILD_NAME);
        }
        $this->assertSelectableFields($row);
        if ($combo && (int) ($row['serial_management_enabled'] ?? 0) !== 0) {
            throw new DomainException(self::ERROR_COMBO_SERIAL);
        }
    }

    /** @param array<string, mixed> $row */
    private function assertChildFields(array $row): void
    {
        if (($row['product_name'] ?? null) !== null) {
            throw new DomainException(self::ERROR_CHILD_PRODUCT_NAME);
        }
        if (($row['product_child_name'] ?? null) === null || trim((string) $row['product_child_name']) === '') {
            throw new DomainException(self::ERROR_CHILD_NAME_REQUIRED);
        }
        $this->assertSelectableFields($row);
    }

    /** @param array<string, mixed> $row */
    private function assertSelectableFields(array $row): void
    {
        if (($row['sku'] ?? null) === null || trim((string) $row['sku']) === '') {
            throw new DomainException(self::ERROR_SKU_REQUIRED);
        }
        if (($row['base_uom_id'] ?? null) === null) {
            throw new DomainException(self::ERROR_UOM_REQUIRED);
        }
        if (($row['price'] ?? null) === null) {
            throw new DomainException(self::ERROR_PRICE_REQUIRED);
        }
        $this->assertNonNegative($row['price'], self::ERROR_NEGATIVE_PRICE);
        if (($row['manual_standard_cost'] ?? null) !== null) {
            $this->assertNonNegative($row['manual_standard_cost'], self::ERROR_NEGATIVE_COST);
        }
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function assertHistoryLock(array $current, array $merged): void
    {
        if (! $this->semanticChanged($current, $merged)) {
            return;
        }
        if ($this->history()->hasTransactionHistory(PositiveId::normalize($current['id'] ?? null, 'product_id', self::ERROR_NOT_FOUND))) {
            throw new DomainException(self::ERROR_SEMANTIC_HISTORY, ['product_id' => $current['id'] ?? null]);
        }
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function semanticChanged(array $current, array $merged): bool
    {
        foreach (['base_uom_id', 'parent_product_id', 'product_type', 'serial_management_enabled'] as $field) {
            if ($this->normalizedValue($current[$field] ?? null, $field) !== $this->normalizedValue($merged[$field] ?? null, $field)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function assertReparentBoundary(array $current, array $merged): void
    {
        if ($this->nullableId($current['parent_product_id'] ?? null) !== $this->nullableId($merged['parent_product_id'] ?? null)) {
            if ($this->history()->hasTransactionHistory(PositiveId::normalize($current['id'] ?? null, 'product_id', self::ERROR_NOT_FOUND))) {
                throw new DomainException(self::ERROR_SEMANTIC_HISTORY, ['product_id' => $current['id'] ?? null]);
            }
            throw new DomainException(self::ERROR_REPARENT_UNSUPPORTED, ['product_id' => $current['id'] ?? null]);
        }
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function typeChanged(array $current, array $merged): bool
    {
        return strtoupper((string) ($current['product_type'] ?? '')) !== strtoupper((string) ($merged['product_type'] ?? ''));
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $this->assertSupported($data, true);
        $payload = $this->withoutInfrastructure($data);
        if (array_key_exists('product_code', $payload)) {
            if (array_key_exists('code', $payload) && $payload['code'] !== $payload['product_code']) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'product_code']);
            }
            $payload['code'] = $payload['product_code'];
        }
        unset($payload['product_code'], $payload['evaluation_organization_id']);
        $payload['code'] = $this->optionalText($payload['code'] ?? null, 'code', 50);
        $payload['sku'] = $this->optionalText($payload['sku'] ?? null, 'sku', 100);
        $payload['barcode'] = $this->optionalText($payload['barcode'] ?? null, 'barcode', 100);
        $payload['product_type'] = $this->enum($payload['product_type'] ?? 'STANDARD', ['STANDARD', 'COMBO'], self::ERROR_INVALID_TYPE);
        $payload['parent_product_id'] = $this->nullableId($payload['parent_product_id'] ?? null);
        $payload['product_group_id'] = $this->nullableId($payload['product_group_id'] ?? null);
        $payload['base_uom_id'] = $this->nullableId($payload['base_uom_id'] ?? null);
        $payload['product_name'] = $this->optionalText($payload['product_name'] ?? null, 'product_name', 255);
        $payload['product_child_name'] = $this->optionalText($payload['product_child_name'] ?? null, 'product_child_name', 255);
        $payload['price'] = $this->decimal($payload['price'] ?? null, 'price');
        $payload['manual_standard_cost'] = $this->decimal($payload['manual_standard_cost'] ?? null, 'manual_standard_cost');
        foreach (['net_weight_g', 'package_gross_weight_g', 'package_length_mm', 'package_width_mm', 'package_height_mm'] as $field) {
            $payload[$field] = $this->decimal($payload[$field] ?? null, $field);
        }
        $payload['description'] = $payload['description'] ?? null;
        if ($payload['description'] !== null && ! is_string($payload['description'])) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }
        $payload['serial_management_enabled'] = $this->boolean($payload['serial_management_enabled'] ?? false, 'serial_management_enabled');
        $payload['status'] = $this->normalizeStatus($payload['status'] ?? 'ACTIVE');

        if ($payload['parent_product_id'] === null) {
            $this->assertRootFields($payload, $payload['product_type'] === 'COMBO');
            if ($payload['product_type'] === 'COMBO' && $payload['serial_management_enabled']) {
                throw new DomainException(self::ERROR_COMBO_SERIAL);
            }
        } else {
            if ($payload['product_type'] !== 'STANDARD') {
                throw new DomainException(self::ERROR_INVALID_TYPE, ['field' => 'product_type']);
            }
            $this->assertChildFields($payload);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeUpdate(array $data): array
    {
        $this->assertSupported($data, false);
        $payload = $this->withoutInfrastructure($data);
        if (array_key_exists('product_code', $payload)) {
            if (array_key_exists('code', $payload) && $payload['code'] !== $payload['product_code']) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'product_code']);
            }
            $payload['code'] = $payload['product_code'];
        }
        unset($payload['product_code'], $payload['evaluation_organization_id']);
        foreach (['code' => 50, 'sku' => 100, 'barcode' => 100, 'product_name' => 255, 'product_child_name' => 255] as $field => $max) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->optionalText($payload[$field], $field, $max);
            }
        }
        foreach (['product_group_id', 'base_uom_id', 'parent_product_id'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->nullableId($payload[$field]);
            }
        }
        if (array_key_exists('product_type', $payload)) {
            $payload['product_type'] = $this->enum($payload['product_type'], ['STANDARD', 'COMBO'], self::ERROR_INVALID_TYPE);
        }
        foreach (['price', 'manual_standard_cost', 'net_weight_g', 'package_gross_weight_g', 'package_length_mm', 'package_width_mm', 'package_height_mm'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->decimal($payload[$field], $field);
            }
        }
        if (array_key_exists('serial_management_enabled', $payload)) {
            $payload['serial_management_enabled'] = $this->boolean($payload['serial_management_enabled'], 'serial_management_enabled');
        }
        if (array_key_exists('description', $payload) && $payload['description'] !== null && ! is_string($payload['description'])) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function assertSupported(array $data, bool $creating): void
    {
        $supported = [
            'code', 'product_code', 'sku', 'barcode', 'parent_product_id', 'product_name', 'product_child_name',
            'product_group_id', 'product_type', 'base_uom_id', 'price', 'net_weight_g', 'package_gross_weight_g',
            'package_length_mm', 'package_width_mm', 'package_height_mm', 'description', 'manual_standard_cost',
            'serial_management_enabled', 'status', 'numbering_series_code', 'evaluation_organization_id',
            'id', 'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true) || (! $creating && $field === 'numbering_series_code')) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutInfrastructure(array $data): array
    {
        unset($data['id'], $data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);
        return $data;
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
        }
        return trim($value);
    }

    private function decimal(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (! is_string($value) || preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }
        $scale = in_array($field, ['price', 'manual_standard_cost'], true) ? 2 : 3;
        $parts = explode('.', ltrim($value, '-'), 2);
        if (strlen($parts[0]) > 15 || (isset($parts[1]) && strlen($parts[1]) > $scale)) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }
        return $value;
    }

    private function assertNonNegative(mixed $value, string $error): void
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL);
        }
        if ((new Decimal((string) $value))->compare('0') < 0) {
            throw new DomainException($error);
        }
    }

    private function boolean(mixed $value, string $field): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === null) {
            return false;
        }
        throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $error): string
    {
        if (! is_string($value)) {
            throw new DomainException($error, ['value' => $value]);
        }
        $value = strtoupper(trim($value));
        if (! in_array($value, $allowed, true)) {
            throw new DomainException($error, ['value' => $value]);
        }
        return $value;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return PositiveId::normalize($value, 'product_id', self::ERROR_INVALID_FIELD);
    }

    private function normalizedValue(mixed $value, string $field): mixed
    {
        if (in_array($field, ['parent_product_id', 'base_uom_id'], true)) {
            return $value === null ? null : (int) $value;
        }
        if ($field === 'product_type') {
            return strtoupper((string) $value);
        }
        return (int) ((bool) $value);
    }

    /** @param array<string, mixed> $row */
    private function rootId(array $row): int
    {
        return $row['parent_product_id'] === null
            ? PositiveId::normalize($row['id'] ?? null, 'product_id', self::ERROR_NOT_FOUND)
            : PositiveId::normalize($row['parent_product_id'], 'parent_product_id', self::ERROR_PARENT_NOT_FOUND);
    }

    private function normalizeStatus(string $status): string
    {
        return \App\Services\WorkflowBusinessStatus::normalize(
            'product', $status, self::STATUSES, self::ERROR_INVALID_STATUS, $this->db,
        );
    }

    private function autoNumber(): AutoNumberService
    {
        return $this->autoNumber ?? new AutoNumberService(db: $this->db);
    }

    private function hasNumberingSeries(?string $seriesCode): bool
    {
        if ($seriesCode !== null && trim($seriesCode) !== '') {
            return true;
        }
        $resource = $this->numberingSeries()->findResourceByType('product');
        if ($resource === null) {
            return false;
        }
        return $this->numberingSeries()->findActiveDefaultsByResource((int) $resource['id']) !== [];
    }

    private function numberingSeries(): AutoNumberingSeriesRepository
    {
        return $this->numberingSeries ?? new AutoNumberingSeriesRepository($this->db);
    }

    private function numberingContext(int $groupId): EvaluationOrganizationContext
    {
        if ($this->organizationRepository()->find($groupId) === null) {
            throw new DomainException('EVALUATION_ORGANIZATION_NOT_FOUND', ['organization_id' => $groupId]);
        }
        return new EvaluationOrganizationContext($groupId, 'GROUP', 'AUTHORITATIVE');
    }

    private function organizationRepository(): OrganizationRepository
    {
        return $this->organizations ?? new OrganizationRepository($this->db);
    }

    /** @param array<string, mixed>|null $target
     *  @param array<string, mixed> $input
     */
    private function authorize(OperationContext $operation, string $action, ?array $target, array $input, mixed $clientHint = null): AuthorizationDecision
    {
        $decision = $this->auth()->authorize($operation, 'product', $action, new EvaluationOrganizationRequest(
            persistedTarget: $target,
            input: $input,
            clientEvaluationOrganizationId: $clientHint,
        ));
        if (! $decision->isAllowed()) {
            if ($action === 'setstate') {
                $this->audit()->recordIfRequired($decision);
            }
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $action]);
        }
        return $decision;
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $row = $this->repo()->findById($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['product_id' => $id]);
        }
        return $row;
    }

    /** @param array<string, mixed> $payload */
    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function persistenceUpdate(array $payload): array
    {
        unset($payload['evaluation_organization_id'], $payload['product_code'], $payload['numbering_series_code']);
        return $payload;
    }

    private function repo(): ProductRepository
    {
        return $this->repository ?? new ProductRepository($this->db);
    }

    private function groups(): ProductGroupService
    {
        return $this->productGroups ?? new ProductGroupService(db: $this->db);
    }

    private function uoms(): ProductUomMasterService
    {
        return $this->productUoms ?? new ProductUomMasterService(db: $this->db);
    }

    private function baseUoms(): ProductUomService
    {
        return $this->productUomService ?? new ProductUomService(productUoms: $this->uoms(), db: $this->db);
    }

    private function history(): ProductHistoryProvider
    {
        return $this->history ?? new NoProductHistoryProvider();
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(repository: $this->repo(), db: $this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): AuthorizationAuditService
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param callable(): mixed $callback */
    private function withStructureLock(int $rootId, callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'product_structure']);
        }
        $lock = $this->domainLock ?? new MariaDbDomainLockService($this->db);
        try {
            return $lock->synchronized('product-structure:' . $rootId, $callback);
        } catch (ConcurrencyException $exception) {
            throw $exception;
        }
    }

    /** @param callable(): mixed $callback */
    private function transaction(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        if (! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation]);
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
}

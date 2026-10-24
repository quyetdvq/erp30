<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom;

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
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized Product-specific UOM conversion and quantity boundary. */
final class ProductUomService
{
    public const ERROR_NOT_FOUND = 'PRODUCT_UOM_CONVERSION_NOT_FOUND';
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_AUTHORIZATION_DENIED';
    public const ERROR_INVALID_CONVERSION = 'UOM_INVALID_CONVERSION';
    public const UOM_INVALID_CONVERSION = self::ERROR_INVALID_CONVERSION;
    public const ERROR_INVALID_REFERENCE = 'INVALID_REFERENCE';
    public const ERROR_INACTIVE_REFERENCE = 'INACTIVE_REFERENCE';
    public const ERROR_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_CONCURRENT_MODIFICATION = 'CONCURRENT_MODIFICATION';
    public const ERROR_INVALID_DECIMAL = 'UOM_INVALID_DECIMAL';
    public const ERROR_INVALID_USAGE_TYPE = 'UOM_INVALID_USAGE_TYPE';
    public const ERROR_PRICE_REQUIRED = 'UOM_PRICE_REQUIRED';
    public const ERROR_INVALID_STATUS = 'UOM_INVALID_STATUS';
    public const ERROR_CONNECTION_INVALID = 'UOM_CONNECTION_INVALID';
    public const ERROR_MUTATION_FAILED = 'UOM_MUTATION_FAILED';

    /** @var list<string> */
    private const USAGE_TYPES = ['PURCHASE', 'SALES', 'BOTH'];

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var list<string> */
    private const MUTABLE_FIELDS = [
        'uom_id', 'conversion_factor', 'price', 'package_gross_weight_g',
        'package_length_mm', 'package_width_mm', 'package_height_mm',
        'usage_type', 'status', 'evaluation_organization_id',
        'id', 'product_id', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductUomConversionRepository $repository = null,
        private readonly ?ProductRepository $products = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomMasterService $productUoms = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        private readonly ?DomainLockService $domainLock = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(int $productId, array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $productId = PositiveId::normalize($productId, 'product_id', ProductRoleService::ERROR_NOT_FOUND);
        $product = $this->existingProduct($productId);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $payload = $this->normalizePayload($data, true);
        $decision = $this->authorize($operation, $product, $payload, $clientHint);
        $rootId = $this->rootId($product);

        return $this->withStructureLock($rootId, function () use ($productId, $payload, $operation): int {
            return $this->transaction(function () use ($productId, $payload, $operation): int {
                $lockedProduct = $this->lockedProduct($productId);
                $this->assertEligibleRole($lockedProduct);
                $this->validateBaseUom($lockedProduct);
                $this->validateNewUom($lockedProduct, (int) $payload['uom_id']);
                $this->assertNoDuplicate($productId, (int) $payload['uom_id']);

                return $this->repo()->create(array_merge($payload, ['product_id' => $productId]), $operation);
            }, 'product_uom_conversion_create');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $id = PositiveId::normalize($id, 'conversion_id', self::ERROR_NOT_FOUND);
        $current = $this->existingConversion($id);
        $product = $this->existingProduct((int) $current['product_id']);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $payload = $this->normalizePayload($data, false);
        if (array_key_exists('product_id', $data)) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['field' => 'product_id']);
        }
        $this->authorize($operation, $product, $payload, $clientHint);
        $rootId = $this->rootId($product);

        return $this->withStructureLock($rootId, function () use ($id, $payload, $operation): bool {
            return $this->transaction(function () use ($id, $payload, $operation): bool {
                $locked = $this->repo()->findForUpdate($id);
                if ($locked === null) {
                    throw new DomainException(self::ERROR_NOT_FOUND, ['conversion_id' => $id]);
                }
                $product = $this->lockedProduct((int) $locked['product_id']);
                $this->assertEligibleRole($product);
                $this->validateBaseUom($product);
                $merged = array_merge($locked, $payload);
                $this->validateMerged($product, $locked, $merged);

                return $this->repo()->update($id, $this->persistencePayload($payload), $operation);
            }, 'product_uom_conversion_update');
        });
    }

    public function setState(int $id, string $status, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $id = PositiveId::normalize($id, 'conversion_id', self::ERROR_NOT_FOUND);
        $current = $this->existingConversion($id);
        $product = $this->existingProduct((int) $current['product_id']);
        $normalizedStatus = $this->status($status);
        $decision = $this->authorize($operation, $product, ['status' => $normalizedStatus], null);
        $rootId = $this->rootId($product);

        return $this->withStructureLock($rootId, function () use ($id, $normalizedStatus, $operation, $decision): bool {
            return $this->transaction(function () use ($id, $normalizedStatus, $operation, $decision): bool {
                $locked = $this->repo()->findForUpdate($id);
                if ($locked === null) {
                    throw new DomainException(self::ERROR_NOT_FOUND, ['conversion_id' => $id]);
                }
                $product = $this->lockedProduct((int) $locked['product_id']);
                if ($normalizedStatus === 'ACTIVE') {
                    $this->assertEligibleRole($product);
                    $this->validateBaseUom($product);
                    $this->validateExistingUom($product, (int) $locked['uom_id']);
                    $this->validateMerged($product, $locked, array_merge($locked, ['status' => 'ACTIVE']));
                }
                $updated = $this->repo()->updateStatus($id, $normalizedStatus, $operation);
                $this->audit()->recordIfRequired($decision);

                return $updated;
            }, 'product_uom_conversion_setstate');
        });
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

    /** @return array<string, mixed>|null */
    public function findByProductAndUom(mixed $productId, mixed $uomId): ?array
    {
        return $this->repo()->findByProductAndUom($productId, $uomId);
    }

    /** @return array<string, mixed>|null */
    public function findActiveByProductAndUom(mixed $productId, mixed $uomId): ?array
    {
        return $this->repo()->findActiveByProductAndUom($productId, $uomId);
    }

    /** @return list<array<string, mixed>> */
    public function listByProduct(mixed $productId): array
    {
        return $this->repo()->listByProduct($productId);
    }

    /**
     * Replace the complete alternate-UOM set atomically.
     *
     * @param list<array<string, mixed>> $submittedItems
     * @return list<array<string, mixed>>
     */
    public function replaceByProduct(
        mixed $productId,
        array $submittedItems,
        ?OperationContext $operation = null,
        mixed $clientHint = null,
    ): array {
        $operation ??= $this->operation();
        $productId = PositiveId::normalize($productId, 'product_id', ProductRoleService::ERROR_NOT_FOUND);
        $product = $this->existingProduct($productId);
        $items = [];
        $seen = [];
        foreach ($submittedItems as $item) {
            $normalized = $this->normalizePayload($item, true);
            $uomId = (int) $normalized['uom_id'];
            if (isset($seen[$uomId])) {
                throw new DomainException(self::ERROR_DUPLICATE, ['uom_id' => $uomId]);
            }
            $seen[$uomId] = true;
            $items[] = $normalized;
        }
        $decision = $this->authorize($operation, $product, ['product_id' => $productId], $clientHint);
        $rootId = $this->rootId($product);

        return $this->withStructureLock($rootId, function () use ($productId, $items, $operation, $decision): array {
            return $this->transaction(function () use ($productId, $items, $operation, $decision): array {
                $lockedProduct = $this->lockedProduct($productId);
                $this->assertEligibleRole($lockedProduct);
                $this->validateBaseUom($lockedProduct);
                $existingRows = $this->repo()->listByProduct($productId);
                $existingByUom = [];
                foreach ($existingRows as $row) {
                    $existingByUom[(int) $row['uom_id']] = $row;
                }
                $submittedUoms = [];
                foreach ($items as $item) {
                    $uomId = (int) $item['uom_id'];
                    $submittedUoms[$uomId] = true;
                    $current = $existingByUom[$uomId] ?? null;
                    if ($current === null) {
                        $this->validateNewUom($lockedProduct, $uomId);
                        $this->usageAndPrice($item['usage_type'], $item['price'] ?? null);
                    } else {
                        $this->validateMerged($lockedProduct, $current, array_merge($current, $item));
                    }
                }
                foreach ($existingRows as $row) {
                    if (! isset($submittedUoms[(int) $row['uom_id']])) {
                        $this->repo()->delete((int) $row['id'], $operation);
                    }
                }
                foreach ($items as $item) {
                    $current = $existingByUom[(int) $item['uom_id']] ?? null;
                    if ($current === null) {
                        $this->repo()->create(array_merge($item, ['product_id' => $productId]), $operation);
                    } else {
                        $this->repo()->update((int) $current['id'], $this->persistencePayload($item), $operation);
                    }
                }
                $this->audit()->recordIfRequired($decision);

                return $this->repo()->listByProduct($productId);
            }, 'product_uom_conversion_replace');
        });
    }

    public function existsForProduct(mixed $productId): bool
    {
        return $this->repo()->existsForProduct($productId);
    }

    /** @return array<string, mixed> */
    public function requireActiveBaseUom(mixed $baseUomId): array
    {
        $id = PositiveId::normalize($baseUomId, 'base_uom_id', self::ERROR_INVALID_REFERENCE);
        $uom = $this->master()->findById($id);
        if ($uom === null) {
            throw new DomainException(ProductUomMasterService::ERROR_NOT_FOUND, ['product_uom_id' => $id]);
        }
        if (($uom['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(ProductUomMasterService::ERROR_INACTIVE_REFERENCE, ['product_uom_id' => $id]);
        }

        return $uom;
    }

    /** @return array<string, mixed> */
    public function validateBaseUom(mixed $product): array
    {
        $row = is_array($product) ? $product : $this->existingProduct($product);
        $baseId = $row['base_uom_id'] ?? null;
        if ($baseId === null) {
            if ($this->roles()->getRole($row) === ProductRoleService::ROLE_PARENT) {
                return [];
            }
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'base_uom_required']);
        }

        return $this->requireActiveBaseUom($baseId);
    }

    /** @return array<string, mixed> */
    public function getBaseUom(mixed $product): array
    {
        return $this->validateBaseUom($product);
    }

    /**
     * Validate a Product/UOM pair for selling-price configuration.
     *
     * @return array{uom: array<string, mixed>, conversion: array<string, mixed>|null, is_base: bool}
     */
    public function validateSellingUom(mixed $product, mixed $uomId): array
    {
        $row = is_array($product) ? $product : $this->existingProduct($product);
        $normalizedUomId = PositiveId::normalize($uomId, 'uom_id', self::ERROR_INVALID_REFERENCE);
        $base = $this->validateBaseUom($row);
        if ((int) $base['id'] === $normalizedUomId) {
            return ['uom' => $base, 'conversion' => null, 'is_base' => true];
        }

        $uom = $this->master()->findById($normalizedUomId);
        if ($uom === null) {
            throw new DomainException(self::ERROR_INVALID_REFERENCE, ['uom_id' => $normalizedUomId]);
        }
        if (($uom['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['uom_id' => $normalizedUomId]);
        }

        $conversion = $this->repo()->findByProductAndUom((int) $row['id'], $normalizedUomId);
        if ($conversion === null) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'conversion_not_found', 'uom_id' => $normalizedUomId]);
        }
        if (($conversion['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['uom_id' => $normalizedUomId]);
        }
        if (! in_array((string) ($conversion['usage_type'] ?? ''), ['SALES', 'BOTH'], true)) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'uom_not_sellable', 'uom_id' => $normalizedUomId]);
        }

        return ['uom' => $uom, 'conversion' => $conversion, 'is_base' => false];
    }

    public function convertToBase(mixed $product, mixed $sourceUomId, mixed $quantity): string
    {
        $row = is_array($product) ? $product : $this->existingProduct($product);
        $role = $this->roles()->getRole($row);
        if (! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_CHILD], true)) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'role_not_convertible']);
        }
        $base = $this->validateBaseUom($row);
        $sourceId = PositiveId::normalize($sourceUomId, 'uom_id', self::ERROR_INVALID_REFERENCE);
        $sourceQuantity = $this->requiredDecimal($quantity, 'quantity', 12);
        if ((new Decimal($sourceQuantity))->compare('0') < 0) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'negative_quantity']);
        }
        if ($sourceId === (int) $base['id']) {
            return $this->canonicalDecimal($sourceQuantity);
        }
        $conversion = $this->repo()->findActiveByProductAndUom((int) $row['id'], $sourceId);
        if ($conversion === null) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'conversion_not_found']);
        }

        return $this->canonicalDecimal((new Decimal($sourceQuantity))->multiply((string) $conversion['conversion_factor'], 12)->value);
    }

    public function convertQuantityToBase(mixed $product, mixed $sourceUomId, mixed $quantity): string
    {
        return $this->convertToBase($product, $sourceUomId, $quantity);
    }

    public function deriveAlternateNetWeight(mixed $product, mixed $conversion): ?string
    {
        $row = is_array($product) ? $product : $this->existingProduct($product);
        $netWeight = $row['net_weight_g'] ?? null;
        if ($netWeight === null || $netWeight === '') {
            return null;
        }
        $factor = is_array($conversion) ? ($conversion['conversion_factor'] ?? null) : $conversion;
        $factor = $this->requiredDecimal($factor, 'conversion_factor', 6);

        return $this->canonicalDecimal((new Decimal($this->requiredDecimal($netWeight, 'net_weight_g', 3)))
            ->multiply($factor, 9)->value);
    }

    public function getAlternateNetWeight(mixed $product, mixed $conversion): ?string
    {
        return $this->deriveAlternateNetWeight($product, $conversion);
    }

    /** @param array<string, mixed> $product */
    private function assertEligibleRole(array $product): void
    {
        $role = $this->roles()->getRole($product);
        if (! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_CHILD], true)) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'role_not_eligible', 'role' => $role]);
        }
    }

    /** @param array<string, mixed> $product */
    private function validateNewUom(array $product, int $uomId): void
    {
        $base = $this->validateBaseUom($product);
        if ((int) $base['id'] === $uomId) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'alternate_is_base']);
        }
        $this->validateExistingUom($product, $uomId);
    }

    /** @param array<string, mixed> $product */
    private function validateExistingUom(array $product, int $uomId): void
    {
        $uom = $this->master()->findById($uomId);
        if ($uom === null) {
            throw new DomainException(self::ERROR_INVALID_REFERENCE, ['uom_id' => $uomId]);
        }
        if (($uom['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['uom_id' => $uomId]);
        }
        if (($product['base_uom_id'] ?? null) !== null && (int) $product['base_uom_id'] === $uomId) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'alternate_is_base']);
        }
    }

    private function assertNoDuplicate(int $productId, int $uomId): void
    {
        if ($this->repo()->findByProductAndUom($productId, $uomId) !== null) {
            throw new DomainException(self::ERROR_DUPLICATE, ['product_id' => $productId, 'uom_id' => $uomId]);
        }
    }

    /** @param array<string, mixed> $product
     *  @param array<string, mixed> $current
     *  @param array<string, mixed> $merged
     */
    private function validateMerged(array $product, array $current, array $merged): void
    {
        $uomId = PositiveId::normalize($merged['uom_id'] ?? null, 'uom_id', self::ERROR_INVALID_REFERENCE);
        if ($uomId !== (int) $current['uom_id']) {
            $this->validateNewUom($product, $uomId);
            $existing = $this->repo()->findByProductAndUom((int) $product['id'], $uomId);
            if ($existing !== null && (int) $existing['id'] !== (int) $current['id']) {
                throw new DomainException(self::ERROR_DUPLICATE, ['product_id' => $product['id'], 'uom_id' => $uomId]);
            }
        }
        $this->usageAndPrice($merged['usage_type'] ?? null, $merged['price'] ?? null);
        $this->decimal($merged['conversion_factor'] ?? null, 'conversion_factor', 6, false);
        if ((new Decimal((string) $merged['conversion_factor']))->compare('0') <= 0) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'factor_not_positive']);
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizePayload(array $data, bool $creating): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::MUTABLE_FIELDS, true)) {
                throw new DomainException(self::ERROR_INVALID_CONVERSION, ['field' => $field]);
            }
        }
        if ($creating && ! array_key_exists('uom_id', $data)) {
            throw new DomainException(self::ERROR_INVALID_REFERENCE, ['field' => 'uom_id']);
        }
        if ($creating && ! array_key_exists('conversion_factor', $data)) {
            throw new DomainException(self::ERROR_INVALID_CONVERSION, ['field' => 'conversion_factor']);
        }
        $payload = $data;
        unset($payload['evaluation_organization_id'], $payload['id'], $payload['product_id'], $payload['created_by'], $payload['updated_by'], $payload['created_at'], $payload['updated_at']);
        if (array_key_exists('uom_id', $payload)) {
            $payload['uom_id'] = PositiveId::normalize($payload['uom_id'], 'uom_id', self::ERROR_INVALID_REFERENCE);
        }
        if (array_key_exists('conversion_factor', $payload)) {
            $payload['conversion_factor'] = $this->requiredDecimal($payload['conversion_factor'], 'conversion_factor', 6);
            if ((new Decimal($payload['conversion_factor']))->compare('0') <= 0) {
                throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'factor_not_positive']);
            }
        }
        if (array_key_exists('price', $payload)) {
            $payload['price'] = $this->decimal($payload['price'], 'price', 2, true);
            if ($payload['price'] !== null && (new Decimal($payload['price']))->compare('0') < 0) {
                throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'price_negative']);
            }
        }
        foreach (['package_gross_weight_g', 'package_length_mm', 'package_width_mm', 'package_height_mm'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->decimal($payload[$field], $field, 3, true);
            }
        }
        if (array_key_exists('usage_type', $payload)) {
            $payload['usage_type'] = $this->usageType($payload['usage_type']);
        } elseif ($creating) {
            $payload['usage_type'] = 'BOTH';
        }
        if (array_key_exists('status', $payload)) {
            $payload['status'] = $this->status($payload['status']);
        } elseif ($creating) {
            $payload['status'] = 'ACTIVE';
        }
        if ($creating) {
            $this->usageAndPrice($payload['usage_type'], $payload['price'] ?? null);
        }

        return $payload;
    }

    private function usageType(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_USAGE_TYPE, ['usage_type' => $value]);
        }
        $value = strtoupper(trim($value));
        if (! in_array($value, self::USAGE_TYPES, true)) {
            throw new DomainException(self::ERROR_INVALID_USAGE_TYPE, ['usage_type' => $value]);
        }

        return $value;
    }

    private function usageAndPrice(mixed $usage, mixed $price): void
    {
        $usage = $this->usageType($usage);
        if (in_array($usage, ['SALES', 'BOTH'], true) && $price === null) {
            throw new DomainException(self::ERROR_PRICE_REQUIRED, ['usage_type' => $usage]);
        }
        if ($price !== null) {
            $price = $this->requiredDecimal($price, 'price', 2);
            if ((new Decimal($price))->compare('0') < 0) {
                throw new DomainException(self::ERROR_INVALID_CONVERSION, ['reason' => 'price_negative']);
            }
        }
    }

    private function status(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $value]);
        }
        $value = strtoupper(trim($value));
        if (! in_array($value, self::STATUSES, true)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $value]);
        }

        return $value;
    }

    private function decimal(mixed $value, string $field, int $scale, bool $nullable): ?string
    {
        if ($value === null || ($nullable && $value === '')) {
            if ($nullable) {
                return null;
            }
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }
        $parts = explode('.', ltrim($value, '-'), 2);
        $maxIntegerDigits = 18 - $scale;
        if (strlen($parts[0]) > $maxIntegerDigits || (isset($parts[1]) && strlen($parts[1]) > $scale)) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }

        return $value;
    }

    private function requiredDecimal(mixed $value, string $field, int $scale): string
    {
        $decimal = $this->decimal($value, $field, $scale, false);
        if ($decimal === null) {
            throw new DomainException(self::ERROR_INVALID_DECIMAL, ['field' => $field]);
        }

        return $decimal;
    }

    private function canonicalDecimal(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $result = $integer . ($fraction === '' ? '' : '.' . $fraction);

        return $negative && $result !== '0' ? '-' . $result : $result;
    }

    /** @return array<string, mixed> */
    private function existingProduct(mixed $id): array
    {
        $id = PositiveId::normalize($id, 'product_id', ProductRoleService::ERROR_NOT_FOUND);
        $row = $this->products()->findById($id);
        if ($row === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $id]);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function lockedProduct(int $id): array
    {
        $row = $this->products()->findForUpdate($id);
        if ($row === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $id]);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function existingConversion(int $id): array
    {
        $row = $this->repo()->findById($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['conversion_id' => $id]);
        }

        return $row;
    }

    /** @param array<string, mixed> $product */
    private function rootId(array $product): int
    {
        return $product['parent_product_id'] === null
            ? PositiveId::normalize($product['id'] ?? null, 'product_id', ProductRoleService::ERROR_NOT_FOUND)
            : PositiveId::normalize($product['parent_product_id'], 'parent_product_id', self::ERROR_INVALID_CONVERSION);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     */
    private function authorize(OperationContext $operation, array $product, array $input, mixed $clientHint): AuthorizationDecision
    {
        $decision = $this->auth()->authorize($operation, 'product', 'edit', new EvaluationOrganizationRequest(
            persistedTarget: $product,
            input: $input,
            clientEvaluationOrganizationId: $clientHint,
        ));
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => 'edit']);
        }

        return $decision;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function persistencePayload(array $payload): array
    {
        unset($payload['evaluation_organization_id'], $payload['product_id'], $payload['id']);

        return $payload;
    }

    private function repo(): ProductUomConversionRepository
    {
        return $this->repository ?? new ProductUomConversionRepository($this->db);
    }

    private function products(): ProductRepository
    {
        return $this->products ?? new ProductRepository($this->db);
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(repository: $this->products(), db: $this->db);
    }

    private function master(): ProductUomMasterService
    {
        return $this->productUoms ?? new ProductUomMasterService(db: $this->db);
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
            throw new DomainException(self::ERROR_CONCURRENT_MODIFICATION, ['product_id' => $rootId], $exception);
        }
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
}

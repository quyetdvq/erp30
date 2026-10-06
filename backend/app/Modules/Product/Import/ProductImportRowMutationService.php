<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Product\Combo\ComboCompositionService;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\ProductGroup\ProductGroupService;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Executes Product import rows through the canonical Product domain services. */
final class ProductImportRowMutationService
{
    public const ERROR_INVALID_MODE = 'IMPORT_INVALID_MODE';
    public const ERROR_TARGET_EXISTS = 'IMPORT_TARGET_EXISTS';
    public const ERROR_TARGET_NOT_FOUND = 'IMPORT_TARGET_NOT_FOUND';
    public const ERROR_PRODUCT_REFERENCE_UNRESOLVED = 'IMPORT_PRODUCT_REFERENCE_UNRESOLVED';
    public const ERROR_UOM_CONVERSION_EXISTS = 'IMPORT_UOM_CONVERSION_EXISTS';
    public const ERROR_UOM_CONVERSION_NOT_FOUND = 'IMPORT_UOM_CONVERSION_NOT_FOUND';
    public const ERROR_COMBO_ITEM_EXISTS = 'IMPORT_COMBO_ITEM_EXISTS';
    public const ERROR_COMBO_ITEM_NOT_FOUND = 'IMPORT_COMBO_ITEM_NOT_FOUND';
    public const ERROR_ROW_MUTATION_FAILED = 'IMPORT_ROW_MUTATION_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @var array<string, array{product_id:int, product_code:string}> */
    private array $sourceMap = [];

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductMasterService $products = null,
        private readonly ?ProductGroupService $groups = null,
        private readonly ?ProductUomMasterService $uomMaster = null,
        private readonly ?ProductUomService $uoms = null,
        private readonly ?ComboCompositionService $combos = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function execute(ProductImportPlan $plan, ?OperationContext $operation = null): ProductImportMutationResult
    {
        $operation ??= $this->operation();
        $this->sourceMap = [];
        $results = [];

        foreach ($plan->getProcessingRows() as $planRow) {
            try {
                $results[] = $this->executeRow($plan->getMode(), $planRow, $operation);
            } catch (DomainException $exception) {
                $results[] = ProductImportRowResult::error($planRow->getRowNumber(), $exception->getErrorCode());
            } catch (\Throwable) {
                $results[] = ProductImportRowResult::error($planRow->getRowNumber(), self::ERROR_ROW_MUTATION_FAILED);
            }
        }

        return new ProductImportMutationResult($results, $this->sourceMap);
    }

    public function mutate(ProductImportPlan $plan, ?OperationContext $operation = null): ProductImportMutationResult
    {
        return $this->execute($plan, $operation);
    }

    private function executeRow(string $mode, ProductImportPlanRow $planRow, OperationContext $operation): ProductImportRowResult
    {
        if (! in_array($mode, [ProductImportTemplateValidator::MODE_CREATE_ONLY, ProductImportTemplateValidator::MODE_UPDATE_ONLY, ProductImportTemplateValidator::MODE_UPSERT], true)) {
            throw new DomainException(self::ERROR_INVALID_MODE, ['mode' => $mode]);
        }

        return match ($planRow->getRecordType()) {
            'PRODUCT' => $this->executeProduct($mode, $planRow, $operation),
            'UOM_CONVERSION' => $this->executeUom($mode, $planRow, $operation),
            'COMBO_ITEM' => $this->executeCombo($mode, $planRow, $operation),
            default => throw new DomainException(ProductImportTemplateValidator::ERROR_INVALID_RECORD_TYPE, ['row' => $planRow->getRowNumber()]),
        };
    }

    private function executeProduct(string $mode, ProductImportPlanRow $planRow, OperationContext $operation): ProductImportRowResult
    {
        $row = $planRow->validatedRow;
        $code = $this->textCell($row->getCell('product_code'));
        $existing = $code === null ? null : $this->products()->findByCode($code);
        $isCreate = match ($mode) {
            ProductImportTemplateValidator::MODE_CREATE_ONLY => true,
            ProductImportTemplateValidator::MODE_UPDATE_ONLY => false,
            ProductImportTemplateValidator::MODE_UPSERT => $existing === null,
            default => throw new DomainException(self::ERROR_INVALID_MODE, ['mode' => $mode]),
        };

        if ($mode === ProductImportTemplateValidator::MODE_CREATE_ONLY && $existing !== null) {
            throw new DomainException(self::ERROR_TARGET_EXISTS, ['product_code' => $code]);
        }
        if (! $isCreate && $code === null) {
            throw new DomainException(ProductImportGraphException::ERROR_PRODUCT_CODE_REQUIRED, ['row' => $planRow->getRowNumber()]);
        }
        if (! $isCreate && $existing === null) {
            throw new DomainException(self::ERROR_TARGET_NOT_FOUND, ['product_code' => $code]);
        }

        if ($isCreate) {
            $productId = $this->products()->create($this->productPayload($planRow, true), $operation);
        } else {
            $productId = (int) ($existing['id'] ?? 0);
            $statusCell = $row->getCell('status');
            $update = function () use ($productId, $planRow, $operation, $statusCell): void {
                $this->products()->update($productId, $this->productPayload($planRow, false), $operation);
                if (! $statusCell->isValue() && ! $statusCell->isExplicitNull()) {
                    return;
                }
                if (! is_string($statusCell->value)) {
                    throw new DomainException(ProductMasterService::ERROR_INVALID_STATUS, ['field' => 'status']);
                }
                $this->products()->setState($productId, $statusCell->value, $operation);
            };
            if ($statusCell->isValue() || $statusCell->isExplicitNull()) {
                $this->transaction($update, 'product_import_update');
            } else {
                $update();
            }
        }

        $product = $this->products()->findById($productId);
        if ($product === null) {
            throw new DomainException(ProductMasterService::ERROR_NOT_FOUND, ['product_id' => $productId]);
        }
        $finalCode = (string) ($product['code'] ?? '');
        if ($planRow->sourceKey !== null) {
            $this->sourceMap[$planRow->sourceKey] = [
                'product_id' => $productId,
                'product_code' => $finalCode,
            ];
        }

        return ProductImportRowResult::success($planRow->getRowNumber(), $productId, $finalCode);
    }

    private function executeUom(string $mode, ProductImportPlanRow $planRow, OperationContext $operation): ProductImportRowResult
    {
        $reference = $planRow->getReference('product');
        $productId = $this->resolveProductReference($reference);
        $uomCode = $this->textCell($planRow->validatedRow->getCell('uom_code'));
        if ($uomCode === null) {
            throw new DomainException(ProductImportGraphException::ERROR_UOM_NOT_FOUND, ['row' => $planRow->getRowNumber()]);
        }
        $uom = $this->uomMaster()->findByCode($uomCode);
        if ($uom === null) {
            throw new DomainException(ProductImportGraphException::ERROR_UOM_NOT_FOUND, ['uom_code' => $uomCode]);
        }
        $uomId = (int) ($uom['id'] ?? 0);
        $existing = $this->uoms()->findByProductAndUom($productId, $uomId);
        $isCreate = match ($mode) {
            ProductImportTemplateValidator::MODE_CREATE_ONLY => true,
            ProductImportTemplateValidator::MODE_UPDATE_ONLY => false,
            ProductImportTemplateValidator::MODE_UPSERT => $existing === null,
            default => throw new DomainException(self::ERROR_INVALID_MODE, ['mode' => $mode]),
        };
        if ($mode === ProductImportTemplateValidator::MODE_CREATE_ONLY && $existing !== null) {
            throw new DomainException(self::ERROR_UOM_CONVERSION_EXISTS, ['product_id' => $productId, 'uom_id' => $uomId]);
        }
        if (! $isCreate && $existing === null) {
            throw new DomainException(self::ERROR_UOM_CONVERSION_NOT_FOUND, ['product_id' => $productId, 'uom_id' => $uomId]);
        }

        $payload = $this->uomPayload($planRow, $isCreate);
        $payload['uom_id'] = $uomId;
        $conversionId = $isCreate
            ? $this->uoms()->create($productId, $payload, $operation)
            : (int) ($existing['id'] ?? 0);
        if (! $isCreate) {
            $this->uoms()->update($conversionId, $payload, $operation);
        }

        return ProductImportRowResult::success($planRow->getRowNumber(), $conversionId);
    }

    private function executeCombo(string $mode, ProductImportPlanRow $planRow, OperationContext $operation): ProductImportRowResult
    {
        $owner = $this->resolveProductReference($planRow->getReference('owner'));
        $component = $this->resolveProductReference($planRow->getReference('component'));
        $existing = $this->findComboItem($owner, $component);
        $isCreate = match ($mode) {
            ProductImportTemplateValidator::MODE_CREATE_ONLY => true,
            ProductImportTemplateValidator::MODE_UPDATE_ONLY => false,
            ProductImportTemplateValidator::MODE_UPSERT => $existing === null,
            default => throw new DomainException(self::ERROR_INVALID_MODE, ['mode' => $mode]),
        };
        if ($mode === ProductImportTemplateValidator::MODE_CREATE_ONLY && $existing !== null) {
            throw new DomainException(self::ERROR_COMBO_ITEM_EXISTS, ['combo_product_id' => $owner, 'component_product_id' => $component]);
        }
        if (! $isCreate && $existing === null) {
            throw new DomainException(self::ERROR_COMBO_ITEM_NOT_FOUND, ['combo_product_id' => $owner, 'component_product_id' => $component]);
        }

        $quantity = $this->cellValue($planRow->validatedRow->getCell('quantity'), $isCreate);
        if ($quantity === ProductImportCell::UPDATE_NO_CHANGE) {
            $quantity = (string) ($existing['quantity'] ?? '');
        }
        $rows = $this->combos()->mergeItem($owner, $component, $quantity, $operation);
        $item = $this->findComboItemInRows($rows, $component);
        if ($item === null) {
            throw new DomainException(self::ERROR_ROW_MUTATION_FAILED, ['row' => $planRow->getRowNumber()]);
        }

        return ProductImportRowResult::success($planRow->getRowNumber(), (int) ($item['id'] ?? 0));
    }

    /** @return array<string, mixed> */
    private function productPayload(ProductImportPlanRow $planRow, bool $creating): array
    {
        $row = $planRow->validatedRow;
        $payload = [];
        foreach ([
            'sku', 'barcode', 'product_name', 'product_child_name', 'product_type', 'price',
            'net_weight_g', 'package_gross_weight_g', 'package_length_mm', 'package_width_mm',
            'package_height_mm', 'description', 'manual_standard_cost', 'serial_management_enabled', 'status',
        ] as $field) {
            $cell = $row->getCell($field);
            if ($creating && $cell->isBlank()) {
                continue;
            }
            $value = $this->cellValue($cell, $creating);
            if (! $creating && $field === 'status') {
                continue;
            }
            if ($value !== ProductImportCell::UPDATE_NO_CHANGE) {
                $payload[$field] = $value;
            }
        }

        $this->mapCatalog($row->getCell('product_group_code'), 'product_group_id', $payload, $creating);
        $this->mapCatalog($row->getCell('base_uom_code'), 'base_uom_id', $payload, $creating, true);
        $parentCell = $row->getCell('parent_source_key');
        $parentCodeCell = $row->getCell('parent_product_code');
        if ($parentCell->isValue() || $parentCodeCell->isValue()) {
            $reference = $planRow->getReference('parent');
            $payload['parent_product_id'] = $this->resolveProductReference($reference);
        } elseif ($creating && ($parentCell->isExplicitNull() || $parentCodeCell->isExplicitNull())) {
            $payload['parent_product_id'] = null;
        } elseif (! $creating && ($parentCell->isExplicitNull() || $parentCodeCell->isExplicitNull())) {
            $payload['parent_product_id'] = null;
        }

        if ($creating) {
            $code = $this->cellValue($row->getCell('product_code'), true);
            if ($code !== ProductImportCell::UPDATE_NO_CHANGE) {
                $payload['product_code'] = $code;
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function uomPayload(ProductImportPlanRow $planRow, bool $creating): array
    {
        $payload = [];
        foreach ([
            'conversion_factor', 'price', 'package_gross_weight_g', 'package_length_mm',
            'package_width_mm', 'package_height_mm', 'usage_type', 'status',
        ] as $field) {
            $cell = $planRow->validatedRow->getCell($field);
            if ($creating && $cell->isBlank() && in_array($field, ['price', 'package_gross_weight_g', 'package_length_mm', 'package_width_mm', 'package_height_mm', 'usage_type', 'status'], true)) {
                continue;
            }
            $value = $this->cellValue($cell, $creating);
            if ($value !== ProductImportCell::UPDATE_NO_CHANGE) {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function mapCatalog(ProductImportCell $cell, string $target, array &$payload, bool $creating, bool $baseUom = false): void
    {
        if ($cell->isBlank()) {
            return;
        }
        if ($cell->isExplicitNull()) {
            $payload[$target] = null;
            return;
        }
        if (! is_string($cell->value) || trim($cell->value) === '') {
            throw new DomainException(ProductImportGraphException::ERROR_REFERENCE_CONFLICT, ['field' => $target]);
        }
        $catalog = $baseUom ? $this->uomMaster()->findByCode($cell->value) : $this->groups()->findByCode($cell->value);
        if ($catalog === null) {
            throw new DomainException($baseUom ? ProductImportGraphException::ERROR_UOM_NOT_FOUND : ProductImportGraphException::ERROR_GROUP_NOT_FOUND, ['value' => $cell->value]);
        }
        $payload[$target] = (int) ($catalog['id'] ?? 0);
    }

    private function resolveProductReference(?ProductImportReference $reference): int
    {
        if ($reference === null) {
            throw new DomainException(self::ERROR_PRODUCT_REFERENCE_UNRESOLVED);
        }
        if ($reference->isSource()) {
            $resolved = $this->sourceMap[$reference->key] ?? null;
            if ($resolved === null) {
                throw new DomainException(self::ERROR_PRODUCT_REFERENCE_UNRESOLVED, ['source_key' => $reference->key]);
            }

            return $resolved['product_id'];
        }
        if ($reference->productId !== null && $reference->productId > 0) {
            return $reference->productId;
        }
        $product = $this->products()->findByCode($reference->key);
        if ($product === null) {
            throw new DomainException(self::ERROR_PRODUCT_REFERENCE_UNRESOLVED, ['product_code' => $reference->key]);
        }

        return (int) ($product['id'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    private function findComboItem(int $ownerId, int $componentId): ?array
    {
        return $this->combos()->findItem($ownerId, $componentId);
    }

    /** @param list<array<string,mixed>> $rows
     *  @return array<string,mixed>|null
     */
    private function findComboItemInRows(array $rows, int $componentId): ?array
    {
        foreach ($rows as $row) {
            if ((int) ($row['component_product_id'] ?? 0) === $componentId) {
                return $row;
            }
        }

        return null;
    }

    private function textCell(ProductImportCell $cell): ?string
    {
        if (! $cell->isValue()) {
            return null;
        }
        if (! is_string($cell->value) || trim($cell->value) === '') {
            throw new DomainException(ProductImportGraphException::ERROR_REFERENCE_CONFLICT);
        }

        return $cell->value;
    }

    private function cellValue(ProductImportCell $cell, bool $creating): mixed
    {
        return $creating ? $cell->resolveForCreate() : $cell->resolveForUpdate();
    }

    private function products(): ProductMasterService
    {
        return $this->products ?? new ProductMasterService(db: $this->db);
    }

    private function groups(): ProductGroupService
    {
        return $this->groups ?? new ProductGroupService(db: $this->db);
    }

    private function uomMaster(): ProductUomMasterService
    {
        return $this->uomMaster ?? new ProductUomMasterService(db: $this->db);
    }

    private function uoms(): ProductUomService
    {
        return $this->uoms ?? new ProductUomService(db: $this->db);
    }

    private function combos(): ComboCompositionService
    {
        return $this->combos ?? new ComboCompositionService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param callable(): void $callback */
    private function transaction(callable $callback, string $operation): void
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_ROW_MUTATION_FAILED, ['operation' => $operation]);
        }
        try {
            $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException(self::ERROR_ROW_MUTATION_FAILED, ['operation' => $operation]);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_ROW_MUTATION_FAILED, ['operation' => $operation], $exception);
        }
    }

}

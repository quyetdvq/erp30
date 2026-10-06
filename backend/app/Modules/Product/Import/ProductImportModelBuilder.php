<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\ProductGroup\ProductGroupService;
use App\Modules\Product\Uom\ProductUomMasterService;
use CodeIgniter\Database\ConnectionInterface;

/** Builds a read-only Product import model and dependency plan. */
final class ProductImportModelBuilder
{
    public const TARGET_CREATE = 'CREATE';
    public const TARGET_UPDATE = 'UPDATE';
    public const TARGET_DEPENDENT = 'DEPENDENT';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductRepository $products = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductGroupService $groups = null,
        private readonly ?ProductUomMasterService $uoms = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function build(ProductImportValidationResult $document): ProductImportPlan
    {
        $sourceRows = [];
        $sourceRowsByCode = [];
        $productMetadata = [];
        $planRows = [];
        $planByRow = [];
        /** @var array<string, ProductImportValidatedRow> $sourceRowsByNode */
        $sourceRowsByNode = [];

        foreach ($document->rows as $validatedRow) {
            $nodeId = $this->nodeId($validatedRow->rowNumber);
            $sourceRowsByNode[$nodeId] = $validatedRow;
            if ($validatedRow->recordType !== 'PRODUCT') {
                continue;
            }

            $sourceKey = $this->requiredText($validatedRow, 'source_key', ProductImportGraphException::ERROR_SOURCE_KEY_REQUIRED);
            if (isset($sourceRows[$sourceKey])) {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_DUPLICATE_SOURCE_KEY, [
                    'source_key' => $sourceKey,
                    'row' => $validatedRow->rowNumber,
                ]);
            }

            $productCode = $this->optionalText($validatedRow, 'product_code');
            if ($productCode !== null && isset($sourceRowsByCode[$productCode])) {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_DUPLICATE_PRODUCT_CODE, [
                    'product_code' => $productCode,
                    'row' => $validatedRow->rowNumber,
                ]);
            }

            $existing = $productCode === null ? null : $this->products()->findByCode($productCode);
            $existingId = $existing === null ? null : (int) ($existing['id'] ?? 0);
            if ($existing !== null) {
                // Existing targets are inspected through the canonical role boundary; no mutation occurs here.
                $this->roles()->getRole($existing);
            }
            if ($document->mode === ProductImportTemplateValidator::MODE_UPDATE_ONLY) {
                if ($productCode === null) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_PRODUCT_CODE_REQUIRED, [
                        'row' => $validatedRow->rowNumber,
                    ]);
                }
                if ($existing === null) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_PRODUCT_NOT_FOUND, [
                        'product_code' => $productCode,
                        'row' => $validatedRow->rowNumber,
                    ]);
                }
            }

            $sourceRows[$sourceKey] = $validatedRow;
            if ($productCode !== null) {
                $sourceRowsByCode[$productCode] = $sourceKey;
            }
            $productMetadata[$sourceKey] = [
                'row' => $validatedRow,
                'product_code' => $productCode,
                'existing' => $existing,
                'existing_id' => $existingId > 0 ? $existingId : null,
            ];
        }

        foreach ($document->rows as $validatedRow) {
            $nodeId = $this->nodeId($validatedRow->rowNumber);
            $sourceKey = $validatedRow->recordType === 'PRODUCT'
                ? $this->requiredText($validatedRow, 'source_key', ProductImportGraphException::ERROR_SOURCE_KEY_REQUIRED)
                : null;
            $productCode = $validatedRow->recordType === 'PRODUCT'
                ? $this->optionalText($validatedRow, 'product_code')
                : null;
            $existingId = $sourceKey === null ? null : ($productMetadata[$sourceKey]['existing_id'] ?? null);
            $targetPath = $validatedRow->recordType === 'PRODUCT'
                ? ($document->mode === ProductImportTemplateValidator::MODE_UPDATE_ONLY || ($document->mode === ProductImportTemplateValidator::MODE_UPSERT && $existingId !== null)
                    ? self::TARGET_UPDATE : self::TARGET_CREATE)
                : self::TARGET_DEPENDENT;

            $references = [];
            if ($validatedRow->recordType === 'PRODUCT') {
                $this->resolveOptionalReference($validatedRow, 'parent_source_key', 'parent_product_code', $sourceRows, $sourceRowsByCode, $productMetadata, $references, 'parent');
                $this->resolveOptionalCatalog($validatedRow, 'product_group_code', $this->groups()->findByCode(...), ProductImportGraphException::ERROR_GROUP_NOT_FOUND);
                $this->resolveOptionalCatalog($validatedRow, 'base_uom_code', $this->uoms()->findByCode(...), ProductImportGraphException::ERROR_UOM_NOT_FOUND);
                $this->assertParentCompatibility($validatedRow, $references['parent'] ?? null, $productMetadata);
            } elseif ($validatedRow->recordType === 'UOM_CONVERSION') {
                $this->resolveRequiredReference($validatedRow, 'product_source_key', 'product_code', $sourceRows, $sourceRowsByCode, $productMetadata, $references, 'product', ProductImportGraphException::ERROR_MISSING_PRODUCT_REFERENCE);
                $this->resolveRequiredCatalog($validatedRow, 'uom_code', $this->uoms()->findByCode(...), ProductImportGraphException::ERROR_UOM_NOT_FOUND);
                $this->assertUomOwnerCompatibility($references['product'], $productMetadata);
            } elseif ($validatedRow->recordType === 'COMBO_ITEM') {
                $this->resolveRequiredReference($validatedRow, 'combo_source_key', 'combo_product_code', $sourceRows, $sourceRowsByCode, $productMetadata, $references, 'owner', ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE);
                $this->resolveRequiredReference($validatedRow, 'component_source_key', 'component_product_code', $sourceRows, $sourceRowsByCode, $productMetadata, $references, 'component', ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE);
                $this->assertComboCompatibility($references['owner'], $references['component'], $productMetadata);
            }

            $planRow = new ProductImportPlanRow(
                $validatedRow,
                $nodeId,
                $sourceKey,
                $productCode,
                $existingId,
                $targetPath,
                $references,
            );
            $planRows[] = $planRow;
            $planByRow[$validatedRow->rowNumber] = $planRow;
        }

        $graph = new ProductImportDependencyGraph();
        $sourceRowNumbers = [];
        foreach ($planRows as $planRow) {
            $graph->addNode($planRow->nodeId);
            $sourceRowNumbers[$planRow->nodeId] = $planRow->getRowNumber();
            foreach ($planRow->references as $reference) {
                if ($reference->isSource() && $reference->sourceRowNumber !== null) {
                    $graph->addDependency($planRow->nodeId, $this->nodeId($reference->sourceRowNumber));
                }
            }
        }

        $uomKeys = [];
        $comboKeys = [];
        foreach ($planRows as $planRow) {
            if ($planRow->getRecordType() === 'UOM_CONVERSION') {
                $owner = $planRow->getReference('product');
                if ($owner === null) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_MISSING_PRODUCT_REFERENCE, ['row' => $planRow->getRowNumber()]);
                }
                $uomCode = $this->requiredText($planRow->validatedRow, 'uom_code', ProductImportGraphException::ERROR_UOM_NOT_FOUND);
                $key = $owner->identity . '|uom:' . $uomCode;
                if (isset($uomKeys[$key])) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_DUPLICATE_UOM_CONVERSION, [
                        'row' => $planRow->getRowNumber(),
                        'identity' => $key,
                    ]);
                }
                $uomKeys[$key] = true;
            }
            if ($planRow->getRecordType() === 'COMBO_ITEM') {
                $owner = $planRow->getReference('owner');
                $component = $planRow->getReference('component');
                if ($owner === null || $component === null) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['row' => $planRow->getRowNumber()]);
                }
                $key = $owner->identity . '|component:' . $component->identity;
                if (isset($comboKeys[$key])) {
                    throw new ProductImportGraphException(ProductImportGraphException::ERROR_DUPLICATE_COMBO_ITEM, [
                        'row' => $planRow->getRowNumber(),
                        'identity' => $key,
                    ]);
                }
                $comboKeys[$key] = true;
            }
        }

        $comboItemsByOwner = [];
        foreach ($planRows as $planRow) {
            if ($planRow->getRecordType() === 'COMBO_ITEM') {
                $owner = $planRow->getReference('owner');
                if ($owner !== null) {
                    $comboItemsByOwner[$owner->identity][] = $planRow;
                }
            }
        }
        foreach ($planRows as $planRow) {
            if ($planRow->getRecordType() !== 'COMBO_ITEM') {
                continue;
            }
            $component = $planRow->getReference('component');
            if ($component === null) {
                continue;
            }
            foreach ($comboItemsByOwner[$component->identity] ?? [] as $nestedItem) {
                $graph->addDependency($planRow->nodeId, $nestedItem->nodeId);
            }
        }

        $processingRows = [];
        foreach ($graph->topologicalOrder($sourceRowNumbers) as $nodeId) {
            $rowNumber = $sourceRowsByNode[$nodeId]->getRowNumber();
            $processingRows[] = $planByRow[$rowNumber];
        }

        return new ProductImportPlan($document->mode, $planRows, $processingRows);
    }

    private function nodeId(int $rowNumber): string
    {
        return 'row:' . $rowNumber;
    }

    private function requiredText(ProductImportValidatedRow $row, string $field, string $error): string
    {
        $value = $this->optionalText($row, $field);
        if ($value === null) {
            throw new ProductImportGraphException($error, ['field' => $field, 'row' => $row->rowNumber]);
        }

        return $value;
    }

    private function optionalText(ProductImportValidatedRow $row, string $field): ?string
    {
        $cell = $row->getCell($field);
        if (! $cell->isValue()) {
            return null;
        }
        if (! is_string($cell->value) || trim($cell->value) === '') {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_REFERENCE_CONFLICT, [
                'field' => $field,
                'row' => $row->rowNumber,
            ]);
        }

        return $cell->value;
    }

    /** @param callable(string):(array<string,mixed>|null) $lookup */
    private function resolveOptionalCatalog(ProductImportValidatedRow $row, string $field, callable $lookup, string $error): void
    {
        $value = $this->optionalText($row, $field);
        if ($value === null || $lookup($value) !== null) {
            return;
        }

        throw new ProductImportGraphException($error, ['field' => $field, 'value' => $value, 'row' => $row->rowNumber]);
    }

    /** @param callable(string):(array<string,mixed>|null) $lookup */
    private function resolveRequiredCatalog(ProductImportValidatedRow $row, string $field, callable $lookup, string $error): void
    {
        $value = $this->requiredText($row, $field, $error);
        if ($lookup($value) === null) {
            throw new ProductImportGraphException($error, ['field' => $field, 'value' => $value, 'row' => $row->rowNumber]);
        }
    }

    /**
     * @param array<string, ProductImportValidatedRow> $sourceRows
     * @param array<string, string> $sourceRowsByCode
     * @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata
     * @param array<string, ProductImportReference> $references
     */
    private function resolveOptionalReference(
        ProductImportValidatedRow $row,
        string $sourceField,
        string $codeField,
        array $sourceRows,
        array $sourceRowsByCode,
        array $productMetadata,
        array &$references,
        string $name,
    ): void {
        $source = $this->optionalText($row, $sourceField);
        $code = $this->optionalText($row, $codeField);
        if ($source === null && $code === null) {
            return;
        }
        $reference = $this->resolveReference($row, $source, $code, $sourceRows, $sourceRowsByCode, $productMetadata, $name);
        $references[$name] = $reference;
        $references[$sourceField] = $reference;
        $references[$codeField] = $reference;
    }

    /**
     * @param array<string, ProductImportValidatedRow> $sourceRows
     * @param array<string, string> $sourceRowsByCode
     * @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata
     * @param array<string, ProductImportReference> $references
     */
    private function resolveRequiredReference(
        ProductImportValidatedRow $row,
        string $sourceField,
        string $codeField,
        array $sourceRows,
        array $sourceRowsByCode,
        array $productMetadata,
        array &$references,
        string $name,
        string $missingError,
    ): void {
        $source = $this->optionalText($row, $sourceField);
        $code = $this->optionalText($row, $codeField);
        if ($source === null && $code === null) {
            throw new ProductImportGraphException($missingError, ['row' => $row->rowNumber]);
        }
        $reference = $this->resolveReference($row, $source, $code, $sourceRows, $sourceRowsByCode, $productMetadata, $name, $missingError);
        $references[$name] = $reference;
        $references[$sourceField] = $reference;
        $references[$codeField] = $reference;
    }

    /**
     * @param array<string, ProductImportValidatedRow> $sourceRows
     * @param array<string, string> $sourceRowsByCode
     * @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata
     */
    private function resolveReference(
        ProductImportValidatedRow $row,
        ?string $source,
        ?string $code,
        array $sourceRows,
        array $sourceRowsByCode,
        array $productMetadata,
        string $name,
        string $missingError = ProductImportGraphException::ERROR_MISSING_PRODUCT_REFERENCE,
    ): ProductImportReference {
        $sourceReference = null;
        if ($source !== null) {
            $sourceRow = $sourceRows[$source] ?? null;
            if ($sourceRow === null) {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_MISSING_SOURCE_REFERENCE, [
                    'field' => $name,
                    'source_key' => $source,
                    'row' => $row->rowNumber,
                ]);
            }
            $metadata = $productMetadata[$source] ?? [];
            $sourceReference = new ProductImportReference(
                ProductImportReference::SOURCE,
                $source,
                'source:' . $source,
                $sourceRow->rowNumber,
                $metadata['existing_id'] ?? null,
                $metadata['existing'] ?? null,
            );
        }

        $codeReference = null;
        if ($code !== null) {
            $sourceKey = $sourceRowsByCode[$code] ?? null;
            if ($sourceKey !== null) {
                $sourceRow = $sourceRows[$sourceKey];
                $metadata = $productMetadata[$sourceKey] ?? [];
                $codeReference = new ProductImportReference(
                    ProductImportReference::SOURCE,
                    $sourceKey,
                    'source:' . $sourceKey,
                    $sourceRow->rowNumber,
                    $metadata['existing_id'] ?? null,
                    $metadata['existing'] ?? null,
                );
            } else {
                $product = $this->products()->findByCode($code);
                if ($product === null) {
                    throw new ProductImportGraphException($missingError, [
                        'field' => $name,
                        'product_code' => $code,
                        'row' => $row->rowNumber,
                    ]);
                }
                $productId = (int) ($product['id'] ?? 0);
                $codeReference = new ProductImportReference(
                    ProductImportReference::PERSISTENT,
                    $code,
                    'product:' . $productId,
                    null,
                    $productId,
                    $product,
                );
            }
        }

        if ($sourceReference !== null && $codeReference !== null && $sourceReference->identity !== $codeReference->identity) {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_REFERENCE_CONFLICT, [
                'field' => $name,
                'row' => $row->rowNumber,
            ]);
        }

        return $sourceReference ?? $codeReference ?? throw new ProductImportGraphException($missingError, ['row' => $row->rowNumber]);
    }

    /** @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata */
    private function assertParentCompatibility(ProductImportValidatedRow $row, ?ProductImportReference $reference, array $productMetadata): void
    {
        if ($reference === null) {
            return;
        }
        $role = $this->roleForReference($reference);
        if ($role !== null && ! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_PARENT], true)) {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_PARENT, ['row' => $row->rowNumber]);
        }
        if ($reference->isSource()) {
            $type = $this->optionalText($productMetadata[$reference->key]['row'], 'product_type');
            if ($type !== null && strtoupper($type) !== 'STANDARD') {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_PARENT, ['row' => $row->rowNumber]);
            }
        }
    }

    /** @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata */
    private function assertUomOwnerCompatibility(ProductImportReference $reference, array $productMetadata): void
    {
        $role = $this->roleForReference($reference);
        if ($role !== null && ! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_CHILD], true)) {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'uom_owner_role']);
        }
        if ($reference->isSource()) {
            $type = $this->optionalText($productMetadata[$reference->key]['row'], 'product_type');
            if ($type !== null && strtoupper($type) !== 'STANDARD') {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'uom_owner_type']);
            }
        }
    }

    /** @param array<string, array{row:ProductImportValidatedRow,product_code:?string,existing:array<string,mixed>|null,existing_id:?int}> $productMetadata */
    private function assertComboCompatibility(ProductImportReference $owner, ProductImportReference $component, array $productMetadata): void
    {
        $ownerRole = $this->roleForReference($owner);
        if ($ownerRole !== null && $ownerRole !== ProductRoleService::ROLE_COMBO) {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'owner_role']);
        }
        $componentRole = $this->roleForReference($component);
        if ($componentRole === ProductRoleService::ROLE_PARENT) {
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'component_role']);
        }
        if ($owner->isSource()) {
            $type = $this->optionalText($productMetadata[$owner->key]['row'], 'product_type');
            if ($type !== null && strtoupper($type) !== 'COMBO') {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'owner_type']);
            }
        }
        if ($component->isSource()) {
            $type = $this->optionalText($productMetadata[$component->key]['row'], 'product_type');
            if ($type !== null && strtoupper($type) === 'PARENT') {
                throw new ProductImportGraphException(ProductImportGraphException::ERROR_INVALID_COMBO_REFERENCE, ['reason' => 'component_type']);
            }
        }
    }

    private function roleForReference(ProductImportReference $reference): ?string
    {
        if ($reference->product === null) {
            return null;
        }

        return $this->roles()->getRole($reference->product);
    }

    private function products(): ProductRepository
    {
        return $this->products ?? new ProductRepository($this->db);
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(repository: $this->products(), db: $this->db);
    }

    private function groups(): ProductGroupService
    {
        return $this->groups ?? new ProductGroupService(db: $this->db);
    }

    private function uoms(): ProductUomMasterService
    {
        return $this->uoms ?? new ProductUomMasterService(db: $this->db);
    }
}

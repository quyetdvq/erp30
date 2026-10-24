<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** One source row in the read-only Product import plan. */
final readonly class ProductImportPlanRow
{
    /**
     * @param array<string, ProductImportReference> $references
     */
    public function __construct(
        public ProductImportValidatedRow $validatedRow,
        public string $nodeId,
        public ?string $sourceKey,
        public ?string $productCode,
        public ?int $existingProductId,
        public string $targetPath,
        public array $references = [],
    ) {
    }

    public function getRowNumber(): int
    {
        return $this->validatedRow->rowNumber;
    }

    public function getRecordType(): string
    {
        return $this->validatedRow->recordType;
    }

    public function getSourceKey(): ?string
    {
        return $this->sourceKey;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function getExistingProductId(): ?int
    {
        return $this->existingProductId;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function getReference(string $field): ?ProductImportReference
    {
        return $this->references[$field] ?? null;
    }
}

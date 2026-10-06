<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** Validated structural row, retaining the original source row number. */
final readonly class ProductImportValidatedRow
{
    /** @param array<string, ProductImportCell> $cells */
    public function __construct(
        public int $rowNumber,
        public string $recordType,
        public array $cells,
    ) {
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function getRecordType(): string
    {
        return $this->recordType;
    }

    /** @return array<string, ProductImportCell> */
    public function getCells(): array
    {
        return $this->cells;
    }

    public function getCell(string $header): ProductImportCell
    {
        return $this->cells[$header] ?? ProductImportCell::fromRaw(null);
    }
}

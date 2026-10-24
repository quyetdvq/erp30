<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** Complete read-only Product import plan, retaining source and processing order. */
final readonly class ProductImportPlan
{
    /**
     * @param list<ProductImportPlanRow> $rows
     * @param list<ProductImportPlanRow> $processingRows
     */
    public function __construct(
        public string $mode,
        public array $rows,
        public array $processingRows,
    ) {
    }

    /** @return list<ProductImportPlanRow> */
    public function getRows(): array
    {
        return $this->rows;
    }

    /** @return list<ProductImportPlanRow> */
    public function getProcessingRows(): array
    {
        return $this->processingRows;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}

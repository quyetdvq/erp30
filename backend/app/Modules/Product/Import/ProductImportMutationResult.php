<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** In-memory result of executing a Product import plan. */
final readonly class ProductImportMutationResult
{
    /**
     * @param list<ProductImportRowResult> $rows
     * @param array<string, array{product_id:int, product_code:string}> $sourceMap
     */
    public function __construct(
        public array $rows,
        public array $sourceMap,
    ) {
    }

    /** @return list<ProductImportRowResult> */
    public function getRows(): array
    {
        return $this->rows;
    }

    /** @return array<string, array{product_id:int, product_code:string}> */
    public function getSourceMap(): array
    {
        return $this->sourceMap;
    }

    /** @return list<ProductImportRowResult> */
    public function getRowsInSourceOrder(): array
    {
        $rows = $this->rows;
        usort($rows, static fn (ProductImportRowResult $left, ProductImportRowResult $right): int => $left->rowNumber <=> $right->rowNumber);

        return $rows;
    }
}

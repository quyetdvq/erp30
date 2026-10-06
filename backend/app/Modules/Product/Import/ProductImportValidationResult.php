<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** Format-neutral result of Product import structural validation. */
final readonly class ProductImportValidationResult
{
    /**
     * @param list<string> $headers
     * @param list<ProductImportValidatedRow> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public string $mode,
    ) {
    }

    /** @return list<string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return list<string> */
    public function getNormalizedHeaders(): array
    {
        return $this->headers;
    }

    /** @return list<ProductImportValidatedRow> */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}

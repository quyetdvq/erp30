<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** Generic parsed CSV structure; no domain or template semantics are applied. */
final readonly class CsvImportDocument
{
    /**
     * @param list<mixed> $header
     * @param list<CsvImportRow> $rows
     */
    public function __construct(
        public array $header,
        public array $rows,
    ) {
    }

    /** @return list<mixed> */
    public function getHeader(): array
    {
        return $this->header;
    }

    /** @return list<CsvImportRow> */
    public function getRows(): array
    {
        return $this->rows;
    }
}

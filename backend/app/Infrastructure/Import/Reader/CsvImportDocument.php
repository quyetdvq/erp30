<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** Generic parsed CSV structure; no domain or template semantics are applied. */
final readonly class CsvImportDocument
{
    /**
     * @param list<string> $header
     * @param list<CsvImportRow> $rows
     */
    public function __construct(
        public array $header,
        public array $rows,
    ) {
    }

    /** @return list<string> */
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

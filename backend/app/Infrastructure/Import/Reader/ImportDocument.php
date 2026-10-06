<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** Generic parsed import document shared by CSV and XLSX readers. */
final readonly class ImportDocument
{
    /**
     * @param list<mixed> $header
     * @param list<ImportRow> $rows
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

    /** @return list<ImportRow> */
    public function getRows(): array
    {
        return $this->rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** One generic CSV data record with its logical source row number. */
final readonly class CsvImportRow
{
    /** @param list<string> $cells */
    public function __construct(
        public int $rowNumber,
        public array $cells,
    ) {
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /** @return list<string> */
    public function getCells(): array
    {
        return $this->cells;
    }
}

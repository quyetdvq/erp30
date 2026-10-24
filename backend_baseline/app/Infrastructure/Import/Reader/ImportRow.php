<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** One generic import record with its original source row number. */
final readonly class ImportRow
{
    /** @param list<mixed> $cells */
    public function __construct(
        public int $rowNumber,
        public array $cells,
    ) {
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /** @return list<mixed> */
    public function getCells(): array
    {
        return $this->cells;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

use RuntimeException;

/** Deterministic structural failure raised by the generic CSV reader. */
final class CsvParseException extends RuntimeException
{
    public const ERROR_EMPTY_INPUT = 'CSV_EMPTY_INPUT';
    public const ERROR_INVALID_UTF8 = 'CSV_INVALID_UTF8';
    public const ERROR_MALFORMED = 'CSV_MALFORMED';

    public function __construct(
        private readonly string $errorCode,
        private readonly int $offset,
        private readonly int $rowNumber,
        string $detail,
    ) {
        parent::__construct(sprintf('%s at byte %d (logical row %d): %s', $errorCode, $offset, $rowNumber, $detail));
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
}

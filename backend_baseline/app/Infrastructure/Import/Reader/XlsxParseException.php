<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

use RuntimeException;

/** Deterministic typed failure raised by the generic XLSX reader. */
final class XlsxParseException extends RuntimeException
{
    public const ERROR_UNREADABLE = 'XLSX_UNREADABLE';
    public const ERROR_EMPTY_WORKBOOK = 'XLSX_EMPTY_WORKBOOK';
    public const ERROR_FORMULA_CELL = 'XLSX_FORMULA_CELL';

    public function __construct(
        private readonly string $errorCode,
        private readonly ?string $worksheet,
        private readonly ?int $rowNumber,
        private readonly ?string $coordinate,
        string $detail,
    ) {
        $location = $worksheet === null
            ? ''
            : sprintf(' in worksheet %s%s', $worksheet, $coordinate === null ? '' : "!{$coordinate}");
        parent::__construct(sprintf('%s%s: %s', $errorCode, $location, $detail));
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getWorksheet(): ?string
    {
        return $this->worksheet;
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function getCoordinate(): ?string
    {
        return $this->coordinate;
    }
}

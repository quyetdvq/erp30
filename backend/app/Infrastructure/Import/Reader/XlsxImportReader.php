<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Strict generic XLSX reader; Product/template semantics belong elsewhere. */
final class XlsxImportReader
{
    /**
     * Read an XLSX path or binary document into the common generic structure.
     *
     * @param string $input Existing path or XLSX binary content.
     */
    public function read(string $input): CsvImportDocument
    {
        $temporaryPath = null;
        $path = $input;
        if (! is_file($input)) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'dinkerp-xlsx-');
            if ($temporaryPath === false || file_put_contents($temporaryPath, $input) === false) {
                if ($temporaryPath !== null) {
                    @unlink($temporaryPath);
                }
                throw new XlsxParseException(XlsxParseException::ERROR_UNREADABLE, null, null, null, 'unable to stage XLSX input');
            }
            $path = $temporaryPath;
        }

        try {
            return $this->readPath($path);
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }
    }

    public function parse(string $input): CsvImportDocument
    {
        return $this->read($input);
    }

    private function readPath(string $path): CsvImportDocument
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $reader->setReadEmptyCells(true);
            if (! $reader->canRead($path)) {
                throw new XlsxParseException(XlsxParseException::ERROR_UNREADABLE, null, null, null, 'input is not a readable XLSX document');
            }
            $spreadsheet = $reader->load($path);
        } catch (XlsxParseException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new XlsxParseException(XlsxParseException::ERROR_UNREADABLE, null, null, null, 'unable to read XLSX document');
        }

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                if ($this->hasData($worksheet)) {
                    return $this->readWorksheet($worksheet);
                }
            }
        } catch (XlsxParseException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new XlsxParseException(XlsxParseException::ERROR_UNREADABLE, null, null, null, 'unable to inspect worksheet data');
        }

        throw new XlsxParseException(XlsxParseException::ERROR_EMPTY_WORKBOOK, null, null, null, 'workbook has no data worksheet');
    }

    private function hasData(Worksheet $worksheet): bool
    {
        foreach ($worksheet->getCoordinates() as $coordinate) {
            $value = $worksheet->getCell($coordinate)->getValue();
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function readWorksheet(Worksheet $worksheet): CsvImportDocument
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $records = [];

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $cells = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $coordinate = Coordinate::stringFromColumnIndex($column) . $rowNumber;
                $cell = $worksheet->getCell($coordinate);
                if ($cell->isFormula()) {
                    throw new XlsxParseException(
                        XlsxParseException::ERROR_FORMULA_CELL,
                        $worksheet->getTitle(),
                        $rowNumber,
                        $cell->getCoordinate(),
                        'formula cells are not accepted',
                    );
                }
                $cells[] = $cell->getValue();
            }
            $records[] = $cells;
        }

        $header = array_shift($records) ?? [];
        $rows = [];
        foreach ($records as $index => $cells) {
            $rows[] = new CsvImportRow($index + 2, $cells);
        }

        return new CsvImportDocument($header, $rows);
    }
}

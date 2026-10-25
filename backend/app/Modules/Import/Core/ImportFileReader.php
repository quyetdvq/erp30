<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use App\Exceptions\DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Bounded CSV/XLSX decoder; never executes formulas, external links or macros. */
final class ImportFileReader
{
    /** @param array<string,mixed> $template
     *  @return array{format:string,header:list<string>,rows:list<array{number:int,cells:array<string,string|null>}>}
     */
    public function read(string $path, string $fileName, array $template): array
    {
        if (!is_file($path) || !is_readable($path) || is_link($path)) {
            throw new DomainException('IMPORT_FILE_INVALID');
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > $template['max_bytes']) {
            throw new DomainException('IMPORT_M8_FILE_LIMIT_EXCEEDED');
        }
        if (strlen($fileName) > 255 || preg_match('/[\x00-\x1f\/\\\\]/', $fileName)) {
            throw new DomainException('IMPORT_FILE_INVALID');
        }
        $format = match (strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION))) {
            'csv' => 'CSV', 'xlsx' => 'XLSX', default => throw new DomainException('IMPORT_FILE_INVALID'),
        };
        $document = $format === 'CSV' ? $this->csv($path, $template) : $this->xlsx($path, $template);
        ImportTemplateCatalog::assertHeader($document['header'], $template['columns']);
        if ($document['rows'] === []) throw new DomainException('IMPORT_FILE_INVALID');
        return ['format'=>$format, ...$document];
    }

    /** @param array<string,mixed> $template @return array{header:list<string>,rows:list<array{number:int,cells:array<string,string|null>}>} */
    private function csv(string $path, array $template): array
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || preg_match('//u', $contents) !== 1) {
            throw new DomainException('IMPORT_FILE_INVALID');
        }
        $handle = fopen('php://temp/maxmemory:2097152', 'w+');
        if ($handle === false) throw new DomainException('IMPORT_FILE_INVALID');
        try {
            fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
            rewind($handle);
            $header = null;
            $rows = [];
            $line = 0;
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($header === null) {
                    $header = array_map(static fn ($v): string => strtolower(trim((string)$v)), $cells);
                    ImportTemplateCatalog::assertHeader($header, $template['columns']);
                    continue;
                }
                $this->append($rows, $header, $cells, $line, $template['max_rows']);
            }
            return ['header'=>$header ?? [],'rows'=>$rows];
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string,mixed> $template @return array{header:list<string>,rows:list<array{number:int,cells:array<string,string|null>}>} */
    private function xlsx(string $path, array $template): array
    {
        if (!class_exists(IOFactory::class)) throw new DomainException('IMPORT_FILE_INVALID');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new DomainException('IMPORT_FILE_INVALID');
        try {
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (!is_array($entry) || ($entry['size'] ?? 0) > 16777216) {
                    throw new DomainException('IMPORT_M8_FILE_LIMIT_EXCEEDED');
                }
                $expanded += (int) $entry['size'];
                if ($expanded > 33554432) throw new DomainException('IMPORT_M8_FILE_LIMIT_EXCEEDED');
            }
        } finally { $zip->close(); }
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new DomainException('IMPORT_FILE_INVALID', [], $e);
        }
        try {
            $sheet = $book->getSheet(0);
            if ($sheet->getHighestDataRow() > $template['max_rows'] + 1
                || Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > count($template['columns'])) {
                throw new DomainException('IMPORT_M8_ROW_LIMIT_EXCEEDED');
            }
            $width = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            $header = [];
            for ($c = 1; $c <= $width; $c++) {
                $header[] = strtolower(trim((string)$sheet->getCell([$c, 1])->getValue()));
            }
            ImportTemplateCatalog::assertHeader($header, $template['columns']);
            $rows = [];
            for ($r = 2; $r <= $sheet->getHighestDataRow(); $r++) {
                $cells=[];
                for ($c=1; $c<=$width; $c++) {
                    $cell=$sheet->getCell([$c,$r]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw new DomainException('IMPORT_FILE_INVALID', ['row'=>$r]);
                    }
                    $value=$cell->getValue();
                    if (is_object($value) || is_bool($value) || is_float($value)) {
                        throw new DomainException('IMPORT_FILE_INVALID', ['row'=>$r]);
                    }
                    $cells[]=$value === null ? '' : (string)$value;
                }
                $this->append($rows, $header, $cells, $r, $template['max_rows']);
            }
            return ['header'=>$header,'rows'=>$rows];
        } finally { $book->disconnectWorksheets(); }
    }

    /** @param list<array{number:int,cells:array<string,string|null>}> $rows
     *  @param list<string> $header @param list<mixed> $cells
     */
    private function append(array &$rows, array $header, array $cells, int $number, int $maximum): void
    {
        if (count($cells) > count($header)) throw new DomainException('IMPORT_INVALID_TEMPLATE');
        if (count(array_filter($cells, static fn ($v): bool => trim((string)$v) !== '')) === 0) return;
        if (count($rows) >= $maximum) throw new DomainException('IMPORT_M8_ROW_LIMIT_EXCEEDED');
        $record=[];
        foreach ($header as $index => $name) {
            $value=trim((string)($cells[$index] ?? ''));
            if (preg_match('//u', $value) !== 1 || preg_match('/\A[\s\x{FEFF}]*[=+@]/u', $value)
                || (preg_match('/\A[\s\x{FEFF}]*-/u', $value) && !preg_match('/\A-[0-9]+(?:\.[0-9]+)?\z/D',$value))) {
                throw new DomainException('IMPORT_FILE_INVALID',['row'=>$number]);
            }
            $record[$name] = $value === '__NULL__' ? null : $value;
        }
        $rows[] = ['number'=>$number, 'cells'=>$record];
    }
}

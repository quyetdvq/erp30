<?php

declare(strict_types=1);

use App\Infrastructure\Import\Reader\XlsxImportReader;
use App\Infrastructure\Import\Reader\XlsxParseException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/** @internal */
final class XlsxImportReaderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->temporaryFiles = [];
        parent::tearDown();
    }

    public function testFirstWorksheetContainingDataWinsAndEmptyFirstSheetIsSkipped(): void
    {
        $path = $this->workbook([
            ['First empty', []],
            ['Second data', [['code', 'name'], ['A', 'Alpha']]],
            ['Third data', [['code', 'name'], ['B', 'Beta']]],
        ]);

        $document = (new XlsxImportReader())->read($path);

        $this->assertSame(['code', 'name'], $document->header);
        $this->assertSame(['A', 'Alpha'], $document->rows[0]->cells);
        $this->assertCount(1, $document->rows);
    }

    public function testHeaderDataUnicodeAndOriginalWorksheetRowsArePreserved(): void
    {
        $path = $this->workbook([
            ['Vietnamese', [
                ['code', 'name', 'note'],
                [null, null, null],
                ['VN-1', 'Điện thoại', 'Hàng hóa'],
            ]],
        ]);

        $document = (new XlsxImportReader())->read($path);

        $this->assertSame(['code', 'name', 'note'], $document->header);
        $this->assertSame(2, $document->rows[0]->rowNumber);
        $this->assertSame([null, null, null], $document->rows[0]->cells);
        $this->assertSame(3, $document->rows[1]->getRowNumber());
        $this->assertSame('Điện thoại', $document->rows[1]->cells[1]);
    }

    public function testNumbersBooleansAndEmptyCellsRemainLiteralValues(): void
    {
        $path = $this->workbook([
            ['Literals', [
                ['text', 'integer', 'decimal', 'flag', 'empty'],
                ['raw', 42, 12.5, true, null],
            ]],
        ]);

        $row = (new XlsxImportReader())->read($path)->rows[0];

        $this->assertSame('raw', $row->cells[0]);
        $this->assertSame(42, $row->cells[1]);
        $this->assertSame(12.5, $row->cells[2]);
        $this->assertTrue($row->cells[3]);
        $this->assertNull($row->cells[4]);
    }

    public function testFormulaCellIsRejectedEvenWhenAFormulaCachedResultExists(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Formula');
        $sheet->fromArray([['header'], ['value']], null, 'A1');
        $sheet->getCell('A2')->setValue('=1+1')->setCalculatedValue(2);
        $path = $this->save($spreadsheet);

        try {
            (new XlsxImportReader())->read($path);
            $this->fail('Expected formula rejection.');
        } catch (XlsxParseException $exception) {
            $this->assertSame(XlsxParseException::ERROR_FORMULA_CELL, $exception->getErrorCode());
            $this->assertSame('Formula', $exception->getWorksheet());
            $this->assertSame(2, $exception->getRowNumber());
            $this->assertSame('A2', $exception->getCoordinate());
        }
    }

    public function testMalformedAndNonXlsxInputFailWithTypedParserError(): void
    {
        try {
            (new XlsxImportReader())->read('this is not an xlsx file');
            $this->fail('Expected unreadable XLSX failure.');
        } catch (XlsxParseException $exception) {
            $this->assertSame(XlsxParseException::ERROR_UNREADABLE, $exception->getErrorCode());
        }

        $path = $this->tempFile('not an xlsx archive');
        try {
            (new XlsxImportReader())->read($path);
            $this->fail('Expected malformed XLSX failure.');
        } catch (XlsxParseException $exception) {
            $this->assertSame(XlsxParseException::ERROR_UNREADABLE, $exception->getErrorCode());
        }
    }

    public function testReaderHasNoProductOrImp004Semantics(): void
    {
        $path = dirname(__DIR__, 4) . '/app/Infrastructure/Import/Reader/XlsxImportReader.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString('App\\Modules\\Product', $contents);
        $this->assertStringNotContainsString('product_code', $contents);
        $this->assertStringNotContainsString('source_key', $contents);
        $this->assertStringNotContainsString('UOM_CONVERSION', $contents);
        $this->assertStringNotContainsString('COMBO_ITEM', $contents);
        $this->assertStringNotContainsString('CREATE_ONLY', $contents);
        $this->assertStringNotContainsString('__NULL__', $contents);
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
    }

    /** @param list<array{0:string,1:list<list<mixed>>}> $sheets */
    private function workbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        foreach ($sheets as $index => [$title, $rows]) {
            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($title);
            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A1');
            }
        }

        return $this->save($spreadsheet);
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $path = $this->tempFile();
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function tempFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'p1-imp003-');
        $this->assertNotFalse($path);
        if ($contents !== '') {
            file_put_contents($path, $contents);
        }
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

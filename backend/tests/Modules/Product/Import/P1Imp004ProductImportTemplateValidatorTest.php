<?php

declare(strict_types=1);

use App\Infrastructure\Import\Reader\CsvImportReader;
use App\Infrastructure\Import\Reader\ImportDocument;
use App\Infrastructure\Import\Reader\ImportRow;
use App\Infrastructure\Import\Reader\XlsxImportReader;
use App\Modules\Product\Import\ProductImportCell;
use App\Modules\Product\Import\ProductImportTemplateValidator;
use App\Modules\Product\Import\ProductImportValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/** @internal */
final class P1Imp004ProductImportTemplateValidatorTest extends TestCase
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

    public function testCsvHeadersAreTrimmedAndLowercasedAndRowsKeepOriginalNumbers(): void
    {
        $document = (new CsvImportReader())->read(" RECORD_TYPE , Product_Code , product_name \nPRODUCT,P-1,Điện thoại\n");
        $result = $this->validator()->validate($document, ProductImportTemplateValidator::MODE_CREATE_ONLY);

        $this->assertSame(['record_type', 'product_code', 'product_name'], $result->headers);
        $this->assertSame(2, $result->rows[0]->rowNumber);
        $this->assertSame('PRODUCT', $result->rows[0]->recordType);
        $this->assertSame('Điện thoại', $result->rows[0]->cells['product_name']->value);
    }

    public function testXlsxHeadersAndLogicalInputHaveEquivalentValidationOutput(): void
    {
        $csv = (new CsvImportReader())->read("record_type,product_code,product_name\nPRODUCT,P-1,Điện thoại\n");
        $xlsx = (new XlsxImportReader())->read($this->workbook([
            ['record_type', 'product_code', 'product_name'],
            ['PRODUCT', 'P-1', 'Điện thoại'],
        ]));

        $csvResult = $this->validator()->validate($csv, ProductImportTemplateValidator::MODE_UPSERT);
        $xlsxResult = $this->validator()->validate($xlsx, ProductImportTemplateValidator::MODE_UPSERT);

        $this->assertSame($csvResult->headers, $xlsxResult->headers);
        $this->assertSame($csvResult->rows[0]->recordType, $xlsxResult->rows[0]->recordType);
        $this->assertSame($csvResult->rows[0]->cells['product_name']->state, $xlsxResult->rows[0]->cells['product_name']->state);
        $this->assertSame(2, $xlsxResult->rows[0]->rowNumber);
    }

    public function testUnknownEmptyAndDuplicateHeadersAreRejectedDeterministically(): void
    {
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument(['record_type', 'not_allowed'], []), 'CREATE_ONLY'),
            ProductImportTemplateValidator::ERROR_INVALID_TEMPLATE,
        );
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument([], []), 'CREATE_ONLY'),
            ProductImportTemplateValidator::ERROR_INVALID_TEMPLATE,
        );
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument(['record_type', ''], []), 'CREATE_ONLY'),
            ProductImportTemplateValidator::ERROR_INVALID_TEMPLATE,
        );
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument(['record_type', ' PRODUCT_CODE ', 'product_code'], []), 'CREATE_ONLY'),
            ProductImportTemplateValidator::ERROR_INVALID_TEMPLATE,
        );
    }

    public function testAllRecordTypesAndModesAreAccepted(): void
    {
        foreach (['PRODUCT', 'UOM_CONVERSION', 'COMBO_ITEM'] as $recordType) {
            $result = $this->validator()->validate(
                new ImportDocument(['record_type'], [new ImportRow(7, [$recordType])]),
                ProductImportTemplateValidator::MODE_CREATE_ONLY,
            );
            $this->assertSame($recordType, $result->rows[0]->recordType);
        }
        foreach (['CREATE_ONLY', 'UPDATE_ONLY', 'UPSERT'] as $mode) {
            $this->assertSame($mode, $this->validator()->validate(
                new ImportDocument(['record_type'], []),
                $mode,
            )->mode);
        }
    }

    public function testInvalidRecordTypeAndModeAreRejected(): void
    {
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument(['record_type'], [new ImportRow(2, ['UNKNOWN'])]), 'CREATE_ONLY'),
            ProductImportTemplateValidator::ERROR_INVALID_RECORD_TYPE,
        );
        $this->assertCode(
            fn () => $this->validator()->validate(new ImportDocument(['record_type'], []), 'MERGE'),
            ProductImportTemplateValidator::ERROR_INVALID_MODE,
        );
    }

    public function testValueBlankAndExplicitNullRemainDistinctWithCreateAndUpdateResolution(): void
    {
        $result = $this->validator()->validate(
            new ImportDocument(['record_type', 'description', 'status'], [new ImportRow(2, ['PRODUCT', '', '__NULL__'])]),
            ProductImportTemplateValidator::MODE_UPDATE_ONLY,
        );
        $row = $result->rows[0];

        $this->assertSame(ProductImportCell::VALUE, $row->cells['record_type']->state);
        $this->assertSame(ProductImportCell::BLANK, $row->cells['description']->state);
        $this->assertSame(ProductImportCell::EXPLICIT_NULL, $row->cells['status']->state);
        $this->assertSame(ProductImportCell::UPDATE_NO_CHANGE, $row->cells['description']->resolveForUpdate());
        $this->assertNull($row->cells['description']->resolveForCreate());
        $this->assertNull($row->cells['status']->resolveForUpdate());
    }

    public function testNonNullableExplicitNullIsRejectedWhenFieldPolicyIsSupplied(): void
    {
        $this->assertCode(
            fn () => $this->validator()->validate(
                new ImportDocument(['record_type', 'product_code'], [new ImportRow(2, ['PRODUCT', '__NULL__'])]),
                'CREATE_ONLY',
                ['product_code' => ['nullable' => false]],
            ),
            ProductImportTemplateValidator::ERROR_INVALID_NULL,
        );
    }

    public function testInfrastructureReaderHasNoProductDependencyAndNoImp005Semantics(): void
    {
        foreach (['CsvImportReader.php', 'XlsxImportReader.php', 'ImportDocument.php', 'ImportRow.php'] as $file) {
            $path = dirname(__DIR__, 4) . '/app/Infrastructure/Import/Reader/' . $file;
            $contents = (string) file_get_contents($path);
            $this->assertStringNotContainsString('App\\Modules\\Product', $contents);
            $this->assertStringNotContainsString('source_key', $contents);
            $this->assertStringNotContainsString('UOM_CONVERSION', $contents);
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
    }

    private function validator(): ProductImportTemplateValidator
    {
        return new ProductImportTemplateValidator();
    }

    private function assertCode(Closure $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product import validation failure.');
        } catch (ProductImportValidationException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }

    /** @param list<list<mixed>> $rows */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'p1-imp004-');
        $this->assertNotFalse($path);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

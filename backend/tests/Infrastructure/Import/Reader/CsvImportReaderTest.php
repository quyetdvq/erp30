<?php

declare(strict_types=1);

use App\Infrastructure\Import\Reader\CsvImportReader;
use App\Infrastructure\Import\Reader\CsvParseException;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CsvImportReaderTest extends TestCase
{
    public function testSimpleRowsAndLogicalRowNumbers(): void
    {
        $document = (new CsvImportReader())->read("code,name\nA,Alpha\nB,Beta\n");

        $this->assertSame(['code', 'name'], $document->header);
        $this->assertSame(2, $document->rows[0]->rowNumber);
        $this->assertSame(['A', 'Alpha'], $document->rows[0]->cells);
        $this->assertSame(3, $document->rows[1]->getRowNumber());
        $this->assertSame(['B', 'Beta'], $document->rows[1]->getCells());
    }

    public function testUtf8VietnameseTextAndBomArePreservedCorrectly(): void
    {
        $document = (new CsvImportReader())->read("\xEF\xBB\xBFcode,name\nVN-1,Điện thoại\n");

        $this->assertSame(['code', 'name'], $document->getHeader());
        $this->assertSame('Điện thoại', $document->rows[0]->cells[1]);
    }

    public function testCommaDelimiterQuotedCommaAndEscapedQuote(): void
    {
        $document = (new CsvImportReader())->read("a,b,c\n1,\"hello, world\",\"say \"\"hi\"\"\"\n");

        $this->assertSame(['1', 'hello, world', 'say "hi"'], $document->rows[0]->cells);
        $this->assertSame(',', CsvImportReader::DELIMITER);
        $this->assertSame('"', CsvImportReader::QUOTE);
    }

    public function testEmptyCellsAndCrLfArePreserved(): void
    {
        $document = (new CsvImportReader())->read("a,b,c\r\n1,,3\r\n");

        $this->assertSame(['1', '', '3'], $document->rows[0]->cells);
        $this->assertSame(2, $document->rows[0]->rowNumber);
    }

    public function testLfAndQuotedMultilineValueAreSupported(): void
    {
        $document = (new CsvImportReader())->read("a,b\n1,\"line one\nline two\"\n2,end\n");

        $this->assertSame("line one\nline two", $document->rows[0]->cells[1]);
        $this->assertSame(2, $document->rows[0]->rowNumber);
        $this->assertSame(3, $document->rows[1]->rowNumber);
    }

    public function testMalformedQuotesFailWithStableCodeAndLocation(): void
    {
        try {
            (new CsvImportReader())->read("a,b\n1,\"unterminated\n");
            $this->fail('Expected malformed CSV failure.');
        } catch (CsvParseException $exception) {
            $this->assertSame(CsvParseException::ERROR_MALFORMED, $exception->getErrorCode());
            $this->assertSame(2, $exception->getRowNumber());
            $this->assertSame(20, $exception->getOffset());
        }

        $this->expectException(CsvParseException::class);
        (new CsvImportReader())->read("a,b\n1,\"ok\" trailing\n");
    }

    public function testInvalidUtf8AndEmptyInputAreRejected(): void
    {
        try {
            (new CsvImportReader())->read("a,\xC3\x28\n");
            $this->fail('Expected invalid UTF-8 failure.');
        } catch (CsvParseException $exception) {
            $this->assertSame(CsvParseException::ERROR_INVALID_UTF8, $exception->getErrorCode());
            $this->assertSame(0, $exception->getOffset());
        }

        try {
            (new CsvImportReader())->read('');
            $this->fail('Expected empty-input failure.');
        } catch (CsvParseException $exception) {
            $this->assertSame(CsvParseException::ERROR_EMPTY_INPUT, $exception->getErrorCode());
        }
    }

    public function testReaderHasNoProductOrXlsxSemantics(): void
    {
        $path = dirname(__DIR__, 4) . '/app/Infrastructure/Import/Reader/CsvImportReader.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString('App\\Modules\\Product', $contents);
        $this->assertStringNotContainsString('product_code', $contents);
        $this->assertStringNotContainsString('source_key', $contents);
        $this->assertStringNotContainsString('PhpSpreadsheet', $contents);
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
    }
}

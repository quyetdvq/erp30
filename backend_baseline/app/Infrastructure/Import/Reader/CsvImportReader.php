<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** Strict generic CSV reader using comma delimiters and double-quote quoting. */
final class CsvImportReader
{
    public const DELIMITER = ',';
    public const QUOTE = '"';

    public function read(string $input): ImportDocument
    {
        if (preg_match('//u', $input) !== 1) {
            throw new CsvParseException(CsvParseException::ERROR_INVALID_UTF8, 0, 1, 'input must be valid UTF-8');
        }

        if (str_starts_with($input, "\xEF\xBB\xBF")) {
            $input = substr($input, 3);
        }
        if ($input === '') {
            throw new CsvParseException(CsvParseException::ERROR_EMPTY_INPUT, 0, 1, 'CSV has no logical records');
        }

        $records = $this->parseRecords($input);
        if ($records === []) {
            throw new CsvParseException(CsvParseException::ERROR_EMPTY_INPUT, 0, 1, 'CSV has no logical records');
        }

        $header = $records[0];
        $rows = [];
        foreach (array_slice($records, 1) as $index => $cells) {
            $rows[] = new ImportRow($index + 2, $cells);
        }

        return new ImportDocument($header, $rows);
    }

    public function parse(string $input): ImportDocument
    {
        return $this->read($input);
    }

    /** @return list<list<string>> */
    private function parseRecords(string $input): array
    {
        $records = [];
        $record = [];
        $cell = '';
        $inQuotes = false;
        $afterClosingQuote = false;
        $fieldStarted = false;
        $length = strlen($input);
        $offset = 0;
        $logicalRow = 1;

        while ($offset < $length) {
            $character = $input[$offset];

            if ($inQuotes) {
                if ($character === self::QUOTE) {
                    if ($offset + 1 < $length && $input[$offset + 1] === self::QUOTE) {
                        $cell .= self::QUOTE;
                        $offset += 2;
                        continue;
                    }
                    $inQuotes = false;
                    $afterClosingQuote = true;
                    $offset++;
                    continue;
                }
                if ($character === "\r") {
                    if ($offset + 1 < $length && $input[$offset + 1] === "\n") {
                        $cell .= "\r\n";
                        $offset += 2;
                    } else {
                        $cell .= "\r";
                        $offset++;
                    }
                    continue;
                }
                $cell .= $character;
                $offset++;
                continue;
            }

            if ($afterClosingQuote) {
                if ($character === self::DELIMITER) {
                    $record[] = $cell;
                    $cell = '';
                    $fieldStarted = false;
                    $afterClosingQuote = false;
                    $offset++;
                    continue;
                }
                if ($character === "\n") {
                    $record[] = $cell;
                    $records[] = $record;
                    $record = [];
                    $cell = '';
                    $fieldStarted = false;
                    $afterClosingQuote = false;
                    $logicalRow++;
                    $offset++;
                    continue;
                }
                if ($character === "\r" && $offset + 1 < $length && $input[$offset + 1] === "\n") {
                    $record[] = $cell;
                    $records[] = $record;
                    $record = [];
                    $cell = '';
                    $fieldStarted = false;
                    $afterClosingQuote = false;
                    $logicalRow++;
                    $offset += 2;
                    continue;
                }
                throw $this->malformed($offset, $logicalRow, 'unexpected character after closing quote');
            }

            if ($character === self::DELIMITER) {
                $record[] = $cell;
                $cell = '';
                $fieldStarted = false;
                $offset++;
                continue;
            }
            if ($character === self::QUOTE) {
                if ($fieldStarted || $cell !== '') {
                    throw $this->malformed($offset, $logicalRow, 'quote must begin a field');
                }
                $inQuotes = true;
                $fieldStarted = true;
                $offset++;
                continue;
            }
            if ($character === "\n") {
                $record[] = $cell;
                $records[] = $record;
                $record = [];
                $cell = '';
                $fieldStarted = false;
                $logicalRow++;
                $offset++;
                continue;
            }
            if ($character === "\r") {
                if ($offset + 1 >= $length || $input[$offset + 1] !== "\n") {
                    throw $this->malformed($offset, $logicalRow, 'bare carriage return is not a record separator');
                }
                $record[] = $cell;
                $records[] = $record;
                $record = [];
                $cell = '';
                $fieldStarted = false;
                $logicalRow++;
                $offset += 2;
                continue;
            }

            $cell .= $character;
            $fieldStarted = true;
            $offset++;
        }

        if ($inQuotes) {
            throw $this->malformed($length, $logicalRow, 'unterminated quoted field');
        }
        if ($afterClosingQuote || $fieldStarted || $cell !== '' || $record !== []) {
            $record[] = $cell;
            $records[] = $record;
        }

        return $records;
    }

    private function malformed(int $offset, int $rowNumber, string $detail): CsvParseException
    {
        return new CsvParseException(CsvParseException::ERROR_MALFORMED, $offset, $rowNumber, $detail);
    }
}

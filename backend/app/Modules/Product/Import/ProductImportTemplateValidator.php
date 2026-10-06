<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Infrastructure\Import\Reader\ImportDocument;
use App\Infrastructure\Import\Reader\ImportHeaderNormalizer;

/** Validates only the shared Product import template structure. */
final class ProductImportTemplateValidator
{
    public const ERROR_INVALID_TEMPLATE = 'IMPORT_INVALID_TEMPLATE';
    public const ERROR_INVALID_RECORD_TYPE = 'IMPORT_INVALID_RECORD_TYPE';
    public const ERROR_INVALID_MODE = 'IMPORT_INVALID_MODE';
    public const ERROR_INVALID_NULL = 'IMPORT_INVALID_NULL';

    public const MODE_CREATE_ONLY = 'CREATE_ONLY';
    public const MODE_UPDATE_ONLY = 'UPDATE_ONLY';
    public const MODE_UPSERT = 'UPSERT';

    /** @var list<string> */
    private const ALLOWED_HEADERS = [
        'record_type', 'source_key', 'product_code', 'sku', 'barcode', 'parent_source_key',
        'parent_product_code', 'product_name', 'product_child_name', 'product_group_code',
        'product_type', 'base_uom_code', 'price', 'net_weight_g', 'package_gross_weight_g',
        'package_length_mm', 'package_width_mm', 'package_height_mm', 'description',
        'manual_standard_cost', 'serial_management_enabled', 'status', 'product_source_key',
        'uom_code', 'conversion_factor', 'usage_type', 'combo_source_key', 'combo_product_code',
        'component_source_key', 'component_product_code', 'quantity',
    ];

    /** @var list<string> */
    private const RECORD_TYPES = ['PRODUCT', 'UOM_CONVERSION', 'COMBO_ITEM'];

    public function __construct(private readonly ?ImportHeaderNormalizer $headers = null)
    {
    }

    /**
     * @param array<string, bool|array{nullable?:bool}> $fieldPolicies
     */
    public function validate(ImportDocument $document, string $mode, array $fieldPolicies = []): ProductImportValidationResult
    {
        $mode = $this->validateMode($mode);
        $headers = ($this->headers ?? new ImportHeaderNormalizer())->normalizeAll($document->header);
        $this->validateHeaders($headers);

        $recordTypeIndex = array_search('record_type', $headers, true);
        if ($recordTypeIndex === false) {
            throw new ProductImportValidationException(self::ERROR_INVALID_TEMPLATE, ['field' => 'record_type']);
        }

        $rows = [];
        foreach ($document->rows as $row) {
            $cells = [];
            foreach ($headers as $index => $header) {
                $cell = ProductImportCell::fromRaw($row->cells[$index] ?? null);
                if ($cell->isExplicitNull() && $this->isNonNullable($fieldPolicies[$header] ?? null)) {
                    throw new ProductImportValidationException(self::ERROR_INVALID_NULL, [
                        'field' => $header,
                        'row' => $row->rowNumber,
                    ]);
                }
                $cells[$header] = $cell;
            }

            $recordTypeCell = $cells['record_type'];
            $recordType = $recordTypeCell->value;
            if (! is_string($recordType) || ! in_array($recordType, self::RECORD_TYPES, true)) {
                throw new ProductImportValidationException(self::ERROR_INVALID_RECORD_TYPE, [
                    'row' => $row->rowNumber,
                    'value' => is_scalar($recordType) ? (string) $recordType : null,
                ]);
            }

            $rows[] = new ProductImportValidatedRow($row->rowNumber, $recordType, $cells);
        }

        return new ProductImportValidationResult($headers, $rows, $mode);
    }

    /** @param list<string> $headers */
    private function validateHeaders(array $headers): void
    {
        if ($headers === []) {
            throw new ProductImportValidationException(self::ERROR_INVALID_TEMPLATE, ['reason' => 'missing_header']);
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                throw new ProductImportValidationException(self::ERROR_INVALID_TEMPLATE, ['column' => $index + 1]);
            }
            if (! in_array($header, self::ALLOWED_HEADERS, true)) {
                throw new ProductImportValidationException(self::ERROR_INVALID_TEMPLATE, ['header' => $header]);
            }
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new ProductImportValidationException(self::ERROR_INVALID_TEMPLATE, ['reason' => 'duplicate_header']);
        }
    }

    private function validateMode(string $mode): string
    {
        if (! in_array($mode, [self::MODE_CREATE_ONLY, self::MODE_UPDATE_ONLY, self::MODE_UPSERT], true)) {
            throw new ProductImportValidationException(self::ERROR_INVALID_MODE, ['mode' => $mode]);
        }

        return $mode;
    }

    /** @param bool|array{nullable?:bool}|null $policy */
    private function isNonNullable(bool|array|null $policy): bool
    {
        if (is_bool($policy)) {
            return ! $policy;
        }

        return is_array($policy) && ($policy['nullable'] ?? true) === false;
    }
}

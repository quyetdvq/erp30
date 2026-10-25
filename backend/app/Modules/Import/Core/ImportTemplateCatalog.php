<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

use App\Exceptions\DomainException;

/** One versioned schema for reader, adapter and generated CSV/XLSX templates. */
final class ImportTemplateCatalog
{
    /** @var array<string,array<string,mixed>> */
    private static array $definitions = [];

    /** @return array{entity_type:string,version:string,max_rows:int,max_bytes:int,record_types:list<string>,columns:list<string>} */
    public static function product(): array
    {
        if (!isset(self::$definitions['PRODUCT'])) {
            $path = dirname(__DIR__) . '/Templates/product-v1.json';
            $raw = file_get_contents($path);
            if ($raw === false) throw new \UnexpectedValueException('Product import template missing.');
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['entity_type'] ?? null) !== 'PRODUCT'
                || ($data['version'] ?? null) !== '1'
                || !is_array($data['columns'] ?? null) || !is_array($data['record_types'] ?? null)
                || ($data['columns'][0] ?? null) !== 'record_type'
                || count($data['columns']) !== count(array_unique($data['columns']))) {
                throw new \UnexpectedValueException('Invalid canonical Product import template.');
            }
            self::$definitions['PRODUCT'] = $data;
        }
        return self::$definitions['PRODUCT'];
    }

    /** @param list<string> $header @param list<string> $columns */
    public static function assertHeader(array $header, array $columns): void
    {
        if ($header === [] || count($header) !== count(array_unique($header))
            || !in_array('record_type', $header, true) || count($header) > count($columns)) {
            throw new DomainException('IMPORT_INVALID_TEMPLATE');
        }
        foreach ($header as $column) {
            if ($column === '' || !in_array($column, $columns, true)) {
                throw new DomainException('IMPORT_INVALID_TEMPLATE', ['field'=>$column]);
            }
        }
    }
}

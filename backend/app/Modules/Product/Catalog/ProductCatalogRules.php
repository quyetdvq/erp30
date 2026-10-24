<?php

declare(strict_types=1);

namespace App\Modules\Product\Catalog;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Baseline API-050 master-data field rules, independent from SQL and HTTP. */
final class ProductCatalogRules
{
    public static function type(string $resource): string
    {
        if (! in_array($resource, ['product_group', 'product_uom'], true)) {
            throw new InvalidArgumentException('UNREVIEWED_PRODUCT_CATALOG_TYPE');
        }
        return $resource;
    }

    public static function codePrefix(string $resource): string
    {
        return $resource === 'product_group' ? 'PRODUCT_GROUP_' : 'PRODUCT_UOM_';
    }

    public static function id(string|int $value, string $resource): string
    {
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $cause) {
            throw new DomainException(self::codePrefix($resource) . 'INVALID_ID', ['field' => 'id'], $cause);
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function payload(string $resource, array $input, bool $create): array
    {
        self::type($resource);
        $output = [];
        $allowed = $resource === 'product_group'
            ? ['parent_id', 'code', 'name', 'description']
            : ['code', 'name', 'description'];
        foreach ($input as $field => $value) {
            if (in_array($field, ['id','created_by','created_at','updated_by','updated_at','evaluation_organization_id'], true)) {
                // Baseline ignores untrusted server-managed columns and client evaluation hints.
                continue;
            }
            if ($field === 'status') {
                if (! $create) {
                    throw new DomainException(self::codePrefix($resource) . 'STATUS_REQUIRES_SETSTATE', ['field'=>'status']);
                }
                $output['status'] = self::state($resource, $value);
                continue;
            }
            if (! in_array($field, $allowed, true)) {
                throw new DomainException(self::codePrefix($resource) . 'FIELD_UNSUPPORTED', ['field' => (string) $field]);
            }
            if ($field === 'parent_id') {
                if ($value === null || $value === '') { $output['parent_id'] = null; continue; }
                try {
                    if (! is_int($value) && ! is_string($value)) {
                        throw new InvalidArgumentException('Parent ID must be an integer.');
                    }
                    $output['parent_id'] = PositiveId::value($value);
                }
                catch (InvalidArgumentException $cause) {
                    throw new DomainException('PRODUCT_GROUP_PARENT_NOT_FOUND', ['field'=>'parent_id'], $cause);
                }
                continue;
            }
            if ($field === 'description') {
                if ($value !== null && (! is_string($value)
                    || ($resource === 'product_uom' && self::length($value) > 255))) {
                    throw new DomainException($resource === 'product_group'
                        ? 'PRODUCT_GROUP_INVALID_FIELD' : 'PRODUCT_UOM_DESCRIPTION_TOO_LONG', ['field'=>'description']);
                }
                $output[$field] = $value;
                continue;
            }
            $limit = $field === 'code' ? ($resource === 'product_group' ? 50 : 30)
                : ($resource === 'product_group' ? 255 : 100);
            if (! is_string($value) || trim($value) === '' || self::length(trim($value)) > $limit) {
                $code = $resource === 'product_group' ? 'PRODUCT_GROUP_REQUIRED_FIELD'
                    : ($field === 'code' ? (is_string($value) && trim($value) !== '' ? 'PRODUCT_UOM_CODE_TOO_LONG' : 'PRODUCT_UOM_CODE_REQUIRED')
                        : (is_string($value) && trim($value) !== '' ? 'PRODUCT_UOM_NAME_TOO_LONG' : 'PRODUCT_UOM_NAME_REQUIRED'));
                throw new DomainException($code, ['field' => $field]);
            }
            $output[$field] = ($field === 'code' && $resource === 'product_group') ? strtoupper(trim($value)) : trim($value);
        }
        if ($create) {
            foreach (['code','name'] as $required) {
                if (! isset($output[$required])) {
                    throw new DomainException($resource === 'product_group' ? 'PRODUCT_GROUP_REQUIRED_FIELD'
                        : ('PRODUCT_UOM_' . strtoupper($required) . '_REQUIRED'), ['field'=>$required]);
                }
            }
            if ($resource === 'product_group') $output['parent_id'] ??= null;
        } elseif ($output === []) {
            throw new DomainException(self::codePrefix($resource) . 'UPDATE_EMPTY');
        }
        return $output;
    }

    public static function state(string $resource, mixed $value): string
    {
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        if (! preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $code)) {
            throw new DomainException(self::codePrefix($resource) . 'INVALID_STATUS', ['field'=>'status']);
        }
        return $code;
    }

    /** @param array<string,mixed> $input */
    public static function requestedState(string $resource, array $input): string
    {
        if (array_keys($input) !== ['status']) {
            throw new DomainException(self::codePrefix($resource) . 'INVALID_STATUS', ['field'=>'status']);
        }
        return self::state($resource, $input['status']);
    }

    /** @param list<array<string,mixed>> $hierarchy */
    public static function assertAcyclicGroupParent(array $hierarchy, ?string $id, ?string $parentId): void
    {
        if ($parentId === null) return;
        if ($id !== null && $id === $parentId) {
            throw new DomainException('PRODUCT_GROUP_SELF_PARENT', ['field'=>'parent_id']);
        }
        $byId = [];
        foreach ($hierarchy as $row) $byId[(string) $row['id']] = $row['parent_id'] === null ? null : (string) $row['parent_id'];
        $seen = [];
        for ($current = $parentId; $current !== null; $current = $byId[$current] ?? null) {
            if (! array_key_exists($current, $byId)) {
                throw new DomainException('PRODUCT_GROUP_PARENT_NOT_FOUND', ['field'=>'parent_id']);
            }
            if (isset($seen[$current])) {
                throw new DomainException('PRODUCT_GROUP_CORRUPTED_HIERARCHY', ['field'=>'parent_id']);
            }
            if ($id !== null && $current === $id) {
                throw new DomainException('PRODUCT_GROUP_HIERARCHY_CYCLE', ['field'=>'parent_id']);
            }
            $seen[$current] = true;
        }
    }

    private static function length(string $value): int
    {
        // Unicode characters, not bytes; no dependency on mbstring in pure fixtures.
        $count = preg_match_all('/./us', $value);
        return $count === false ? PHP_INT_MAX : $count;
    }
}

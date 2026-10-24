<?php

declare(strict_types=1);

namespace App\Modules\Product\Product\Application;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;

/** Product-only list ordering semantics; generic list sources stay unchanged. */
final class ProductListSort
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array<int,string> $parentNames
     * @return list<array<string,mixed>>
     */
    public static function withFullNames(array $rows, array $parentNames): array
    {
        foreach ($rows as &$row) {
            $parentId = $row['parent_product_id'] ?? null;
            if ($parentId === null) {
                $name = $row['product_name'] ?? null;
                if (! is_string($name) || trim($name) === '') {
                    throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['reason' => 'required_name', 'field' => 'product_name']);
                }
                $row['full_name'] = trim($name);
                continue;
            }
            $childName = $row['product_child_name'] ?? null;
            if (! is_string($childName) || trim($childName) === '') {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['reason' => 'required_name', 'field' => 'product_child_name']);
            }
            if (! array_key_exists((int) $parentId, $parentNames)) {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                    'reason' => 'parent_not_found', 'product_id' => $row['id'] ?? null,
                ]);
            }
            if (trim($parentNames[(int) $parentId]) === '') {
                throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['reason' => 'required_name', 'field' => 'parent.product_name']);
            }
            $row['full_name'] = trim($parentNames[(int) $parentId]) . ' - ' . trim($childName);
        }
        unset($row);
        return $rows;
    }

    /** Return null for other fields so legacy Unicode/numeric code sorting is unchanged. */
    public static function compareField(string $field, mixed $left, mixed $right): ?int
    {
        if ($field !== 'price') {
            return null;
        }
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        $a = self::decimalParts($left);
        $b = self::decimalParts($right);
        if ($a[0] !== $b[0]) {
            return $a[0] ? -1 : 1;
        }
        $int = strlen($a[1]) <=> strlen($b[1]);
        $int = $int ?: strcmp($a[1], $b[1]);
        $fraction = strcmp(str_pad($a[2], max(strlen($a[2]), strlen($b[2])), '0'),
            str_pad($b[2], max(strlen($a[2]), strlen($b[2])), '0'));
        $comparison = $int ?: $fraction;
        return $a[0] ? -$comparison : $comparison;
    }

    /** @return array{bool,string,string} negative, integer, fractional digits */
    private static function decimalParts(mixed $value): array
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new PersistenceException(ProductRepository::ERROR_PERSISTENCE_FAILED, 'sortProductPrice');
        }
        $value = (string) $value;
        if (preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', $value) !== 1) {
            throw new PersistenceException(ProductRepository::ERROR_PERSISTENCE_FAILED, 'sortProductPrice');
        }
        $negative = str_starts_with($value, '-');
        $parts = explode('.', ltrim($value, '-'), 2);
        $integer = ltrim($parts[0], '0') ?: '0';
        $fraction = rtrim($parts[1] ?? '', '0');
        if ($integer === '0' && $fraction === '') {
            $negative = false;
        }
        return [$negative, $integer, $fraction];
    }
}

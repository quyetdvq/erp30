<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Exceptions\DomainException;

/**
 * Minimal, read-only Product selector projection for a bounded SQL page.
 * Reproduces ProductReadRules role/name semantics without hydrating the
 * entire catalog. Parent rows are joined for CHILD labels; indexed EXISTS
 * determines whether a root STANDARD row is a PARENT or SIMPLE.
 */
final class ProductLookupProjection
{
    /** @param array<string,mixed> $row joined SQL row, already PBAC-filtered
     *  @return array<string,mixed>
     */
    public static function fromJoined(array $row): array
    {
        $type = strtoupper((string) ($row['product_type'] ?? ''));
        $parentId = $row['parent_product_id'] ?? null;
        $hasChildren = in_array($row['has_children'] ?? null, [true, 1, '1'], true);
        $parent = $parentId !== null;
        if ($type === 'COMBO') {
            if ($parent || $hasChildren || $row['product_child_name'] !== null) {
                self::invalid();
            }
            $role = 'COMBO';
            $name = self::name($row['product_name']);
        } elseif ($type === 'STANDARD') {
            if ($parent) {
                if ($hasChildren || (string) $parentId === (string) $row['id']
                    || strtoupper((string) ($row['parent_type'] ?? '')) !== 'STANDARD'
                    || ($row['parent_parent_id'] ?? null) !== null
                    || $row['product_name'] !== null) {
                    self::invalid();
                }
                $role = 'CHILD';
                $name = self::name($row['parent_name']) . ' - '
                    . self::name($row['product_child_name']);
            } else {
                if ($row['product_child_name'] !== null) self::invalid();
                $role = $hasChildren ? 'PARENT' : 'SIMPLE';
                $name = self::name($row['product_name']);
            }
        } else {
            self::invalid();
        }
        $status = (string) $row['status'];
        return [
            'id' => (string) $row['id'],
            'product_code' => $row['code'] === null ? null : (string) $row['code'],
            'full_name' => $name,
            'role' => $role,
            'status' => $status,
            'base_uom_id' => $row['base_uom_id'] === null ? null : (string) $row['base_uom_id'],
            'selectable' => strtoupper($status) === 'ACTIVE' && in_array($role, ['SIMPLE', 'CHILD', 'COMBO'], true),
        ];
    }

    private static function name(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') self::invalid();
        return trim($value);
    }

    private static function invalid(): never
    {
        throw new DomainException('PRODUCT_INVALID_ROLE', ['reason' => 'invalid_lookup_structure']);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Shared\Media\ImageUrl;

/** Legacy whole-catalog fixture plus bounded SQL-joined Product role/name projection. */
final class ProductReadRules
{
    /**
     * @param list<array<string,mixed>> $rows Unfiltered, persisted Product rows.
     * @return array<string,array<string,mixed>> Full read DTO indexed by canonical Product ID.
     */
    public static function describe(array $rows): array
    {
        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $id = self::id($row['id'] ?? null);
            if (isset($byId[$id])) self::invalid('duplicate_id');
            $byId[$id] = $row;
            if (($row['parent_product_id'] ?? null) !== null) {
                $parent = self::id($row['parent_product_id']);
                $children[$parent][] = $id;
            }
        }

        $projected = [];
        foreach ($byId as $id => $row) {
            $type = strtoupper((string) ($row['product_type'] ?? ''));
            $parentId = ($row['parent_product_id'] ?? null) === null
                ? null : self::id($row['parent_product_id']);
            $ownChildren = $children[$id] ?? [];
            if ($type === 'COMBO') {
                if ($parentId !== null || $ownChildren !== []) self::invalid('combo_structure');
                $role = 'COMBO';
                $fullName = self::name($row['product_name'] ?? null);
                if (($row['product_child_name'] ?? null) !== null) self::invalid('combo_child_name');
            } elseif ($type === 'STANDARD') {
                if ($parentId !== null) {
                    if ($parentId === $id || $ownChildren !== []) self::invalid('nested_product');
                    $parent = $byId[$parentId] ?? null;
                    if ($parent === null || strtoupper((string) ($parent['product_type'] ?? '')) !== 'STANDARD'
                        || ($parent['parent_product_id'] ?? null) !== null) self::invalid('parent_not_root_standard');
                    if (($row['product_name'] ?? null) !== null) self::invalid('child_product_name');
                    $role = 'CHILD';
                    $fullName = self::name($parent['product_name'] ?? null) . ' - '
                        . self::name($row['product_child_name'] ?? null);
                } else {
                    $role = $ownChildren === [] ? 'SIMPLE' : 'PARENT';
                    $fullName = self::name($row['product_name'] ?? null);
                    if (($row['product_child_name'] ?? null) !== null) self::invalid('root_child_name');
                }
            } else {
                self::invalid('invalid_product_type');
            }
            $projected[$id] = self::project($row, $role, $fullName);
        }
        return $projected;
    }

    /**
     * Project exactly ONE Product from a scoped SQL row with indexed Parent
     * JOIN and child EXISTS metadata, without hydrating all other products.
     * @param array<string,mixed> $joined
     * @return array<string,mixed>
     */
    public static function describeJoined(array $joined): array
    {
        $id = self::id($joined['id'] ?? null);
        $type = strtoupper((string) ($joined['product_type'] ?? ''));
        $parentId = ($joined['parent_product_id'] ?? null) === null
            ? null : self::id($joined['parent_product_id']);
        $children = $joined['__has_children'] ?? null;
        if (!in_array($children, [true,false,0,1,'0','1'], true)) self::invalid('missing_child_structure');
        $hasChildren = in_array($children, [true,1,'1'], true);
        if ($type === 'COMBO') {
            if ($parentId !== null || $hasChildren || ($joined['product_child_name'] ?? null) !== null) {
                self::invalid('combo_structure');
            }
            $role = 'COMBO';
            $fullName = self::name($joined['product_name'] ?? null);
        } elseif ($type === 'STANDARD') {
            if ($parentId !== null) {
                if ($parentId === $id || $hasChildren || ($joined['product_name'] ?? null) !== null
                    || strtoupper((string)($joined['__parent_type'] ?? '')) !== 'STANDARD'
                    || ($joined['__parent_parent_id'] ?? null) !== null
                    || ($joined['__parent_child_name'] ?? null) !== null) {
                    self::invalid('parent_not_root_standard');
                }
                $role = 'CHILD';
                // A child may be VIEW-authorized while its parent State is
                // hidden. Never leak the parent's label via full_name.
                $parentVisibility = $joined['__parent_visible'] ?? null;
                if (!in_array($parentVisibility, [true,false,0,1,'0','1'], true)) {
                    self::invalid('missing_parent_visibility_scope');
                }
                $parentVisible = in_array($parentVisibility, [true,1,'1'], true);
                $childName = self::name($joined['product_child_name'] ?? null);
                $fullName = $parentVisible
                    ? self::name($joined['__parent_name'] ?? null) . ' - ' . $childName
                    : $childName;
            } else {
                if (($joined['product_child_name'] ?? null) !== null) self::invalid('root_child_name');
                $role = $hasChildren ? 'PARENT' : 'SIMPLE';
                $fullName = self::name($joined['product_name'] ?? null);
            }
        } else {
            self::invalid('invalid_product_type');
        }
        foreach (['__parent_name','__parent_visible','__parent_type','__parent_parent_id','__parent_child_name','__has_children'] as $key) {
            unset($joined[$key]);
        }
        return self::project($joined, $role, $fullName);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function project(array $row, string $role, string $fullName): array
    {
        $row['role'] = $role;
        $row['full_name'] = $fullName;
        $row['selectable'] = strtoupper((string) ($row['status'] ?? '')) === 'ACTIVE'
            && in_array($role, ['SIMPLE', 'CHILD', 'COMBO'], true);
        $row['leaf_transactionable'] = in_array($role, ['SIMPLE', 'CHILD'], true);
        $row['serial_management_enabled'] = in_array(
            $row['serial_management_enabled'] ?? null, [true, 1, '1'], true
        );
        $row['image_available'] = is_string($row['image_filename'] ?? null)
            && $row['image_filename'] !== '';
        // Only validated baseline-shaped filenames are served via public/upload/YYYY.
        // Old private 40-hex names and arbitrary paths never become static URLs.
        $row['image_url'] = ImageUrl::for(
            is_string($row['image_filename'] ?? null) ? $row['image_filename'] : null,
            'product',
        );
        unset($row['image_filename'], $row['image_mime'], $row['image_size_bytes']);
        $row['product_code'] = $row['code'] ?? null;
        unset($row['code']);
        return $row;
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function listQuery(array $query): array
    {
        if (isset($query['sort']) && is_string($query['sort'])) {
            // The frozen API-050 sort alias product_id denotes persisted id.
            $terms = explode(',', $query['sort']);
            foreach ($terms as &$term) {
                if ($term === 'product_id') $term = 'id';
                elseif ($term === '-product_id') $term = '-id';
            }
            unset($term);
            $query['sort'] = implode(',', $terms);
        }
        return $query;
    }

    private static function id(mixed $value): string
    {
        if ((! is_int($value) && ! is_string($value))
            || preg_match('/\A[1-9][0-9]*\z/D', (string) $value) !== 1) self::invalid('invalid_id');
        $id = ltrim((string) $value, '0');
        return $id === '' ? '0' : $id;
    }

    private static function name(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') self::invalid('required_name');
        return trim($value);
    }

    private static function invalid(string $reason): never
    {
        throw new DomainException('PRODUCT_INVALID_ROLE', ['reason' => $reason]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Shared\Media\ImageUrl;

/** Full frozen Product DTO from one PBAC-authorized, bounded joined row. */
final class ProductRelationshipProjection
{
    /** @param array<string,mixed> $joined @return array<string,mixed> */
    public static function detail(array $joined): array
    {
        $option = ProductLookupProjection::fromJoined($joined);
        $row = $joined;
        foreach (['parent_type', 'parent_parent_id', 'parent_name', 'has_children',
            'image_filename', 'image_mime', 'image_size_bytes', 'code'] as $aux) {
            unset($row[$aux]);
        }
        foreach (['id', 'parent_product_id', 'product_group_id', 'base_uom_id'] as $id) {
            if (array_key_exists($id, $row) && $row[$id] !== null) $row[$id] = (string)$row[$id];
        }
        $row['product_code'] = $option['product_code'];
        $row['role'] = $option['role'];
        $row['full_name'] = $option['full_name'];
        $row['selectable'] = $option['selectable'];
        $row['leaf_transactionable'] = in_array($option['role'], ['SIMPLE', 'CHILD'], true);
        $row['serial_management_enabled'] = in_array(
            $joined['serial_management_enabled'] ?? null, [true, 1, '1'], true
        );
        $filename = $joined['image_filename'] ?? null;
        $row['image_available'] = is_string($filename) && $filename !== '';
        $row['image_url'] = ImageUrl::for(is_string($filename) ? $filename : null, 'product');
        return $row;
    }
}

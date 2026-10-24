<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** Preserve the API's numeric/string ID representation for import jobs and rows. */
final class ProductImportResponsePresenter
{
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function jobOutput(array $row): array
    {
        foreach (['id', 'created_by', 'updated_by'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (string) $row[$field];
            }
        }
        foreach (['total_rows', 'success_rows', 'error_rows'] as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function rowOutput(array $row): array
    {
        return [
            'row_number' => (int) ($row['row_number'] ?? 0),
            'row_status' => (string) ($row['row_status'] ?? ''),
            'entity_key' => $row['entity_key'] ?? null,
            'target_ref_id' => ($row['target_ref_id'] ?? null) === null ? null : (string) $row['target_ref_id'],
            'error_code' => $row['error_code'] ?? null,
            'error_message' => $row['error_message'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

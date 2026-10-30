<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Execution;

/**
 * Public list identity of an ALREADY PBAC-authorized Workflow target.
 * Never query by ID separately or serialize an arbitrary business row.
 */
final class BusinessWorkflowDisplay
{
    /** @var array<string,list<string>> */
    private const NAME_COLUMNS = [
        'province' => ['name'], 'ward' => ['name_full', 'name'],
        'organization' => ['name'], 'department' => ['name'],
        'position' => ['name'], 'employee' => ['employee_name'],
        'product_group' => ['name'], 'product_uom' => ['name'],
        'product' => ['product_name', 'product_child_name'],
        'price_list' => ['name'],
        'organization_contact' => ['name'], 'employee_contact' => ['name'],
        'customer' => ['name'], 'customer_contact' => ['name'], 'supplier_contact' => ['name'],
    ];

    /** @var array<string,list<string>> */
    private const CODE_COLUMNS = [
        'province' => ['code'], 'ward' => ['code'],
        'organization' => ['code'], 'department' => ['code'],
        'position' => ['code'], 'employee' => ['employee_code'],
        'product_group' => ['code'], 'product_uom' => ['code'],
        'product' => ['code', 'sku'], 'price_list' => ['code'],
        'customer' => ['customer_code'],
    ];

    /** @param array<string,mixed> $authorizedRow
     *  @return array{name:?string,code:?string}
     */
    public static function fromAuthorizedRow(string $resourceType, array $authorizedRow): array
    {
        // Fail closed for unreviewed resource types. Never return contact details,
        // company financials, phone, email, tax ID, or arbitrary SQL columns.
        if (! isset(self::NAME_COLUMNS[$resourceType])) {
            return ['name' => null, 'code' => null];
        }
        $name = self::first($authorizedRow, self::NAME_COLUMNS[$resourceType]);
        return [
            'name' => $name,
            'code' => self::first($authorizedRow, self::CODE_COLUMNS[$resourceType] ?? []),
        ];
    }

    /** @param array<string,mixed> $row @param list<string> $columns */
    private static function first(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }
}

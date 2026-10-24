<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * Exact-string filtering and deterministic sorting of ALREADY AUTHORIZED rows.
 * Never call before per-row PBAC checks; total is always post-authorization.
 */
final class AuthorizedListPage
{
    private const SPECS = [
        'province' => [ ['code','name'], ['status','code','name'], ['code','name','status'] ],
        'ward' => [ ['code','name','name_full'], ['province_id','code','name','status'], ['code','name','name_full','province_id','status'] ],
        'contact' => [ ['name','email','phone','mobile'], ['contact_type','contact_ref_id','status'], ['name','email','phone','mobile','contact_type','contact_ref_id','status'] ],
        'product_group' => [ ['code','name'], ['code','name','parent_id','status'], ['code','name','parent_id','status'] ],
        'product_uom' => [ ['code','name'], ['code','name','status'], ['code','name','status'] ],
        'product' => [ ['product_code','sku','barcode','product_name','product_child_name','full_name'], ['product_group_id','base_uom_id','parent_product_id','product_type','status'], ['full_name','product_name','price','product_code','sku','product_type','status'] ],
        'organization' => [ ['code','name','legal_name'], ['parent_id','type','status'], ['code','name','legal_name','parent_id','type','status'] ],
        'department' => [ ['code','name'], ['parent_id','status'], ['code','name','status'] ],
        'position' => [ ['code','name'], ['status'], ['code','name','level','status'] ],
        'employee' => [ ['employee_code','employee_name'], ['status'], ['employee_code','employee_name','status'] ],
        'employee_assignment' => [ ['description'], ['employee_id','organization_id','department_id','position_id','is_primary','status'], ['effective_from','effective_to','is_primary','status'] ],
        // M3.3 Access Management list contracts from baseline API-030.
        'authorization_group' => [ ['code','name'], ['status'], ['code','name','status'] ],
        'security_user' => [ ['username','email','employee_code','employee_name'], ['status','active'], ['id','username','email','status','created_at'] ],
        'resource' => [ ['type','name'], ['domain','status'], ['type','name','domain','status'] ],
        'policy' => [ [], ['organization_id','resource_id','state_id','status','participant_type','participant_ref_id'], ['id','organization_id','resource_id','status'] ],
        'authorization_log' => [ ['action','decision','reason','resource_type'], ['user_id','resource_type','resource_id','action','decision','organization_id','request_id'], ['id','created_at'] ],
        'workflow_state' => [ ['code','name'], ['status'], ['code','name','status'] ],
        'lifecycle' => [ ['code','name'], ['status'], ['code','name','status'] ],
    ];

    /**
     * @param list<array<string,mixed>> $authorizedRows
     * @param array<string,mixed> $query
     * @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public static function paginate(string $resource, array $authorizedRows, array $query): array
    {
        $spec = self::SPECS[$resource] ?? null;
        if ($spec === null) {
            throw new \InvalidArgumentException('Invalid list resource.');
        }
        [$searchFields, $filterFields, $sortFields] = $spec;
        foreach ($query as $name => $_) {
            if (! in_array($name, ['q','page','page_size','sort','filter'], true)) {
                throw new BadRequestException('Malformed list query key.');
            }
        }
        $page = self::positiveInt($query['page'] ?? '1');
        $pageSize = self::positiveInt($query['page_size'] ?? '50');
        if ($pageSize > 200 || $page > intdiv(PHP_INT_MAX, $pageSize)) {
            throw new BadRequestException('Invalid pagination.');
        }
        $needle = $query['q'] ?? null;
        if ($needle !== null && ! is_string($needle)) {
            throw new BadRequestException('Invalid search term.');
        }
        $needle = $needle === null ? null : trim($needle);
        if ($needle === '') {
            $needle = null;
        }
        if ($needle !== null && $searchFields === []) {
            throw new BadRequestException('Search is not supported.');
        }
        $sort = $query['sort'] ?? null;
        if ($sort !== null && (! is_string($sort) || $sort === '')) {
            throw new BadRequestException('Invalid sort.');
        }
        $terms = [];
        foreach ($sort === null ? ['id'] : explode(',', $sort) as $raw) {
            if (preg_match('/\A(-?)([a-z][a-z0-9_]*)\z/D', $raw, $matches) !== 1) {
                throw new BadRequestException('Invalid sort field.');
            }
            $field = $matches[2];
            if (($field !== 'id' && ! in_array($field, $sortFields, true)) || isset($terms[$field])) {
                throw new BadRequestException('Unsupported sort.');
            }
            $terms[$field] = $matches[1] === '-' ? -1 : 1;
        }
        $terms['id'] ??= 1;
        $filters = $query['filter'] ?? [];
        if (! is_array($filters) || ($query['filter'] ?? null) === [] && array_key_exists('filter', $query)) {
            throw new BadRequestException('Invalid filter.');
        }
        foreach ($filters as $field => &$values) {
            if (! is_string($field) || ! in_array($field, $filterFields, true)) {
                throw new BadRequestException('Unsupported filter field.');
            }
            $values = is_array($values) ? $values : [$values];
            if ($values === [] || ! array_is_list($values)) {
                throw new BadRequestException('Invalid filter values.');
            }
            foreach ($values as &$value) {
                if (! is_scalar($value)) {
                    throw new BadRequestException('Invalid filter value.');
                }
                $value = (string) $value;
            }
            unset($value);
        }
        unset($values);
        $rows = array_values(array_filter($authorizedRows, static function (array $row) use ($needle, $searchFields, $filters): bool {
            if ($needle !== null) {
                $found = false;
                foreach ($searchFields as $field) {
                    if (isset($row[$field]) && str_contains(mb_strtolower((string) $row[$field]), mb_strtolower($needle))) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    return false;
                }
            }
            foreach ($filters as $field => $values) {
                if (! in_array((string) ($row[$field] ?? ''), $values, true)) {
                    return false;
                }
            }
            return true;
        }));
        $total = count($rows);
        usort($rows, static function (array $a, array $b) use ($terms): int {
            foreach ($terms as $field => $dir) {
                $left = $a[$field] ?? null;
                $right = $b[$field] ?? null;
                if ($left === $right) {
                    continue;
                }
                if ($left === null || $right === null) {
                    $cmp = $left === null ? -1 : 1;
                } elseif (is_numeric($left) && is_numeric($right)) {
                    $la = ltrim((string) $left, '0') ?: '0';
                    $lb = ltrim((string) $right, '0') ?: '0';
                    $cmp = strlen($la) <=> strlen($lb);
                    $cmp = $cmp ?: strcmp($la, $lb);
                } else {
                    $cmp = strcmp(mb_strtolower((string) $left), mb_strtolower((string) $right));
                }
                if ($cmp !== 0) {
                    return $cmp * $dir;
                }
            }
            return 0;
        });
        return [
            'data' => array_slice($rows, ($page - 1) * $pageSize, $pageSize),
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ];
    }

    private static function positiveInt(mixed $value): int
    {
        if ((! is_string($value) && ! is_int($value)) || preg_match('/\A[1-9][0-9]*\z/D', (string) $value) !== 1) {
            throw new BadRequestException('Invalid positive integer.');
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($number)) {
            throw new BadRequestException('Integer outside supported range.');
        }
        return $number;
    }
}

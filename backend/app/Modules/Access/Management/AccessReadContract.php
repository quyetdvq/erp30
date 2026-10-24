<?php

declare(strict_types=1);

namespace App\Modules\Access\Management;

use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * Fixed Access-owned READ catalog. This table contains only fixed, reviewed
 * resource-to-table mappings; no caller supplies SQL table names.
 */
final class AccessReadContract
{
    public const TABLES = [
        'authorization_group' => 'groups',
        'resource' => 'resources',
        'policy' => 'policies',
        'authorization_log' => 'authorization_logs',
    ];

    public static function table(string $type): string
    {
        return self::TABLES[$type] ?? throw new \InvalidArgumentException('Unsupported Access read resource.');
    }

    /**
     * Baseline supports include=actions on Resource list only. The detail
     * always includes Resource actions. Do not accept multiple/unreviewed joins.
     *
     * @param array<string,mixed> $query
     */
    public static function consumeInclude(string $type, array &$query): bool
    {
        if (! array_key_exists('include', $query)) {
            return false;
        }
        if ($type !== 'resource' || $query['include'] !== 'actions') {
            throw new BadRequestException('Malformed list query: include.');
        }
        unset($query['include']);
        return true;
    }

    /**
     * Baseline authorization-log list accepts bounded created_from/to filters.
     * The date strings are compared against persisted SQL DATETIME strings.
     *
     * @param array<string,mixed> $query
     * @param list<array<string,mixed>> $authorized
     * @return array<string,mixed>
     */
    public static function consumeLogDateFilters(string $type, array $query, array &$authorized): array
    {
        if ($type !== 'authorization_log' || ! isset($query['filter']) || ! is_array($query['filter'])) {
            return $query;
        }
        $filter = $query['filter'];
        foreach (['created_from', 'created_to'] as $field) {
            if (! array_key_exists($field, $filter)) {
                continue;
            }
            $values = is_array($filter[$field]) ? $filter[$field] : [$filter[$field]];
            if (count($values) !== 1 || ! is_scalar($values[0])
                || preg_match('/\A\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2})?\z/D', (string) $values[0]) !== 1) {
                throw new BadRequestException('Malformed list query: filter.' . $field . '.');
            }
            $needle = (string) $values[0];
            $authorized = array_values(array_filter($authorized, static function (array $row) use ($field, $needle): bool {
                $created = (string) ($row['created_at'] ?? '');
                return $field === 'created_from' ? $created >= $needle : $created <= $needle;
            }));
            unset($filter[$field]);
        }
        if ($filter === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filter;
        }
        return $query;
    }

    /** @param list<array<string,mixed>> $pageRows @param list<array<string,mixed>> $actionRows
     * @return list<array<string,mixed>>
     */
    public static function attachPageActions(string $type, array $pageRows, array $actionRows): array
    {
        if ($type !== 'policy' && $type !== 'resource') {
            throw new \InvalidArgumentException('Resource action decoration not supported.');
        }
        $parentKey = $type === 'resource' ? 'resource_id' : 'policy_id';
        $actions = [];
        foreach ($actionRows as $action) {
            $parent = (string) ($action[$parentKey] ?? '');
            if ($parent === '') {
                throw new \UnexpectedValueException('Orphan relation action.');
            }
            if ($type === 'resource') {
                $actions[$parent][] = [
                    'id' => (string) $action['id'],
                    'code' => (string) $action['code'],
                    'description' => $action['description'] ?? null,
                ];
            } else {
                $actions[$parent][] = $action;
            }
        }
        foreach ($pageRows as &$row) {
            $row['actions'] = $actions[(string) $row['id']] ?? [];
        }
        unset($row);
        return $pageRows;
    }
}

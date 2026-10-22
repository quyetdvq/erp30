<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application;

/**
 * Project an already authorized/paginated Organization page for tree display.
 *
 * Missing parents (including parents on another page) must not be exposed or
 * fetched outside the authorized result. The original row order, keys, scalar
 * types and list metadata are preserved by the caller.
 */
final class VisibleOrganizationTreeProjector
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function project(array $rows): array
    {
        $visibleIds = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $visibleIds[(string) $row['id']] = true;
            }
        }
        foreach ($rows as &$row) {
            if (($row['parent_id'] ?? null) !== null && ! isset($visibleIds[(string) $row['parent_id']])) {
                $row['parent_id'] = null;
            }
        }
        unset($row);

        return $rows;
    }
}

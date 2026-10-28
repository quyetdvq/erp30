<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use App\Modules\Access\UnifiedAccessService;
use InvalidArgumentException;

/**
 * M10.2: public, boolean-only advisory hints for a previously VIEW-authorized row.
 * This registry is intentionally explicit; an unknown resource/action MUST NOT
 * inherit a permission or be evaluated using a similarly named resource.
 *
 * These calls reuse the current request's PBAC snapshot. Organization resources
 * are evaluated PER ROW (owner/State may differ), never cached by State alone.
 * No HTTP requests, cross-request cache, database mutations or policy internals.
 */
final class EffectiveRowActionHints
{
    /** @var array<string,list<string>> */
    private const ACTIONS = [
        'product_group' => ['edit', 'setstate'],
        'product_uom' => ['edit', 'setstate'],
        'province' => ['edit', 'setstate'],
        'ward' => ['edit', 'setstate'],
        'department' => ['edit', 'setstate'],
        'position' => ['edit', 'setstate'],
        'employee' => ['edit', 'setstate'],
        'organization' => ['edit', 'setstate'],
        'employee_assignment' => ['edit', 'setstate'],
        'organization_contact' => ['edit', 'setstate'],
        'employee_contact' => ['edit', 'setstate'],
        'customer' => ['edit', 'setstate'],
        'customer_contact' => ['edit', 'setstate'],
        'price_list' => ['edit', 'setstate'],
        'authorization_group' => ['edit', 'setstate'],
        'policy' => ['edit', 'setstate'],
        'resource' => ['edit', 'setstate', 'delete', 'configure_numbering'],
        'user' => ['edit', 'setstate'],
        'lifecycle' => ['edit', 'setstate'],
        'workflow_state' => ['edit', 'setstate'],
    ];

    /** @return list<string> */
    public static function actions(string $resource): array
    {
        return self::ACTIONS[$resource] ?? throw new InvalidArgumentException('Unreviewed row action resource.');
    }

    /**
     * @param array<string,mixed> $trustedRow
     * @return array<string,bool>
     */
    public static function forViewedRow(UnifiedAccessService $access, string $resource, array $trustedRow): array
    {
        return self::evaluate($resource, $trustedRow,
            static fn (string $action, array $row): bool =>
                $access->decideMutation($resource, $action, $row)['allowed'] === true);
    }

    /**
     * Pure seam for tests. Call ONLY after the caller has proven canonical VIEW;
     * never make an access decision from a client-supplied row.
     * @param array<string,mixed> $trustedRow
     * @param callable(string,array<string,mixed>):bool $decision
     * @return array<string,bool>
     */
    public static function evaluate(string $resource, array $trustedRow, callable $decision): array
    {
        if ($trustedRow === []) {
            throw new InvalidArgumentException('A trusted persisted row is required.');
        }
        $result = [];
        foreach (self::actions($resource) as $action) {
            $result[$action] = $decision($action, $trustedRow) === true;
        }
        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/**
 * ORGANIZATION example for the shared compiler. Enumerate trusted organization
 * IDs, NOT Price List rows, then decide each organization/State using canonical
 * PBAC (including assignments, ancestry, DENY and ABSOLUTE_DENY).
 * Kept opt-in until live SQL/PBAC parity is established for Pricing.
 */
final class PriceListScopeProvider implements ResourceScopeProvider
{
    public function __construct(private readonly BaseConnection $db) {}
    public function resource(): string { return 'price_list'; }
    public function domain(): string { return 'ORGANIZATION'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        // Controlled readiness limit; no permissive over-limit fallback.
        $result = $this->db->table('organizations')->select('id')
            ->orderBy('id', 'ASC')->limit(513)->get();
        if ($result === false) throw new UnexpectedValueException('Organization scope metadata query failed.');
        $rows = $result->getResultArray();
        if (count($rows) > 512) {
            throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: organization set too large.');
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
                throw new UnexpectedValueException('Invalid trusted Organization ID.');
            }
            $ids[] = $id;
        }
        return AuthorizedSqlScope::organization('price_list', $access->viewableOrganizationStateBuckets('price_list', $ids));
    }
}

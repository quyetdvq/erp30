<?php

declare(strict_types=1);

namespace App\Modules\Organization\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Trusted metadata-only Organization enumeration; never enumerate business
 * rows to infer permission. One snapshot-bound actor has a distinct decision
 * for every Organization ID and canonical State, including DENY lanes.
 */
final class OrganizationPeopleScopeProvider implements ResourceScopeProvider
{
    public function __construct(
        private readonly string $type,
        private readonly BaseConnection $db,
    ) {
        if (!in_array($type, ['organization', 'employee_assignment'], true)) {
            throw new InvalidArgumentException('Unreviewed Organization / People resource.');
        }
    }

    public function resource(): string { return $this->type; }
    public function domain(): string { return 'ORGANIZATION'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        $result = $this->db->table('organizations')->select('id')
            ->orderBy('id', 'ASC')->limit(513)->get();
        if ($result === false) {
            throw new UnexpectedValueException('Organization scope metadata query failed.');
        }
        $rows = $result->getResultArray();
        if (count($rows) > 512) {
            // A future chunked/temporary-table compiler may remove this bound.
            // Never weaken PBAC to return unscoped records.
            throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: more than 512 organizations.');
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
                throw new UnexpectedValueException('Untrusted Organization identifier.');
            }
            $ids[] = $id;
        }
        return AuthorizedSqlScope::organization(
            $this->type,
            $access->viewableOrganizationStateBuckets($this->type, $ids),
        );
    }
}

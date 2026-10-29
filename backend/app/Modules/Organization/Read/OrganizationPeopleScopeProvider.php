<?php

declare(strict_types=1);

namespace App\Modules\Organization\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\Query\TrustedOrganizationIdPager;
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
        // Bypass expensive metadata enumeration when the actor has no base VIEW.
        if (!$access->hasBaseViewPermission($this->type)) {
            return AuthorizedSqlScope::organization($this->type, []);
        }
        $ids = TrustedOrganizationIdPager::collect(function (?string $last, int $size): array {
            $builder = $this->db->table('organizations')->select('id');
            if ($last !== null) $builder->where('id >', $last);
            $query = $builder->orderBy('id', 'ASC')->limit($size)->get();
            if ($query === false) throw new UnexpectedValueException('Organization metadata query failed.');
            return $query->getResultArray();
        });
        return AuthorizedSqlScope::organization(
            $this->type,
            $access->viewableOrganizationStateBuckets($this->type, $ids),
        );
    }
}

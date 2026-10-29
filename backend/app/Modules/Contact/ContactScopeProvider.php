<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\Query\TrustedOrganizationIdPager;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Two independent ORGANIZATION PBAC resources. Organization IDs are enumerated
 * from trusted Organization metadata, not from the Contact owner references.
 */
final class ContactScopeProvider implements ResourceScopeProvider
{
    public function __construct(
        private readonly string $type,
        private readonly BaseConnection $db,
    ) {
        if (!in_array($type, ['organization_contact', 'employee_contact'], true)) {
            throw new InvalidArgumentException('Unreviewed Contact scope resource.');
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

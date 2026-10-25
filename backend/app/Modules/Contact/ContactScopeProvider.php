<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
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
        // Same fail-closed bound as M8.6B. Future DB-backed scope compilation
        // can replace this limit after authenticated MariaDB parity/EXPLAIN.
        $query = $this->db->table('organizations')->select('id')
            ->orderBy('id', 'ASC')->limit(513)->get();
        if ($query === false) throw new UnexpectedValueException('Contact organization metadata query failed.');
        $rows = $query->getResultArray();
        if (count($rows) > 512) {
            throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: more than 512 organizations.');
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
                throw new UnexpectedValueException('Invalid persisted Organization identifier.');
            }
            $ids[] = $id;
        }
        return AuthorizedSqlScope::organization(
            $this->type,
            $access->viewableOrganizationStateBuckets($this->type, $ids),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Bootstrap\AccessReadAdapters;
use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;

/** Trusted owner -> PBAC organization scope; no client-supplied scope is used. */
final class ContactScope
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @param array<string,mixed> $row @return array{resource:string,target:array<string,mixed>} */
    public function resolve(array $row): array
    {
        $owner = ContactRules::owner($row);
        if ($owner['type'] === 'ORGANIZATION') {
            $organizationId = $owner['id'];
            if ($this->db->table('organizations')->select('id')->where('id', $organizationId)->get()->getRowArray() === null) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
        } else {
            $people = AccessReadAdapters::people($this->db);
            if ($people->employeeById($owner['id']) === null) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
            $rows = $people->effectiveAssignments($owner['id'], gmdate('Y-m-d'), true);
            $primary = ContactRules::uniqueActivePrimary($rows);
            if ($primary === null) {
                // Baseline requires exactly one canonical, current primary assignment.
                throw new DomainException('CONTACT_PRIMARY_SCOPE_UNAVAILABLE');
            }
            $organizationId = ContactRules::id($primary['organization_id'] ?? null, 'organization_id');
            if ($this->db->table('organizations')->select('id')->where('id', $organizationId)->get()->getRowArray() === null) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
        }
        // The contact table has no organization_id. This synthetic field comes
        // exclusively from authoritative persisted owner facts, never the body.
        return ['resource' => $owner['resource'],
            'target' => [...$row, 'organization_id' => $organizationId]];
    }
}

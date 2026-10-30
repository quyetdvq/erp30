<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Bootstrap\AccessReadAdapters;
use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;

/** Trusted owner -> PBAC organization scope; no client-supplied scope is used. */
final class ContactScope
{
    public function __construct(private readonly BaseConnection $db, private readonly ?string $utcDate = null)
    {
    }

    /** @param array<string,mixed> $row @return array{resource:string,target:array<string,mixed>} */
    public function resolve(array $row, ?array $supplierParents = null): array
    {
        $owner = ContactRules::owner($row);
        if ($owner['type'] === 'ORGANIZATION') {
            $organizationId = $owner['id'];
            if ($this->db->table('organizations')->select('id')->where('id', $organizationId)->get()->getRowArray() === null) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
        } elseif ($owner['type'] === 'SUPPLIER') {
            // MASTER scope must NOT inherit an Employee Organization. Supplier
            // existence is verified using persisted facts, never a client hint.
            // For a list page the page-local batch avoids per-row SELECTs.
            if ($supplierParents === null) {
                $parent = $this->db->table('suppliers')->select('id')
                    ->where('id', $owner['id'])->get();
                if ($parent === false || $parent->getRowArray() === null) {
                    throw new DomainException('CONTACT_OWNER_NOT_FOUND');
                }
            } elseif (!isset($supplierParents[$owner['id']])) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
            return ['resource' => 'supplier_contact', 'target' => $row];
        } elseif ($owner['type'] === 'CUSTOMER') {
            $customer = $this->db->table('customers')->where('id', $owner['id'])->get()->getRowArray();
            if ($customer === null) throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            // MASTER Customer Contact is evaluated at GROUP, never at a client-supplied Company.
            return ['resource' => 'customer_contact', 'target' => $row];
        } else {
            $people = AccessReadAdapters::people($this->db);
            if ($people->employeeById($owner['id']) === null) {
                throw new DomainException('CONTACT_OWNER_NOT_FOUND');
            }
            $rows = $people->effectiveAssignments($owner['id'], $this->utcDate ?? gmdate('Y-m-d'), true);
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

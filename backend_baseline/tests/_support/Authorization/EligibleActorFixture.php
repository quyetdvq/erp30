<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use CodeIgniter\Database\BaseConnection;

/**
 * Provides employment eligibility for positive PBAC test actors only.
 *
 * Negative scenarios must deliberately omit this fixture so that mandatory
 * eligibility continues to be covered by authorization regression tests.
 */
final class EligibleActorFixture
{
    /** @param BaseConnection<mixed, mixed> $db */
    public static function assignAtOrganization(BaseConnection $db, int $userId, int $organizationId): void
    {
        $db->table('employees')->insert([
            'user_id' => $userId,
            'employee_code' => 'TEST-EMP-' . bin2hex(random_bytes(8)),
            'employee_name' => 'Eligible test actor',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
        $employeeId = (int) $db->insertID();
        $db->table('employee_assignments')->insert([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'department_id' => null,
            'position_id' => null,
            'is_primary' => 0,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);
    }
}

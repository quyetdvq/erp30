<?php

declare(strict_types=1);

namespace Tests\Support\Workflow;

use CodeIgniter\Database\BaseConnection;
use Tests\Support\Foundation\RuntimeFixture;

/** Test-only helpers; callers must have guarded a disposable erp_test DB. */
final class BeReorg011BusinessActionsFixture
{
    public static function code(string $prefix = 'BE011H-'): string
    {
        $code = $prefix . uniqid();
        if (strlen($code) > 20) {
            throw new \RuntimeException('Province/ward test code exceeds VARCHAR(20).');
        }
        return $code;
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function rootWriter(BaseConnection $db, RuntimeFixture $fixture): void
    {
        self::rootActor($db, $fixture->writer, $fixture->group);
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function rootActor(BaseConnection $db, int $actor, int $organizationId): void
    {
        $employee = $db->table('employees')->where('user_id', $actor)->get()->getRowArray();
        if ($employee === null) {
            throw new \RuntimeException('Missing fixture writer employee.');
        }
        if (! $db->table('employee_assignments')->insert([
            'employee_id' => (int) $employee['id'], 'organization_id' => $organizationId,
            'department_id' => null, 'position_id' => null, 'is_primary' => 0,
            'effective_from' => '2026-01-01', 'effective_to' => null,
            'status' => 'ACTIVE', 'created_at' => '2026-10-17 05:00:00',
        ])) {
            throw new \RuntimeException('Could not grant test actor effective root assignment.');
        }
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function grant(BaseConnection $db, string $resource, string $action, string $group = 'BR001-WRITER'): void
    {
        $actorGroup = $db->table('groups')->where('code', $group)->get()->getRowArray();
        $resourceAction = $db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id=ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $action)->get()->getRowArray();
        if ($actorGroup === null || $resourceAction === null) {
            throw new \RuntimeException('Missing test group/resource-action: ' . $resource . '.' . $action);
        }
        $duplicate = $db->table('group_permissions')->where('group_id', (int) $actorGroup['id'])
            ->where('resource_action_id', (int) $resourceAction['id'])->get()->getRowArray();
        if ($duplicate === null && ! $db->table('group_permissions')->insert([
            'group_id' => (int) $actorGroup['id'], 'resource_action_id' => (int) $resourceAction['id'],
            'created_at' => '2026-10-17 05:00:00',
        ])) {
            throw new \RuntimeException('Unable to grant ' . $resource . '.' . $action);
        }
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function auditCount(BaseConnection $db): int
    {
        return (int) $db->table('authorization_logs')->where('action', 'setstate')->countAllResults();
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function status(BaseConnection $db, string $table, int $id): string
    {
        $row = $db->table($table)->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Missing test record');
        }
        return (string) $row['status'];
    }

    /** @param BaseConnection<mixed,mixed> $db */
    public static function contact(BaseConnection $db, string $owner, int $ownerId): int
    {
        if (! $db->table('contacts')->insert([
            'contact_type' => $owner, 'contact_ref_id' => $ownerId,
            'name' => 'BE011 owner boundary', 'status' => 'ACTIVE',
            'created_at' => '2026-10-17 05:00:00',
        ])) {
            throw new \RuntimeException('Could not insert test Contact.');
        }
        return (int) $db->insertID();
    }
}

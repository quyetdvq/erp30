<?php

declare(strict_types=1);

namespace Tests\M11\Support;

use App\Modules\Notifications\WorkflowNotificationAssigneesService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

require_once __DIR__ . '/CanonicalGroupRootFixture.php';

/** Deterministic data setup; ONLY called inside the integration test's outer transaction. */
final class WorkflowFixture
{
    public string $targetId;
    public string $actorId;
    public string $firstRecipientId;
    public string $secondRecipientId;
    public string $inactiveRecipientId;
    private string $resourceId;
    private string $lifecycleId;
    private string $groupRootId;
    private string $tag;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->tag = strtoupper(bin2hex(random_bytes(5)));
    }

    public function install(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        // Follow the canonical-root fixture in backend_baseline: a second GROUP
        // root makes PBAC fail closed. Reuse an existing root without mutating it.
        $this->groupRootId = CanonicalGroupRootFixture::findOrCreate(
            $this->db,
            fn (): string => $this->insert('organizations', [
                'type' => 'GROUP', 'code' => 'M11_' . $this->tag,
                'name' => 'M11 TEST ROOT', 'status' => 'ACTIVE',
                'parent_id' => null, 'created_at' => $now,
            ]),
        );
        $this->lifecycleId = $this->insert('lifecycles', [
            'code' => 'M11_' . $this->tag, 'name' => 'M11 Test Lifecycle',
            'status' => 'ACTIVE', 'created_at' => $now,
        ]);
        $stateIds = [];
        foreach (['M11_A','M11_B','M11_C'] as $index => $code) {
            $stateIds[$code] = $this->insert('workflow_states', [
                'code' => $code, 'name' => $code, 'status' => 'ACTIVE', 'created_at' => $now,
            ]);
            $this->insert('lifecycle_states', [
                'lifecycle_id' => $this->lifecycleId, 'state_id' => $stateIds[$code],
                'sequence' => ($index + 1) * 10, 'is_initial' => $index === 0 ? 1 : 0,
                'is_final' => $index === 2 ? 1 : 0, 'created_at' => $now,
            ]);
        }
        foreach ([['M11_A','M11_B','m11_submit'], ['M11_B','M11_C','m11_approve']] as [$from,$to,$action]) {
            $this->insert('lifecycle_transitions', [
                'lifecycle_id' => $this->lifecycleId, 'from_state_id' => $stateIds[$from],
                'to_state_id' => $stateIds[$to], 'action_code' => $action,
                'status' => 'ACTIVE', 'created_at' => $now,
            ]);
        }
        $existing = $this->db->table('resources')->where('type', 'province')->get()->getRowArray();
        if ($existing === null) {
            $this->resourceId = $this->insert('resources', [
                'type' => 'province', 'name' => 'province', 'domain' => 'MASTER',
                'status' => 'ACTIVE', 'lifecycle_id' => $this->lifecycleId, 'created_at' => $now,
            ]);
        } else {
            $this->resourceId = (string) $existing['id'];
            $this->db->table('resources')->where('id', $this->resourceId)->update([
                'status' => 'ACTIVE', 'domain' => 'MASTER', 'lifecycle_id' => $this->lifecycleId,
            ]);
        }
        foreach (['view', 'setstate', 'm11_submit', 'm11_approve'] as $code) {
            $exists = $this->db->table('resource_actions')->where('resource_id', $this->resourceId)
                ->where('code', $code)->get()->getRowArray();
            if ($exists === null) {
                $this->insert('resource_actions', [
                    'resource_id' => $this->resourceId, 'code' => $code, 'created_at' => $now,
                ]);
            }
        }
        $this->actorId = $this->user('actor');
        $this->firstRecipientId = $this->user('first');
        $this->secondRecipientId = $this->user('second');
        $this->inactiveRecipientId = $this->user('inactive');
        $this->db->table('users')->where('id', $this->inactiveRecipientId)->update(['active' => 0]);
        $this->targetId = $this->insert('provinces', [
            'code' => substr('M11' . $this->tag, 0, 20), 'name' => 'M11 notification fixture',
            'status' => 'M11_A', 'created_at' => $now,
        ]);
        $existingSetting = $this->db->table('notification_settings')
            ->where('setting_key', 'workflow.action_required.in_app')->get()->getRowArray();
        if ($existingSetting === null) {
            $this->db->table('notification_settings')->insert([
                'setting_key' => 'workflow.action_required.in_app', 'default_value' => 1,
                'allow_user_override' => 0, 'revision' => '1', 'override_epoch' => '1',
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('notification_settings')
                ->where('setting_key', 'workflow.action_required.in_app')->update([
                    'default_value' => 1, 'allow_user_override' => 0,
                ]);
        }
    }

    public function actor(?string $id = null): User
    {
        $userId = $id ?? $this->actorId;
        $row = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Fixture user disappeared');
        }
        return new User($row);
    }

    /** @param list<string>|null $ids */
    public function assign(?array $ids = null): array
    {
        return (new WorkflowNotificationAssigneesService($this->actor(), $this->db))
            ->replace('province', $this->targetId, [
                'expected_revision' => '0',
                'user_ids' => $ids ?? [$this->firstRecipientId, $this->secondRecipientId],
            ]);
    }

    public function revoke(string $userId, string $permission): void
    {
        $this->db->table('auth_permissions_users')->where('user_id', $userId)
            ->where('permission', $permission)->delete();
    }

    private function user(string $role): string
    {
        $id = $this->insert('users', [
            'username' => strtolower('m11_' . $role . '_' . $this->tag),
            'active' => 1, 'status' => 'ACTIVE', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $employeeId = $this->insert('employees', [
            'user_id' => $id, 'employee_code' => 'M11_' . $role . '_' . $this->tag,
            'employee_name' => 'M11 ' . $role, 'status' => 'ACTIVE',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->insert('employee_assignments', [
            'employee_id' => $employeeId, 'organization_id' => $this->groupRootId,
            'is_primary' => 1, 'effective_from' => gmdate('Y-m-d', time() - 86400),
            'effective_to' => null, 'status' => 'ACTIVE', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        foreach (['view','setstate','m11_submit','m11_approve'] as $code) {
            $this->db->table('auth_permissions_users')->insert([
                'user_id' => $id, 'permission' => 'province.' . $code,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
        return $id;
    }

    /** @param array<string,mixed> $row */
    private function insert(string $table, array $row): string
    {
        if (! $this->db->table($table)->insert($row)) {
            throw new RuntimeException('M11 fixture insert failed for ' . $table);
        }
        return (string) $this->db->insertID();
    }
}

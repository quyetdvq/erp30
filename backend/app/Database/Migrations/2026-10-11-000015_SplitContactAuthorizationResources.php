<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * CHANGE-CONTACT-AUTH-001. Authorization resources, NOT additional Contact tables.
 * Existing ALLOW rights are never copied. Existing restrictive policy actions
 * are carried forward conservatively to both supported owner kinds.
 * All DML is transactional; never delete old policy/grant audit history.
 */
final class SplitContactAuthorizationResources extends Migration
{
    /** @var list<string> */
    private const ACTIONS = ['view', 'create', 'edit', 'setstate', 'export'];

    /** @var list<string> */
    private const NEW_TYPES = ['organization_contact', 'employee_contact'];

    public function up(): void
    {
        $this->db->transBegin();
        try {
            // Preserve an opt-in legacy Contact Lifecycle across the split.
            // Both new Resource types reuse its State/transition definitions;
            // executing transitions still requires a registered target adapter.
            // Dropping this link would silently weaken State-scoped DENYs.
            $legacy = $this->db->table('resources')->where('type', 'contact')->get()->getRowArray();
            $legacyLifecycleId = $legacy === null || $legacy['lifecycle_id'] === null
                ? null : (int) $legacy['lifecycle_id'];
            if ($legacyLifecycleId !== null) {
                $this->assertLegacyLifecycleUsable($legacyLifecycleId);
            }
            $targets = [];
            foreach (self::NEW_TYPES as $type) {
                $targets[$type] = $this->ensureResource($type, $legacyLifecycleId);
            }
            if ($legacy !== null) {
                $this->copyRestrictivePolicies((int) $legacy['id'], $targets);
                // Existing group/direct-user grants remain in the database for
                // audit, but cannot authorize any real Contact API operation.
                $this->db->table('resources')->where('id', (int) $legacy['id'])
                    ->update(['status' => 'INACTIVE']);
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: transaction failed.');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function down(): void
    {
        // Allow empty database/test rebuilds. Refuse rollback as soon as any
        // Contact grant, policy, or audit record exists: broad legacy access
        // MUST NOT be re-enabled automatically on a populated installation.
        $resourceRows = $this->db->table('resources')
            ->whereIn('type', [...self::NEW_TYPES, 'contact'])->get()->getResultArray();
        $byType = [];
        foreach ($resourceRows as $row) {
            $byType[(string) $row['type']] = (int) $row['id'];
        }
        $ids = array_values($byType);
        if ($ids !== []) {
            $actions = $this->db->table('resource_actions')->select('id')
                ->whereIn('resource_id', $ids)->get()->getResultArray();
            $actionIds = array_map(static fn (array $row): int => (int) $row['id'], $actions);
            if ($this->db->table('policies')->whereIn('resource_id', $ids)->countAllResults() > 0
                || $this->db->table('authorization_logs')->whereIn('resource_id', $ids)->countAllResults() > 0
                || ($actionIds !== [] && $this->db->table('group_permissions')
                    ->whereIn('resource_action_id', $actionIds)->countAllResults() > 0)
                || $this->db->table('auth_permissions_users')
                    ->like('permission', 'contact.', 'before')->countAllResults() > 0
                || $this->db->table('auth_permissions_users')
                    ->like('permission', '_contact.', 'both')->countAllResults() > 0
            ) {
                throw new RuntimeException('CONTACT_AUTH_ROLLBACK_REQUIRES_REVIEW');
            }
        }
        $this->db->transBegin();
        try {
            foreach (self::NEW_TYPES as $type) {
                if (! isset($byType[$type])) {
                    continue;
                }
                $id = $byType[$type];
                $this->db->table('resource_actions')->where('resource_id', $id)->delete();
                $this->db->table('resources')->where('id', $id)->delete();
            }
            if (isset($byType['contact'])) {
                $this->db->table('resources')->where('id', $byType['contact'])->update(['status' => 'ACTIVE']);
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('CONTACT_AUTH_ROLLBACK_REQUIRES_REVIEW');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function ensureResource(string $type, ?int $legacyLifecycleId): int
    {
        $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
        if ($row === null) {
            $this->db->table('resources')->insert([
                'type' => $type,
                'name' => $type,
                'domain' => 'ORGANIZATION',
                'lifecycle_id' => $legacyLifecycleId,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->db->insertID();
        } else {
            if (($row['domain'] ?? null) !== 'ORGANIZATION') {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: invalid target Resource domain.');
            }
            $id = (int) $row['id'];
            $existingLifecycleId = $row['lifecycle_id'] === null ? null : (int) $row['lifecycle_id'];
            if ($existingLifecycleId !== null && $existingLifecycleId !== $legacyLifecycleId) {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: target Resource has conflicting Lifecycle.');
            }
            if ($existingLifecycleId === null && $legacyLifecycleId !== null) {
                // A previously provisioned target may already have policies;
                // silently attaching a Lifecycle could change their meaning.
                if ($this->db->table('policies')->where('resource_id', $id)->countAllResults() > 0) {
                    throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: target Resource Policies require Lifecycle review.');
                }
                $this->db->table('resources')->where('id', $id)
                    ->update(['lifecycle_id' => $legacyLifecycleId]);
            }
        }
        foreach (self::ACTIONS as $action) {
            if ($this->db->table('resource_actions')->where('resource_id', $id)
                ->where('code', $action)->countAllResults() === 0) {
                $this->db->table('resource_actions')->insert([
                    'resource_id' => $id, 'code' => $action,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $id;
    }

    /**
     * Preserve a valid opt-in Lifecycle, including a DRAFT initial State.
     * Existing Contact statuses must remain representable, and a new DRAFT
     * Contact must have an active setstate transition into ACTIVE.
     * This migration never edits the shared Lifecycle or existing Contacts.
     */
    private function assertLegacyLifecycleUsable(int $lifecycleId): void
    {
        $lifecycle = $this->db->table('lifecycles')->where('id', $lifecycleId)->get()->getRowArray();
        if ($lifecycle === null || strtoupper((string) ($lifecycle['status'] ?? '')) !== 'ACTIVE') {
            throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: legacy Lifecycle is missing/inactive.');
        }
        $members = $this->db->table('lifecycle_states ls')
            ->select('ls.state_id, ls.is_initial, ws.code, ws.status')
            ->join('workflow_states ws', 'ws.id = ls.state_id')
            ->where('ls.lifecycle_id', $lifecycleId)->get()->getResultArray();
        if ($members === []) {
            throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: legacy Lifecycle has no States.');
        }
        $initials = [];
        $codes = [];
        foreach ($members as $member) {
            $code = (string) ($member['code'] ?? '');
            if ($code === '' || $code !== strtoupper(trim($code))
                || (string) ($member['status'] ?? '') !== 'ACTIVE'
                || isset($codes[$code])) {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: legacy Lifecycle has invalid/inactive States.');
            }
            $codes[$code] = (int) $member['state_id'];
            if ((int) ($member['is_initial'] ?? 0) === 1) {
                $initials[] = $code;
            }
        }
        if (count($initials) !== 1 || ! in_array($initials[0], ['ACTIVE', 'DRAFT'], true)) {
            throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: legacy Lifecycle initial State requires review.');
        }

        // HEX enforces byte-exact status comparison even with a case-insensitive
        // MariaDB collation. Never silently map invalid persisted Contacts.
        $stored = $this->db->table('contacts')->distinct()
            ->select('HEX(contact_type) AS owner_hex, HEX(status) AS status_hex', false)
            ->get()->getResultArray();
        foreach ($stored as $row) {
            $owner = hex2bin((string) ($row['owner_hex'] ?? ''));
            $status = hex2bin((string) ($row['status_hex'] ?? ''));
            if ($owner === false || ! in_array($owner, ['ORGANIZATION', 'EMPLOYEE'], true)
                || $status === false || ! array_key_exists($status, $codes)) {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: persisted Contact owner/State requires review.');
            }
        }
        if ($initials[0] === 'DRAFT') {
            if (! isset($codes['ACTIVE'])) {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: DRAFT Lifecycle missing ACTIVE State.');
            }
            $activation = $this->db->table('lifecycle_transitions')
                ->select('action_code, status')
                ->where('lifecycle_id', $lifecycleId)
                ->where('from_state_id', $codes['DRAFT'])
                ->where('to_state_id', $codes['ACTIVE'])
                ->where('action_code', 'setstate')
                ->where('status', 'ACTIVE')->get()->getRowArray();
            if ($activation === null || ($activation['action_code'] ?? null) !== 'setstate'
                || ($activation['status'] ?? null) !== 'ACTIVE') {
                throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: DRAFT to ACTIVE transition missing.');
            }
        }
    }

    /** @param array<string,int> $targets */
    private function copyRestrictivePolicies(int $legacyResourceId, array $targets): void
    {
        $policies = $this->db->table('policies')
            ->where('resource_id', $legacyResourceId)->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($policies as $source) {
            $rows = $this->db->table('policy_actions pa')
                ->select('pa.effect, ra.code, ra.resource_id')
                ->join('resource_actions ra', 'ra.id = pa.resource_action_id')
                ->where('pa.policy_id', (int) $source['id'])->get()->getResultArray();
            $restrictive = [];
            foreach ($rows as $row) {
                if ((int) $row['resource_id'] !== $legacyResourceId
                    || ! in_array($row['effect'], ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                    throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: invalid legacy Policy Action.');
                }
                if (in_array($row['effect'], ['DENY', 'ABSOLUTE_DENY'], true)) {
                    if (! in_array($row['code'], self::ACTIONS, true)) {
                        throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: unsupported DENY Action.');
                    }
                    $restrictive[(string) $row['code']] = (string) $row['effect'];
                }
            }
            if ($restrictive === []) {
                continue;
            }
            foreach ($targets as $targetId) {
                $identity = [
                    'organization_id' => (int) $source['organization_id'],
                    'resource_id' => $targetId,
                    'state_id' => $source['state_id'] === null ? null : (int) $source['state_id'],
                    'participant_type' => (string) $source['participant_type'],
                    'participant_ref_id' => (int) $source['participant_ref_id'],
                ];
                $builder = $this->db->table('policies');
                foreach ($identity as $key => $value) {
                    $builder->where($key, $value);
                }
                $existing = $builder->get()->getRowArray();
                if ($existing !== null) {
                    throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: target Policy scope already exists.');
                }
                $this->db->table('policies')->insert([
                    ...$identity,
                    'status' => (string) $source['status'],
                    'created_by' => $source['created_by'],
                    'created_at' => $source['created_at'],
                ]);
                $newPolicyId = (int) $this->db->insertID();
                foreach ($restrictive as $action => $effect) {
                    $actionRow = $this->db->table('resource_actions')
                        ->where('resource_id', $targetId)->where('code', $action)
                        ->get()->getRowArray();
                    if ($actionRow === null) {
                        throw new RuntimeException('CONTACT_AUTH_MIGRATION_BLOCKED: target Action missing.');
                    }
                    $this->db->table('policy_actions')->insert([
                        'policy_id' => $newPolicyId,
                        'resource_action_id' => (int) $actionRow['id'],
                        'effect' => $effect,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }
}

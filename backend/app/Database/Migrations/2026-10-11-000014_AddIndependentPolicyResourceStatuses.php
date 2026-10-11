<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Additive change: status of the policy/resource object, NOT policy.state_id.
 * Existing rows stay ACTIVE to preserve existing PBAC behavior on rollout.
 * DDL in MariaDB auto-commits: each addition is idempotent for safe retries.
 */
final class AddIndependentPolicyResourceStatuses extends Migration
{
    public function up(): void
    {
        // CI4 caches field names on the connection. Test migrations repeatedly
        // rebuild the schema using that same connection, so always inspect the
        // actual columns instead of a prior migration run's metadata.
        $this->db->resetDataCache();
        foreach (['policies', 'resources'] as $table) {
            if (! $this->db->fieldExists('status', $table)) {
                $this->forge->addColumn($table, [
                    'status' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'null' => false,
                        'default' => 'ACTIVE',
                    ],
                ]);
                $this->db->resetDataCache();
            }
        }
        // Existing installations already have the Resource Catalog. Introduce
        // the new protected action without auto-granting it to existing users.
        foreach (['resource', 'policy'] as $type) {
            $resource = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            if ($resource === null) {
                continue;
            }
            $resourceId = (int) $resource['id'];
            if ($this->db->table('resource_actions')
                ->where('resource_id', $resourceId)->where('code', 'setstate')
                ->countAllResults() === 0) {
                $this->db->table('resource_actions')->insert([
                    'resource_id' => $resourceId,
                    'code' => 'setstate',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        // Global PBAC state IDs are needed even for resources without a
        // Lifecycle. Do not alter the status of pre-existing Workflow States.
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED', 'PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'] as $code) {
            if ($this->db->table('workflow_states')->where('code', $code)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('workflow_states')->insert([
                'code' => $code,
                'name' => $code,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->resetDataCache();
    }

    public function down(): void
    {
        // Refuse a rollback that silently reactivates intentionally disabled
        // Policies or Resources by deleting their persisted statuses.
        $this->db->resetDataCache();
        foreach (['policies', 'resources'] as $table) {
            if ($this->db->fieldExists('status', $table)) {
                $count = $this->db->table($table)->where('status !=', 'ACTIVE')->countAllResults();
                if ($count > 0) {
                    throw new RuntimeException('NATIVE_STATE_ROLLBACK_BLOCKED: inactive records exist in ' . $table);
                }
            }
        }
        foreach (['policies', 'resources'] as $table) {
            if ($this->db->fieldExists('status', $table)) {
                $this->forge->dropColumn($table, 'status');
                $this->db->resetDataCache();
            }
        }
        $this->db->resetDataCache();
    }
}

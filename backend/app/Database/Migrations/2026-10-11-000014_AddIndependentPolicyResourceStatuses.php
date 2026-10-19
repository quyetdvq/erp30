<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Additive change: status of the policy/resource object, NOT policy.state_id.
 * Existing rows stay ACTIVE to preserve existing PBAC behavior on rollout.
 * DDL in MariaDB auto-commits: each addition is idempotent for safe retries.
 */
final class AddIndependentPolicyResourceStatuses extends Migration
{
    /** @return BaseConnection<mixed,mixed> */
    private function connection(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('Migration requires a CodeIgniter database connection.');
        }
        return $this->db;
    }

    public function up(): void
    {
        // CI4 caches field names on the connection. Test migrations repeatedly
        // rebuild the schema using that same connection, so always inspect the
        // actual columns instead of a prior migration run's metadata.
        $this->connection()->resetDataCache();
        foreach (['policies', 'resources'] as $table) {
            if (! $this->connection()->fieldExists('status', $table)) {
                $this->forge->addColumn($table, [
                    'status' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'null' => false,
                        'default' => 'ACTIVE',
                    ],
                ]);
                $this->connection()->resetDataCache();
            }
        }
        // Existing installations already have the Resource Catalog. Introduce
        // the new protected action without auto-granting it to existing users.
        foreach (['resource', 'policy'] as $type) {
            $query = $this->connection()->table('resources')->where('type', $type)->get();
            if ($query === false) {
                throw new RuntimeException('Failed to read Resource catalog during migration.');
            }
            $resource = $query->getRowArray();
            if ($resource === null) {
                continue;
            }
            $resourceId = (int) $resource['id'];
            if ($this->connection()->table('resource_actions')
                ->where('resource_id', $resourceId)->where('code', 'setstate')
                ->countAllResults() === 0) {
                $this->connection()->table('resource_actions')->insert([
                    'resource_id' => $resourceId,
                    'code' => 'setstate',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        // Global PBAC state IDs are needed even for resources without a
        // Lifecycle. Do not alter the status of pre-existing Workflow States.
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED', 'PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'] as $code) {
            if ($this->connection()->table('workflow_states')->where('code', $code)->countAllResults() > 0) {
                continue;
            }
            $this->connection()->table('workflow_states')->insert([
                'code' => $code,
                'name' => $code,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->connection()->resetDataCache();
    }

    public function down(): void
    {
        // Refuse a rollback that silently reactivates intentionally disabled
        // Policies or Resources by deleting their persisted statuses.
        $this->connection()->resetDataCache();
        foreach (['policies', 'resources'] as $table) {
            if ($this->connection()->fieldExists('status', $table)) {
                $count = $this->connection()->table($table)->where('status !=', 'ACTIVE')->countAllResults();
                if ($count > 0) {
                    throw new RuntimeException('NATIVE_STATE_ROLLBACK_BLOCKED: inactive records exist in ' . $table);
                }
            }
        }
        foreach (['policies', 'resources'] as $table) {
            if ($this->connection()->fieldExists('status', $table)) {
                $this->forge->dropColumn($table, 'status');
                $this->connection()->resetDataCache();
            }
        }
        $this->connection()->resetDataCache();
    }
}

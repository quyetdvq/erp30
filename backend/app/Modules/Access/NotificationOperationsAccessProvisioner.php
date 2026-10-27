<?php

declare(strict_types=1);

namespace App\Modules\Access;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * E1.2A opt-in catalog provisioning only: one SYSTEM Resource + VIEW Action.
 * No Base Permission, Shield, ERP group or policy grants are created or changed.
 * Never called from a web request, migration, or an existing global seeder.
 */
final class NotificationOperationsAccessProvisioner
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function provision(): void
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('NFX_E12A_PROVISION_TRANSACTION_FAILED');
        }
        try {
            $resource = $this->db->table('resources')
                ->where('type', 'notification_operations')->get()->getRowArray();
            if ($resource === null) {
                if (! $this->db->table('resources')->insert([
                    'type' => 'notification_operations', 'name' => 'notification_operations',
                    'domain' => 'SYSTEM', 'status' => 'ACTIVE', 'lifecycle_id' => null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('NFX_E12A_PROVISION_RESOURCE_FAILED');
                }
                $resource = $this->db->table('resources')
                    ->where('type', 'notification_operations')->get()->getRowArray();
            }
            if ($resource === null || ($resource['type'] ?? null) !== 'notification_operations'
                || ($resource['domain'] ?? null) !== 'SYSTEM'
                || ($resource['status'] ?? null) !== 'ACTIVE'
                || ($resource['lifecycle_id'] ?? null) !== null) {
                // Never revive an inactive resource or rewrite security metadata.
                throw new RuntimeException('NFX_E12A_PROVISION_RESOURCE_CONFLICT');
            }
            $id = (string) $resource['id'];
            $actions = $this->db->table('resource_actions')
                ->select('code')->where('resource_id', $id)->get()->getResultArray();
            $viewCount = 0;
            foreach ($actions as $action) {
                if (($action['code'] ?? null) !== 'view') {
                    // E1.2A is strictly VIEW-only. Unknown Actions need manual review.
                    throw new RuntimeException('NFX_E12A_PROVISION_ACTION_CONFLICT');
                }
                ++$viewCount;
            }
            if ($viewCount > 1) {
                throw new RuntimeException('NFX_E12A_PROVISION_ACTION_CONFLICT');
            }
            if ($viewCount === 0) {
                // A stale direct Shield string without a catalog Action would
                // become effective after creation; require explicit review.
                $permissionTable = config('Auth')->tables['permissions_users'];
                if ($this->db->table($permissionTable)
                    ->where('permission', 'notification_operations.view')->countAllResults() !== 0) {
                    throw new RuntimeException('NFX_E12A_PROVISION_PREEXISTING_GRANTS');
                }
                if (! $this->db->table('resource_actions')->insert([
                    'resource_id' => $id, 'code' => 'view', 'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('NFX_E12A_PROVISION_ACTION_FAILED');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('NFX_E12A_PROVISION_COMMIT_FAILED');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}

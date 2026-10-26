<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Modules\Notifications\NotificationCatalog;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** Additive and idempotent. Never grants a Base Permission or revives a policy. */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_SEED_TRANSACTION_FAILED');
        }
        try {
            $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
            if ($resource === null) {
                if (! $this->db->table('resources')->insert([
                    'type' => 'notification_setting', 'name' => 'notification_setting',
                    'domain' => 'SYSTEM', 'status' => 'ACTIVE', 'lifecycle_id' => null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('M11_SEED_RESOURCE_FAILED');
                }
                $resource = $this->db->table('resources')->where('type', 'notification_setting')->get()->getRowArray();
            }
            if ($resource === null || (string) $resource['domain'] !== 'SYSTEM' || $resource['lifecycle_id'] !== null) {
                throw new RuntimeException('M11_SEED_RESOURCE_CONFLICT');
            }
            $resourceId = (string) $resource['id'];
            foreach (['view', 'edit'] as $action) {
                $exists = $this->db->table('resource_actions')
                    ->where('resource_id', $resourceId)->where('code', $action)->countAllResults();
                if ($exists === 0 && ! $this->db->table('resource_actions')->insert([
                    'resource_id' => $resourceId, 'code' => $action, 'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('M11_SEED_ACTION_FAILED');
                }
            }
            foreach (NotificationCatalog::definitions() as $key => $definition) {
                $existing = $this->db->table('notification_settings')->where('setting_key', $key)->get()->getRowArray();
                if ($existing !== null) {
                    // Once installed, settings belong to administrators; never overwrite on re-seed.
                    continue;
                }
                if (! $this->db->table('notification_settings')->insert([
                    'setting_key' => $key,
                    'default_value' => $definition['default'] ? 1 : 0,
                    'allow_user_override' => $definition['override'] ? 1 : 0,
                    'revision' => '1', 'override_epoch' => '1',
                    'updated_by' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('M11_SEED_SETTING_FAILED');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('M11_SEED_COMMIT_FAILED');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}

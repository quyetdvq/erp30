<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Modules\Numbering\Configuration\NumberingRules;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** Explicit opt-in DEV provisioning: Customer default Numbering, never implicit in migrations. */
final class CustomerDefaultNumberingSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->tableExists('customers') || ! $this->db->tableExists('auto_numbering_series')) {
            throw new RuntimeException('CUS_NUMBERING_MIGRATIONS_REQUIRED');
        }
        if (! $this->db->transBegin()) throw new RuntimeException('CUS_NUMBERING_SEED_TRANSACTION_FAILED');
        try {
            // Serialize provisioning with NumberingService, which also locks the resource row.
            $sql = $this->db->table('resources')->where('type', 'customer')->getCompiledSelect().' FOR UPDATE';
            $result = $this->db->query($sql);
            if ($result === false) throw new RuntimeException('CUS_NUMBERING_RESOURCE_LOCK_FAILED');
            $resource = $result->getRowArray();
            if ($resource === null) throw new RuntimeException('CUS_RESOURCE_NOT_FOUND_SEED_CORE_FIRST');

            $existing = $this->db->table('auto_numbering_series')
                ->where('resource_id', $resource['id'])->get()->getResultArray();
            $defaults = array_values(array_filter($existing, static fn(array $row): bool =>
                strtoupper((string) $row['status']) === 'ACTIVE' && (int) $row['is_default'] === 1));
            if (count($defaults) > 1) throw new RuntimeException('CUS_NUMBERING_MULTIPLE_ACTIVE_DEFAULTS');
            if ($defaults !== []) {
                $default = $defaults[0];
                if (strtoupper((string) $default['scope_type']) !== 'GROUP'
                    || ! in_array(strtoupper((string) $default['generation_mode']),
                        ['AUTO_REQUIRED', 'AUTO_IF_EMPTY'], true)) {
                    throw new RuntimeException('CUS_NUMBERING_DEFAULT_NOT_GROUP_AUTO');
                }
                NumberingRules::validatePattern($default);
                if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                    throw new RuntimeException('CUS_NUMBERING_SEED_COMMIT_FAILED');
                }
                echo "CUS NUMBERING: existing ACTIVE default retained; nothing changed.\n";
                return;
            }
            // An existing series without a default is an admin configuration decision.
            // Do not silently select or override it.
            if ($existing !== []) {
                throw new RuntimeException('CUS_NUMBERING_SERIES_EXISTS_NO_DEFAULT_CONFIGURE_IN_UI');
            }
            $data = NumberingRules::normalize([
                'resource_id' => (string) $resource['id'],
                'series_code' => 'CUS_DEFAULT',
                'name' => 'Customer default code',
                'scope_type' => 'GROUP',
                'pattern' => 'KH{SEQ:06}',
                'reset_policy' => 'NEVER',
                'generation_mode' => 'AUTO_IF_EMPTY',
                'start_number' => 1,
                'increment_by' => 1,
                'is_default' => true,
                'status' => 'ACTIVE',
            ], true);
            NumberingRules::validatePattern($data);
            if (! $this->db->table('auto_numbering_series')->insert([
                ...$data, 'created_at' => gmdate('Y-m-d H:i:s'),
            ])) throw new RuntimeException('CUS_NUMBERING_SEED_INSERT_FAILED');
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('CUS_NUMBERING_SEED_COMMIT_FAILED');
            }
            echo "CUS NUMBERING: created GROUP CUS_DEFAULT, KH{SEQ:06}, AUTO_IF_EMPTY.\n";
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }
}

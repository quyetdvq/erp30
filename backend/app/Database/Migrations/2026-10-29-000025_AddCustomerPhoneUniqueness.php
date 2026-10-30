<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Modules\Customer\CustomerPhone;
use CodeIgniter\Database\Migration;
use InvalidArgumentException;
use RuntimeException;

/**
 * P2-CUS: Customer-wide phone uniqueness without rewriting display phones.
 * Run on a backed-up DEV clone first; pause Customer writes during this migration.
 * Existing missing numbers remain NULL until repaired; all new and edited
 * Customers are required to provide a complete phone/address profile.
 */
final class AddCustomerPhoneUniqueness extends Migration
{
    private const KEY_NAME = 'uq_customers_phone_key';

    public function up(): void
    {
        $table = $this->db->prefixTable('customers');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $table) !== 1) {
            throw new RuntimeException('CUS_PHONE_INVALID_TABLE_PREFIX');
        }
        // Preflight BEFORE altering the schema. Never silently merge duplicates.
        $existing = $this->db->table('customers')->select('id, phone')->orderBy('id', 'ASC')->get();
        if ($existing === false) throw new RuntimeException('CUS_PHONE_LEGACY_SCAN_FAILED');
        $seen = [];
        $keys = [];
        foreach ($existing->getResultArray() as $row) {
            $id = (string) $row['id'];
            $phone = $row['phone'];
            if ($phone === null || trim((string) $phone) === '') {
                $keys[$id] = null;
                continue; // Legacy incomplete rows require separate operator remediation.
            }
            try {
                $key = CustomerPhone::key((string) $phone);
            } catch (InvalidArgumentException $cause) {
                throw new RuntimeException('CUS_PHONE_LEGACY_INVALID_ID_' . $id, 0, $cause);
            }
            if (isset($seen[$key])) {
                throw new RuntimeException('CUS_PHONE_LEGACY_DUPLICATE_IDS_' . $seen[$key] . '_' . $id);
            }
            $seen[$key] = $id;
            $keys[$id] = $key;
        }

        // MariaDB DDL implicitly commits. Each stage is repeatable after an
        // interrupted migration; no DROP, mass replacement or data deletion.
        $column = $this->db->query(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'phone_key'],
        );
        if ($column === false) throw new RuntimeException('CUS_PHONE_KEY_COLUMN_INSPECTION_FAILED');
        if ((int) ($column->getRowArray()['total'] ?? 0) === 0) {
            $sql = 'ALTER TABLE `' . $table . '` ADD COLUMN phone_key '
                . 'VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL';
            if ($this->db->query($sql) === false) throw new RuntimeException('CUS_PHONE_KEY_ADD_FAILED');
        }
        foreach ($keys as $id => $key) {
            if (! $this->db->table('customers')->where('id', $id)->update(['phone_key' => $key])) {
                throw new RuntimeException('CUS_PHONE_KEY_BACKFILL_FAILED');
            }
        }
        $index = $this->db->query(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, self::KEY_NAME],
        );
        if ($index === false) throw new RuntimeException('CUS_PHONE_KEY_INDEX_INSPECTION_FAILED');
        if ((int) ($index->getRowArray()['total'] ?? 0) === 0) {
            if ($this->db->query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `'
                . self::KEY_NAME . '` (`phone_key`)') === false) {
                throw new RuntimeException('CUS_PHONE_KEY_UNIQUE_INDEX_FAILED');
            }
        }
    }

    public function down(): void
    {
        // Reverse schema/DML only with an explicit backup and DBA signoff.
        throw new RuntimeException('CUS_PHONE_UNIQUENESS_ROLLBACK_REQUIRES_MANUAL_REVIEW');
    }
}

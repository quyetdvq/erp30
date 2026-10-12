<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Workflow state codes are VARCHAR(50), so Lifecycle-managed target statuses
 * must persist the same vocabulary without truncating long state names.
 * Independent Resources and technical statuses remain unchanged.
 */
final class WidenBusinessWorkflowStatusCodes extends Migration
{
    private const TABLES = [
        'provinces', 'wards', 'organizations', 'contacts', 'departments',
        'positions', 'employees', 'product_groups', 'product_uoms',
        'products', 'price_lists',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $table = str_replace('`', '``', $this->db->prefixTable($name));
            $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `status` VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            $table = str_replace('`', '``', $this->db->prefixTable($name));
            $result = $this->db->query("SELECT COUNT(*) AS total FROM `{$table}` WHERE CHAR_LENGTH(`status`) > 20");
            if ((int) ($result->getRowArray()['total'] ?? 0) > 0) {
                throw new \RuntimeException('Refusing unsafe downgrade: business status exceeds 20 characters in ' . $name);
            }
        }
        foreach (self::TABLES as $name) {
            $table = str_replace('`', '``', $this->db->prefixTable($name));
            $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `status` VARCHAR(20) NOT NULL");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\UserDisplayMetadataResetPolicy;
use CodeIgniter\Database\Migration;

/** Add optional account display fields while leaving Shield identities intact. */
final class AddShieldUserDisplayFields extends Migration
{
    public function up(): void
    {
        $table = (string) config('Auth')->tables['users'];
        $this->forge->addColumn($table, [
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'avatar_filename' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $table = (string) config('Auth')->tables['users'];
        $quoted = str_replace('`', '``', $this->db->prefixTable($table));
        $row = $this->db->query("SELECT COUNT(*) AS total FROM `{$quoted}` WHERE full_name IS NOT NULL OR avatar_filename IS NOT NULL")->getRowArray();
        // CI DatabaseTestTrait refreshes migration batches before every test.
        // Only an explicitly opted-in PHPUnit run against disposable erp_test
        // may drop populated TEST columns; real downgrades still fail closed.
        if ((int) ($row['total'] ?? 0) > 0) {
            // The connected schema must also match: do not trust only the
            // configured database property if a DSN selected another schema.
            $active = $this->db->query('SELECT DATABASE() AS db_name')->getRowArray();
            if ((string) $this->db->database !== 'erp_test' || ! UserDisplayMetadataResetPolicy::allows(
                ENVIRONMENT,
                (string) ($active['db_name'] ?? ''),
                $_ENV['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] ?? null,
            )) {
                throw new \RuntimeException('Refusing downgrade: account display metadata exists.');
            }
        }
        $this->forge->dropColumn($table, ['full_name', 'avatar_filename']);
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\UserDisplayMetadataResetPolicy;
use CodeIgniter\Database\Migration;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;

/** Add optional account display fields while leaving Shield identities intact. */
final class AddShieldUserDisplayFields extends Migration
{
    /** @return BaseConnection<mixed,mixed> */
    private function connection(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \RuntimeException('Migration requires a CodeIgniter database connection.');
        }
        return $this->db;
    }

    /** @return ResultInterface<mixed,mixed> */
    private function requireResult(mixed $result): ResultInterface
    {
        if (! $result instanceof ResultInterface) {
            throw new \RuntimeException('Migration database query failed.');
        }
        return $result;
    }

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
        $quoted = str_replace('`', '``', $this->connection()->prefixTable($table));
        $rowResult = $this->connection()->query("SELECT COUNT(*) AS total FROM `{$quoted}` WHERE full_name IS NOT NULL OR avatar_filename IS NOT NULL");
        $row = $this->requireResult($rowResult)->getRowArray();
        // CI DatabaseTestTrait refreshes migration batches before every test.
        // Only an explicitly opted-in PHPUnit run against disposable erp_test
        // may drop populated TEST columns; real downgrades still fail closed.
        if ((int) ($row['total'] ?? 0) > 0) {
            // The connected schema must also match: do not trust only the
            // configured database property if a DSN selected another schema.
            $active = $this->requireResult($this->connection()->query('SELECT DATABASE() AS db_name'))->getRowArray();
            if ((string) $this->connection()->database !== 'erp_test' || ! UserDisplayMetadataResetPolicy::allows(
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

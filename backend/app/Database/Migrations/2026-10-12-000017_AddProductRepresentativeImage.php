<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;

/** One optional representative image per Product, regardless of its role/type. */
final class AddProductRepresentativeImage extends Migration
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
        $this->forge->addColumn('products', [
            'image_filename' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'image_mime' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'image_size_bytes' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $table = str_replace('`', '``', $this->connection()->prefixTable('products'));
        $result = $this->connection()->query("SELECT COUNT(*) AS total FROM `{$table}` WHERE image_filename IS NOT NULL");
        if ((int) ($this->requireResult($result)->getRowArray()['total'] ?? 0) > 0) {
            throw new \RuntimeException('Refusing downgrade: Product image data still exists.');
        }
        $this->forge->dropColumn('products', ['image_filename', 'image_mime', 'image_size_bytes']);
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddProductGroupHierarchy extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('product_groups', [
            'parent_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'id',
            ],
        ]);
        $this->forge->addKey('parent_id');
        $this->forge->addForeignKey('parent_id', 'product_groups', 'id');
        $this->forge->processIndexes('product_groups');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('product_groups', 'product_groups_parent_id_foreign');
        $this->forge->dropColumn('product_groups', 'parent_id');
    }
}

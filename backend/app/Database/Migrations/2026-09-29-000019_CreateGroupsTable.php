<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateGroupsTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('groups')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 255],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'description' => ['type' => 'TEXT', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->addKey('status');
            $this->forge->createTable('groups');
        }

        $groups = config('AuthGroups')->groups;
        $builder = $this->db->table('groups');

        foreach ($groups as $code => $definition) {
            $normalisedCode = strtolower((string) $code);

            if ($builder->where('code', $normalisedCode)->countAllResults() > 0) {
                $builder->resetQuery();
                continue;
            }

            $builder->resetQuery();
            $builder->insert([
                'code' => $normalisedCode,
                'name' => $definition['title'] ?? (string) $code,
                'description' => $definition['description'] ?? null,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $membershipTable = config('Auth')->tables['groups_users'];
        $memberships = [];

        // Shield may be migrated in the same --all run, so membership data is
        // optional at this point. The configured groups above are sufficient
        // to establish the ERP group catalog before Shield tables exist.
        if ($this->db->tableExists($membershipTable)) {
            $memberships = $this->db->table($membershipTable)
                ->select('group')
                ->distinct()
                ->get()
                ->getResultArray();
        }

        foreach ($memberships as $membership) {
            $code = strtolower(trim((string) $membership['group']));

            if ($code === '' || $builder->where('code', $code)->countAllResults() > 0) {
                $builder->resetQuery();
                continue;
            }

            $builder->insert([
                'code' => $code,
                'name' => $code,
                'description' => null,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('groups');
    }
}

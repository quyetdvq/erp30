<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * One-time explicit bootstrap for an already migrated and inspected ERP schema.
 * Never runs migrations or marks legacy migrations as applied.
 * See app/Database/README.md before running against an existing database.
 */
final class BackendBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WorkflowStateSeeder::class);
        $this->call(ResourceActionSeeder::class);
        $this->call(GroupRootSeeder::class);
        $this->call(PrivilegedAuthorizationGroupSeeder::class);
        if ($this->db->tableExists('customers')) {
            $this->call(CustomerCoreSeeder::class);
        }
    }
}

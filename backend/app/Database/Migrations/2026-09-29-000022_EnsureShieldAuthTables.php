<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Database\Migrations\CreateAuthTables;

/**
 * Ensures the Shield auth schema is present in databases bootstrapped before
 * the package migration namespace was registered by the application.
 */
final class EnsureShieldAuthTables extends Migration
{
    public function up(): void
    {
        $usersTable = config('Auth')->tables['users'];

        if ($this->db->tableExists($usersTable)) {
            return;
        }

        (new CreateAuthTables())->up();
    }

    public function down(): void
    {
        // Shield owns these tables. They are intentionally not removed here.
    }
}

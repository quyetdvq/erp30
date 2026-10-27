<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Modules\Access\NotificationOperationsAccessProvisioner;
use CodeIgniter\Database\Seeder;

/** Manual, explicit E1.2A catalog provisioning; NEVER grants user/group rights. */
final class NotificationOperationsAccessSeeder extends Seeder
{
    public function run(): void
    {
        (new NotificationOperationsAccessProvisioner($this->db))->provision();
    }
}

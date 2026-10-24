<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/** Existing users table is retained. These are the two ERP-owned columns. */
final class UserModel extends ShieldUserModel
{
    protected $allowedFields = [
        'username', 'status', 'status_message', 'active', 'last_active',
        'full_name', 'avatar_filename',
    ];
}

<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/** ERP-owned, additive metadata for the canonical Shield User. */
final class UserModel extends ShieldUserModel
{
    protected $allowedFields = [
        'username', 'status', 'status_message', 'active', 'last_active',
        'full_name', 'avatar_filename',
    ];
}

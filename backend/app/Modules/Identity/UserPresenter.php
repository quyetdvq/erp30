<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Access\BasePermissionReader;
use App\Shared\Media\ImageUrl;
use CodeIgniter\Shield\Entities\User;

final class UserPresenter
{
    public function __construct(private readonly BasePermissionReader $permissions = new BasePermissionReader())
    {
    }

    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        $groups = $user->getGroups() ?? [];
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->getEmail(),
            'name' => $user->full_name ?? null,
            'has_avatar' => is_string($user->avatar_filename ?? null) && $user->avatar_filename !== '',
            'avatar_url' => ImageUrl::for($user->avatar_filename ?? null, 'avatar'),
            'active' => (bool) $user->active,
            'status' => $user->status,
            'groups' => array_values(array_map('strval', $groups)),
            'base_permissions' => $this->permissions->forUser($user),
        ];
    }
}

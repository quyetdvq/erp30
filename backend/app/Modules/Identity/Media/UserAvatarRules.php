<?php

declare(strict_types=1);

namespace App\Modules\Identity\Media;

use App\Shared\Media\ImageUrl;
use App\Shared\Media\PublicImageStorage;

/** Pure baseline Avatar rules: strict owner identity, upload boundary and public projection. */
final class UserAvatarRules
{
    public static function isOwner(string $actorId, string $targetId): bool
    {
        return $actorId !== '' && $actorId === $targetId;
    }

    /** Returns the exact frozen error or null when multipart is suitable for byte inspection. */
    public static function uploadError(bool $valid, ?int $error, ?int $size): ?string
    {
        if (!$valid) {
            return in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'USER_AVATAR_INVALID_SIZE' : 'USER_AVATAR_INVALID_CONTENT';
        }
        if ($size === null || $size < 1 || $size > PublicImageStorage::AVATAR_MAX_BYTES) {
            return 'USER_AVATAR_INVALID_SIZE';
        }
        return null;
    }

    /** @return array{user_id:string,has_avatar:bool,avatar_url:?string} */
    public static function response(string $id, ?string $filename): array
    {
        $url = ImageUrl::for($filename, 'avatar');
        return ['user_id' => $id, 'has_avatar' => $filename !== null && $filename !== '', 'avatar_url' => $url];
    }

    /** Never delete a private/legacy filename or the still-current file. */
    public static function mayDeleteOld(?string $old, ?string $next): bool
    {
        return is_string($old) && $old !== '' && $old !== $next
            && ImageUrl::for($old, 'avatar') !== null;
    }
}

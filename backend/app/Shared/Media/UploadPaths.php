<?php

declare(strict_types=1);

namespace App\Shared\Media;

/**
 * Single physical root for uploaded files, relative to the v2 Backend root.
 * The legacy-compatible image URL remains /uploads/{year}/{filename}.
 * This resolver performs no database or image-byte reads.
 */
final class UploadPaths
{
    public static function root(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload';
    }

    public static function imagePath(?string $filename, string $type): ?string
    {
        if (ImageUrl::for($filename, $type) === null) {
            return null;
        }

        return self::root() . DIRECTORY_SEPARATOR . substr($filename, 0, 4)
            . DIRECTORY_SEPARATOR . $filename;
    }
}

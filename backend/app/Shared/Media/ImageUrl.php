<?php

declare(strict_types=1);

namespace App\Shared\Media;

/** Deterministic URL for validated existing filenames; no file or SQL reads. */
final class ImageUrl
{
    public static function for(?string $filename, string $type): ?string
    {
        if (! in_array($type, ['avatar', 'product'], true) || ! is_string($filename)) {
            return null;
        }
        if (preg_match('/\A([0-9]{4})([0-9]{2})([0-9]{2})' . $type . '[0-9]{7}\.(?:jpg|png|webp)\z/D', $filename, $parts) !== 1
            || (int) $parts[1] < 2020 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }
        return '/uploads/' . $parts[1] . '/' . $filename;
    }
}

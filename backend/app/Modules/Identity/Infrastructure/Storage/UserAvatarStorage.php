<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Storage;

use App\Exceptions\DomainException;
use GdImage;
use RuntimeException;

/**
 * Private, metadata-free user avatar images. This layer does not authorize users
 * or update database records; its only persistent side effect is a random file.
 */
final class UserAvatarStorage
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_DIMENSION = 2048;

    private readonly string $directory;

    /** The optional directory is for isolated storage tests, never from HTTP input. */
    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? rtrim(WRITEPATH, '/\\')
            . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'user-avatars';
    }

    /** @return array{mime:string,extension:string,size:int,width:int,height:int} */
    public function inspect(string $temporaryFile): array
    {
        $size = is_file($temporaryFile) ? @filesize($temporaryFile) : false;
        if (! is_int($size) || $size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('USER_AVATAR_INVALID_SIZE', ['field' => 'image']);
        }

        $info = @getimagesize($temporaryFile);
        // Successful getimagesize() guarantees integer dimensions and a MIME
        // entry; the false check must precede all array reads.
        if ($info === false
            || $info[0] < 1 || $info[1] < 1
            || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
        if (! is_string($mime) || $mime !== $info['mime']) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw new DomainException('USER_AVATAR_INVALID_TYPE', ['field' => 'image']);
        }

        $bytes = @file_get_contents($temporaryFile);
        if (! is_string($bytes) || strlen($bytes) !== $size || $this->isAnimated($bytes, $mime)) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }
        $decoded = $this->decode($bytes);
        try {
            if (imagesx($decoded) !== $info[0] || imagesy($decoded) !== $info[1]) {
                throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
            }
        } finally {
            imagedestroy($decoded);
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $info[0],
            'height' => $info[1],
        ];
    }

    /** @return array{filename:string,mime:string} */
    public function store(string $temporaryFile): array
    {
        $validated = $this->inspect($temporaryFile);
        $bytes = @file_get_contents($temporaryFile);
        if (! is_string($bytes)) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }
        $decoded = $this->decode($bytes);
        try {
            $this->ensureDirectory();
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $filename = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
                $path = $this->path($filename);
                $stream = @fopen($path, 'x+b');
                if (! is_resource($stream)) {
                    continue;
                }
                try {
                    @chmod($path, 0600);
                    if ($validated['extension'] !== 'jpg') {
                        imagesavealpha($decoded, true);
                    }
                    try {
                        $written = match ($validated['extension']) {
                            'jpg' => @imagejpeg($decoded, $stream, 87),
                            'png' => @imagepng($decoded, $stream, 6),
                            'webp' => @imagewebp($decoded, $stream, 85),
                        };
                    } finally {
                        fclose($stream);
                    }
                    // Never leave a partly encoded or failed file behind.
                    $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
                    if (! $written || ! is_file($path) || @filesize($path) < 1
                        || $actualMime !== $validated['mime'] || ! is_array(@getimagesize($path))) {
                        throw new RuntimeException('USER_AVATAR_STORAGE_FAILED');
                    }
                    return ['filename' => $filename, 'mime' => $validated['mime']];
                } catch (\Throwable $exception) {
                    @unlink($path);
                    if ($exception instanceof RuntimeException) {
                        throw $exception;
                    }
                    throw new RuntimeException('USER_AVATAR_STORAGE_FAILED', 0, $exception);
                }
            }
            throw new RuntimeException('USER_AVATAR_STORAGE_FAILED');
        } finally {
            imagedestroy($decoded);
        }
    }

    public function path(string $filename): string
    {
        if (preg_match('/\A[a-f0-9]{40}\.(?:jpg|png|webp)\z/D', $filename) !== 1) {
            throw new DomainException('USER_AVATAR_NOT_FOUND');
        }
        return $this->directory . DIRECTORY_SEPARATOR . $filename;
    }

    public function delete(string $filename): void
    {
        $path = $this->path($filename);
        if ((is_file($path) || is_link($path)) && ! @unlink($path)) {
            throw new RuntimeException('USER_AVATAR_STORAGE_FAILED');
        }
    }

    private function ensureDirectory(): void
    {
        if (is_link($this->directory)) {
            throw new RuntimeException('USER_AVATAR_STORAGE_FAILED');
        }
        if (! is_dir($this->directory)
            && ! @mkdir($this->directory, 0700, true)
            && ! is_dir($this->directory)) {
            throw new RuntimeException('USER_AVATAR_STORAGE_FAILED');
        }
    }

    private function decode(string $bytes): GdImage
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagewebp') || ! function_exists('imagetypes')
            || (imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP)) !== (IMG_JPG | IMG_PNG | IMG_WEBP)) {
            throw new RuntimeException('USER_AVATAR_CODEC_UNAVAILABLE');
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }
        return $image;
    }

    private function isAnimated(string $bytes, string $mime): bool
    {
        if ($mime === 'image/png' && str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            // Parse PNG chunk headers; an APNG acTL chunk indicates animation.
            $offset = 8;
            $total = strlen($bytes);
            while ($offset + 12 <= $total) {
                $parsed = unpack('N', substr($bytes, $offset, 4));
                if ($parsed === false || ! isset($parsed[1])) {
                    return true; // Malformed container: reject as unsafe.
                }
                $length = $parsed[1];
                if ($length > $total - $offset - 12) {
                    break;
                }
                if (substr($bytes, $offset + 4, 4) === 'acTL') {
                    return true;
                }
                $offset += 12 + $length;
            }
        }
        if ($mime === 'image/webp' && strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF'
            && substr($bytes, 8, 4) === 'WEBP') {
            $offset = 12;
            $total = strlen($bytes);
            while ($offset + 8 <= $total) {
                $type = substr($bytes, $offset, 4);
                $parsed = unpack('V', substr($bytes, $offset + 4, 4));
                if ($parsed === false || ! isset($parsed[1])) {
                    return true; // Malformed container: reject as unsafe.
                }
                $length = $parsed[1];
                if ($length > $total - $offset - 8) {
                    break;
                }
                if ($type === 'ANIM' || $type === 'ANMF'
                    || ($type === 'VP8X' && $length > 0 && (ord($bytes[$offset + 8]) & 0x02) !== 0)) {
                    return true;
                }
                $offset += 8 + $length + ($length % 2);
            }
        }
        return false;
    }
}

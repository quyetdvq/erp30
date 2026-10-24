<?php

declare(strict_types=1);

namespace App\Shared\Media;

use App\Exceptions\DomainException;
use GdImage;
use RuntimeException;

/**
 * Public upload pipeline for Product and User Avatar. Authorization and DB
 * metadata stay with the owning module. Only validated images enter uploads/.
 */
final class PublicImageStorage
{
    public const MAX_DIMENSION = 2048;
    public const PRODUCT_MAX_BYTES = 3 * 1024 * 1024;
    public const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_SOURCE_PIXELS = 40_000_000; // Guard GD memory exhaustion.

    public function __construct(private readonly ?string $root = null)
    {
    }

    /** @return array{mime:string,extension:string,size:int,width:int,height:int} */
    public function inspect(string $file, string $type): array
    {
        self::assertType($type);
        $size = is_file($file) ? @filesize($file) : false;
        $limit = $type === 'product' ? self::PRODUCT_MAX_BYTES : self::AVATAR_MAX_BYTES;
        if (! is_int($size) || $size < 1 || $size > $limit) {
            throw new DomainException(self::error($type, 'INVALID_SIZE'), ['field' => 'image']);
        }
        $info = @getimagesize($file);
        if (! is_array($info) || ! isset($info[0], $info[1], $info['mime'])
            || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw new DomainException(self::error($type, 'INVALID_CONTENT'), ['field' => 'image']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (! is_string($mime) || $mime !== $info['mime']) {
            throw new DomainException(self::error($type, 'INVALID_CONTENT'), ['field' => 'image']);
        }
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw new DomainException(self::error($type, 'INVALID_TYPE'), ['field' => 'image']);
        }
        // Animated files must not be silently flattened to their first frame.
        if ($this->animated($file, $mime)) {
            throw new DomainException(self::error($type, 'INVALID_CONTENT'), ['field' => 'image']);
        }
        return ['mime' => $mime, 'extension' => $extension, 'size' => $size,
            'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $file, string $type): array
    {
        $info = $this->inspect($file, $type);
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagewebp') || ! function_exists('imagetypes')
            || (imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP)) !== (IMG_JPG | IMG_PNG | IMG_WEBP)) {
            throw new RuntimeException('IMAGE_CODEC_UNAVAILABLE');
        }
        $bytes = @file_get_contents($file);
        $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (! $source instanceof GdImage) {
            throw new DomainException(self::error($type, 'INVALID_CONTENT'), ['field' => 'image']);
        }
        try {
            $width = $info['width'];
            $height = $info['height'];
            $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
            $newWidth = max(1, (int) floor($width * $scale));
            $newHeight = max(1, (int) floor($height * $scale));
            $output = imagecreatetruecolor($newWidth, $newHeight);
            if (! $output instanceof GdImage) {
                throw new RuntimeException('IMAGE_STORAGE_FAILED');
            }
            try {
                if ($info['extension'] !== 'jpg') {
                    imagealphablending($output, false);
                    imagesavealpha($output, true);
                    imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
                }
                if (! imagecopyresampled($output, $source, 0, 0, 0, 0,
                    $newWidth, $newHeight, $width, $height)) {
                    throw new RuntimeException('IMAGE_STORAGE_FAILED');
                }
                return $this->save($output, $info['mime'], $info['extension'], $type);
            } finally {
                imagedestroy($output);
            }
        } finally {
            imagedestroy($source);
        }
    }

    /** Validate ownership before file cleanup or controlled migration; legacy paths are not served via HTTP. */
    public function path(string $filename, string $type): string
    {
        self::assertType($type);
        if (self::isNewFilename($filename, $type)) {
            if (is_link($this->base()) || is_link($this->base() . DIRECTORY_SEPARATOR . substr($filename, 0, 4))) {
                throw new DomainException(self::error($type, 'NOT_FOUND'));
            }
            return $this->base() . DIRECTORY_SEPARATOR . substr($filename, 0, 4)
                . DIRECTORY_SEPARATOR . $filename;
        }
        throw new DomainException(self::error($type, 'NOT_FOUND'));
    }

    /** Static frontend URL; the physical file lives in public/upload/YYYY. */
    public static function publicUrl(?string $filename, string $type): ?string
    {
        if (! is_string($filename) || ! self::isNewFilename($filename, $type)) {
            return null; // Existing private files require a deliberate migration.
        }
        return ImageUrl::for($filename, $type);
    }

    public function delete(string $filename, string $type): void
    {
        $path = $this->path($filename, $type);
        if ((is_file($path) || is_link($path)) && ! @unlink($path)) {
            throw new RuntimeException('IMAGE_STORAGE_FAILED');
        }
    }

    private function save(GdImage $image, string $mime, string $extension, string $type): array
    {
        $now = time();
        // YYYYMMDD + type + last seven decimal Unix seconds. Exclusive 'x+b'
        // prevents overwrite; on collision reserve a succeeding second's name.
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $stamp = $now + $attempt;
            $date = gmdate('Ymd', $stamp);
            $folder = $this->base() . DIRECTORY_SEPARATOR . substr($date, 0, 4);
            $this->ensureFolder($folder);
            $filename = $date . $type . sprintf('%07d', $stamp % 10_000_000) . '.' . $extension;
            $path = $folder . DIRECTORY_SEPARATOR . $filename;
            $stream = @fopen($path, 'x+b');
            if (! is_resource($stream)) {
                if (file_exists($path) || is_link($path)) {
                    continue;
                }
                throw new RuntimeException('IMAGE_STORAGE_FAILED');
            }
            try {
                @chmod($path, 0644); // Restricted public/upload/ images only.
                $written = match ($extension) {
                    'jpg' => @imagejpeg($image, $stream, 87),
                    'png' => @imagepng($image, $stream, 6),
                    'webp' => @imagewebp($image, $stream, 85),
                };
                fclose($stream);
                if (! $written || ! is_file($path) || @filesize($path) < 1
                    || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== $mime) {
                    throw new RuntimeException('IMAGE_STORAGE_FAILED');
                }
                return ['filename' => $filename, 'mime' => $mime, 'size' => (int) filesize($path)];
            } catch (\Throwable $error) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                @unlink($path);
                throw $error;
            }
        }
        throw new RuntimeException('IMAGE_FILENAME_COLLISION');
    }

    private function base(): string
    {
        return $this->root ?? UploadPaths::root();
    }

    private function ensureFolder(string $folder): void
    {
        $base = $this->base();
        if (is_link($base) || is_link($folder)) {
            throw new RuntimeException('IMAGE_STORAGE_FAILED');
        }
        if ((! is_dir($folder) && ! @mkdir($folder, 0755, true) && ! is_dir($folder))
            || is_link($folder)) {
            throw new RuntimeException('IMAGE_STORAGE_FAILED');
        }
    }

    private static function isNewFilename(string $filename, string $type): bool
    {
        self::assertType($type);
        $pattern = '/\A([0-9]{4})([0-9]{2})([0-9]{2})' . $type . '[0-9]{7}\.(?:jpg|png|webp)\z/D';
        return preg_match($pattern, $filename, $parts) === 1
            && (int) $parts[1] >= 2020
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private static function assertType(string $type): void
    {
        if ($type !== 'product' && $type !== 'avatar') {
            throw new \InvalidArgumentException('Unrecognized image owner.');
        }
    }

    private static function error(string $type, string $suffix): string
    {
        return ($type === 'product' ? 'PRODUCT_IMAGE_' : 'USER_AVATAR_') . $suffix;
    }

    private function animated(string $file, string $mime): bool
    {
        if ($mime === 'image/jpeg') {
            return false;
        }
        $bytes = @file_get_contents($file);
        if (! is_string($bytes)) {
            return true;
        }
        if ($mime === 'image/png') {
            $offset = 8;
            $length = strlen($bytes);
            while ($offset + 12 <= $length) {
                $size = unpack('N', substr($bytes, $offset, 4));
                if (! isset($size[1]) || $size[1] > $length - $offset - 12) {
                    return true;
                }
                if (substr($bytes, $offset + 4, 4) === 'acTL') {
                    return true;
                }
                $offset += 12 + $size[1];
            }
            return false;
        }
        if ($mime === 'image/webp' && substr($bytes, 0, 4) === 'RIFF'
            && substr($bytes, 8, 4) === 'WEBP') {
            $offset = 12;
            $length = strlen($bytes);
            while ($offset + 8 <= $length) {
                $type = substr($bytes, $offset, 4);
                $size = unpack('V', substr($bytes, $offset + 4, 4));
                if (! isset($size[1]) || $size[1] > $length - $offset - 8) {
                    return true;
                }
                if ($type === 'ANIM' || $type === 'ANMF'
                    || ($type === 'VP8X' && $size[1] > 0 && (ord($bytes[$offset + 8]) & 0x02) !== 0)) {
                    return true;
                }
                $offset += 8 + $size[1] + ($size[1] % 2);
            }
        }
        return false;
    }
}

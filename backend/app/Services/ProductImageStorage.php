<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/** No public URLs or user-controlled filenames. Binary files live under WRITEPATH. */
final class ProductImageStorage
{
    public const MAX_BYTES = 3 * 1024 * 1024;
    public const MAX_DIMENSION = 5000;

    /** @return array{mime:string,extension:string,size:int} */
    public function inspect(string $temporaryFile): array
    {
        $size = is_file($temporaryFile) ? filesize($temporaryFile) : false;
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_SIZE', ['field' => 'image']);
        }
        $dimensions = @getimagesize($temporaryFile);
        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1], $dimensions['mime'])
            || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > self::MAX_DIMENSION || $dimensions[1] > self::MAX_DIMENSION) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_CONTENT', ['field' => 'image']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
        if (! is_string($mime) || $mime !== $dimensions['mime']) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_CONTENT', ['field' => 'image']);
        }
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_TYPE', ['field' => 'image']);
        }
        return ['mime' => $mime, 'extension' => $extension, 'size' => (int) $size];
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        $validated = $this->inspect($temporaryFile);
        $directory = $this->directory();
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Cannot create Product image directory.');
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
        if (! @copy($temporaryFile, $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new \RuntimeException('Unable to store Product image.');
        }
        @chmod($directory . DIRECTORY_SEPARATOR . $filename, 0640);
        return ['filename' => $filename, 'mime' => $validated['mime'], 'size' => $validated['size']];
    }

    public function path(string $filename): string
    {
        // Stored filename must never become an arbitrary path read primitive.
        if (preg_match('/\A[a-f0-9]{40}\.(?:jpg|png|webp)\z/D', $filename) !== 1) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        return $this->directory() . DIRECTORY_SEPARATOR . $filename;
    }

    public function delete(string $filename): void
    {
        $path = $this->path($filename);
        if (is_file($path) && ! @unlink($path)) {
            log_message('error', 'Cannot remove obsolete Product image');
        }
    }

    private function directory(): string
    {
        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'product-images';
    }
}

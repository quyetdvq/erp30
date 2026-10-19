<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Infrastructure\Storage;

use App\Modules\Product\Media\Ports\ProductImageStoragePort;
use App\Services\ProductImageStorage;

/** Preserve the existing private file format, generated filenames and cleanup behavior. */
final class LegacyProductImageStorageAdapter implements ProductImageStoragePort
{
    public function __construct(private readonly ProductImageStorage $storage)
    {
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        return $this->storage->store($temporaryFile);
    }

    public function path(string $filename): string
    {
        return $this->storage->path($filename);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function delete(string $filename): void
    {
        $this->storage->delete($filename);
    }
}

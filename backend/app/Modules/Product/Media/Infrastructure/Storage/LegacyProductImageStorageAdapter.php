<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Infrastructure\Storage;

use App\Modules\Product\Media\Ports\ProductImageStoragePort;
use App\Services\ProductImageStorage;

/** Compatibility adapter for authorized writes/deletion (no binary GET). */
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

    public function delete(string $filename): void
    {
        $this->storage->delete($filename);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Product\Media\Infrastructure\Storage\LocalProductImageStorage;

/** Legacy storage surface: public signatures, constants, path and errors are preserved. */
final class ProductImageStorage
{
    public const MAX_BYTES = LocalProductImageStorage::MAX_BYTES;
    public const MAX_DIMENSION = LocalProductImageStorage::MAX_DIMENSION;

    private ?LocalProductImageStorage $delegate = null;

    /** @return array{mime:string,extension:string,size:int} */
    public function inspect(string $temporaryFile): array
    {
        return $this->canonical()->inspect($temporaryFile);
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        return $this->canonical()->store($temporaryFile);
    }

    public function path(string $filename): string
    {
        return $this->canonical()->path($filename);
    }

    public function delete(string $filename): void
    {
        $this->canonical()->delete($filename);
    }

    private function canonical(): LocalProductImageStorage
    {
        return $this->delegate ??= new LocalProductImageStorage();
    }
}

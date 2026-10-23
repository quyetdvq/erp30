<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Infrastructure\Storage;

use App\Infrastructure\Media\YearlyImageStorage;
use App\Modules\Product\Media\Ports\ProductImageStoragePort;

/** Product-owned adapter: validation/resizing/filesystem is shared with User Avatar. */
final class LocalProductImageStorage implements ProductImageStoragePort
{
    public const MAX_BYTES = YearlyImageStorage::PRODUCT_MAX_BYTES;
    public const MAX_DIMENSION = YearlyImageStorage::MAX_DIMENSION;

    private YearlyImageStorage $files;

    public function __construct(?YearlyImageStorage $files = null)
    {
        $this->files = $files ?? new YearlyImageStorage();
    }

    /** @return array{mime:string,extension:string,size:int,width:int,height:int} */
    public function inspect(string $temporaryFile): array
    {
        return $this->files->inspect($temporaryFile, 'product');
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        return $this->files->store($temporaryFile, 'product');
    }

    public function path(string $filename): string
    {
        return $this->files->path($filename, 'product');
    }

    public function delete(string $filename): void
    {
        try {
            $this->files->delete($filename, 'product');
        } catch (\App\Exceptions\DomainException $exception) {
            throw $exception;
        } catch (\RuntimeException) {
            log_message('error', 'Could not remove obsolete Product image');
        }
    }
}

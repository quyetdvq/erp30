<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Ports;

/** The underlying private filesystem layout and file mutation semantics are unchanged. */
interface ProductImageStoragePort
{
    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array;

    public function path(string $filename): string;

    public function isFile(string $path): bool;

    public function delete(string $filename): void;
}

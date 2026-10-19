<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Ports;

/** Product-owned image metadata write boundary. */
interface ProductImageMetadataPort
{
    public function write(int $id, ?string $filename, ?string $mime, ?int $size): void;
}

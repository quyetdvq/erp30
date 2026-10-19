<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Application;

use App\Exceptions\DomainException;
use App\Modules\Product\Media\Ports\ProductImageMetadataPort;
use App\Modules\Product\Media\Ports\ProductImageStoragePort;
use App\Modules\Product\Media\Ports\ProductMediaAccessPort;

/**
 * PM-1: same authorize/store/metadata/cleanup/readback order as legacy ProductImageService.
 * No implicit transaction: filesystem effects must remain observable after caller rollback.
 */
final class ProductImageApplicationService
{
    public function __construct(
        private readonly ProductMediaAccessPort $access,
        private readonly ProductImageMetadataPort $metadata,
        private readonly ProductImageStoragePort $storage,
    ) {
    }

    /** @return array{path:string,mime:string} */
    public function read(int $id): array
    {
        $row = $this->access->authorizedProduct($id, 'view');
        $filename = $row['image_filename'] ?? null;
        $mime = $row['image_mime'] ?? null;
        if (! is_string($filename) || $filename === '' || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        $path = $this->storage->path($filename);
        if (! $this->storage->isFile($path)) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        return ['path' => $path, 'mime' => $mime];
    }

    /** @return array<string,mixed> */
    public function upload(int $id, string $temporaryFile): array
    {
        $row = $this->access->authorizedProduct($id, 'edit');
        $new = $this->storage->store($temporaryFile);
        try {
            $this->metadata->write($id, $new['filename'], $new['mime'], $new['size']);
        } catch (\Throwable $exception) {
            $this->storage->delete($new['filename']);
            throw $exception;
        }
        if (is_string($row['image_filename'] ?? null) && $row['image_filename'] !== '') {
            $this->storage->delete($row['image_filename']);
        }
        return $this->access->publicProduct($id);
    }

    /** @return array<string,mixed> */
    public function remove(int $id): array
    {
        $row = $this->access->authorizedProduct($id, 'edit');
        $this->metadata->write($id, null, null, null);
        if (is_string($row['image_filename'] ?? null) && $row['image_filename'] !== '') {
            $this->storage->delete($row['image_filename']);
        }
        return $this->access->publicProduct($id);
    }
}

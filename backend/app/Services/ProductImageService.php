<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\ConnectionInterface;

/** Product.view for binary reads; Product.edit for upload/replace/delete. */
final class ProductImageService
{
    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private Api050ProductCatalogService $catalog;
    private ProductImageStorage $storage;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?Api050ProductCatalogService $catalog = null,
        ?ProductImageStorage $storage = null,
    ) {
        $this->db = $db ?? db_connect();
        $this->catalog = $catalog ?? new Api050ProductCatalogService(db: $this->db);
        $this->storage = $storage ?? new ProductImageStorage();
    }

    /** @return array{path:string,mime:string} */
    public function read(int $id): array
    {
        $row = $this->catalog->productForImage($id, 'view');
        $filename = $row['image_filename'] ?? null;
        $mime = $row['image_mime'] ?? null;
        if (! is_string($filename) || $filename === '' || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        $path = $this->storage->path($filename);
        if (! is_file($path)) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        return ['path' => $path, 'mime' => $mime];
    }

    /** @return array<string,mixed> */
    public function upload(int $id, string $temporaryFile): array
    {
        $row = $this->catalog->productForImage($id, 'edit');
        $new = $this->storage->store($temporaryFile);
        try {
            $this->writeMetadata($id, $new['filename'], $new['mime'], $new['size']);
        } catch (\Throwable $exception) {
            $this->storage->delete($new['filename']);
            throw $exception;
        }
        if (is_string($row['image_filename'] ?? null) && $row['image_filename'] !== '') {
            $this->storage->delete($row['image_filename']);
        }
        return $this->catalog->getProduct($id);
    }

    /** @return array<string,mixed> */
    public function remove(int $id): array
    {
        $row = $this->catalog->productForImage($id, 'edit');
        $this->writeMetadata($id, null, null, null);
        if (is_string($row['image_filename'] ?? null) && $row['image_filename'] !== '') {
            $this->storage->delete($row['image_filename']);
        }
        return $this->catalog->getProduct($id);
    }

    private function writeMetadata(int $id, ?string $filename, ?string $mime, ?int $size): void
    {
        try {
            $values = [
                'image_filename' => $filename,
                'image_mime' => $mime,
                'image_size_bytes' => $size,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $actor = auth()->user();
            if ($actor !== null && is_numeric($actor->id ?? null)) {
                $values['updated_by'] = (int) $actor->id;
            }
            $updated = $this->db->table('products')->where('id', $id)->update($values);
            if ($updated === false) {
                throw new \RuntimeException('Product image metadata write failed');
            }
        } catch (\Throwable $exception) {
            throw new PersistenceException('PRODUCT_PERSISTENCE_FAILED', 'writeProductImage', $exception);
        }
    }
}

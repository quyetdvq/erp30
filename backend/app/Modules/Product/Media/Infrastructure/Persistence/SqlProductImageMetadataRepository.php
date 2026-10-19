<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Infrastructure\Persistence;

use App\Exceptions\PersistenceException;
use App\Modules\Product\Media\Ports\ProductImageMetadataPort;
use CodeIgniter\Database\ConnectionInterface;

/** Retains the exact legacy SQL write, process-local timestamp and current Shield actor. */
final class SqlProductImageMetadataRepository implements ProductImageMetadataPort
{
    /** @param ConnectionInterface<mixed,mixed> $db */
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function write(int $id, ?string $filename, ?string $mime, ?int $size): void
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

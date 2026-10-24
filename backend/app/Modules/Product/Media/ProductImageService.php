<?php

declare(strict_types=1);

namespace App\Modules\Product\Media;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Product\Master\ProductReadService;
use App\Shared\Media\ImageUrl;
use App\Shared\Media\PublicImageStorage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Product-owned representative image mutations, on the SAME MariaDB mutex as
 * Product Master writes. File contents never pass through a read controller.
 *
 * Filesystem and SQL cannot commit atomically. A new file is staged before
 * the SQL commit and removed on failure; a replaced file is unlinked only
 * after the metadata commit. Failed best-effort old-file cleanup is logged.
 */
final class ProductImageService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly PublicImageStorage $images;

    public function __construct(
        private readonly User $actor,
        ?BaseConnection $db = null,
        ?PublicImageStorage $images = null,
    ) {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->images = $images ?? new PublicImageStorage();
    }

    /** @return array<string,mixed> */
    public function upload(string $rawId, ?UploadedFile $file): array
    {
        return $this->mutate($rawId, $file, false);
    }

    /** @return array<string,mixed> */
    public function remove(string $rawId): array
    {
        return $this->mutate($rawId, null, true);
    }

    /** @return array<string,mixed> */
    private function mutate(string $rawId, ?UploadedFile $file, bool $remove): array
    {
        try { $id = PositiveId::value($rawId); }
        catch (InvalidArgumentException $cause) {
            throw new DomainException('PRODUCT_NOT_FOUND', ['field'=>'id'], $cause);
        }
        if ($this->db->transDepth !== 0) {
            throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'nested_media_transaction']);
        }

        // Must match the ProductMutationService mutex exactly. Serializes
        // image edits with concurrent Product PATCH and SETSTATE writes.
        $name = 'dinkerp:' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'development') . ':'
            . $this->db->getDatabase() . ':product-master';
        if (strlen($name) > 64) $name = substr($name, 0, 30) . ':' . substr(hash('sha256', $name), 0, 32);
        $lock = $this->db->query('SELECT GET_LOCK(' . $this->db->escape($name) . ', 10) AS acquired');
        if ($lock === false || (int) ($lock->getRowArray()['acquired'] ?? 0) !== 1) {
            throw new DomainException('PRODUCT_MUTATION_LOCK_FAILED');
        }

        $newFilename = null;
        $oldFilename = null;
        try {
            if (! $this->db->transBegin()) {
                throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'media_begin']);
            }
            try {
                $row = $this->lockedProduct($id);
                // Security precedence: persisted PBAC before multipart validation.
                if (! $this->access->canView('product', $row)) {
                    throw new PageNotFoundException('Product is not available.');
                }
                if (! $this->access->decideMutation('product', 'edit', $row)['allowed']) {
                    throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'edit']);
                }
                $oldFilename = is_string($row['image_filename'] ?? null) ? $row['image_filename'] : null;

                if (! $remove) {
                    $this->assertUpload($file);
                    try { $stored = $this->images->store($file->getTempName(), 'product'); }
                    catch (DomainException $error) { throw $error; }
                    catch (Throwable $cause) {
                        throw new DomainException('PRODUCT_IMAGE_STORAGE_FAILED', [], $cause);
                    }
                    $newFilename = $stored['filename'];
                }

                try {
                    $updated = $this->db->table('products')->where('id', $id)->update([
                        'image_filename' => $newFilename,
                        // Match baseline nullable legacy columns: filename is the
                        // canonical persisted reference, not client-supplied MIME.
                        'image_mime' => null,
                        'image_size_bytes' => null,
                        'updated_by' => (int) $this->actor->id,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable $cause) {
                    throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'media_metadata'], $cause);
                }
                if ($updated === false || ! $this->db->transStatus()) {
                    throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'media_metadata']);
                }
                if (! $this->db->transCommit()) {
                    throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'media_commit']);
                }
            } catch (Throwable $cause) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                // Never leave an orphan from a failed metadata transaction.
                if ($newFilename !== null) $this->cleanup($newFilename);
                throw $cause;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(' . $this->db->escape($name) . ')');
        }

        // Old legacy private SHA filenames are deliberately NOT accessible
        // under public/upload and are left untouched for controlled migration.
        if ($oldFilename !== null && $oldFilename !== $newFilename
            && ImageUrl::for($oldFilename, 'product') !== null) {
            $this->cleanup($oldFilename);
        }
        return (new ProductReadService($this->actor, $this->db))->show($id);
    }

    /** @return array<string,mixed> */
    private function lockedProduct(string $id): array
    {
        $builder = $this->db->table('products')->where('id', $id);
        $query = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        if ($query === false) {
            throw new DomainException('PRODUCT_MUTATION_FAILED', ['operation'=>'media_select']);
        }
        $row = $query->getRowArray();
        if ($row === null) throw new DomainException('PRODUCT_NOT_FOUND');
        return $row;
    }

    private function assertUpload(?UploadedFile $file): void
    {
        if ($file === null || ! $file->isValid()) {
            $error = $file?->getError();
            $code = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'PRODUCT_IMAGE_INVALID_SIZE' : 'PRODUCT_IMAGE_INVALID_CONTENT';
            throw new DomainException($code, ['field'=>'image']);
        }
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > PublicImageStorage::PRODUCT_MAX_BYTES) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_SIZE', ['field'=>'image']);
        }
    }

    private function cleanup(string $filename): void
    {
        try { $this->images->delete($filename, 'product'); }
        catch (Throwable $error) {
            // DB commit cannot be undone here. Never lie to the client with a
            // post-commit 500; log orphan for a separate, audited cleanup.
            log_message('error', 'Product image cleanup failed for {filename}: {message}', [
                'filename'=>$filename, 'message'=>$error->getMessage(),
            ]);
        }
    }
}

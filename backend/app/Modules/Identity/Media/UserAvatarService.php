<?php

declare(strict_types=1);

namespace App\Modules\Identity\Media;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Media\PublicImageStorage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Shield User-owned avatar upload/replacement/deletion.
 * An authenticated owner may manage only their own avatar without user.edit;
 * a different target requires persisted target-scoped user.view + user.edit PBAC.
 *
 * The image is staged first, then compare-and-swap metadata is committed.
 * A failed transaction removes the new image; the old public file is removed
 * only after commit, only if no other Shield User still references it.
 */
final class UserAvatarService
{
    private readonly BaseConnection $db;
    private readonly PublicImageStorage $storage;

    public function __construct(
        private readonly User $actor,
        ?BaseConnection $db = null,
        ?PublicImageStorage $storage = null,
    ) {
        $this->db = $db ?? db_connect();
        $this->storage = $storage ?? new PublicImageStorage();
    }

    /** @return array{user_id:string,has_avatar:bool,avatar_url:?string} */
    public function upload(string $rawId, ?UploadedFile $file): array
    {
        $id = $this->id($rawId);
        $row = $this->authorizedRow($id); // PBAC BEFORE interpreting multipart errors.
        $this->assertUpload($file);
        $this->assertIndependentTransaction();

        try {
            $created = $this->storage->store($file->getTempName(), 'avatar');
        } catch (DomainException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $error);
        }
        $new = $created['filename'];
        $old = $this->oldName($row);
        try {
            $this->persist($id, $old, $new, $row);
        } catch (Throwable $error) {
            $this->cleanup($new, $id);
            throw $error;
        }
        if (UserAvatarRules::mayDeleteOld($old, $new)) {
            $this->cleanupOldIfUnreferenced($old, $id);
        }
        return UserAvatarRules::response($id, $new);
    }

    /** @return array{user_id:string,has_avatar:bool,avatar_url:?string} */
    public function remove(string $rawId): array
    {
        $id = $this->id($rawId);
        $row = $this->authorizedRow($id);
        $this->assertIndependentTransaction();
        $old = $this->oldName($row);
        if ($old !== null) {
            $this->persist($id, $old, null, $row);
            if (UserAvatarRules::mayDeleteOld($old, null)) {
                $this->cleanupOldIfUnreferenced($old, $id);
            }
        }
        return UserAvatarRules::response($id, null);
    }

    private function id(string $raw): string
    {
        try {
            return PositiveId::value($raw);
        } catch (InvalidArgumentException $error) {
            throw new DomainException('USER_NOT_FOUND', ['field' => 'id'], $error);
        }
    }

    /** @return array<string,mixed> */
    private function authorizedRow(string $id): array
    {
        try {
            $result = $this->db->table((string) config('Auth')->tables['users'])
                ->select('id, active, status, deleted_at, avatar_filename')
                ->where('id', $id)->where('deleted_at', null)->get();
            if ($result === false) throw new RuntimeException('Avatar target SELECT failed.');
            $row = $result->getRowArray();
        } catch (Throwable $error) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $error);
        }
        if (!is_array($row)) {
            throw new DomainException('USER_NOT_FOUND');
        }

        // Only the authenticated session's own Shield User ID receives the
        // narrow self-service exception. A client-supplied ID is not an actor.
        if (UserAvatarRules::isOwner((string) $this->actor->id, $id)) {
            return $row;
        }
        $access = new UnifiedAccessService($this->actor, $this->db);
        if (!$access->canView('user', $row)) {
            throw new DomainException('USER_NOT_FOUND');
        }
        if (!$access->decideMutation('user', 'edit', $row)['allowed']) {
            throw new DomainException('USER_AUTHORIZATION_DENIED', ['action' => 'edit']);
        }
        return $row;
    }

    private function assertUpload(?UploadedFile $file): void
    {
        $size = $file?->getSize();
        $code = UserAvatarRules::uploadError(
            $file !== null && $file->isValid(),
            $file?->getError(),
            is_int($size) ? $size : null,
        );
        if ($code !== null) {
            throw new DomainException($code, ['field' => 'image']);
        }
    }

    private function assertIndependentTransaction(): void
    {
        // No outer commit may resurrect a pointer to an image we already deleted.
        if ($this->db->transDepth !== 0) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED');
        }
    }

    /** Compare-and-swap catches concurrent PUT/POST/DELETE or User state changes. */
    private function persist(string $id, ?string $expected, ?string $next, array $snapshot): void
    {
        if (!$this->db->transBegin()) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED');
        }
        try {
            $updated = $this->db->table((string) config('Auth')->tables['users'])
                ->where('id', $id)->where('deleted_at', null)
                ->where('status', (string) $snapshot['status'])
                ->where('active', (int) $snapshot['active'])
                ->where('avatar_filename', $expected)
                ->update(['avatar_filename' => $next, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            if ($updated === false || !$this->db->transStatus()) {
                throw new DomainException('USER_AVATAR_STORAGE_FAILED');
            }
            if ($this->db->affectedRows() !== 1) {
                throw new DomainException('USER_AVATAR_CONCURRENT_UPDATE');
            }
            if (!$this->db->transCommit()) {
                throw new DomainException('USER_AVATAR_STORAGE_FAILED');
            }
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($error instanceof DomainException) throw $error;
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $error);
        }
    }

    /** @param array<string,mixed> $row */
    private function oldName(array $row): ?string
    {
        $name = $row['avatar_filename'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    private function cleanup(string $filename, string $id): void
    {
        try {
            $this->storage->delete($filename, 'avatar');
        } catch (Throwable) {
            log_message('warning', 'User avatar newly staged image cleanup deferred for user #{id}', ['id' => $id]);
        }
    }

    private function cleanupOldIfUnreferenced(string $old, string $id): void
    {
        try {
            $count = $this->db->table((string) config('Auth')->tables['users'])
                ->where('avatar_filename', $old)->countAllResults();
            if ($count === 0) {
                $this->storage->delete($old, 'avatar');
            }
        } catch (Throwable) {
            // SQL committed; do not falsely return 500 or restore old metadata.
            log_message('warning', 'User avatar orphan cleanup deferred for user #{id}', ['id' => $id]);
        }
    }
}

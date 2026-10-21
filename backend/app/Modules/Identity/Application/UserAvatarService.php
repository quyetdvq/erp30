<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Authorization\AuthorizationService;
use App\Modules\Identity\Infrastructure\Storage\UserAvatarStorage;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/** Owns avatar authorization and conditional metadata transitions; never serves public file URLs. */
final class UserAvatarService
{
    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private UserAvatarStorage $storage;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        private readonly ?AuthorizationService $authorization = null,
        ?UserAvatarStorage $storage = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
        $this->db = $db ?? db_connect();
        $this->storage = $storage ?? new UserAvatarStorage();
    }

    /** @return array{path:string,mime:string} */
    public function read(int $userId): array
    {
        $user = $this->authorizedRow($userId, 'view');
        $filename = $this->avatarName($user);
        if ($filename === null) {
            throw new DomainException('USER_AVATAR_NOT_FOUND');
        }
        try {
            $path = $this->storage->path($filename);
            if (is_link($path) || ! is_file($path)) {
                throw new DomainException('USER_AVATAR_NOT_FOUND');
            }
            $mime = match (pathinfo($filename, PATHINFO_EXTENSION)) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => throw new DomainException('USER_AVATAR_NOT_FOUND'),
            };
            if ((new \finfo(FILEINFO_MIME_TYPE))->file($path) !== $mime) {
                throw new DomainException('USER_AVATAR_NOT_FOUND');
            }
            return ['path' => $path, 'mime' => $mime];
        } catch (DomainException $exception) {
            throw new DomainException('USER_AVATAR_NOT_FOUND', [], $exception);
        }
    }

    /** Explicit preflight for the HTTP adapter, before inspecting multipart details. */
    public function assertCanMutate(int $userId): void
    {
        $this->authorizedRow($userId, 'edit');
    }

    /** @return array{user_id:string,has_avatar:bool} */
    public function upload(int $userId, string $temporaryFile): array
    {
        $row = $this->authorizedRow($userId, 'edit');
        try {
            $new = $this->storage->store($temporaryFile);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $exception);
        }

        try {
            $this->persistAvatarFilename($userId, $this->avatarName($row), $new['filename'], $row);
        } catch (Throwable $exception) {
            $this->discardNewFile($new['filename'], $userId);
            throw $exception;
        }

        $old = $this->avatarName($row);
        if ($old !== null) {
            $this->discardOrphanedFile($old, $userId);
        }
        return ['user_id' => (string) $userId, 'has_avatar' => true];
    }

    /** @return array{user_id:string,has_avatar:bool} */
    public function remove(int $userId): array
    {
        $row = $this->authorizedRow($userId, 'edit');
        $old = $this->avatarName($row);
        if ($old !== null) {
            $this->persistAvatarFilename($userId, $old, null, $row);
            $this->discardOrphanedFile($old, $userId);
        }
        return ['user_id' => (string) $userId, 'has_avatar' => false];
    }

    /** @return array<string,mixed> */
    private function authorizedRow(int $userId, string $action): array
    {
        // Resolve the actor before consulting user existence or any image metadata.
        $operation = $this->operation();
        if ($userId < 1) {
            throw new DomainException('USER_NOT_FOUND');
        }
        try {
            $user = $this->db->table(config('Auth')->tables['users'])
                ->select('id, active, status, deleted_at, avatar_filename')
                ->where('id', $userId)->where('deleted_at', null)
                ->get();
            if ($user === false) {
                throw new \RuntimeException('User avatar lookup query failed.');
            }
            $user = $user->getRowArray();
        } catch (Throwable $exception) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $exception);
        }
        if (! is_array($user)) {
            throw new DomainException('USER_NOT_FOUND');
        }
        // Only an authenticated session identity, not a caller-supplied ID,
        // receives this narrow self-service exception to the PBAC user rights.
        if (! $operation->systemActor && $operation->actorUserId === $userId) {
            return $user;
        }

        $target = [
            'id' => (int) $user['id'],
            'active' => (bool) $user['active'],
            'status' => strtoupper((string) $user['status']),
        ];
        $authorizer = $this->authorization ?? new AuthorizationService(db: $this->db);
        $view = $authorizer->authorize(
            $operation, 'user', 'view',
            new EvaluationOrganizationRequest(persistedTarget: $target),
        );
        if (! $view->isAllowed()) {
            throw new DomainException('USER_NOT_FOUND');
        }
        if ($action === 'edit') {
            $edit = $authorizer->authorize(
                $operation, 'user', 'edit',
                new EvaluationOrganizationRequest(persistedTarget: $target),
            );
            if (! $edit->isAllowed()) {
                throw new DomainException('USER_AUTHORIZATION_DENIED');
            }
        }
        return $user;
    }

    /**
     * Compare-and-swap protects the current file from competing PUT/DELETE operations.
     * The expected status/active state also prevents writes to a target whose State changed.
     * @param array<string,mixed> $snapshot
     */
    private function persistAvatarFilename(int $userId, ?string $expected, ?string $next, array $snapshot): void
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new DomainException('USER_AVATAR_STORAGE_FAILED');
        }
        try {
            $ok = $this->db->table(config('Auth')->tables['users'])
                ->where('id', $userId)
                ->where('deleted_at', null)
                ->where('status', (string) $snapshot['status'])
                ->where('active', (int) $snapshot['active'])
                ->where('avatar_filename', $expected)
                ->update([
                    'avatar_filename' => $next,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($ok === false) {
                throw new DomainException('USER_AVATAR_STORAGE_FAILED');
            }
            if ($this->db->affectedRows() !== 1) {
                throw new DomainException('USER_AVATAR_CONCURRENT_UPDATE');
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('USER_AVATAR_STORAGE_FAILED');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('USER_AVATAR_STORAGE_FAILED', [], $exception);
        }
    }

    /** @param array<string,mixed> $row */
    private function avatarName(array $row): ?string
    {
        return is_string($row['avatar_filename'] ?? null) && $row['avatar_filename'] !== ''
            ? $row['avatar_filename']
            : null;
    }

    private function discardNewFile(string $filename, int $userId): void
    {
        try {
            $this->storage->delete($filename);
        } catch (Throwable) {
            log_message('warning', 'User avatar failed-upload cleanup failed for user #{id}', ['id' => $userId]);
        }
    }

    /** Old images are deleted only after metadata commit, and never while still referenced. */
    private function discardOrphanedFile(string $filename, int $userId): void
    {
        // Nested transactions cannot delete the old image yet: an enclosing
        // rollback would restore the database pointer to that same file.
        // Defer cleanup for the orphan sweeper instead of risking data loss.
        if ($this->db instanceof BaseConnection && $this->db->transDepth > 0) {
            return;
        }
        try {
            $references = $this->db->table(config('Auth')->tables['users'])
                ->where('avatar_filename', $filename)->countAllResults();
            if ($references === 0) {
                $this->storage->delete($filename);
            }
        } catch (Throwable) {
            // Keep the committed metadata and let a controlled orphan cleanup handle this.
            log_message('warning', 'User avatar orphan cleanup deferred for user #{id}', ['id' => $userId]);
        }
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id ?? null) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }
}

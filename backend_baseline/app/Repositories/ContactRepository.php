<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Modules\Contact\Application\Ports\ContactRepositoryInterface;
use App\Modules\Contact\Contracts\ContactErrors;
use App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint; existing callers also satisfy the Contact module port. */
final class ContactRepository implements ContactRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = ContactErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = ContactErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_OWNER_IMMUTABLE = ContactErrors::ERROR_OWNER_IMMUTABLE;
    public const ERROR_UPDATE_EMPTY = ContactErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = ContactErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = ContactErrors::ERROR_FIELD_REQUIRED;

    private readonly SqlContactRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlContactRepository(db: $db);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->delegate->find($id);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        return $this->delegate->insert($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        return $this->delegate->update($id, $data);
    }

    /** @return list<array<string, mixed>> */
    public function findByOwner(string $contactType, int $contactRefId, ?string $status = null): array
    {
        return $this->delegate->findByOwner($contactType, $contactRefId, $status);
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        return $this->delegate->search($filters);
    }

    public function ownerExists(string $table, int $ownerId): bool
    {
        return $this->delegate->ownerExists($table, $ownerId);
    }

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool
    {
        return $this->delegate->wardBelongsToProvince($wardId, $provinceId);
    }
}

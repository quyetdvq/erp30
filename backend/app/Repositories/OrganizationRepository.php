<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Bootstrap\OrganizationFactory;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Modules\Organization\Contracts\OrganizationErrors;
use App\Modules\Organization\Infrastructure\Persistence\SqlOrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint; existing callers also satisfy the module port. */
final class OrganizationRepository implements OrganizationRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = OrganizationErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = OrganizationErrors::ERROR_CONNECTION_INVALID;
    public const ERROR_UPDATE_EMPTY = OrganizationErrors::ERROR_UPDATE_EMPTY;
    public const ERROR_FIELD_UNSUPPORTED = OrganizationErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = OrganizationErrors::ERROR_FIELD_REQUIRED;

    private readonly SqlOrganizationRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = OrganizationFactory::repository(db: $db);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->delegate->find($id);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->delegate->findByCode($code);
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        return $this->delegate->findAll($filters);
    }

    public function provinceExists(int $provinceId): bool
    {
        return $this->delegate->provinceExists($provinceId);
    }

    public function wardExists(int $wardId): bool
    {
        return $this->delegate->wardExists($wardId);
    }

    public function wardBelongsToProvince(int $wardId, int $provinceId): bool
    {
        return $this->delegate->wardBelongsToProvince($wardId, $provinceId);
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
}

<?php

declare(strict_types=1);

namespace Tests\Support\Foundation;

use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Foundation\Application\Ports\LocationRepositoryInterface;
use Closure;

/** Test-only synchronization point. All reads/writes still use the real SQL repository. */
final class BeforeWardLockRepository implements LocationRepositoryInterface
{
    public function __construct(private readonly LocationRepositoryInterface $inner, private ?Closure $beforeLock)
    {
    }
    public function find(string $table, int $id): ?array { return $this->inner->find($table, $id); }
    public function findByCode(string $table, string $code): ?array { return $this->inner->findByCode($table, $code); }
    public function findAll(string $table): array { return $this->inner->findAll($table); }
    public function provinceExists(int $id): bool { return $this->inner->provinceExists($id); }
    public function wardBelongsToProvince(int $wardId, int $provinceId): bool { return $this->inner->wardBelongsToProvince($wardId, $provinceId); }
    public function wardHasConflictingAddress(int $wardId, int $newProvinceId): bool { return $this->inner->wardHasConflictingAddress($wardId, $newProvinceId); }
    public function lockWardForUpdate(int $id): ?array
    {
        $callback = $this->beforeLock;
        $this->beforeLock = null;
        if ($callback !== null) { $callback(); }
        return $this->inner->lockWardForUpdate($id);
    }
    public function insert(string $table, array $data, array $required, OperationContext $operation): int
    {
        return $this->inner->insert($table, $data, $required, $operation);
    }
    public function update(string $table, int $id, array $data, OperationContext $operation): bool
    {
        return $this->inner->update($table, $id, $data, $operation);
    }
}

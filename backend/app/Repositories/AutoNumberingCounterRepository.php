<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Allocation\Application\Ports\NumberingCounterRepositoryInterface;
use App\Modules\Numbering\Allocation\Infrastructure\Persistence\SqlNumberingCounterRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for Auto Numbering counter persistence. */
final class AutoNumberingCounterRepository implements NumberingCounterRepositoryInterface
{
    public const ERROR_PERSISTENCE_FAILED = SqlNumberingCounterRepository::ERROR_PERSISTENCE_FAILED;
    public const ERROR_CONNECTION_INVALID = SqlNumberingCounterRepository::ERROR_CONNECTION_INVALID;
    public const ERROR_TRANSACTION_REQUIRED = SqlNumberingCounterRepository::ERROR_TRANSACTION_REQUIRED;
    public const ERROR_INVALID_ID = SqlNumberingCounterRepository::ERROR_INVALID_ID;
    public const ERROR_INVALID_VALUE = SqlNumberingCounterRepository::ERROR_INVALID_VALUE;
    public const ERROR_DUPLICATE = SqlNumberingCounterRepository::ERROR_DUPLICATE;
    public const ERROR_FOREIGN_KEY = SqlNumberingCounterRepository::ERROR_FOREIGN_KEY;
    public const ERROR_NOT_FOUND = SqlNumberingCounterRepository::ERROR_NOT_FOUND;
    public const ERROR_UPDATE_FAILED = SqlNumberingCounterRepository::ERROR_UPDATE_FAILED;

    private readonly SqlNumberingCounterRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlNumberingCounterRepository($db);
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array
    {
        return $this->delegate->find($seriesId, $scopeOrganizationId, $periodKey);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array
    {
        return $this->delegate->findForUpdate($seriesId, $scopeOrganizationId, $periodKey);
    }

    public function insert(
        mixed $seriesId,
        mixed $scopeOrganizationId,
        string $periodKey,
        mixed $lastValue,
        ?OperationContext $operation = null,
    ): int {
        return $this->delegate->insert($seriesId, $scopeOrganizationId, $periodKey, $lastValue, $operation);
    }

    public function updateLastValue(mixed $counterId, mixed $lastValue, ?OperationContext $operation = null): void
    {
        $this->delegate->updateLastValue($counterId, $lastValue, $operation);
    }
}

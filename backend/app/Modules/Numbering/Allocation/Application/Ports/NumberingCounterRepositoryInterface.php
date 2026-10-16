<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Allocation\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

interface NumberingCounterRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array;

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array;

    public function insert(
        mixed $seriesId,
        mixed $scopeOrganizationId,
        string $periodKey,
        mixed $lastValue,
        ?OperationContext $operation = null,
    ): int;

    public function updateLastValue(mixed $counterId, mixed $lastValue, ?OperationContext $operation = null): void;
}

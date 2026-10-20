<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Exceptions\PersistenceException;

/** Shared SQL read contract, including the unchanged persistence error mapping. */
trait FindsRepositoryRow
{
    /** @param callable(): mixed $query
     *  @return array<string,mixed>|null
     */
    private function findRow(string $operation, callable $query): ?array
    {
        try {
            $result = $query();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }
}

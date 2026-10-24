<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/**
 * Narrow, read-only optimization seam for the two MASTER list operations.
 * Authorization decides visibility; this interface never grants permission.
 */
interface ProductMasterListSqlAuthorizationPort
{
    public function operation(): OperationContext;

    /**
     * null: unable to prove exact State buckets (fallback to every-row PBAC).
     * []: no visible State; COUNT/PAGE must both be empty.
     * @return list<array{status:string}>|null
     */
    public function authorizedBuckets(OperationContext $operation): ?array;

    /** @template T @param callable():T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;

    /** @param array<string,mixed> $row Original persisted record; no request-provided State. */
    public function recheckRowVisible(OperationContext $operation, array $row): bool;
}

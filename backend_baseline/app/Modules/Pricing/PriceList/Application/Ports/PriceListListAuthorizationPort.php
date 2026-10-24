<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Pricing-owned, read-only PBAC seam for GET /api/v1/price-lists.
 *
 * Authorization continues to decide eligibility, owner scope, persisted
 * State and policy precedence. No privileged lookup or skip-PBAC flag is
 * exposed by this contract. It is internal to the Pricing list path.
 */
interface PriceListListAuthorizationPort
{
    public function operation(): OperationContext;

    /**
     * @template T
     * @param callable():T $read
     * @return T
     */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;

    /**
     * null = optimization not proven safe; use full per-row PBAC fallback.
     * [] = verified deny-all, so SQL COUNT/page must both return zero.
     *
     * @param ConnectionInterface<mixed,mixed> $db Same session as read snapshot.
     * @return list<array{organization_id:int,status:string}>|null
     */
    public function authorizedBuckets(OperationContext $operation, ConnectionInterface $db): ?array;

    /**
     * Check the authoritative persisted row. If $operation is null, capture
     * the operation again for each fallback row (legacy API-050 behavior).
     * DomainException propagates for the caller to handle per original path.
     *
     * @param array<string,mixed> $row
     */
    public function rowAllowed(array $row, ?OperationContext $operation = null): bool;
}

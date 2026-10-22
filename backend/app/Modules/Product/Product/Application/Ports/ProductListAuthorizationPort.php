<?php

declare(strict_types=1);

namespace App\Modules\Product\Product\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

/**
 * Consumer-owned read-only PBAC seam for Product List.
 *
 * The Bootstrap adapter uses the established Authorization/Workflow entrypoints.
 * The Product list query does not import their legacy implementations or
 * decide policy precedence, owner scope, or resource lifecycle rules.
 * This is internal to Product; it is not a new public module/API contract.
 */
interface ProductListAuthorizationPort
{
    public function requiresRowStateAuthorization(): bool;

    public function operation(): OperationContext;

    /** Throw PRODUCT_AUTHORIZATION_DENIED on an ordinary view denial. */
    public function authorizeBaseView(): void;

    /** @param array<string,mixed> $row */
    public function rowVisible(array $row): bool;

    /** @param array<string,mixed> $row */
    public function recheckRowVisible(OperationContext $operation, array $row): bool;

    /**
     * Keep the existing scoped Authorization read-only batch around the SQL path.
     * @template T
     * @param callable():T $read
     * @return T
     */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;

    /**
     * null = bucket inference unsafe; retain exact per-row fallback.
     * [] = no authorized State buckets; COUNT must be zero.
     * @return list<string>|null
     */
    public function authorizedStatuses(OperationContext $operation): ?array;
}

<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom\Application\Ports;

/**
 * UOM List-only authorization boundary owned by the Product module.
 *
 * Authorization's existing PBAC service remains responsible for Base
 * Permission, State policies and the authoritative persisted target.
 * This read port must not become a generic authorization bypass.
 */
interface ProductUomListAuthorizationPort
{
    /** Equivalent to the API-050 resource lifecycle/state-policy probe. */
    public function requiresRowStateAuthorization(): bool;

    /** Raise the original PRODUCT_UOM_AUTHORIZATION_DENIED on denial. */
    public function authorizeBaseView(): void;

    /**
     * Evaluate product_uom.view against the persisted row.
     * Existing API-050 semantics capture a fresh operation for each row.
     *
     * @param array<string,mixed> $row
     */
    public function rowVisible(array $row): bool;
}

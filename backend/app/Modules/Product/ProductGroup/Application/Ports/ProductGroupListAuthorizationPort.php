<?php

declare(strict_types=1);

namespace App\Modules\Product\ProductGroup\Application\Ports;

/**
 * Product Group list-only PBAC boundary, owned by Product.
 *
 * Existing Authorization/Workflow remains authoritative. This port does not
 * grant privileged lookup or allow a caller to substitute Evaluation Org.
 */
interface ProductGroupListAuthorizationPort
{
    /** Does a lifecycle or state-scoped policy require persisted-row decisions? */
    public function requiresRowStateAuthorization(): bool;

    /** Check the original product_group.view base gate or throw its domain error. */
    public function authorizeBaseView(): void;

    /** @param array<string,mixed> $row Exact persisted Product Group row. */
    public function rowVisible(array $row): bool;
}

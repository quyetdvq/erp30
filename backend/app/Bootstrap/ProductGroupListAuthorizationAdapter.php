<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Product\ProductGroup\Application\Ports\ProductGroupListAuthorizationPort;

/**
 * Transitional API-050 composition adapter: no new Authorization dependency
 * from the canonical Product Group module into legacy facade/implementation.
 * Callbacks are bound by the trusted facade to its persisted-target PBAC.
 */
final class ProductGroupListAuthorizationAdapter implements ProductGroupListAuthorizationPort
{
    /**
     * @param \Closure():bool $requiresState
     * @param \Closure():void $authorizeBase
     * @param \Closure(array<string,mixed>):bool $rowDecision
     */
    public function __construct(
        private readonly \Closure $requiresState,
        private readonly \Closure $authorizeBase,
        private readonly \Closure $rowDecision,
    ) {
    }

    public function requiresRowStateAuthorization(): bool
    {
        return ($this->requiresState)();
    }

    public function authorizeBaseView(): void
    {
        ($this->authorizeBase)();
    }

    /** @param array<string,mixed> $row */
    public function rowVisible(array $row): bool
    {
        return ($this->rowDecision)($row);
    }
}

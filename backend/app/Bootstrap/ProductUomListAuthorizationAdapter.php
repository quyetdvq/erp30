<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Product\Uom\Application\Ports\ProductUomListAuthorizationPort;

/**
 * Transitional API-050 composition adapter for Product UOM list reads.
 *
 * Callbacks keep the facade's original lazy Authorization, Workflow state
 * resolution and per-row OperationContext timing. They are bound only by
 * the trusted API-050 facade, never by client-supplied arguments.
 */
final class ProductUomListAuthorizationAdapter implements ProductUomListAuthorizationPort
{
    /** @var \Closure():bool */
    private readonly \Closure $requiresState;
    /** @var \Closure():void */
    private readonly \Closure $authorizeBase;
    /** @var \Closure(array<string,mixed>):bool */
    private readonly \Closure $rowDecision;

    /**
     * @param \Closure():bool $requiresState
     * @param \Closure():void $authorizeBase
     * @param \Closure(array<string,mixed>):bool $rowDecision
     */
    public function __construct(
        \Closure $requiresState,
        \Closure $authorizeBase,
        \Closure $rowDecision,
    ) {
        $this->requiresState = $requiresState;
        $this->authorizeBase = $authorizeBase;
        $this->rowDecision = $rowDecision;
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

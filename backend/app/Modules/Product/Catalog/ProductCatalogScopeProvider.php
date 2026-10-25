<?php

declare(strict_types=1);

namespace App\Modules\Product\Catalog;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use InvalidArgumentException;

/** Canonical MASTER/GROUP-root PBAC for Product Group and UOM catalog reads. */
final class ProductCatalogScopeProvider implements ResourceScopeProvider
{
    public function __construct(private readonly string $type)
    {
        if (!in_array($type, ['product_group','product_uom'], true)) {
            throw new InvalidArgumentException('Unsupported Product catalog scope resource.');
        }
    }

    public function resource(): string { return $this->type; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master($this->type,
            $access->viewableMasterLifecycleStatuses($this->type));
    }
}

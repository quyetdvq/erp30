<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;

/** Reviewed Product MASTER scope; reuses canonical PBAC State decisions. */
final class ProductScopeProvider implements ResourceScopeProvider
{
    public function resource(): string { return 'product'; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master('product', $access->viewableMasterLifecycleStatuses('product'));
    }
}

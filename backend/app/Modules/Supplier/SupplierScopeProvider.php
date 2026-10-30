<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;

/** Group MASTER Supplier VIEW scope, evaluated with the canonical PBAC snapshot. */
final class SupplierScopeProvider implements ResourceScopeProvider
{
    public function resource(): string { return 'supplier'; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master('supplier',
            $access->viewableMasterLifecycleStatuses('supplier'));
    }
}

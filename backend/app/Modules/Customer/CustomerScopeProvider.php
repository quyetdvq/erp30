<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;

/** Reviewed GROUP/MASTER Customer VIEW scope, compiled by P1 canonical PBAC. */
final class CustomerScopeProvider implements ResourceScopeProvider
{
    public function resource(): string { return 'customer'; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master('customer',
            $access->viewableMasterLifecycleStatuses('customer'));
    }
}

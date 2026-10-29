<?php

declare(strict_types=1);

namespace App\Modules\Location\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use InvalidArgumentException;

/** Exact canonical MASTER VIEW statuses, never an HTTP-supplied owner. */
final class LocationMasterScopeProvider implements ResourceScopeProvider
{
    public function __construct(private readonly string $type)
    {
        if (!in_array($type, ['province', 'ward'], true)) {
            throw new InvalidArgumentException('Unreviewed Location scope resource.');
        }
    }

    public function resource(): string { return $this->type; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master($this->type, $access->viewableMasterLifecycleStatuses($this->type));
    }
}

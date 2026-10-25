<?php

declare(strict_types=1);

namespace App\Modules\People\Read;

use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\Query\ResourceScopeProvider;
use App\Modules\Access\UnifiedAccessService;
use InvalidArgumentException;

/** Reviewed MASTER scope for Employee, Department and Position only. */
final class PeopleMasterScopeProvider implements ResourceScopeProvider
{
    public function __construct(private readonly string $resource)
    {
        if (!in_array($resource, ['employee', 'department', 'position'], true)) {
            throw new InvalidArgumentException('Unreviewed People MASTER resource.');
        }
    }

    public function resource(): string { return $this->resource; }
    public function domain(): string { return 'MASTER'; }

    public function compile(UnifiedAccessService $access): AuthorizedSqlScope
    {
        return AuthorizedSqlScope::master(
            $this->resource,
            $access->viewableMasterLifecycleStatuses($this->resource),
        );
    }
}

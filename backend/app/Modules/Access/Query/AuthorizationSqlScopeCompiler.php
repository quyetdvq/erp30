<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use App\Modules\Access\UnifiedAccessService;
use UnexpectedValueException;

/** A single fail-closed entry point for reviewed resource SQL VIEW providers. */
final class AuthorizationSqlScopeCompiler
{
    private const REVIEWED_DOMAINS = ['product'=>'MASTER', 'product_group'=>'MASTER', 'product_uom'=>'MASTER', 'price_list'=>'ORGANIZATION', 'organization'=>'ORGANIZATION', 'employee_assignment'=>'ORGANIZATION', 'employee'=>'MASTER', 'department'=>'MASTER', 'position'=>'MASTER', 'province'=>'MASTER', 'ward'=>'MASTER', 'organization_contact'=>'ORGANIZATION', 'employee_contact'=>'ORGANIZATION', 'customer'=>'MASTER', 'customer_contact'=>'MASTER'];

    public function __construct(private readonly UnifiedAccessService $access) {}

    public function compile(ResourceScopeProvider $provider): AuthorizedSqlScope
    {
        if ((self::REVIEWED_DOMAINS[$provider->resource()] ?? null) !== $provider->domain()) {
            throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: unreviewed provider.');
        }
        $scope = $provider->compile($this->access);
        if ($scope->resource !== $provider->resource() || $scope->domain !== $provider->domain()) {
            throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: resource or domain mismatch.');
        }
        // Access restrictions are compiled by the canonical service; there is
        // no permissive catch/fallback to SELECT without a WHERE predicate.
        return $scope;
    }
}

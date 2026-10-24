<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Product\Product\Application\AuthorizedProductReadService;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/** Stateless composition for shared Product reads, independent of API-050. */
final class ProductReadAccessFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function create(?ConnectionInterface $db = null): AuthorizedProductReadService
    {
        $db ??= db_connect();
        return new AuthorizedProductReadService(
            new ProductMasterService(db: $db),
            new ProductRoleService(db: $db),
            new AuthorizationService(db: $db),
            static function (): OperationContext {
                $user = auth()->user();
                if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
                    throw new DomainException('UNAUTHENTICATED');
                }
                return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
            },
        );
    }
}

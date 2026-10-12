<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\RequestContext;
use App\Services\BusinessWorkflowActions;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Scoped workflow action discovery and execution for business Resources. */
final class BusinessWorkflow extends ApiController
{
    public function states(string $resource): ResponseInterface
    {
        return $this->respondData((new BusinessWorkflowActions())->states($resource, $this->operation()));
    }

    public function options(string $resource, int $id): ResponseInterface
    {
        return $this->respondData((new BusinessWorkflowActions())->options($resource, $id, $this->operation()));
    }

    public function execute(string $resource, int $id, string $action): ResponseInterface
    {
        // The action is part of the route, not a client-selected state/scope ID.
        return $this->respondData((new BusinessWorkflowActions())->execute($resource, $id, $action, $this->operation()));
    }

    private function operation(): \App\Infrastructure\Mutation\OperationContext
    {
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }
}

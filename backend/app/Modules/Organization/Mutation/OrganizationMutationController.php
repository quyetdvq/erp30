<?php

declare(strict_types=1);

namespace App\Modules\Organization\Mutation;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exact API-010 Organization write surface, behind existing feature gate. */
final class OrganizationMutationController extends Controller
{
    public function createOrganization(): ResponseInterface
    {
        return $this->dispatch(fn (OrganizationMutationService $service) =>
            $service->create(JsonRequest::readObject($this->request)), 201);
    }

    public function updateOrganization(string $id): ResponseInterface
    {
        return $this->dispatch(fn (OrganizationMutationService $service) =>
            $service->update($id, JsonRequest::readObject($this->request)));
    }

    public function setOrganizationState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (OrganizationMutationService $service) =>
            $service->setState($id, JsonRequest::readObject($this->request)['status'] ?? null));
    }

    /** @param callable(OrganizationMutationService):array<string,mixed> $run */
    private function dispatch(callable $run, int $status=200): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $run(new OrganizationMutationService($actor)), $status);
    }
}

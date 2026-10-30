<?php

declare(strict_types=1);

namespace App\Modules\Locations\Mutation;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** The six baseline Province/Ward mutation endpoints; no CRUD auto-router. */
final class LocationMutationController extends Controller
{
    public function createProvince(): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->create('province', JsonRequest::readObject($this->request)), 201);
    }

    public function updateProvince(string $id): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->update('province', $id, JsonRequest::readObject($this->request)));
    }

    public function setProvinceState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->setState('province', $id, JsonRequest::readObject($this->request)['status'] ?? null));
    }

    public function createWard(): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->create('ward', JsonRequest::readObject($this->request)), 201);
    }

    public function updateWard(string $id): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->update('ward', $id, JsonRequest::readObject($this->request)));
    }

    public function setWardState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (LocationMutationService $s) => $s->setState('ward', $id, JsonRequest::readObject($this->request)['status'] ?? null));
    }

    /** @param callable(LocationMutationService):array<string,mixed> $callback */
    private function dispatch(callable $callback, int $status = 200): ResponseInterface
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        return ApiResponder::success($this->response, $callback(new LocationMutationService($actor)), $status);
    }
}

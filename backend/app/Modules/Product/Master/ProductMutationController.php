<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exact API-050 Product CREATE, PATCH and state-transition entry points. */
final class ProductMutationController extends Controller
{
    public function createProduct(): ResponseInterface
    { return $this->write(fn(ProductMutationService $service):array => $service->create(JsonRequest::readObject($this->request)),201); }

    public function updateProduct(string $id): ResponseInterface
    { return $this->write(fn(ProductMutationService $service):array => $service->update($id,JsonRequest::readObject($this->request))); }

    public function setProductState(string $id): ResponseInterface
    { return $this->write(fn(ProductMutationService $service):array => $service->setState($id,JsonRequest::readObject($this->request))); }

    /** @param callable(ProductMutationService):array<string,mixed> $work */
    private function write(callable $work,int $status=200): ResponseInterface
    {
        $actor=auth()->user();
        if (! $actor instanceof User) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        if (strtolower((string)env('DINKERP_M33B_WRITE_MODE','off'))!=='enabled') {
            return ApiResponder::error($this->response,'ACCESS_MUTATION_NOT_ENABLED',403);
        }
        return ApiResponder::success($this->response,$work(new ProductMutationService($actor)),$status);
    }
}

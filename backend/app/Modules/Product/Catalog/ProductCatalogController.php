<?php

declare(strict_types=1);

namespace App\Modules\Product\Catalog;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Reviewed API-050 group/UOM operations. No generated or implicit CRUD routes. */
final class ProductCatalogController extends Controller
{
    public function listProductGroups(): ResponseInterface { return $this->list('product_group'); }
    public function listProductUoms(): ResponseInterface { return $this->list('product_uom'); }
    public function getProductGroup(string $id): ResponseInterface { return $this->show('product_group', $id); }
    public function getProductUom(string $id): ResponseInterface { return $this->show('product_uom', $id); }
    public function createProductGroup(): ResponseInterface { return $this->write('product_group', fn (ProductCatalogService $s): array => $s->create(JsonRequest::readObject($this->request)), 201); }
    public function createProductUom(): ResponseInterface { return $this->write('product_uom', fn (ProductCatalogService $s): array => $s->create(JsonRequest::readObject($this->request)), 201); }
    public function updateProductGroup(string $id): ResponseInterface { return $this->write('product_group', fn (ProductCatalogService $s): array => $s->update($id, JsonRequest::readObject($this->request))); }
    public function updateProductUom(string $id): ResponseInterface { return $this->write('product_uom', fn (ProductCatalogService $s): array => $s->update($id, JsonRequest::readObject($this->request))); }
    public function setProductGroupState(string $id): ResponseInterface { return $this->write('product_group', fn (ProductCatalogService $s): array => $s->setState($id, JsonRequest::readObject($this->request))); }
    public function setProductUomState(string $id): ResponseInterface { return $this->write('product_uom', fn (ProductCatalogService $s): array => $s->setState($id, JsonRequest::readObject($this->request))); }

    private function actor(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    private function list(string $resource): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        $result = (new ProductCatalogService($actor, $resource))->list($this->request->getGet());
        return ApiResponder::success($this->response, $result['data'], 200,
            ['page' => $result['page'], 'page_size' => $result['page_size'], 'total' => $result['total']]);
    }

    private function show(string $resource, string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response, (new ProductCatalogService($actor, $resource))->show($id));
    }

    /** @param callable(ProductCatalogService):array<string,mixed> $task */
    private function write(string $resource, callable $task, int $status = 200): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $task(new ProductCatalogService($actor, $resource)), $status);
    }
}

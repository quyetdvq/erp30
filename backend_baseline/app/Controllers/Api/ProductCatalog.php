<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\Api050ProductCatalogService;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-050 surface. */
final class ProductCatalog extends ApiController
{
    public function listGroups(): ResponseInterface
    {
        return $this->collection($this->service()->listGroups($this->query()));
    }

    public function createGroup(): ResponseInterface
    {
        return $this->respondData($this->service()->createGroup($this->jsonPayload()), 201);
    }

    public function showGroup(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getGroup($id));
    }

    public function updateGroup(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->updateGroup($id, $this->jsonPayload()));
    }

    public function setGroupState(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->setGroupState($id, $this->state()));
    }

    public function listUoms(): ResponseInterface
    {
        return $this->collection($this->service()->listUoms($this->query()));
    }

    public function createUom(): ResponseInterface
    {
        return $this->respondData($this->service()->createUom($this->jsonPayload()), 201);
    }

    public function showUom(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getUom($id));
    }

    public function updateUom(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->updateUom($id, $this->jsonPayload()));
    }

    public function setUomState(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->setUomState($id, $this->state()));
    }

    public function listProducts(): ResponseInterface
    {
        return $this->collection($this->service()->listProducts($this->query()));
    }

    public function createProduct(): ResponseInterface
    {
        return $this->respondData($this->service()->createProduct($this->jsonPayload()), 201);
    }

    public function showProduct(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getProduct($id));
    }

    public function updateProduct(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->updateProduct($id, $this->jsonPayload()));
    }

    public function setProductState(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->setProductState($id, $this->state()));
    }

    public function listConversions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getConversions($id));
    }

    public function replaceConversions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->replaceConversions($id, $this->items(), $this->clientEvaluationOrganization()));
    }

    public function listPriceLists(): ResponseInterface
    {
        return $this->collection($this->service()->listPriceLists($this->query()));
    }

    public function createPriceList(): ResponseInterface
    {
        return $this->respondData($this->service()->createPriceList($this->jsonPayload()), 201);
    }

    public function showPriceList(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getPriceList($id));
    }

    public function updatePriceList(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->updatePriceList($id, $this->jsonPayload()));
    }

    public function setPriceListState(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->setPriceListState($id, $this->state()));
    }

    public function listPriceListItems(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getPriceListItems($id));
    }

    public function replacePriceListItems(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->replacePriceListItems($id, $this->items()));
    }

    public function listComboItems(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->getComboItems($id));
    }

    public function replaceComboItems(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->replaceComboItems($id, $this->items()));
    }

    /** @param array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string} $result */
    private function collection(array $result): ResponseInterface
    {
        return ApiTransport::collection(
            $this->response,
            $result['items'],
            $result['page'],
            $result['page_size'],
            $result['total'],
        );
    }

    /** @return array<string,mixed> */
    private function query(): array
    {
        $query = $this->request->getGet();
        return is_array($query) ? $query : [];
    }

    /** @return list<array<string,mixed>> */
    private function items(): array
    {
        $payload = $this->jsonPayload();
        if (isset($payload['items'])) {
            if (! is_array($payload['items'])) {
                throw new BadRequestException('Items must be a list.');
            }
            $items = [];
            foreach (array_values($payload['items']) as $item) {
                if (! is_array($item)) {
                    throw new BadRequestException('Items must be a list of objects.');
                }
                $items[] = $item;
            }
            return $items;
        }
        $items = [];
        foreach (array_values($payload) as $item) {
            if (! is_array($item)) {
                throw new BadRequestException('Items must be a list of objects.');
            }
            $items[] = $item;
        }
        return $items;
    }

    private function state(): string
    {
        $payload = $this->jsonPayload();
        if (! isset($payload['status']) || ! is_string($payload['status'])) {
            throw new BadRequestException('State status is required.');
        }
        return $payload['status'];
    }

    private function clientEvaluationOrganization(): mixed
    {
        $payload = $this->jsonPayload();
        return $payload['evaluation_organization_id'] ?? null;
    }

    private function service(): Api050ProductCatalogService
    {
        return new Api050ProductCatalogService();
    }
}

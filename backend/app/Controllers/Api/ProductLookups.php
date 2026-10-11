<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\ProductLookupService;
use CodeIgniter\HTTP\ResponseInterface;

/** Read-only, session-authorized Product selector and batch projections. */
final class ProductLookups extends ApiController
{
    public function options(): ResponseInterface
    {
        $query = $this->request->getGet();
        $result = $this->service()->options(is_array($query) ? $query : []);
        return ApiTransport::collection($this->response, $result['items'], $result['page'],
            $result['page_size'], $result['total']);
    }

    public function resolve(): ResponseInterface
    {
        return $this->respondData($this->service()->resolve($this->jsonPayload()['product_ids'] ?? null));
    }

    public function resolveDetails(): ResponseInterface
    {
        return $this->respondData($this->service()->resolveDetails($this->jsonPayload()['product_ids'] ?? null));
    }

    public function relationPreviews(): ResponseInterface
    {
        $payload = $this->jsonPayload();
        return $this->respondData($this->service()->relationPreviews(
            $payload['product_ids'] ?? null, $payload['limit_per_product'] ?? 2,
        ));
    }

    public function resolveUomLabels(): ResponseInterface
    {
        return $this->respondData($this->service()->resolveUomLabels($this->jsonPayload()['uom_ids'] ?? null));
    }

    public function salesUomOptions(): ResponseInterface
    {
        return $this->respondData($this->service()->salesUomOptions($this->jsonPayload()['product_ids'] ?? null));
    }

    private function service(): ProductLookupService
    {
        return new ProductLookupService();
    }
}

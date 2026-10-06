<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\Api020EmployeeStructureService;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-020 resources. */
final class EmployeeStructure extends ApiController
{
    public function list(string $resource): ResponseInterface
    {
        $result = $this->service()->list($resource, $this->request->getGet());

        return ApiTransport::collection(
            $this->response,
            $result->data,
            $result->query->page,
            $result->query->pageSize,
            $result->total,
        );
    }

    public function create(string $resource): ResponseInterface
    {
        return $this->respondData($this->service()->create($resource, $this->jsonPayload()), 201);
    }

    public function show(string $resource, int $id): ResponseInterface
    {
        return $this->respondData($this->service()->get($resource, $id));
    }

    public function update(string $resource, int $id): ResponseInterface
    {
        return $this->respondData($this->service()->update($resource, $id, $this->jsonPayload()));
    }

    public function setState(string $resource, int $id): ResponseInterface
    {
        $payload = $this->jsonPayload();
        $status = $payload['status'] ?? null;
        if (! is_string($status)) {
            throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('State status is required.');
        }

        return $this->respondData($this->service()->setState($resource, $id, strtoupper(trim($status))));
    }

    private function service(): Api020EmployeeStructureService
    {
        return new Api020EmployeeStructureService();
    }
}

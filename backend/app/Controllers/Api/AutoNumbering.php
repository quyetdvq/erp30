<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\Api040AutoNumberingService;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-040 surface. */
final class AutoNumbering extends ApiController
{
    public function list(int $resource): ResponseInterface
    {
        $data = $this->service()->list($resource);

        return ApiTransport::collection($this->response, $data, 1, max(1, count($data)), count($data));
    }

    public function create(int $resource): ResponseInterface
    {
        return $this->respondData($this->service()->create($resource, $this->jsonPayload()), 201);
    }

    public function show(int $resource, int $series): ResponseInterface
    {
        return $this->respondData($this->service()->get($resource, $series));
    }

    public function update(int $resource, int $series): ResponseInterface
    {
        return $this->respondData($this->service()->update($resource, $series, $this->jsonPayload()));
    }

    public function setState(int $resource, int $series): ResponseInterface
    {
        $payload = $this->jsonPayload();
        if (! isset($payload['status']) || ! is_string($payload['status'])) {
            throw new BadRequestException('State status is required.');
        }

        return $this->respondData($this->service()->setState($resource, $series, $payload['status']));
    }

    public function validateConfig(int $resource): ResponseInterface
    {
        return $this->respondData($this->service()->validateConfig($resource, $this->jsonPayload()));
    }

    private function service(): Api040AutoNumberingService
    {
        return new Api040AutoNumberingService();
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiDisplaySettingsService;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY Display Settings API. */
final class DisplaySettings extends ApiController
{
    public function runtime(): ResponseInterface
    {
        return $this->respondData($this->service()->runtime($this->request->getGet()));
    }

    public function definitions(): ResponseInterface
    {
        return $this->respondData($this->service()->definitions());
    }

    public function values(): ResponseInterface
    {
        return $this->respondData($this->service()->values($this->request->getGet()));
    }

    public function update(): ResponseInterface
    {
        return $this->respondData($this->service()->set($this->jsonPayload()));
    }

    public function reset(): ResponseInterface
    {
        return $this->respondData($this->service()->reset($this->jsonPayload()));
    }

    private function service(): ApiDisplaySettingsService
    {
        return new ApiDisplaySettingsService();
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Infrastructure\Api\ApiTransport;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;

abstract class ApiController extends BaseController
{
    /** @return array<string, mixed> */
    protected function jsonPayload(): array
    {
        $body = trim((string) $this->request->getBody());
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new BadRequestException('Malformed JSON request body.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $meta */
    protected function respondData(mixed $data = null, int $status = 200, array $meta = []): ResponseInterface
    {
        return ApiTransport::success($this->response, $data, $status, $meta);
    }

    /** @param list<array<string, mixed>> $details */
    protected function respondError(string $code, int $status, array $details = []): ResponseInterface
    {
        return ApiTransport::error($this->response, $code, ApiTransport::message($code, $status), $status, $details);
    }

    protected function respondNoContent(): ResponseInterface
    {
        return ApiTransport::success($this->response, null, 204);
    }
}

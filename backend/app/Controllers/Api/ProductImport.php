<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\Api060ProductImportService;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-060 surface. */
final class ProductImport extends ApiController
{
    public function start(): ResponseInterface
    {
        if (! $this->request instanceof IncomingRequest) {
            throw new BadRequestException('Import file is required.');
        }
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            throw new BadRequestException('Import file is required.');
        }
        $source = file_get_contents($file->getTempName());
        if ($source === false) {
            throw new BadRequestException('Import file cannot be read.');
        }
        $mode = $this->request->getPost('mode');
        if (! is_string($mode) || trim($mode) === '') {
            throw new BadRequestException('Import mode is required.');
        }

        return $this->respondData(
            $this->service()->start($source, $file->getClientName(), $mode),
            201,
        );
    }

    public function list(): ResponseInterface
    {
        $result = $this->service()->list($this->query());

        return ApiTransport::collection(
            $this->response,
            $result['items'],
            $result['page'],
            $result['page_size'],
            $result['total'],
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->get($id));
    }

    public function rows(int $id): ResponseInterface
    {
        $result = $this->service()->rows($id, $this->query());

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

    private function service(): Api060ProductImportService
    {
        return new Api060ProductImportService();
    }
}

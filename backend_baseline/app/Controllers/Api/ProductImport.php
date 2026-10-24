<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiTransport;
use App\Services\Api060ProductImportService;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Import;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-060 surface. */
final class ProductImport extends ApiController
{
    public function start(): ResponseInterface
    {
        if (! $this->request instanceof IncomingRequest) {
            throw new BadRequestException('Import file is required.');
        }
        $file = $this->request->getFile('file');
        $limits = config(Import::class);
        $limits->assertPhpUploadSettings();
        if ($file === null || ! $file->isValid()) {
            if ($file !== null && $file->getError() === UPLOAD_ERR_INI_SIZE) {
                throw new DomainException(Api060ProductImportService::ERROR_FILE_TOO_LARGE, ['field' => 'file']);
            }
            throw new BadRequestException('Import file is required.');
        }
        $size = $file->getSize();
        if (is_int($size)) {
            $limits->assertFileSize($size);
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

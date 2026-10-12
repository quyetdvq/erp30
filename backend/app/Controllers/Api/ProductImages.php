<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\DomainException;
use App\Services\ProductImageService;
use App\Services\ProductImageStorage;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/** Authorized binary endpoints: a Product's image is never public storage. */
final class ProductImages extends ApiController
{
    public function show(int $id): ResponseInterface
    {
        $image = $this->service()->read($id);
        $body = @file_get_contents($image['path']);
        if ($body === false) {
            throw new DomainException('PRODUCT_IMAGE_NOT_FOUND');
        }
        return $this->response
            ->setHeader('Content-Type', $image['mime'])
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($body);
    }

    public function upload(int $id): ResponseInterface
    {
        // Deny unauthorized mutations before reporting upload validation.
        (new \App\Services\Api050ProductCatalogService())->productForImage($id, 'edit');
        if (! $this->request instanceof IncomingRequest) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_CONTENT', ['field' => 'image']);
        }
        $file = $this->request->getFile('image');
        if ($file === null || ! $file->isValid()) {
            $error = $file?->getError();
            $code = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'PRODUCT_IMAGE_INVALID_SIZE' : 'PRODUCT_IMAGE_INVALID_CONTENT';
            throw new DomainException($code, ['field' => 'image']);
        }
        $size = $file->getSize();
        if (! is_int($size) || $size > ProductImageStorage::MAX_BYTES || $size < 1) {
            throw new DomainException('PRODUCT_IMAGE_INVALID_SIZE', ['field' => 'image']);
        }
        return $this->respondData($this->service()->upload($id, $file->getTempName()));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->remove($id));
    }

    private function service(): ProductImageService
    {
        return new ProductImageService();
    }
}

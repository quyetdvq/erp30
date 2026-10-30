<?php

declare(strict_types=1);

namespace App\Modules\Product\Media;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Frozen Product image mutations. No image-byte read API is exposed. */
final class ProductImageController extends Controller
{
    public function uploadProductImage(string $id): ResponseInterface
    {
        $actor = $this->authenticatedWriter();
        if ($actor instanceof ResponseInterface) return $actor;

        // Authorization of the persisted Product occurs within the service,
        // BEFORE inspecting multipart content or its upload error.
        $file = $this->request instanceof IncomingRequest ? $this->request->getFile('image') : null;
        return ApiResponder::success($this->response, (new ProductImageService($actor))->upload($id, $file));
    }

    public function deleteProductImage(string $id): ResponseInterface
    {
        $actor = $this->authenticatedWriter();
        if ($actor instanceof ResponseInterface) return $actor;
        return ApiResponder::success($this->response, (new ProductImageService($actor))->remove($id));
    }

    private function authenticatedWriter(): User|ResponseInterface
    {
        $actor = auth()->user();
        if (! $actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return $actor;
    }
}

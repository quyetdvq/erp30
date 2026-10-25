<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** M7.4: three frozen, read-only POST projections; no write-mode bypass. */
final class ProductRelationshipController extends Controller
{
    public function resolveProductDetails(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductRelationshipService($actor))->resolveDetails(JsonRequest::readObject($this->request)));
    }

    public function previewProductRelations(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductRelationshipService($actor))->relationPreviews(JsonRequest::readObject($this->request)));
    }

    public function resolveProductSalesUomOptions(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductRelationshipService($actor))->salesUomOptions(JsonRequest::readObject($this->request)));
    }

    private function actor(): ?User
    {
        $actor = auth()->user();
        return $actor instanceof User ? $actor : null;
    }
}

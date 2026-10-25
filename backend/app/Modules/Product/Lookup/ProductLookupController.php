<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Three frozen API-050 lookup operations; read-only irrespective of write-mode. */
final class ProductLookupController extends Controller
{
    public function searchProductOptions(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        $query = $this->request->getGet();
        $page = (new ProductLookupService($actor))->options(is_array($query) ? $query : []);
        return ApiResponder::success($this->response, $page['data'], 200,
            ['page'=>$page['page'], 'page_size'=>$page['page_size'], 'total'=>$page['total']]);
    }

    public function resolveProductOptions(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductLookupService($actor))->resolveProducts(JsonRequest::readObject($this->request)));
    }

    public function resolveProductUomLabels(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductLookupService($actor))->resolveUomLabels(JsonRequest::readObject($this->request)));
    }

    private function actor(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }
}

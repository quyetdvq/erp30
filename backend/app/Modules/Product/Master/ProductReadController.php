<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Reviewed Product Master GET routes; create/edit/state remain unregistered. */
final class ProductReadController extends Controller
{
    private function actor(): ?User
    {
        $actor = auth()->user();
        return $actor instanceof User ? $actor : null;
    }

    public function listProducts(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        $query = $this->request->getGet();
        $page = (new ProductReadService($actor))->list(is_array($query) ? $query : []);
        return ApiResponder::success($this->response, $page['data'], 200,
            ['page'=>$page['page'], 'page_size'=>$page['page_size'], 'total'=>$page['total']]);
    }

    public function getProduct(string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response, (new ProductReadService($actor))->show($id));
    }
}

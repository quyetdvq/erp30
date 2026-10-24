<?php

declare(strict_types=1);

namespace App\Modules\Identity\Management;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exact baseline Security User GET endpoints owned by Identity. */
final class IdentityReadController extends Controller
{
    public function userPermissions(string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $target = (new IdentityReadService($actor))->userPermissions($id);
        return $target === null
            ? ApiResponder::error($this->response, 'USER_NOT_FOUND', 404)
            : ApiResponder::success($this->response, $target);
    }

    public function securityUsers(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $query = $this->request->getGet();
        if (! is_array($query)) {
            return ApiResponder::error($this->response, 'INVALID_REQUEST', 400);
        }
        $page = (new IdentityReadService($actor))->securityUsers($query);
        return ApiResponder::success($this->response, $page['data'], 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    private function actor(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }
}

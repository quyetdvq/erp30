<?php

declare(strict_types=1);

namespace App\Modules\Identity\Media;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exactly the two reviewed User Avatar mutations; no PHP image-read endpoint. */
final class UserAvatarController extends Controller
{
    public function uploadUserAvatar(string $id): ResponseInterface
    {
        $actor = $this->authenticatedWriter();
        if ($actor instanceof ResponseInterface) return $actor;
        $file = $this->request instanceof IncomingRequest ? $this->request->getFile('image') : null;
        return ApiResponder::success($this->response, (new UserAvatarService($actor))->upload($id, $file));
    }

    public function deleteUserAvatar(string $id): ResponseInterface
    {
        $actor = $this->authenticatedWriter();
        if ($actor instanceof ResponseInterface) return $actor;
        return ApiResponder::success($this->response, (new UserAvatarService($actor))->remove($id));
    }

    private function authenticatedWriter(): User|ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return $actor;
    }
}

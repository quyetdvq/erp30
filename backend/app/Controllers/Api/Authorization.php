<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Authorization\AuthorizationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class Authorization extends BaseController
{
    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    public function profile(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'UNAUTHENTICATED',
            ]);
        }

        /** @var AuthorizationService $authorization */
        $authorization = service('authorization');

        return $this->response->setJSON($authorization->profile((int) $user->id));
    }

    public function check(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'UNAUTHENTICATED',
            ]);
        }

        $resource = trim((string) $this->request->getGet('resource'));
        $action = trim((string) $this->request->getGet('action'));
        $state = trim((string) $this->request->getGet('state'));
        $organizationId = filter_var($this->request->getGet('organization_id'), FILTER_VALIDATE_INT);

        if ($resource === '' || $action === '' || $state === '' || $organizationId === false || $organizationId < 1) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'INVALID_AUTHORIZATION_REQUEST',
            ]);
        }

        /** @var AuthorizationService $authorization */
        $authorization = service('authorization');
        $decision = $authorization->authorize(
            $resource,
            $action,
            $organizationId,
            $state,
            (int) $user->id,
            $this->request->getHeaderLine('X-Request-ID') ?: null,
        );

        return $this->response->setJSON($decision->toArray());
    }
}

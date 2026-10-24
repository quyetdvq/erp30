<?php

declare(strict_types=1);

namespace App\Modules\Access\Management;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exact reviewed GET endpoints; no string method/arg routing in CodeIgniter. */
final class AccessReadController extends Controller
{
    public function listAuthorizationGroups(): ResponseInterface
    {
        return $this->listing('authorization_group');
    }

    public function showAuthorizationGroup(string $id): ResponseInterface
    {
        return $this->detail('authorization_group', $id);
    }

    public function listResources(): ResponseInterface
    {
        return $this->listing('resource');
    }

    public function showResource(string $id): ResponseInterface
    {
        return $this->detail('resource', $id);
    }

    public function listPolicies(): ResponseInterface
    {
        return $this->listing('policy');
    }

    public function showPolicy(string $id): ResponseInterface
    {
        return $this->detail('policy', $id);
    }

    public function listAuthorizationLogs(): ResponseInterface
    {
        return $this->listing('authorization_log');
    }

    public function showAuthorizationLog(string $id): ResponseInterface
    {
        return $this->detail('authorization_log', $id);
    }

    public function groupMembers(string $id): ResponseInterface
    {
        return $this->relation('authorization_group', 'members', $id);
    }

    public function groupPermissions(string $id): ResponseInterface
    {
        return $this->relation('authorization_group', 'permissions', $id);
    }

    public function resourceActions(string $id): ResponseInterface
    {
        return $this->relation('resource', 'actions', $id);
    }

    private function listing(string $type): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $query = $this->request->getGet();
        if (! is_array($query)) {
            return ApiResponder::error($this->response, 'INVALID_REQUEST', 400);
        }
        $page = (new AccessReadService($actor))->listing($type, $query);
        return ApiResponder::success($this->response, $page['data'], 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    private function detail(string $type, string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $row = (new AccessReadService($actor))->detail($type, $id);
        return $row === null
            ? ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404)
            : ApiResponder::success($this->response, $row);
    }

    private function relation(string $type, string $relation, string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $rows = (new AccessReadService($actor))->relation($type, $relation, $id);
        return $rows === null
            ? ApiResponder::error($this->response, 'RESOURCE_NOT_FOUND', 404)
            : ApiResponder::success($this->response, $rows);
    }

    private function actor(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Api\ApiTransport;
use App\Services\Api030AuthorizationWorkflowService;
use App\Services\ShieldUserIdentityService;
use CodeIgniter\HTTP\ResponseInterface;

/** Thin HTTP adapter for the CONTRACT_READY P1-API-030 resources. */
final class AuthorizationWorkflow extends ApiController
{
    public function list(string $resource): ResponseInterface
    {
        $result = $this->service()->list($resource, $this->request->getGet());
        return ApiTransport::collection($this->response, $result->data, $result->query->page, $result->query->pageSize, $result->total);
    }

    public function securityUsers(): ResponseInterface
    {
        $result = $this->service()->securityUsers($this->request->getGet());
        return ApiTransport::collection($this->response, $result->data, $result->query->page, $result->query->pageSize, $result->total);
    }

    public function createUser(): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->create($this->jsonPayload()), 201);
    }

    public function updateUser(int $id): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->update($id, $this->jsonPayload()));
    }

    public function setUserState(int $id): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->setState($id, $this->jsonPayload()));
    }

    public function resetUserPassword(int $id): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->resetPassword($id, $this->jsonPayload()));
    }

    public function userPermissions(int $id): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->permissions($id));
    }

    public function replaceUserPermissions(int $id): ResponseInterface
    {
        return $this->respondData((new ShieldUserIdentityService())->replacePermissions($id, $this->jsonPayload()));
    }

    public function create(string $resource): ResponseInterface
    {
        return $this->respondData($this->service()->create($resource, $this->jsonPayload()), 201);
    }

    public function show(string $resource, int $id): ResponseInterface
    {
        return $this->respondData($this->service()->get($resource, $id));
    }

    public function update(string $resource, int $id): ResponseInterface
    {
        return $this->respondData($this->service()->update($resource, $id, $this->jsonPayload()));
    }

    public function setState(string $resource, int $id): ResponseInterface
    {
        $payload = $this->jsonPayload();
        if (! isset($payload['status']) || ! is_string($payload['status'])) {
            throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('State status is required.');
        }
        return $this->respondData($this->service()->setState($resource, $id, $payload['status']));
    }

    public function deletePolicy(int $id): ResponseInterface
    {
        $this->service()->deletePolicy($id);
        return $this->respondNoContent();
    }

    public function deleteResource(int $id): ResponseInterface
    {
        $this->service()->deleteResourceActionTarget($id);
        return $this->respondNoContent();
    }

    public function members(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->members($id));
    }

    public function replaceMembers(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->replaceMembers($id, $this->jsonPayload()));
    }

    public function permissions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->permissions($id));
    }

    public function replacePermissions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->replacePermissions($id, $this->jsonPayload()));
    }

    public function actions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->resourceActions($id));
    }

    public function createAction(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->createResourceAction($id, $this->jsonPayload()), 201);
    }

    public function updateAction(int $id, int $actionId): ResponseInterface
    {
        return $this->respondData($this->service()->updateResourceAction($id, $actionId, $this->jsonPayload()));
    }

    public function deleteAction(int $id, int $actionId): ResponseInterface
    {
        $this->service()->deleteResourceAction($id, $actionId);
        return $this->respondNoContent();
    }

    public function configureStates(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->configureLifecycle($id, $this->jsonPayload()));
    }

    public function states(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->lifecycleStates($id));
    }

    public function transitions(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->lifecycleTransitions($id));
    }

    private function service(): Api030AuthorizationWorkflowService
    {
        return new Api030AuthorizationWorkflowService();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Explicitly registered mutation endpoints. All checks happen in the
 * application service, and routes are disabled by default for safe rollout.
 */
final class AccessMutationController extends Controller
{
    public function createAuthorizationGroup(): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->createGroup($this->json()), 201);
    }

    public function updateAuthorizationGroup(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->updateGroup($id, $this->json()));
    }

    public function setAuthorizationGroupState(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->groupState($id, $this->json()['status'] ?? null));
    }

    public function replaceAuthorizationGroupMembers(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->replaceGroupMembers($id, $this->json()));
    }

    public function replaceAuthorizationGroupPermissions(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->replaceGroupPermissions($id, $this->json()));
    }

    public function createResource(): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->createResource($this->json()), 201);
    }

    public function updateResource(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->updateResource($id, $this->json()));
    }

    public function setResourceState(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->resourceState($id, $this->json()['status'] ?? null));
    }

    public function deleteResource(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->deleteResource($id), 204);
    }

    public function createResourceAction(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->createResourceAction($id, $this->json()), 201);
    }

    public function updateResourceAction(string $id, string $actionId): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->updateResourceAction($id, $actionId, $this->json()));
    }

    public function deleteResourceAction(string $id, string $actionId): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->deleteResourceAction($id, $actionId), 204);
    }

    // M3.3B2 policy + bundled Policy Actions (there is no standalone
    // Policy Action route in the v1.2.4 OpenAPI contract).
    public function createPolicy(): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->createPolicy($this->json()), 201);
    }

    public function updatePolicy(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->updatePolicy($id, $this->json()));
    }

    public function setPolicyState(string $id): ResponseInterface
    {
        $payload = $this->json();
        if (! isset($payload['status']) || ! is_string($payload['status'])) {
            throw new BadRequestException('State status is required.');
        }
        return $this->dispatch(fn ($s) => $s->policyState($id, $payload['status']));
    }

    public function deletePolicy(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->deletePolicy($id), 204);
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        // Identical to baseline ApiController::jsonPayload(): an empty body is
        // an empty payload, and syntactically valid JSON arrays are passed to
        // the domain validator rather than reinterpreted as query parameters.
        $body = trim((string) $this->request->getBody());
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new BadRequestException('Malformed JSON request body.');
        }
        return $decoded;
    }

    /** @param callable(AccessMutationService):mixed $callback */
    private function dispatch(callable $callback, int $status = 200): ResponseInterface
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        // Deliberate deployment gate. Existing read flows are untouched, and
        // no write is enabled merely by installing the overlay.
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        $result = $callback(new AccessMutationService($actor));
        return ApiResponder::success($this->response, $result, $status);
    }
}

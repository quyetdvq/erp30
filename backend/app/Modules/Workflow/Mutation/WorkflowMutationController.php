<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Mutation;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exactly eight OpenAPI v1.2.4 Workflow mutations. All writes default OFF. */
final class WorkflowMutationController extends Controller
{
    public function createWorkflowState(): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->create('workflow_state', $this->json()), 201);
    }

    public function updateWorkflowState(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->update('workflow_state', $id, $this->json()));
    }

    public function setWorkflowStateState(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->setState('workflow_state', $id, $this->json()['status'] ?? null));
    }

    public function createLifecycle(): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->create('lifecycle', $this->json()), 201);
    }

    public function updateLifecycle(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->update('lifecycle', $id, $this->json()));
    }

    public function setLifecycleState(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->setState('lifecycle', $id, $this->json()['status'] ?? null));
    }

    public function configureLifecycleStates(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->configure($id, $this->json()));
    }

    public function configureLifecycleTransitions(string $id): ResponseInterface
    {
        return $this->dispatch(fn ($s) => $s->configure($id, $this->json()));
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        $body = trim((string) $this->request->getBody());
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new BadRequestException('Malformed JSON request body.');
        }
        return $data;
    }

    /** @param callable(WorkflowMutationService):mixed $callback */
    private function dispatch(callable $callback, int $status = 200): ResponseInterface
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        return ApiResponder::success($this->response, $callback(new WorkflowMutationService($actor)), $status);
    }
}

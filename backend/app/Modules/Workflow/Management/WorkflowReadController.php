<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Management;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Reviewed Workflow GET handlers; URL/method/response contract unchanged. */
final class WorkflowReadController extends Controller
{
    public function listWorkflowStates(): ResponseInterface
    {
        return $this->listing('workflow_state');
    }

    public function showWorkflowState(string $id): ResponseInterface
    {
        return $this->detail('workflow_state', $id);
    }

    public function listLifecycles(): ResponseInterface
    {
        return $this->listing('lifecycle');
    }

    public function showLifecycle(string $id): ResponseInterface
    {
        return $this->detail('lifecycle', $id);
    }

    public function lifecycleStates(string $id): ResponseInterface
    {
        return $this->relation('lifecycle', 'states', $id);
    }

    public function lifecycleTransitions(string $id): ResponseInterface
    {
        return $this->relation('lifecycle', 'transitions', $id);
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
        $page = (new WorkflowReadService($actor))->listing($type, $query);
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
        $row = (new WorkflowReadService($actor))->detail($type, $id);
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
        $rows = (new WorkflowReadService($actor))->relation($type, $relation, $id);
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

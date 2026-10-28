<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class WorkflowNotificationAssigneesController extends Controller
{
    public function listing(string $resource, string $id): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->listing($resource, $id));
    }

    public function candidates(string $resource, string $id): ResponseInterface
    {
        $this->noStore();
        $query = $this->request->getGet() ?? [];
        if (!is_array($query)) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $page = $this->service()->candidates($resource, $id, $query);
        return ApiResponder::success($this->response, $page['data'], 200, [
            'page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total'],
        ]);
    }

    public function replace(string $resource, string $id): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        if (! NotificationFlags::workflowWriteEnabled(db_connect())) {
            return ApiResponder::error($this->response, 'NOTIFICATION_SETTINGS_DISABLED', 403);
        }
        $payload = $this->request->getJSON(true);
        if (! is_array($payload) || array_is_list($payload)) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return ApiResponder::success($this->response, $this->service()->replace($resource, $id, $payload));
    }

    private function service(): WorkflowNotificationAssigneesService
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return new WorkflowNotificationAssigneesService($actor);
    }

    private function rejectQuery(): void
    {
        if ($this->request->getGet() !== []) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
    }

    private function noStore(): void
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
    }
}

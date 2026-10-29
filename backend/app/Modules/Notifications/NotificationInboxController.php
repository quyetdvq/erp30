<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class NotificationInboxController extends Controller
{
    public function summary(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->summary());
    }

    /** Explicit CSRF-protected POST: never mutate Outbox via GET summary. */
    public function sync(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        $this->rejectBody();
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return ApiResponder::success($this->response,
            (new NotificationOnDemandSync(db_connect()))->refresh($actor));
    }

    public function listing(): ResponseInterface
    {
        $this->noStore();
        return ApiResponder::success($this->response, $this->service()->listing($this->request->getGet()));
    }

    public function markRead(string $id): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        $this->rejectBody();
        return ApiResponder::success($this->response, $this->service()->markRead($id));
    }

    public function markAllRead(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        $this->rejectBody();
        return ApiResponder::success($this->response, $this->service()->markAllRead());
    }

    private function service(): NotificationInboxService
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return new NotificationInboxService($actor);
    }

    private function rejectQuery(): void
    {
        if ($this->request->getGet() !== []) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
    }

    private function rejectBody(): void
    {
        if (trim((string) $this->request->getBody()) !== '') {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
    }

    private function noStore(): void
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
    }
}

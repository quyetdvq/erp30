<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Authenticated, same-origin/CSRF-protected browser Push subscription API. */
final class NotificationPushController extends Controller
{
    public function config(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->config());
    }

    public function listing(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->listing());
    }

    public function subscribe(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        if (strlen((string) $this->request->getBody()) > 5000) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return ApiResponder::success($this->response,
            $this->service()->subscribe(JsonRequest::readObject($this->request)), 201);
    }

    public function unsubscribe(string $id): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        if (trim((string) $this->request->getBody()) !== '') {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return ApiResponder::success($this->response, $this->service()->unsubscribe($id));
    }

    private function service(): NotificationPushService
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return new NotificationPushService($user);
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

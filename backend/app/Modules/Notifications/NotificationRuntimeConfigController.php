<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Authenticated SYSTEM settings; no separate .env write gate or secret fields. */
final class NotificationRuntimeConfigController extends Controller
{
    public function listing(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->listing());
    }

    public function update(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        if (strlen((string) $this->request->getBody()) > 8192) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return ApiResponder::success($this->response, $this->service()->update(JsonRequest::readObject($this->request)));
    }

    private function service(): NotificationRuntimeConfigService
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return new NotificationRuntimeConfigService($actor);
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

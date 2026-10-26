<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Four authenticated M11.1A endpoints. Inbox and Worker are M11.1B. */
final class NotificationSettingsController extends Controller
{
    public function listPolicies(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->policies());
    }

    public function updatePolicies(): ResponseInterface
    {
        $this->noStore();
        $this->allowWrites();
        return ApiResponder::success($this->response, $this->service()->updatePolicies($this->body()));
    }

    public function listPreferences(): ResponseInterface
    {
        $this->noStore();
        $this->rejectQuery();
        return ApiResponder::success($this->response, $this->service()->preferences());
    }

    public function updatePreferences(): ResponseInterface
    {
        $this->noStore();
        $this->allowWrites();
        return ApiResponder::success($this->response, $this->service()->updatePreferences($this->body()));
    }

    private function service(): NotificationSettingsService
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return new NotificationSettingsService($user);
    }

    /** @return array<string,mixed> */
    private function body(): array
    {
        if (strlen((string) $this->request->getBody()) > 16384) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        return JsonRequest::readObject($this->request);
    }

    private function allowWrites(): void
    {
        // Independent M11 gate. Do not turn on legacy M33B mutations for Notifications.
        if (strtolower((string) env('DINKERP_M11_SETTINGS_WRITE_MODE', 'off')) !== 'enabled') {
            throw new DomainException('NOTIFICATION_SETTINGS_DISABLED');
        }
    }

    private function rejectQuery(): void
    {
        $query = $this->request->getGet();
        if (is_array($query) && $query !== []) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
    }

    private function noStore(): void
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
    }
}

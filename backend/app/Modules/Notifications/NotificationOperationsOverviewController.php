<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** One authenticated, read-only aggregate endpoint. No worker controls. */
final class NotificationOperationsOverviewController extends Controller
{
    public function overview(): ResponseInterface
    {
        $actor = $this->readActor();
        return ApiResponder::success($this->response,
            (new NotificationOperationsOverviewService($actor))->overview());
    }

    public function diagnostics(): ResponseInterface
    {
        $actor = $this->readActor();
        return ApiResponder::success($this->response,
            (new NotificationOperationsDiagnosticsService($actor))->diagnostics());
    }

    /** Reject query parameters before any Operations read. */
    private function readActor(): User
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if ($this->request->getGet() !== []) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return $actor;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class MyWorkflowController extends Controller
{
    /** Strictly read-only; the user ID is always taken from Shield. */
    public function listing(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new DomainException('UNAUTHENTICATED');
        }
        return ApiResponder::success($this->response,
            (new MyWorkflowService($actor))->listing($this->request->getGet()));
    }
}

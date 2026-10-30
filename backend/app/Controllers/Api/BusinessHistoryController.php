<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Bootstrap\CustomerHistoryVisibility;
use App\Modules\History\HistoryReadService;
use App\Modules\History\HistoryMonitorReadService;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Composition root: History service depends on a port, not Customer code. */
final class BusinessHistoryController extends Controller
{
    /** Privileged cross-module list. No generic unauthenticated History browser. */
    public function monitor(): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $page = (new HistoryMonitorReadService($actor))->listing($this->request->getGet());
        return ApiResponder::success($this->response, $page['data'], 200,
            ['page'=>$page['page'], 'page_size'=>$page['page_size'], 'total'=>$page['total'],
                'from'=>$page['from'], 'to'=>$page['to']]);
    }

    public function customer(string $id): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $page = (new HistoryReadService($actor, new CustomerHistoryVisibility()))
            ->customer($id, $this->request->getGet());
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return ApiResponder::success($this->response, $page['data'], 200,
            ['page'=>$page['page'], 'page_size'=>$page['page_size'], 'total'=>$page['total']]);
    }
}

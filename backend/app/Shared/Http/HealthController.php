<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

final class HealthController extends Controller
{
    public function index(): ResponseInterface
    {
        return ApiResponder::success($this->response, [
            'service' => 'mini-erp-backend',
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function options(): ResponseInterface
    {
        return ApiResponder::success($this->response, null, 204);
    }
}

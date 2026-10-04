<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class Health extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'service' => 'mini-erp-backend',
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

}

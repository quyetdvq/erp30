<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

final class Health extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondData([
            'service' => 'mini-erp-backend',
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->respondNoContent();
    }

}

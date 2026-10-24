<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

final class Cors extends ApiController
{
    public function options(): ResponseInterface
    {
        return $this->respondNoContent();
    }
}

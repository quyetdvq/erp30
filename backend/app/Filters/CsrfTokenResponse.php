<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Exposes the rotated session CSRF token to the SPA. */
final class CsrfTokenResponse implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $security = service('security');
        $token = $security->getHash();
        if (is_string($token) && $token !== '') {
            $response->setHeader($security->getHeaderName(), $token);
        }

        return $response;
    }
}

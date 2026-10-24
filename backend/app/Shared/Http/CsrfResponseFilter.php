<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Expose the rotated CSRF token to the unchanged Axios interceptor. */
final class CsrfResponseFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $security = service('security');
        $hash = $security->getHash();
        if (is_string($hash) && $hash !== '') {
            $response->setHeader($security->getHeaderName(), $hash);
        }
        return $response;
    }
}

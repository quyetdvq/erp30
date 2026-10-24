<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class RequestMetadataFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        RequestMetadata::clear();
        $locale = 'vi';
        if ($request instanceof IncomingRequest) {
            $locale = $request->negotiate('language', ['vi', 'en']);
            if (! in_array($locale, ['vi', 'en'], true)) {
                $locale = 'vi';
            }
            $request->setLocale($locale);
        }
        RequestMetadata::begin($request->getHeaderLine('X-Request-ID'), $locale);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $response->setHeader('X-Request-ID', RequestMetadata::id());
        return $response->setHeader('Content-Language', RequestMetadata::locale());
    }
}

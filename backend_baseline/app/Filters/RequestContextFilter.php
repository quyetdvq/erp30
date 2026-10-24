<?php

declare(strict_types=1);

namespace App\Filters;

use App\Infrastructure\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class RequestContextFilter implements FilterInterface
{
    /** @param list<string>|null $arguments */
    public function before(RequestInterface $request, $arguments = null)
    {
        $requestId = $request->getHeaderLine('X-Request-ID');
        if (! self::isValidRequestId($requestId)) {
            $requestId = self::newRequestId();
        }

        $locale = 'vi';
        if ($request instanceof IncomingRequest) {
            $locale = $request->negotiate('language', ['vi', 'en']);
            if (! in_array($locale, ['vi', 'en'], true)) {
                $locale = 'vi';
            }
            $request->setLocale($locale);
        }

        RequestContext::begin($requestId, $locale);
        return null;
    }

    /** @param list<string>|null $arguments */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $requestId = RequestContext::requestId();
        if ($requestId !== null) {
            $response->setHeader('X-Request-ID', $requestId);
        }
        $response->setHeader('Content-Language', RequestContext::locale());
        return $response;
    }

    private static function isValidRequestId(string $requestId): bool
    {
        return (bool) preg_match('/\A[0-9A-Za-z][0-9A-Za-z._:-]{0,127}\z/', $requestId);
    }

    private static function newRequestId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

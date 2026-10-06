<?php

declare(strict_types=1);

namespace App\Debug;

use App\Infrastructure\Api\ApiErrorMapper;
use App\Infrastructure\Api\ApiTransport;
use App\Infrastructure\RequestContext;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Exceptions as ExceptionsConfig;
use Throwable;

/** JSON exception handler for /api/v1; non-API requests keep CI4 handling. */
final class ApiExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly ExceptionsConfig $config)
    {
    }

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if (! self::isApiRequest($request)) {
            (new ExceptionHandler($this->config))->handle($exception, $request, $response, $statusCode, $exitCode);
            return;
        }

        self::establishContext($request);
        $error = ApiErrorMapper::map($exception, $statusCode);
        ApiTransport::error($response, $error['code'], $error['message'], $error['status'], $error['details'])->send();

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }

    private static function establishContext(RequestInterface $request): void
    {
        $locale = RequestContext::locale();
        if ($request instanceof IncomingRequest) {
            $negotiated = $request->negotiate('language', ['vi', 'en']);
            $locale = in_array($negotiated, ['vi', 'en'], true) ? $negotiated : 'vi';
            $request->setLocale($locale);
        }

        $requestId = RequestContext::requestId() ?? ApiTransport::ensureRequestId();
        RequestContext::begin($requestId, $locale);
    }

    private static function isApiRequest(RequestInterface $request): bool
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');
        $requestUri = service('superglobals')->server('REQUEST_URI');
        $rawPath = parse_url(is_string($requestUri) ? $requestUri : '', PHP_URL_PATH);
        if (is_string($rawPath) && $rawPath !== '') {
            $rawPath = '/' . ltrim($rawPath, '/');
            if ($rawPath === '/api/v1' || str_starts_with($rawPath, '/api/v1/')) {
                return true;
            }
        }

        return $path === '/api/v1' || str_starts_with($path, '/api/v1/');
    }
}

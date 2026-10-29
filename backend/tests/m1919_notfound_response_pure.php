<?php

declare(strict_types=1);

/** Execute the actual API exception handler without vendor or a DB. */
namespace CodeIgniter\HTTP {
    interface RequestInterface { public function getUri(): object; }
    interface ResponseInterface
    {
        public function setStatusCode(int $code): static;
        public function setHeader(string $name, string $value): static;
        public function setBody(string $body): static;
        public function send(): static;
    }
}
namespace CodeIgniter\Debug {
    interface ExceptionHandlerInterface
    {
        public function handle(\Throwable $e, \CodeIgniter\HTTP\RequestInterface $r, \CodeIgniter\HTTP\ResponseInterface $s, int $statusCode, int $exitCode): void;
    }
    class ExceptionHandler implements ExceptionHandlerInterface
    {
        public static int $delegations = 0;
        public function __construct(object $config) {}
        public function handle(\Throwable $e, \CodeIgniter\HTTP\RequestInterface $r, \CodeIgniter\HTTP\ResponseInterface $s, int $statusCode, int $exitCode): void
        {
            self::$delegations++;
        }
    }
}
namespace CodeIgniter\Exceptions { class PageNotFoundException extends \RuntimeException {} }
namespace Config { class Exceptions {} }
namespace {
    if (! defined('ENVIRONMENT')) define('ENVIRONMENT', 'testing');
    require_once __DIR__ . '/../app/Shared/Http/ApiExceptionHandler.php';

    class Uri { public function __construct(private string $path) {} public function getPath(): string { return $this->path; } }
    class Req implements \CodeIgniter\HTTP\RequestInterface {
        public function __construct(private string $path) {}
        public function getUri(): object { return new Uri($this->path); }
    }
    class Resp implements \CodeIgniter\HTTP\ResponseInterface {
        public int $status = 200;
        public array $headers = [];
        public string $body = '';
        public bool $sent = false;
        public function setStatusCode(int $code): static { $this->status=$code; return $this; }
        public function setHeader(string $name, string $value): static { $this->headers[strtolower($name)]=$value; return $this; }
        public function setBody(string $body): static { $this->body=$body; return $this; }
        public function send(): static { $this->sent=true; return $this; }
    }
    $check = static function(bool $okay, string $label) use (&$n): void {
        if (!$okay) throw new \RuntimeException('M19.19 exception execution failed: ' . $label);
        $n++;
    };
    $n = 0;
    $handler = new \App\Shared\Http\ApiExceptionHandler(new \Config\Exceptions());
    $_SERVER['REQUEST_URI'] = '/uploads/2026/20261027product3105515.jpg';
    $response = new Resp();
    $handler->handle(new \CodeIgniter\Exceptions\PageNotFoundException('raw file path'), new Req('/uploads/2026/20261027product3105515.jpg'), $response, 404, 0);
    $check($response->status===404, 'missing media stays 404');
    $check($response->body==='Not Found', 'no trace or filesystem path exposed');
    $check($response->sent, 'response actually sent');
    $check($response->headers['content-type']==='text/plain; charset=utf-8', 'safe plain 404');
    $check(\CodeIgniter\Debug\ExceptionHandler::$delegations===0, 'CI4 broken trace handler not called on 404');

    $_SERVER['REQUEST_URI'] = '/some-other-page';
    $response = new Resp();
    $handler->handle(new \RuntimeException('generic fault'), new Req('/some-other-page'), $response, 500, 1);
    $check(\CodeIgniter\Debug\ExceptionHandler::$delegations===1, 'other non-API exceptions keep existing handler');
    echo "M19.19 NOTFOUND EXECUTION: {$n}/{$n} PASS\n";
}

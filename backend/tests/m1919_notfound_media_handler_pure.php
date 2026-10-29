<?php

declare(strict_types=1);

/** Non-API 404 must not enter CI4 4.7.5's trace-masking error path. */
$base = dirname(__DIR__);
$handler = (string) file_get_contents($base . '/app/Shared/Http/ApiExceptionHandler.php');
$check = static function (bool $ok, string $label) use (&$n): void {
    if (! $ok) throw new RuntimeException('M19.19 404: ' . $label);
    $n++;
};
$n = 0;
$check(str_contains($handler, 'use CodeIgniter\\Exceptions\\PageNotFoundException;'), 'correct CI4 404 exception class');
$check(str_contains($handler, '$exception instanceof PageNotFoundException'), 'explicit not found safety');
$check(str_contains($handler, "->setStatusCode(404)"), 'keeps HTTP 404');
$check(str_contains($handler, "->setBody('Not Found')"), 'no exception or file path leakage');
$check(str_contains($handler, 'if (! $isApi) {'), 'API exceptions still handled separately');
$check(str_contains($handler, '(new ExceptionHandler($this->config))->handle('), 'non-404 failures keep CI4 handler');
$check(str_contains($handler, 'ApiResponder::error('), 'API error envelope still active');
$check(!str_contains($handler, "setStatusCode(200)"), '404 not masquerading as success');
$root = (string) file_get_contents($base . '/.htaccess');
$public = (string) file_get_contents($base . '/public/.htaccess');
$check(str_contains($root, 'public/upload/$1/$2'), 'root rewrite intact');
$check(str_contains($public, 'upload/$1/$2'), 'public rewrite intact');
$check(!str_contains($handler, 'file_get_contents($physical'), 'no PHP private media byte proxy');
echo "M19.19 MEDIA 404: {$n}/{$n} PASS\n";

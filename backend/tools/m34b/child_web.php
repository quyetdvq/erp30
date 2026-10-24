<?php

declare(strict_types=1);

// Internal M3.4B loopback HTTP entrypoint; reached ONLY after router.php
// verifies the nonce-owned MariaDB marker for the current request.
if (PHP_SAPI !== 'cli-server' || version_compare(PHP_VERSION, '8.2', '<')) {
    http_response_code(503);
    exit(1);
}
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}
require_once __DIR__ . '/M34BChildPaths.php';
$root = dirname(__DIR__, 2);
$paths = M34BChildPaths::forSandbox($root);

define('FCPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require $paths->systemDirectory . '/Boot.php';
exit(\CodeIgniter\Boot::bootWeb($paths));

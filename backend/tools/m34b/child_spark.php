<?php

declare(strict_types=1);

// Internal M3.4B CLI entrypoint. Does not read backend/.env.
// Do not use for normal Backend CLI work.
if (PHP_SAPI !== 'cli' || version_compare(PHP_VERSION, '8.2', '<')) {
    exit(1);
}
require_once __DIR__ . '/M34BChildPaths.php';
$root = dirname(__DIR__, 2);
$paths = M34BChildPaths::forSandbox($root);

define('FCPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

if (is_dir($paths->testsDirectory . '/_support/') && ! defined('SUPPORTPATH')) {
    define('SUPPORTPATH', realpath($paths->testsDirectory . '/_support/') . DIRECTORY_SEPARATOR);
}

require $paths->systemDirectory . '/Boot.php';
exit(\CodeIgniter\Boot::bootSpark($paths));

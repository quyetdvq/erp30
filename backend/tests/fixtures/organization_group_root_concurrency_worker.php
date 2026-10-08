<?php

declare(strict_types=1);

putenv('CI_ENVIRONMENT=testing');
$_ENV['CI_ENVIRONMENT'] = 'testing';
$_SERVER['CI_ENVIRONMENT'] = 'testing';
if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

$backend = dirname(__DIR__, 2);
require $backend . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Paths.php';
$paths = new Config\Paths();
if (! defined('FCPATH')) {
    define('FCPATH', $backend . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
}
if (! defined('TESTPATH')) {
    define('TESTPATH', realpath($paths->testsDirectory) . DIRECTORY_SEPARATOR);
}
if (! defined('SUPPORTPATH')) {
    define('SUPPORTPATH', realpath(TESTPATH . '_support') . DIRECTORY_SEPARATOR);
}
require $paths->systemDirectory . DIRECTORY_SEPARATOR . 'Boot.php';
CodeIgniter\Boot::bootConsole($paths);

[$readyPath, $startPath, $resultPath] = array_slice($argv, 1, 3);
file_put_contents($readyPath, 'ready');
$deadline = microtime(true) + 15.0;
while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

try {
    (new App\Database\Seeds\GroupRootSeeder(new Config\Database()))->run();
    file_put_contents($resultPath, json_encode(['ok' => true], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultPath, json_encode([
        'ok' => false,
        'code' => $exception instanceof App\Exceptions\DomainException ? $exception->getErrorCode() : get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
}

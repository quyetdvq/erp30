<?php

declare(strict_types=1);

// This router is launched ONLY by m34b_run.php on 127.0.0.1. Every
// request must attest the generated disposable MariaDB schema BEFORE CI4
// may execute its mutation routes. Do not replace it with a public URL.
$database = getenv('M34B_ONLY_SCHEMA');
$nonce = getenv('M34B_ONLY_NONCE');
$pdoPort = getenv('database.default.port');
$ciSchema = getenv('database.default.database');
$ciHost = getenv('database.default.hostname');
$writeFlag = getenv('DINKERP_M33B_WRITE_MODE');
// Use normal CI4 development semantics only inside this spawned child.
// Guard BOTH connection groups, including their physical table prefix.
$testSchema = getenv('database.tests.database');
$defaultPrefix = getenv('database.default.DBPrefix');
$testPrefix = getenv('database.tests.DBPrefix');
$allowed = is_string($database) && is_string($nonce)
    && preg_match('/\A[a-f0-9]{32}\z/D', $nonce) === 1
    && $database === 'erp_m34b_' . substr($nonce, 0, 16) . '_sandbox'
    && $database === $ciSchema && $database === $testSchema
    && $ciHost === '127.0.0.1'
    && is_string($defaultPrefix) && $defaultPrefix === $testPrefix
    && preg_match('/\A(?:[a-z][a-z0-9_]{0,30}_)?\z/D', $defaultPrefix) === 1
    && getenv('CI_ENVIRONMENT') === 'development'
    && getenv('database.tests.hostname') === '127.0.0.1'
    && $writeFlag === 'enabled'
    && is_string($pdoPort) && preg_match('/\A[0-9]{1,5}\z/D', $pdoPort) === 1
    && (int) $pdoPort > 0 && (int) $pdoPort <= 65535;
if ($allowed) {
    try {
        $db = new PDO(
            'mysql:host=127.0.0.1;port=' . $pdoPort . ';dbname=' . $database . ';charset=utf8mb4',
            (string) getenv('database.default.username'),
            (string) getenv('database.default.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $marker = $db->query("SELECT run_id FROM m34b_sandbox_guard WHERE id=1 AND purpose='M34B_DISPOSABLE_SCHEMA'")
            ->fetchColumn();
        $allowed = is_string($marker) && hash_equals($nonce, $marker)
            && (string) $db->query('SELECT DATABASE()')->fetchColumn() === $database;
    } catch (Throwable) {
        $allowed = false;
    }
}
if (! $allowed) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'M34B local sandbox attestation failed';
    return true;
}

$public = dirname(__DIR__, 2) . '/public';
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (str_contains($path, "\0") || str_contains($path, '..')) {
    http_response_code(400);
    return true;
}
if ($path !== '/' && is_file($public . $path)) {
    return false;
}
// Use a sandbox-only CodeIgniter entrypoint that does NOT load backend/.env.
require __DIR__ . '/child_web.php';

<?php

declare(strict_types=1);

// M4.2A.1-only router for 127.0.0.1. Every
// request must attest the generated disposable MariaDB schema BEFORE CI4
// may execute its mutation routes. Do not replace it with a public URL.
$database = getenv('M34B_ONLY_SCHEMA');
$nonce = getenv('M34B_ONLY_NONCE');
$pdoPort = getenv('database.default.port');
$ciSchema = getenv('database.default.database');
$ciHost = getenv('database.default.hostname');
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
    echo 'M42 local sandbox attestation failed';
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
// M4.2B ONLY: attest the schema above *before* recording that each independent
// PHP server accepted the pending Numbering POST. The marker cannot be placed
// in a public directory or outside this nonce's private sandbox run folder.
$raceRole = getenv('M42_RACE_ROLE');
$raceDir = getenv('M42_RACE_DIR');
$expectedRaceDir = dirname(__DIR__, 2) . '/writable/m42/run_' . substr($nonce, 0, 16);
// Windows uses backslashes in dirname(__DIR__) but parent passes a mixed-separator
// path; compare canonical paths, never raw strings, and forbid symlink paths.
$expectedRaceReal = realpath($expectedRaceDir);
$providedRaceReal = is_string($raceDir) ? realpath($raceDir) : false;
if (in_array($raceRole, ['a', 'b'], true)
    && $expectedRaceReal !== false && $providedRaceReal === $expectedRaceReal
    && is_string($raceDir) && !is_link($raceDir)
    && is_dir($expectedRaceDir) && !is_link($expectedRaceDir)
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && preg_match('#\A/api/v1/resources/[1-9][0-9]*/numbering-series\z#D', $path) === 1) {
    if (file_put_contents($expectedRaceDir . '/race_' . $raceRole . '_accepted', "ACCEPTED\n", LOCK_EX) === false) {
        http_response_code(503);
        return true;
    }
}
// Use a sandbox-only CodeIgniter entrypoint that does NOT load backend/.env.
require __DIR__ . '/child_web.php';

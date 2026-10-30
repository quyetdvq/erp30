<?php

declare(strict_types=1);

/**
 * M3.4B local-child bootstrap ONLY. The real backend .env MUST NOT be loaded
 * by either the spawned Spark CLI or the loopback HTTP child.
 *
 * CI4 4.7.5 DotEnv copies values into $_ENV/$_SERVER even when putenv() is
 * already populated, and BaseConfig prefers those arrays to getenv(). This
 * can silently restore the source DB configuration from backend/.env.
 *
 * Select an EMPTY nonce-owned envDirectory BEFORE Boot::bootSpark/bootWeb,
 * so the explicitly supplied process environment is the only DB authority.
 */
final class M34BChildPaths
{
    public static function forSandbox(string $backendRoot): \Config\Paths
    {
        $root = realpath($backendRoot);
        if ($root === false || ! is_dir($root . '/app/Config')) {
            throw new RuntimeException('M34B_CHILD_GUARD: Invalid Backend root.');
        }
        $nonce = getenv('M34B_ONLY_NONCE');
        $schema = getenv('M34B_ONLY_SCHEMA');
        if (! is_string($nonce) || preg_match('/\A[a-f0-9]{32}\z/D', $nonce) !== 1
            || ! is_string($schema) || $schema !== 'erp_m34b_' . substr($nonce, 0, 16) . '_sandbox'
            || getenv('CI_ENVIRONMENT') !== 'development'
) {
            throw new RuntimeException('M34B_CHILD_GUARD: Missing exact sandbox context.');
        }

        $prefix = getenv('database.default.DBPrefix');
        $port = getenv('database.default.port');
        if (getenv('database.default.database') !== $schema
            || getenv('database.tests.database') !== $schema
            || getenv('database.default.hostname') !== '127.0.0.1'
            || getenv('database.tests.hostname') !== '127.0.0.1'
            || getenv('database.default.DBDriver') !== 'MySQLi'
            || getenv('database.tests.DBDriver') !== 'MySQLi'
            || ! is_string($prefix)
            || preg_match('/\A(?:[a-z][a-z0-9_]{0,30}_)?\z/D', $prefix) !== 1
            || getenv('database.tests.DBPrefix') !== $prefix
            || ! is_string($port) || preg_match('/\A[0-9]{1,5}\z/D', $port) !== 1
            || (int) $port < 1 || (int) $port > 65535
            || getenv('database.tests.port') !== $port
            || getenv('database.default.username') !== getenv('database.tests.username')
            || getenv('database.default.password') !== getenv('database.tests.password')) {
            throw new RuntimeException('M34B_CHILD_GUARD: Sandbox DB connection settings disagree.');
        }

        $base = realpath($root . '/writable/m34b');
        $directory = realpath($root . '/writable/m34b/run_' . substr($nonce, 0, 16));
        if ($base === false || $directory === false || dirname($directory) !== $base
            || basename($directory) !== 'run_' . substr($nonce, 0, 16)
            || is_file($directory . '/.env')) {
            throw new RuntimeException('M34B_CHILD_GUARD: Isolated env directory is missing or unsafe.');
        }

        require_once $root . '/app/Config/Paths.php';
        $paths = new \Config\Paths();
        $expectedApp = realpath($root . '/app');
        if (realpath($paths->appDirectory) !== $expectedApp) {
            throw new RuntimeException('M34B_CHILD_GUARD: Backend app path is unexpected.');
        }
        // CI4 Boot::loadDotEnv() explicitly respects Paths::envDirectory.
        // This directory must stay empty of .env; do not copy production settings.
        $paths->envDirectory = $directory;
        return $paths;
    }
}

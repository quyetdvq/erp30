<?php

declare(strict_types=1);

/**
 * Safe CodeIgniter test database harness for P1-ENV-004.
 *
 * This script only permits destructive operations when the configured tests
 * database is exactly erp_test. Credentials remain in the local runtime
 * configuration and are never written to the repository.
 */

const APPROVED_TEST_DATABASE = 'erp_test';

$backendPath = dirname(__DIR__);
$action      = strtolower($argv[1] ?? 'help');
$arguments   = array_slice($argv, 2);

putenv('CI_ENVIRONMENT=testing');
$_ENV['CI_ENVIRONMENT']    = 'testing';
$_SERVER['CI_ENVIRONMENT'] = 'testing';
putenv('database.defaultGroup=tests');
$_ENV['database.defaultGroup']    = 'tests';
$_SERVER['database.defaultGroup'] = 'tests';
if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

require $backendPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Paths.php';

$paths = new Config\Paths();
if (! defined('FCPATH')) {
    define('FCPATH', $backendPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
}
if (! defined('TESTPATH')) {
    define('TESTPATH', realpath($paths->testsDirectory) . DIRECTORY_SEPARATOR);
}
if (! defined('SUPPORTPATH')) {
    define('SUPPORTPATH', realpath(TESTPATH . '_support') . DIRECTORY_SEPARATOR);
}
require $paths->systemDirectory . DIRECTORY_SEPARATOR . 'Boot.php';
CodeIgniter\Boot::bootConsole($paths);

$databaseConfig = new Config\Database();
$testConfig     = $databaseConfig->tests;
$configuredDb   = (string) ($testConfig['database'] ?? '');

if (! in_array($action, ['help', 'inventory'], true)) {
    assertTestingEnvironment();
    assertApprovedDatabase($configuredDb);
}

try {
    $exitCode = match ($action) {
        'help'      => showHelp(),
        'inventory' => inventory($testConfig),
        'prepare'   => prepareDatabase($testConfig),
        'reset'     => resetDatabase($testConfig, $arguments),
        'fresh'     => freshMigration($testConfig, $arguments),
        'forward-domain' => forwardDomainMigration($testConfig, $arguments),
        'seed'      => seedResourceCatalog($testConfig, $arguments),
        'rebuild'   => rebuildTwice($testConfig, $arguments),
        'db-tests'  => runPhpUnit('tests/database', 'database PHPUnit tests'),
        'full-tests' => runPhpUnit('', 'full PHPUnit suite'),
        default     => fail("Unknown action: {$action}"),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
}

exit($exitCode);

/**
 * @param array<string, mixed> $config
 */
function assertApprovedDatabase(string $database): void
{
    if ($database !== APPROVED_TEST_DATABASE) {
        throw new RuntimeException(
            "Refusing test DB operation: Config\\Database::tests points to '{$database}', expected '" . APPROVED_TEST_DATABASE . "'.",
        );
    }
}

function assertTestingEnvironment(): void
{
    if (ENVIRONMENT !== 'testing') {
        throw new RuntimeException("Refusing test DB operation: runtime environment is '" . ENVIRONMENT . "', expected 'testing'.");
    }
}

/**
 * @param array<string, mixed> $config
 */
function connect(array $config, bool $selectDatabase = true): mysqli
{
    $connection = mysqli_init();
    if ($connection === false) {
        throw new RuntimeException('Unable to initialize MySQLi.');
    }

    $database = $selectDatabase ? (string) $config['database'] : null;
    $connected = mysqli_real_connect(
        $connection,
        (string) $config['hostname'],
        (string) $config['username'],
        (string) $config['password'],
        $database,
        (int) $config['port'],
    );

    if (! $connected) {
        throw new RuntimeException('Database connection failed: ' . mysqli_connect_error());
    }

    return $connection;
}

/**
 * @param array<string, mixed> $config
 */
function prepareDatabase(array $config): int
{
    $connection = connect($config, false);
    $database   = $connection->real_escape_string((string) $config['database']);
    $sql        = sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
        $database,
    );

    if (! $connection->query($sql)) {
        throw new RuntimeException('Unable to prepare test database: ' . $connection->error);
    }

    $connection->close();
    fwrite(STDOUT, "Prepared isolated test database: {$database}" . PHP_EOL);

    return 0;
}

/**
 * @param array<string, mixed> $config
 * @param list<string>         $arguments
 */
function resetDatabase(array $config, array $arguments): int
{
    requireConfirmation($arguments);
    prepareDatabase($config);

    $connection = connect($config);
    $connection->query('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $connection->query('SHOW FULL TABLES');

    if ($tables === false) {
        throw new RuntimeException('Unable to enumerate test database tables: ' . $connection->error);
    }

    while ($row = $tables->fetch_row()) {
        $table = str_replace('`', '``', (string) $row[0]);
        $type  = strtoupper((string) ($row[1] ?? 'BASE TABLE'));
        $verb  = str_contains($type, 'VIEW') ? 'VIEW' : 'TABLE';

        if (! $connection->query("DROP {$verb} IF EXISTS `{$table}`")) {
            throw new RuntimeException("Unable to drop {$verb} {$table}: " . $connection->error);
        }
    }

    $connection->query('SET FOREIGN_KEY_CHECKS = 1');
    $connection->close();
    fwrite(STDOUT, 'Reset completed for approved test database: ' . APPROVED_TEST_DATABASE . PHP_EOL);

    return 0;
}

/**
 * @param array<string, mixed> $config
 * @param list<string>         $arguments
 */
function freshMigration(array $config, array $arguments): int
{
    resetDatabase($config, $arguments);

    return runSpark('migrate --all -g tests', 'fresh migration (database.defaultGroup=tests)');
}

/**
 * @param array<string, mixed> $config
 * @param list<string>         $arguments
 */
function rebuildTwice(array $config, array $arguments): int
{
    requireConfirmation($arguments);
    $first  = freshMigration($config, $arguments);
    $second = freshMigration($config, $arguments);

    return ($first === 0 && $second === 0) ? 0 : 1;
}

/**
 * Rehearse the real forward migrator from the immediately preceding legacy
 * resources.domain state. This is deliberately limited to the approved test
 * database and leaves it at the current migration level.
 *
 * @param array<string, mixed> $config
 * @param list<string>         $arguments
 */
function forwardDomainMigration(array $config, array $arguments): int
{
    requireConfirmation($arguments);
    if (freshMigration($config, $arguments) !== 0) {
        return 1;
    }
    if (seedResourceCatalog($config, $arguments) !== 0) {
        return 1;
    }

    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Database'
        . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR . '2026-10-05-000010_NormalizeResourceDomains.php';
    $migration = new App\Database\Migrations\NormalizeResourceDomains(
        Config\Database::forge(db_connect('tests')),
    );
    $migration->down();

    $connection = connect($config);
    $prefix = str_replace('`', '``', (string) $config['DBPrefix']);
    $updates = [
        "UPDATE `{$prefix}resources` SET domain = NULL WHERE type = 'province'",
        "UPDATE `{$prefix}resources` SET domain = 'COMPANY' WHERE type = 'contact'",
        "UPDATE `{$prefix}resources` SET domain = 'MASTER' WHERE type = 'employee_assignment'",
        "UPDATE `{$prefix}resources` SET domain = 'SYSTEM' WHERE type = 'organization'",
    ];
    foreach ($updates as $sql) {
        if (! $connection->query($sql)) {
            throw new RuntimeException('Unable to create legacy Resource Domain rehearsal state: ' . $connection->error);
        }
    }
    if (! $connection->query("DELETE FROM `{$prefix}migrations` WHERE version = '2026-10-05-000010'")) {
        throw new RuntimeException('Unable to return test migration ledger to pre-000010 state: ' . $connection->error);
    }
    $connection->close();

    return runSpark('migrate --all -g tests', 'forward migration from pre-000010 Resource Domain state');
}

/**
 * @param array<string, mixed> $config
 * @param list<string>         $arguments
 */
function seedResourceCatalog(array $config, array $arguments): int
{
    requireConfirmation($arguments);
    (new App\Database\Seeds\ResourceActionSeeder(new Config\Database()))->run();
    fwrite(STDOUT, 'Resource catalog seed completed for approved test database: ' . APPROVED_TEST_DATABASE . PHP_EOL);

    return 0;
}

function runSpark(string $arguments, string $label): int
{
    $command = quoteCommand(PHP_BINARY) . ' spark ' . $arguments;
    fwrite(STDOUT, "--- {$label}: {$command} ---" . PHP_EOL);

    // Spark's testing bootstrap expects PHPUnit's SUPPORTPATH constant.
    // The harness has already verified testing + erp_test before invoking it,
    // and -g tests selects the isolated connection explicitly.
    $previousEnvironment = getenv('CI_ENVIRONMENT');
    putenv('CI_ENVIRONMENT=development');
    $result = runProcess($command);
    putenv('CI_ENVIRONMENT=' . ($previousEnvironment === false ? '' : $previousEnvironment));

    return $result;
}

function runPhpUnit(string $path, string $label): int
{
    $command = quoteCommand(PHP_BINARY) . ' vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
    if ($path !== '') {
        $command .= ' ' . quoteCommand($path);
    }
    $command .= ' --no-coverage';
    fwrite(STDOUT, "--- {$label}: {$command} ---" . PHP_EOL);

    return runProcess($command);
}

function runProcess(string $command): int
{
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__),
    );

    if (! is_resource($process)) {
        throw new RuntimeException("Unable to start command: {$command}");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($stdout !== false && $stdout !== '') {
        fwrite(STDOUT, $stdout);
    }
    if ($stderr !== false && $stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    fwrite(STDOUT, "Exit code: {$exitCode}" . PHP_EOL);

    return $exitCode;
}

/**
 * @param list<string> $arguments
 */
function requireConfirmation(array $arguments): void
{
    if (! in_array('--yes', $arguments, true)) {
        throw new RuntimeException('Destructive test DB operation requires the explicit --yes flag.');
    }
}

function quoteCommand(string $value): string
{
    return '"' . str_replace('"', '\\"', $value) . '"';
}

function showHelp(): int
{
    fwrite(STDOUT, <<<'HELP'
Usage:
  php tools/test-db-harness.php inventory
  php tools/test-db-harness.php prepare
  php tools/test-db-harness.php reset --yes
  php tools/test-db-harness.php fresh --yes
  php tools/test-db-harness.php forward-domain --yes
  php tools/test-db-harness.php seed --yes
  php tools/test-db-harness.php rebuild --yes
  php tools/test-db-harness.php db-tests
  php tools/test-db-harness.php full-tests

Destructive actions require --yes and are refused unless Config\Database::$tests
points exactly to the approved database erp_test.
HELP
    );
    fwrite(STDOUT, PHP_EOL);

    return 0;
}

/**
 * @param array<string, mixed> $config
 */
function inventory(array $config): int
{
    $database = (string) $config['database'];
    fwrite(STDOUT, "Configured test database: {$database}" . PHP_EOL);
    fwrite(STDOUT, 'Configured test host: ' . (string) $config['hostname'] . ':' . (string) $config['port'] . PHP_EOL);
    fwrite(STDOUT, 'Configured test prefix: ' . (string) $config['DBPrefix'] . PHP_EOL);

    if (ENVIRONMENT !== 'testing') {
        fwrite(STDOUT, "Runtime environment: " . ENVIRONMENT . PHP_EOL);
        fwrite(STDOUT, 'Isolation guard: FAIL (runtime environment must be testing)' . PHP_EOL);

        return 1;
    }
    fwrite(STDOUT, 'Runtime environment: ' . ENVIRONMENT . PHP_EOL);
    if ($database !== APPROVED_TEST_DATABASE) {
        fwrite(STDOUT, 'Isolation guard: FAIL (approved database is ' . APPROVED_TEST_DATABASE . ')' . PHP_EOL);

        return 1;
    }

    $connection = connect($config);
    $result     = $connection->query('SELECT COUNT(*) FROM `' . $connection->real_escape_string((string) $config['DBPrefix']) . 'migrations`');
    $count      = $result === false ? 'unavailable' : (string) ($result->fetch_row()[0] ?? '0');
    $connection->close();

    fwrite(STDOUT, 'Isolation guard: PASS' . PHP_EOL);
    fwrite(STDOUT, "Migration rows currently visible: {$count}" . PHP_EOL);

    return 0;
}

function fail(string $message): int
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);

    return 2;
}

<?php

declare(strict_types=1);

/** BE-REORG-001A: one command, one log; no application migration/seed commands. */
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php tools/test-foundation.php --confirm-test-db\n"
        . "Run ONLY after confirming the loopback erp_test database is disposable.\n"
        . "Runs the full PHPUnit suite once (including HTTP/lock tests), then PHPStan.\n"
        . "Writes ../test-result.log. Does not run tests on the business database.\n");
    exit($arguments === [] ? 2 : 0);
}
if ($arguments !== ['--confirm-test-db']) {
    fwrite(STDERR, "Unknown arguments. Use --help.\n");
    exit(2);
}

$backend = dirname(__DIR__);
chdir($backend);
$logPath = dirname($backend) . '/test-result.log';
$log = null;
$runLock = null;
$exit = 2;
try {
    $runLock = fopen($backend . '/writable/be-reorg-001a-run.lock', 'c');
    if ($runLock === false || ! flock($runLock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another Foundation runner is active. Do not run tests concurrently on the same DB.');
    }
    $log = fopen($logPath, 'wb');
    if ($log === false) { throw new RuntimeException('Cannot create test-result.log.'); }
    $write = static function (string $text) use ($log): void {
        fwrite(STDOUT, $text);
        fwrite($log, $text);
        fflush($log);
    };
    $label = defined('BE_REORG_AUTHORIZATION_ADMIN_CASES') ? 'BE-REORG-007'
        : (defined('BE_REORG_NUMBERING_CASES') ? 'BE-REORG-006'
        : (defined('BE_REORG_SETTINGS_CASES') ? 'BE-REORG-005'
            : (defined('BE_REORG_EMPLOYEE_STRUCTURE_CASES') ? 'BE-REORG-004'
                : (defined('BE_REORG_CONTACT_CASES') ? 'BE-REORG-003'
                    : (defined('BE_REORG_ORGANIZATION_CASES') ? 'BE-REORG-002' : 'BE-REORG-001A')))));
    $write($label . ' verification - ' . gmdate('c') . PHP_EOL . 'PHP ' . PHP_VERSION . PHP_EOL);
    $write("Scope: full backend tests + in-process HTTP + real DB locking; NOT browser/Apache UAT.\n");
    foreach (['app/Bootstrap/FoundationFactory.php',
        'app/Modules/Foundation/Application/LocationService.php',
        'app/Modules/Foundation/Application/Ports/LocationRepositoryInterface.php',
        'app/Modules/Foundation/Contracts/LocationErrors.php',
        'app/Modules/Foundation/Infrastructure/Persistence/SqlLocationRepository.php',
        'app/Integration/Foundation/LocationLifecycleAdapter.php'] as $prerequisite) {
        if (! is_file($prerequisite)) {
            throw new RuntimeException('BE-REORG-001 must be applied first; missing ' . $prerequisite);
        }
    }
    if (defined('BE_REORG_ORGANIZATION_CASES')) {
        foreach (['app/Bootstrap/OrganizationFactory.php',
            'app/Modules/Organization/Application/OrganizationService.php',
            'app/Modules/Organization/Application/Ports/OrganizationRepositoryInterface.php',
            'app/Modules/Organization/Infrastructure/Persistence/SqlOrganizationRepository.php',
            'app/Modules/Organization/Contracts/OrganizationErrors.php',
            'tests/unit/BeReorg001FoundationPersistenceTypesTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-002 / 001B checkpoint; missing ' . $prerequisite);
            }
        }
    }
    if (defined('BE_REORG_CONTACT_CASES')) {
        foreach (['app/Bootstrap/ContactFactory.php',
            'app/Modules/Contact/Application/ContactService.php',
            'app/Modules/Contact/Application/Ports/ContactRepositoryInterface.php',
            'app/Modules/Contact/Infrastructure/Persistence/SqlContactRepository.php',
            'app/Modules/Contact/Contracts/ContactErrors.php',
            'tests/unit/BeReorg003ContactBoundaryTest.php',
            'tests/unit/BeReorg003ContactCompatibilityTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-003 checkpoint; missing ' . $prerequisite);
            }
        }
    }
    if (defined('BE_REORG_EMPLOYEE_STRUCTURE_CASES')) {
        foreach (['app/Bootstrap/EmployeeStructureFactory.php',
            'app/Modules/EmployeeStructure/Department/Application/DepartmentService.php',
            'app/Modules/EmployeeStructure/Position/Application/PositionService.php',
            'app/Modules/EmployeeStructure/Employee/Application/EmployeeService.php',
            'app/Modules/EmployeeStructure/Assignment/Application/EmployeeAssignmentService.php',
            'tests/unit/BeReorg004EmployeeStructureBoundaryTest.php',
            'tests/unit/BeReorg004EmployeeStructureCompatibilityTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-004 checkpoint; missing ' . $prerequisite);
            }
        }
    }
    if (defined('BE_REORG_SETTINGS_CASES')) {
        foreach (['app/Bootstrap/SettingsFactory.php',
            'app/Modules/Settings/Display/Application/DisplaySettingsResolver.php',
            'app/Modules/Settings/Display/Application/DisplaySettingsMutationService.php',
            'app/Modules/Settings/Display/Application/Ports/DisplaySettingsStorageInterface.php',
            'app/Modules/Settings/Display/Application/Ports/ConfigurationAuditEventWriterInterface.php',
            'app/Modules/Settings/Display/Infrastructure/CodeIgniterDisplaySettingsStorage.php',
            'app/Modules/Settings/Display/Infrastructure/SqlConfigurationAuditEventRepository.php',
            'tests/unit/BeReorg005SettingsBoundaryTest.php',
            'tests/unit/BeReorg005SettingsCompatibilityTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-005 checkpoint; missing ' . $prerequisite);
            }
        }
    }
    if (defined('BE_REORG_NUMBERING_CASES')) {
        foreach (['app/Bootstrap/NumberingFactory.php',
            'app/Modules/Numbering/Configuration/Application/AutoNumberingConfigurationService.php',
            'app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php',
            'app/Modules/Numbering/Issuance/Application/AutoNumberService.php',
            'app/Modules/Numbering/Scope/Application/AutoNumberingScopePeriodResolver.php',
            'app/Modules/Numbering/Pattern/Application/CanonicalNumberingPatternValidator.php',
            'tests/unit/BeReorg006NumberingBoundaryTest.php',
            'tests/unit/BeReorg006NumberingCompatibilityTest.php',
            'tests/unit/BeReorg006NumberingFactoryTest.php',
            'tests/database/BeReorg006NumberingHttpTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-006 checkpoint; missing ' . $prerequisite);
            }
        }
    }
    if (defined('BE_REORG_AUTHORIZATION_ADMIN_CASES')) {
        foreach (['app/Bootstrap/AuthorizationAdministrationFactory.php',
            'app/Modules/Authorization/Administration/Groups/Application/Ports/GroupRepositoryPort.php',
            'app/Modules/Authorization/Administration/Policies/Application/Ports/PolicyAdministrationRepositoryPort.php',
            'app/Modules/Authorization/Administration/Groups/Infrastructure/Persistence/SqlAuthorizationGroupRepository.php',
            'app/Modules/Authorization/Administration/Policies/Infrastructure/Persistence/SqlPolicyRepository.php',
            'app/Modules/Authorization/Administration/Policies/Validation/PolicyValidator.php',
            'app/Modules/Authorization/Administration/Policies/Validation/PolicyParticipantValidator.php',
            'app/Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php',
            'app/Modules/Authorization/Administration/Policies/Application/PolicyAdministrationService.php',
            'tests/unit/BeReorg007AuthorizationBoundaryTest.php',
            'tests/unit/BeReorg007AuthorizationCompatibilityTest.php',
            'tests/database/BeReorg007AuthorizationPolicySqlParityTest.php',
            'tests/unit/BeReorg007AuthorizationFactoryTest.php',
            'tests/database/BeReorg007AuthorizationHttpTest.php'] as $prerequisite) {
            if (! is_file($prerequisite)) {
                throw new RuntimeException('Incomplete BE-REORG-007 checkpoint; missing ' . $prerequisite);
            }
        }
    }
    $required = ['mysqli', 'intl', 'mbstring', 'dom', 'xml', 'xmlwriter', 'bcmath'];
    $missing = array_values(array_filter($required, static fn (string $name): bool => ! extension_loaded($name)));
    if ($missing !== []) { throw new RuntimeException('Missing PHP extensions: ' . implode(', ', $missing)); }
    if (! is_file('vendor/autoload.php') || ! is_file('vendor/bin/phpunit') || ! is_file('vendor/bin/phpstan')) {
        throw new RuntimeException('Missing locked dependencies. Use composer install, not composer update.');
    }
    if (! function_exists('proc_open')) { throw new RuntimeException('proc_open is required for the test runner.'); }

    // Preserve local PHPUnit XML/env settings, but do not silently replace a custom bootstrap.
    $xmlPath = is_file('phpunit.xml') ? 'phpunit.xml' : 'phpunit.dist.xml';
    $xml = new DOMDocument();
    if (! $xml->load($xmlPath, LIBXML_NONET) || $xml->documentElement === null) {
        throw new RuntimeException('Cannot read PHPUnit configuration.');
    }
    $bootstrap = $xml->documentElement->getAttribute('bootstrap');
    $normalBootstrap = realpath('vendor/codeigniter4/framework/system/Test/bootstrap.php');
    if (realpath($bootstrap) !== $normalBootstrap || $normalBootstrap === false) {
        throw new RuntimeException('Custom PHPUnit bootstrap detected. Stop rather than override it.');
    }
    putenv('CI_ENVIRONMENT=testing');
    $_ENV['CI_ENVIRONMENT'] = $_SERVER['CI_ENVIRONMENT'] = 'testing';
    if (! defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
    require 'app/Config/Paths.php';
    $paths = new Config\Paths();
    if (! defined('FCPATH')) { define('FCPATH', $backend . '/public/'); }
    if (! defined('TESTPATH')) { define('TESTPATH', $backend . '/tests/'); }
    if (! defined('SUPPORTPATH')) { define('SUPPORTPATH', TESTPATH . '_support/'); }
    require $paths->systemDirectory . '/Boot.php';
    CodeIgniter\Boot::bootConsole($paths);
    require_once 'tests/_support/Foundation/RuntimeGuard.php';
    $database = new Config\Database();
    $target = Tests\Support\Foundation\RuntimeGuard::assertConfiguration(ENVIRONMENT, $database->defaultGroup, $database->tests);
    $prefix = (string) $database->tests['DBPrefix'];
    $write('Confirmed test target: ' . $target . ' (prefix: ' . $prefix . ')' . PHP_EOL);
    $write("WARNING: DatabaseTestTrait can refresh erp_test tables. Do not run another test process on this DB.\n");
    putenv('ERP_FOUNDATION_EXPECTED_TARGET=' . $target);
    putenv('ERP_FOUNDATION_EXPECTED_PREFIX=' . $prefix);

    $guardExit = foundationRun([PHP_BINARY, 'tools/test-foundation-guard.php'], $backend, $write);
    if ($guardExit !== 0) { throw new RuntimeException('Test safety guard self-check failed.'); }
    $junit = $backend . '/build/logs/be-reorg-001a.xml';
    if (! is_dir(dirname($junit)) && ! mkdir(dirname($junit), 0777, true)) {
        throw new RuntimeException('Cannot create build/logs.');
    }
    if (is_file($junit) && ! unlink($junit)) { throw new RuntimeException('Cannot remove stale JUnit result.'); }
    $write(defined('BE_REORG_AUTHORIZATION_ADMIN_CASES')
        ? "\n--- Full PHPUnit (includes 24 Authorization Administration cases, 91 existing PBAC authority cases, 20 Numbering cases, 39 Settings cases, 64 Employee Structure cases, 32 Contact cases, 37 Organization cases and Foundation runtime tests) ---\n"
        : (defined('BE_REORG_NUMBERING_CASES')
        ? "\n--- Full PHPUnit (includes 20 Numbering reorganization cases, 68 existing Numbering cases, 39 Settings cases, 64 Employee Structure cases, 32 Contact cases, 37 Organization cases and the existing Foundation runtime tests) ---\n"
        : (defined('BE_REORG_SETTINGS_CASES')
            ? "\n--- Full PHPUnit (includes 39 Settings cases, 64 Employee Structure cases, 32 Contact cases, 37 Organization cases and the existing Foundation runtime tests) ---\n"
            : (defined('BE_REORG_EMPLOYEE_STRUCTURE_CASES')
                ? "\n--- Full PHPUnit (includes 64 Employee Structure cases, 32 Contact cases, 37 Organization cases and the existing Foundation runtime tests) ---\n"
                : (defined('BE_REORG_CONTACT_CASES')
                    ? "\n--- Full PHPUnit (includes 32 Contact cases, 37 Organization cases and the existing Foundation runtime tests) ---\n"
                    : (defined('BE_REORG_ORGANIZATION_CASES')
                        ? "\n--- Full PHPUnit (includes 37 Organization cases and the existing Foundation runtime tests) ---\n"
                        : "\n--- Full PHPUnit (includes the 13 new Foundation runtime tests) ---\n"))))));
    $unitExit = foundationRun([PHP_BINARY, 'vendor/bin/phpunit', '--configuration', $xmlPath,
        '--bootstrap', 'tests/_support/Foundation/guarded_bootstrap.php', '--no-coverage', '--colors=never',
        '--log-junit', $junit], $backend, $write);
    $write('PHPUnit exit code: ' . $unitExit . PHP_EOL);
    if ($unitExit !== 0) {
        $exit = 1;
        $write("STOP: PHPUnit did not pass. PHPStan not run; send test-result.log.\n");
    } else {
        foundationCheckJUnit($junit);
        $write("Foundation coverage gate: all 9 HTTP + 4 DB-lock tests executed, none skipped.\n");
        if (defined('BE_REORG_ORGANIZATION_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_ORGANIZATION_CASES);
            $write("Organization coverage gate: all 37 new cases executed, none skipped (5 HTTP + 3 domain-lock).\n");
        }
        if (defined('BE_REORG_CONTACT_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_CONTACT_CASES);
            $write("Contact coverage gate: all 32 new cases executed, none skipped (5 HTTP + 4 lifecycle).\n");
        }
        if (defined('BE_REORG_EMPLOYEE_STRUCTURE_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_EMPLOYEE_STRUCTURE_CASES);
            $write("Employee Structure coverage gate: all 64 new cases executed, none skipped (5 HTTP + 3 real DB lock).\n");
        }
        if (defined('BE_REORG_SETTINGS_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_SETTINGS_CASES);
            foundationCheckJUnit($junit, BE_REORG_SETTINGS_EXISTING_CASES);
            $write("Settings coverage gate: all 39 new cases executed, none skipped (5 HTTP; no new lock). Existing Settings gate: all 29 cases executed, none skipped.\n");
        }
        if (defined('BE_REORG_NUMBERING_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_NUMBERING_CASES);
            foundationCheckJUnit($junit, BE_REORG_NUMBERING_EXISTING_CASES);
            $write("Numbering coverage gate: all 20 new cases executed, none skipped (6 HTTP; no new lock). Existing Numbering gate: all 68 cases executed, none skipped.\n");
        }
        if (defined('BE_REORG_AUTHORIZATION_ADMIN_CASES')) {
            foundationCheckJUnit($junit, BE_REORG_AUTHORIZATION_ADMIN_CASES);
            foundationCheckJUnit($junit, BE_REORG_AUTHORIZATION_EXISTING_CASES);
            $write("Authorization Administration coverage gate: all 24 new cases executed, none skipped (6 HTTP). Existing Authorization/PBAC gate: all 91 authority cases executed, none skipped.\n");
        }
        $write("\n--- PHPStan (report existing diagnostics honestly; no ignore/baseline changes) ---\n");
        $stanExit = foundationRun([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '-c', 'phpstan.neon',
            '--no-progress', '--no-ansi', '--memory-limit=1G'], $backend, $write);
        $write('PHPStan exit code: ' . $stanExit . PHP_EOL);
        $exit = $stanExit === 0 ? 0 : 1;
        $write($exit === 0 ? "RESULT: automated checks PASS. Browser UAT remains separate.\n"
            : "RESULT: PHPUnit PASS; PHPStan FAIL. Compare diagnostics to baseline, not just counts.\n");
    }
} catch (Throwable $exception) {
    // No config dump, connection password, session or token is printed.
    $text = 'BLOCKED: ' . $exception->getMessage() . PHP_EOL;
    fwrite(STDERR, $text);
    if (is_resource($log)) { fwrite($log, $text); }
    $exit = 2;
} finally {
    if (is_resource($log)) { fclose($log); }
    if (is_resource($runLock)) { flock($runLock, LOCK_UN); fclose($runLock); }
}
fwrite(STDOUT, 'Log: ' . $logPath . PHP_EOL);
exit($exit);

/** @param list<string> $command @param callable(string):void $write */
function foundationRun(array $command, string $directory, callable $write): int
{
    $temporary = tempnam(sys_get_temp_dir(), 'br001a-');
    if ($temporary === false) { throw new RuntimeException('Cannot allocate subprocess output file.'); }
    $process = null;
    $reader = null;
    try {
        // Both streams go to one file. Avoid pipe deadlocks and Windows shell quoting.
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $temporary, 'ab'], 2 => ['redirect', 1]],
            $pipes, $directory, null, ['bypass_shell' => true]);
        if (! is_resource($process)) { throw new RuntimeException('Cannot start test subprocess.'); }
        fclose($pipes[0]);
        $reader = fopen($temporary, 'rb');
        if ($reader === false) { throw new RuntimeException('Cannot read subprocess output.'); }
        do {
            $output = stream_get_contents($reader);
            if ($output !== false && $output !== '') { $write($output); }
            $status = proc_get_status($process);
            if ($status['running']) { usleep(100000); }
        } while ($status['running']);
        $output = stream_get_contents($reader);
        if ($output !== false && $output !== '') { $write($output); }
        $closed = proc_close($process);
        $process = null;
        return $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        if (is_resource($reader)) { fclose($reader); }
        unlink($temporary);
    }
}

/** @param array<class-string,positive-int> $required */
function foundationCheckJUnit(string $path, array $required = [
    'BeReorg001FoundationHttpTest' => 9, 'BeReorg001FoundationLockTest' => 4,
]): void
{
    $document = new DOMDocument();
    if (! is_file($path) || ! $document->load($path, LIBXML_NONET)) {
        throw new RuntimeException('Missing fresh JUnit evidence; do not accept an empty/filtered suite.');
    }
    $xpath = new DOMXPath($document);
    foreach ($required as $class => $expected) {
        $nodes = $xpath->query('//testcase[@class="' . $class . '"]');
        if ($nodes === false || $nodes->length !== $expected) {
            throw new RuntimeException('JUnit is missing expected cases: ' . $class);
        }
        foreach ($nodes as $node) {
            foreach (['failure', 'error', 'skipped'] as $tag) {
                if ($node instanceof DOMElement && $node->getElementsByTagName($tag)->length > 0) {
                    throw new RuntimeException('A required case failed or was skipped: ' . $class);
                }
            }
        }
    }
}

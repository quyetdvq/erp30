<?php

declare(strict_types=1);

/**
 * BE-REORG-009 guarded acceptance runner.
 * Composes BE008's guarded full-suite runner in one child, never resets a DB itself.
 * PHPStan's inherited raw exit 1 remains 1 until diagnostics are compared to BE008.
 */

/** @return array<string, list<string>> Exact accepted BE009 class/method inventory. */
function be009RequiredMethods(): array
{
    return [
        'BeReorg009LifecycleBoundaryTest' => [
            'testApplicationPortDeclaresFiveCanonicalOperations',
            'testRepositoryPortExposesOnlyNineApplicationOperations',
            'testCanonicalOwnersDoNotImportLegacyFacades',
            'testFacadesDelegateWithoutBusinessOrSqlImplementation',
            'testExistingAdaptersAndDtoContractsRemainUnchanged',
        ],
        'BeReorg009LifecycleCompatibilityTest' => [
            'testLegacyServiceConstructorPreservesSevenPositionalAndNamedParameters',
            'testLegacyServiceRetainsEightConstantsAndFiveMethods',
            'testLegacyRepositoryConstructorConstantsAndThirtyNineMethods',
            'testRepositoryAliasesDelegateToOneSqlInstance',
            'testAdapterRegistrationUsesSameInstanceAndRejectsDuplicates',
            'testLegacyServicePreservesReturnShapesAndExceptionClasses',
        ],
        'BeReorg009LifecycleFactoryTest' => [
            'testFactoryCreatesFreshApplicationPerCallWithoutSharedRegistry',
            'testFactoryKeepsEverySuppliedCollaboratorByIdentity',
            'testFactoryUsesOneSuppliedConnectionForOmittedCollaborators',
            'testFactoryRetainsLazyDefaultsAndOperationContextTiming',
        ],
        'BeReorg009LifecycleRepositoryParityTest' => [
            'testLegacyAndSqlLifecycleCrudHaveIdenticalRowsAndAudit',
            'testStateCatalogCrudsAndPositiveIdsRemainEquivalent',
            'testMembershipOrderingFlagsAndUniquenessRemainEquivalent',
            'testTransitionOrderingActiveFilteringAndAliasesMatch',
            'testFalseQueryMissingUpdateAndInvalidPayloadKeepTypedFailures',
            'testTransactionalRollbackAndLockUseSuppliedConnection',
        ],
        'BeReorg009LifecycleApplicationParityTest' => [
            'testActiveConfigHasExactlyOneInitialWithMultipleFinalAllowed',
            'testInvalidMembershipOrTransitionFailsClosedBeforeWrites',
            'testConfigureValidatesCombinedBatchAndAtomicallyRollsBack',
            'testResolveTransitionRequiresActiveLifecycleAndActiveDestination',
            'testExecuteDenialAuditsWithoutMutationUsingTrustedState',
            'testMismatchedDestinationFailsBeforeAnyAuthorization',
            'testExecuteReturnsOriginalSnapshotAndRejectsStaleLockedData',
        ],
        'BeReorg009LifecycleHttpTest' => [
            'testLifecycleStatesAndTransitionsRoutesPreserveEnvelopeCsrfAndAudit',
            'testBusinessSetStateDeniedAndInvalidRequestsHaveNoWrites',
        ],
        'BeReorg009LifecycleLockTest' => [
            'testLifecycleForUpdateLocksOnlyOnSharedSuppliedSessionUntilRelease',
            'testTargetAdapterStaleStateRaisesConcurrencyAndRollsBack',
        ],
    ];
}

/** @return array<string, string> */
function be009TestFiles(): array
{
    return [
        'BeReorg009LifecycleBoundaryTest' => 'tests/unit/BeReorg009LifecycleBoundaryTest.php',
        'BeReorg009LifecycleCompatibilityTest' => 'tests/unit/BeReorg009LifecycleCompatibilityTest.php',
        'BeReorg009LifecycleFactoryTest' => 'tests/unit/BeReorg009LifecycleFactoryTest.php',
        'BeReorg009LifecycleRepositoryParityTest' => 'tests/database/BeReorg009LifecycleRepositoryParityTest.php',
        'BeReorg009LifecycleApplicationParityTest' => 'tests/database/BeReorg009LifecycleApplicationParityTest.php',
        'BeReorg009LifecycleHttpTest' => 'tests/database/BeReorg009LifecycleHttpTest.php',
        'BeReorg009LifecycleLockTest' => 'tests/database/BeReorg009LifecycleLockTest.php',
    ];
}

/** Verify source inventory before any database work; rejects silent extra tests. */
function be009Preflight(string $backend, array $required): void
{
    foreach ([
        'app/Services/LifecycleService.php',
        'app/Repositories/LifecycleRepository.php',
        'app/Bootstrap/WorkflowLifecycleFactory.php',
        'app/Modules/Workflow/Lifecycle/Application/LifecycleApplicationService.php',
        'app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleApplicationPort.php',
        'app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleRepositoryPort.php',
        'app/Modules/Workflow/Lifecycle/Infrastructure/Persistence/SqlLifecycleRepository.php',
        'tests/_support/Workflow/BeReorg009LifecycleFixture.php',
        'tools/test-authorization-decision.php',
    ] as $path) {
        if (! is_file($backend . '/' . $path)) {
            throw new RuntimeException('BE009 missing prerequisite: ' . $path);
        }
    }
    if (array_sum(array_map('count', $required)) !== 32 || count($required) !== 7) {
        throw new RuntimeException('BE009 must pin exactly seven classes and 32 cases.');
    }
    foreach (be009TestFiles() as $class => $relative) {
        if (! isset($required[$class]) || ! is_file($backend . '/' . $relative)) {
            throw new RuntimeException('BE009 missing required test file: ' . $relative);
        }
        $content = file_get_contents($backend . '/' . $relative);
        if (! is_string($content) || preg_match('/\bfinal\s+class\s+' . preg_quote($class, '/') . '\b/', $content) !== 1) {
            throw new RuntimeException('BE009 unexpected test class: ' . $relative);
        }
        preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', $content, $matches);
        $found = $matches[1];
        $expect = $required[$class];
        sort($found);
        sort($expect);
        if ($found !== $expect || count(array_unique($found)) !== count($found)) {
            throw new RuntimeException('BE009 exact method inventory mismatch: ' . $class);
        }
    }
}

/** At least 948 cases must exist; all BE009 exact cases must execute cleanly. */
function be009AssertJUnit(string $junit, array $required): void
{
    if (! is_file($junit)) {
        throw new RuntimeException('BE009 fresh JUnit missing.');
    }
    $xml = new DOMDocument();
    if (! $xml->load($junit, LIBXML_NONET) || $xml->documentElement === null) {
        throw new RuntimeException('BE009 JUnit malformed.');
    }
    $xpath = new DOMXPath($xml);
    $all = $xpath->query('//testcase');
    if ($all === false || $all->length < 948) {
        throw new RuntimeException('BE009 full JUnit must contain at least 948 testcases (baseline 916 + 32).');
    }
    foreach ($all as $case) {
        if (! $case instanceof DOMElement) {
            throw new RuntimeException('BE009 malformed testcase node.');
        }
        foreach (['failure', 'error', 'skipped'] as $status) {
            if ($case->getElementsByTagName($status)->length !== 0) {
                throw new RuntimeException('BE009 full suite contains ' . $status . ': ' . $case->getAttribute('class') . '::' . $case->getAttribute('name'));
            }
        }
    }
    foreach ($required as $class => $expected) {
        $nodes = $xpath->query('//testcase[@class="' . $class . '"]');
        if ($nodes === false || $nodes->length !== count($expected)) {
            throw new RuntimeException('BE009 missing/duplicate class cases: ' . $class);
        }
        $names = [];
        foreach ($nodes as $case) {
            if (! $case instanceof DOMElement) {
                throw new RuntimeException('BE009 malformed testcase.');
            }
            $name = $case->getAttribute('name');
            if ($name === '' || in_array($name, $names, true)) {
                throw new RuntimeException('BE009 unnamed/duplicate method: ' . $class);
            }
            $names[] = $name;
        }
        sort($names);
        sort($expected);
        if ($names !== $expected) {
            throw new RuntimeException('BE009 method names differ from approved inventory: ' . $class);
        }
    }
}

/** Exit 2 on blocked/unsafe; exit 1 on inherited PHPStan diagnostics; 0 only for actual clean tools. */
function be009Run(array $argv): int
{
    $args = array_slice($argv, 1);
    if ($args === ['--help']) {
        fwrite(STDOUT, "Usage: php tools/test-workflow-lifecycle.php --confirm-test-db\n"
            . "REQUIRES disposable 127.0.0.1:3306/erp_test, prefix dinktech_, single test process.\n"
            . "Runs inherited BE008 guard once (full PHPUnit + PHPStan), verifies all 32 new BE009 tests\n"
            . "and saves a fresh backend/build/logs/be-reorg-009.xml. Raw PHPStan exit 1 is NOT a PASS.\n"
            . "Never run concurrently with another test process on erp_test.\n");
        return 0;
    }
    if ($args !== ['--confirm-test-db']) {
        fwrite(STDERR, "BE009 refuses database work without --confirm-test-db. Use --help.\n");
        return 2;
    }
    $backend = dirname(__DIR__);
    try {
        $required = be009RequiredMethods();
        be009Preflight($backend, $required);
        if (! function_exists('proc_open') || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('BE009 requires proc_open and the PHP DOM extension.');
        }
        require_once $backend . '/tools/test-authorization-decision.php';
        $inheritedRequired = be008RequiredCases();
        $inheritedNames = be008Preflight($backend, $inheritedRequired);
        $inheritedXml = $backend . '/build/logs/be-reorg-001a.xml';
        $junit = $backend . '/build/logs/be-reorg-009.xml';
        $log = dirname($backend) . '/test-result.log';
        foreach ([$junit, $inheritedXml] as $file) {
            if (is_file($file) && ! unlink($file)) {
                throw new RuntimeException('BE009 cannot remove stale JUnit: ' . $file);
            }
        }
        $spool = tempnam(sys_get_temp_dir(), 'be009-');
        if ($spool === false) {
            throw new RuntimeException('BE009 cannot open output spool.');
        }
        $process = null;
        $reader = null;
        $exit = 2;
        try {
            $process = proc_open([PHP_BINARY, 'tools/test-authorization-decision.php', '--confirm-test-db'],
                [0 => ['pipe', 'r'], 1 => ['file', $spool, 'ab'], 2 => ['redirect', 1]],
                $pipes, $backend, null, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('BE009 cannot launch inherited guarded BE008 process.');
            }
            fclose($pipes[0]);
            $reader = fopen($spool, 'rb');
            if ($reader === false) {
                throw new RuntimeException('BE009 cannot read output spool.');
            }
            do {
                $chunk = stream_get_contents($reader);
                if (is_string($chunk) && $chunk !== '') {
                    fwrite(STDOUT, $chunk);
                }
                $status = proc_get_status($process);
                if ($status['running']) {
                    usleep(100000);
                }
            } while ($status['running']);
            $chunk = stream_get_contents($reader);
            if (is_string($chunk) && $chunk !== '') {
                fwrite(STDOUT, $chunk);
            }
            $closed = proc_close($process);
            $process = null;
            $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            if (is_resource($reader)) {
                fclose($reader);
            }
            unlink($spool);
        }
        if (! in_array($exit, [0, 1], true)) {
            throw new RuntimeException('Inherited BE008 runner blocked with code ' . $exit);
        }
        $contents = is_file($log) ? file_get_contents($log) : false;
        if (! is_string($contents) || ! str_contains($contents, 'PHPUnit exit code: 0')
            || ! str_contains($contents, 'PHPStan exit code: ')
            || ! str_contains($contents, 'BE-REORG-008 decision coverage gate:')) {
            throw new RuntimeException('Inherited runner did not complete PHPUnit/PHPStan/BE008 coverage.');
        }
        foreach ([
            'Foundation guard: 17 checks PASS.',
            'Foundation coverage gate:', 'Organization coverage gate:', 'Contact coverage gate:',
            'Employee Structure coverage gate:', 'Settings coverage gate:', 'Numbering coverage gate:',
            'Authorization Administration coverage gate:', 'BE-REORG-008 decision coverage gate:',
        ] as $evidence) {
            if (! str_contains($contents, $evidence)) {
                throw new RuntimeException('Missing inherited coverage gate: ' . $evidence);
            }
        }
        be008AssertJUnit($inheritedXml, $inheritedRequired, $inheritedNames);
        be009AssertJUnit($inheritedXml, $required);
        if (! copy($inheritedXml, $junit)) {
            throw new RuntimeException('Could not save fresh BE009 JUnit.');
        }
        $summary = "\nBE-REORG-009 Lifecycle coverage gate: all 32 exact new cases executed; none skipped, errored or failed.\n"
            . "Existing BE008 26, BE007 24 and PBAC 91 inherited gates retained.\n"
            . "BE009 runner child exit code: " . $exit . "\n"
            . ($exit === 0
                ? "RESULT: Full PHPUnit PASS, PHPStan raw exit 0. Review diagnostics and accept checkpoint explicitly.\n"
                : "RESULT: Full PHPUnit PASS; PHPStan raw exit 1. Compare diagnostic identities against 121 BE008 inherited errors before acceptance.\n");
        if (file_put_contents($log, $summary, FILE_APPEND) === false) {
            throw new RuntimeException('BE009 cannot append test-result.log.');
        }
        fwrite(STDOUT, $summary . 'Log: ' . $log . PHP_EOL);
        return $exit;
    } catch (Throwable $error) {
        fwrite(STDERR, 'BE009 BLOCKED: ' . $error->getMessage() . PHP_EOL);
        return 2;
    }
}

// Safe to include for synthetic JUnit tests without touching erp_test.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(be009Run($argv));
}

<?php

declare(strict_types=1);

/**
 * BE-REORG-010 guarded acceptance: one inherited BE009 full-suite process.
 * A PHPStan exit 1 is preserved until the 121-diagnostic baseline is compared.
 */

/** @return array<string, list<string>> */
function be010RequiredMethods(): array
{
    return [
        'BeReorg010StateResolutionBoundaryTest' => [
            'testApplicationPortDeclaresExactlyElevenOperations',
            'testReadPortDeclaresExactlyEightReadOnlyOperations',
            'testContractsKeepEightPublicErrorsAndInternalConflictCode',
            'testReadAdapterImplementsPortWithThreeExistingCollaborators',
            'testPortAndAdapterDoNotExposeMutationOrSqlSurface',
        ],
        'BeReorg010StateResolutionCompatibilityTest' => [
            'testFacadeConstructorRetainsFourPositionalAndNamedParameters',
            'testFacadeRetainsEightConstantsAndElevenMethodSignatures',
            'testFacadeKeepsSingleEngineWithExplicitDelegation',
            'testFacadePreservesReturnShapesAndDomainErrorContext',
            'testBe007AndBe008ReflectionPathsStillVerifyRepositoryConnection',
        ],
        'BeReorg010StateResolutionFactoryTest' => [
            'testFactoryCreatesFreshEngineReaderAndEmptyCaches',
            'testFactoryPreservesEveryInjectedRepositoryIdentity',
            'testFactoryDefaultsUseExactlyTheSuppliedDbConnection',
            'testReadAdapterPassesThroughAllEightReadsIncludingInactivePolicies',
        ],
        'BeReorg010StateResolutionDomainTest' => [
            'testTenIndependentResourcesAndTwoAllStatesOnlyBehaveExactly',
            'testLifecycleBoundResourceAcceptsActiveMemberCodesBeyondNativeVocabulary',
            'testShieldStateUsesPersistedStatusAndValidatedActiveFlag',
            'testDisabledWorkflowStateCanReactivateButCannotAuthorizeOtherNativeResource',
            'testReassignmentRejectsInactivePolicyOutsideNextLifecycle',
            'testInitialStateAndMemberValidationFailClosedWithStableCodes',
            'testStateIdAndCodeMappingRejectInvalidOrNonmemberStates',
            'testAuthorizationUsesPersistedStateAndRejectsClientHints',
        ],
        'BeReorg010StateResolutionCacheTest' => [
            'testResourceCacheIsNormalizedAndScopedToOneResolver',
            'testMembershipCacheIsScopedToOneResolver',
            'testAnyAndActiveCacheKeysNeverCrossContaminate',
            'testTrustedCatalogRowPreservesHistoricalCacheKeyAndScopeSemantics',
        ],
        'BeReorg010StateResolutionHttpTest' => [
            'testApi030ResourceAndLifecycleRoutesPreserveErrorsAndCsrf',
            'testPbacDeniedAndAllowedStateRoutesPreserveNoUnauthorizedWrites',
        ],
        'BeReorg010StateResolutionDatabaseTest' => [
            'testInactivePolicyConflictAndAllStatesPolicyRemainDistinct',
            'testFreshResolverObservesRealDatabaseLifecycleAndStateUpdates',
        ],
    ];
}

/** @return array<string, string> */
function be010TestFiles(): array
{
    return [
        'BeReorg010StateResolutionBoundaryTest' => 'tests/unit/BeReorg010StateResolutionBoundaryTest.php',
        'BeReorg010StateResolutionCompatibilityTest' => 'tests/unit/BeReorg010StateResolutionCompatibilityTest.php',
        'BeReorg010StateResolutionFactoryTest' => 'tests/database/BeReorg010StateResolutionFactoryTest.php',
        'BeReorg010StateResolutionDomainTest' => 'tests/unit/BeReorg010StateResolutionDomainTest.php',
        'BeReorg010StateResolutionCacheTest' => 'tests/unit/BeReorg010StateResolutionCacheTest.php',
        'BeReorg010StateResolutionHttpTest' => 'tests/database/BeReorg010StateResolutionHttpTest.php',
        'BeReorg010StateResolutionDatabaseTest' => 'tests/database/BeReorg010StateResolutionDatabaseTest.php',
    ];
}

/** Source method inventory and architecture before any database operation. */
function be010Preflight(string $backend, array $required): void
{
    foreach ([
        'app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php',
        'app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateReadPort.php',
        'app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php',
        'app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php',
        'app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php',
        'app/Bootstrap/WorkflowStateResolutionFactory.php',
        'app/Services/ResourceWorkflowStateResolver.php',
        'tests/_support/Workflow/BeReorg010StateReadFixture.php',
        'tools/test-workflow-lifecycle.php',
        'tests/unit/BeReorg007AuthorizationFactoryTest.php',
        'tests/unit/BeReorg008DecisionFactoryTest.php',
    ] as $path) {
        if (! is_file($backend . '/' . $path)) {
            throw new RuntimeException('BE010 missing prerequisite: ' . $path);
        }
    }
    if (count($required) !== 7 || array_sum(array_map('count', $required)) !== 30) {
        throw new RuntimeException('BE010 requires exactly seven classes and 30 methods.');
    }
    foreach (be010TestFiles() as $class => $relative) {
        $path = $backend . '/' . $relative;
        if (! isset($required[$class]) || ! is_file($path)) {
            throw new RuntimeException('BE010 missing required test: ' . $relative);
        }
        $content = file_get_contents($path);
        if (! is_string($content) || preg_match('/\bfinal\s+class\s+' . preg_quote($class, '/') . '\b/', $content) !== 1) {
            throw new RuntimeException('BE010 unexpected test class: ' . $relative);
        }
        preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', $content, $matches);
        $found = $matches[1];
        $expected = $required[$class];
        sort($found);
        sort($expected);
        if ($found !== $expected || count(array_unique($found)) !== count($found)) {
            throw new RuntimeException('BE010 exact method inventory mismatch: ' . $class);
        }
    }
}

/** Full execution check: at least 978 new+inherited cases, no missing/duplicated/skipped BE010 methods. */
function be010AssertJUnit(string $junit, array $required): void
{
    if (! is_file($junit)) {
        throw new RuntimeException('BE010 fresh inherited JUnit missing.');
    }
    $xml = new DOMDocument();
    if (! $xml->load($junit, LIBXML_NONET) || $xml->documentElement === null) {
        throw new RuntimeException('BE010 malformed JUnit.');
    }
    $xpath = new DOMXPath($xml);
    $all = $xpath->query('//testcase');
    if ($all === false || $all->length < 978) {
        throw new RuntimeException('BE010 requires at least 978 JUnit testcases (948 inherited + 30 BE010).');
    }
    foreach ($all as $case) {
        if (! $case instanceof DOMElement || $case->getAttribute('name') === '') {
            throw new RuntimeException('BE010 malformed/unnamed JUnit testcase.');
        }
        foreach (['failure', 'error', 'skipped'] as $status) {
            if ($case->getElementsByTagName($status)->length > 0) {
                throw new RuntimeException('BE010 full suite contains ' . $status . ': '
                    . $case->getAttribute('class') . '::' . $case->getAttribute('name'));
            }
        }
    }
    foreach ($required as $class => $expected) {
        $nodes = $xpath->query('//testcase[@class="' . $class . '"]');
        if ($nodes === false || $nodes->length !== count($expected)) {
            throw new RuntimeException('BE010 missing/duplicate class cases: ' . $class);
        }
        $names = [];
        foreach ($nodes as $case) {
            if (! $case instanceof DOMElement) {
                throw new RuntimeException('BE010 malformed testcase.');
            }
            $name = $case->getAttribute('name');
            if ($name === '' || in_array($name, $names, true)) {
                throw new RuntimeException('BE010 unnamed/duplicate method: ' . $class);
            }
            $names[] = $name;
        }
        sort($names);
        sort($expected);
        if ($names !== $expected) {
            throw new RuntimeException('BE010 method names differ from approved inventory: ' . $class);
        }
    }
}

/** Safe only on confirmed disposable test DB. Exit 1 retains raw inherited PHPStan status. */
function be010Run(array $argv): int
{
    $args = array_slice($argv, 1);
    if ($args === ['--help']) {
        fwrite(STDOUT, "Usage: php tools/test-resource-state-resolution.php --confirm-test-db\n"
            . "Requires disposable 127.0.0.1:3306/erp_test, prefix dinktech_; never run two test processes.\n"
            . "Runs one inherited BE009 full-suite/DB/HTTP/PHPStan guard; requires at least 978 tests, including exactly 30 BE010.\n"
            . "PHPStan raw exit 1 remains 1 until diagnostic identities match the BE009 baseline.\n");
        return 0;
    }
    if ($args !== ['--confirm-test-db']) {
        fwrite(STDERR, "BE010 refuses database work without --confirm-test-db. Use --help.\n");
        return 2;
    }
    $backend = dirname(__DIR__);
    try {
        $required = be010RequiredMethods();
        be010Preflight($backend, $required);
        if (! function_exists('proc_open') || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('BE010 requires proc_open and PHP DOM extension.');
        }
        $junit = $backend . '/build/logs/be-reorg-010.xml';
        $inherited = $backend . '/build/logs/be-reorg-009.xml';
        $baseJunit = $backend . '/build/logs/be-reorg-001a.xml';
        $log = dirname($backend) . '/test-result.log';
        foreach ([$junit, $inherited, $baseJunit] as $path) {
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('BE010 cannot remove stale JUnit: ' . $path);
            }
        }
        $spool = tempnam(sys_get_temp_dir(), 'be010-');
        if ($spool === false) {
            throw new RuntimeException('BE010 cannot open inherited output spool.');
        }
        $process = null;
        $reader = null;
        $exit = 2;
        try {
            $process = proc_open([PHP_BINARY, 'tools/test-workflow-lifecycle.php', '--confirm-test-db'],
                [0 => ['pipe', 'r'], 1 => ['file', $spool, 'ab'], 2 => ['redirect', 1]],
                $pipes, $backend, null, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('BE010 cannot start inherited BE009 guard.');
            }
            fclose($pipes[0]);
            $reader = fopen($spool, 'rb');
            if ($reader === false) {
                throw new RuntimeException('BE010 cannot read inherited output spool.');
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
            throw new RuntimeException('BE010 inherited BE009 runner blocked with code ' . $exit);
        }
        $contents = is_file($log) ? file_get_contents($log) : false;
        if (! is_string($contents) || ! str_contains($contents, 'PHPUnit exit code: 0')
            || ! str_contains($contents, 'PHPStan exit code: ')
            || ! str_contains($contents, 'BE-REORG-009 Lifecycle coverage gate:')
            || ! str_contains($contents, 'BE-REORG-008 decision coverage gate:')
            || ! str_contains($contents, 'Foundation guard: 17 checks PASS.')) {
            throw new RuntimeException('BE010 inherited PHPUnit/PHPStan/BE009 gates did not complete.');
        }
        require_once $backend . '/tools/test-workflow-lifecycle.php';
        be009AssertJUnit($inherited, be009RequiredMethods());
        be010AssertJUnit($inherited, $required);
        if (! copy($inherited, $junit)) {
            throw new RuntimeException('BE010 cannot save fresh verified JUnit.');
        }
        $summary = "\nBE-REORG-010 State Resolution gate: all 30 exact cases executed; none failed, errored or skipped.\n"
            . "Inherited BE009 32, BE008 26, BE007 24, PBAC 91 and Foundation 17 checks retained.\n"
            . "BE010 runner child exit code: " . $exit . "\n"
            . ($exit === 0
                ? "RESULT: Full PHPUnit PASS; PHPStan raw exit 0. Review and accept checkpoint explicitly.\n"
                : "RESULT: Full PHPUnit PASS; PHPStan raw exit 1. Compare 121 inherited diagnostic identities; do not call PHPStan clean.\n");
        if (file_put_contents($log, $summary, FILE_APPEND) === false) {
            throw new RuntimeException('BE010 cannot append test-result.log.');
        }
        fwrite(STDOUT, $summary . 'Log: ' . $log . PHP_EOL);
        return $exit;
    } catch (Throwable $error) {
        fwrite(STDERR, 'BE010 BLOCKED: ' . $error->getMessage() . PHP_EOL);
        return 2;
    }
}

// Functions may be included by CLI-safe synthetic tests without touching a DB.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(be010Run($argv));
}

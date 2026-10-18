<?php

declare(strict_types=1);

/**
 * BE012 guarded acceptance: one BE011 inherited child, exact 36 BE012 cases,
 * 1048 JUnit cases overall. PHPStan raw exit 1 remains visible.
 */

/** @return array<string,list<string>> */
function be012RequiredMethods(): array
{
    return [
        'BeReorg012WriteBoundaryBoundaryTest' => [
            'testReadPortContainsOnlyThreeStateLookups',
            'testAdapterWrapsBothBe010PortAndLegacyFacade',
            'testApplicationPortDefinesFourCanonicalOperations',
            'testApplicationDoesNotImportLegacyServicesOrSql',
            'testErrorsPreserveLegacyCodesAndContexts',
        ],
        'BeReorg012WriteBoundaryCompatibilityTest' => [
            'testBoundaryFacadeRetainsTwoStaticSignatures',
            'testStatusFacadeRetainsNormalizeSignatureAndDefaults',
            'testInitialNormalizerRetainsConstructorTwoMethodsAndConstant',
            'testFacadesDelegateWithoutDuplicatedAlgorithms',
            'testExistingConsumersAndRoutesRemainUnchanged',
        ],
        'BeReorg012WriteBoundaryFactoryTest' => [
            'testFactoryCreatesFreshEngineWithoutSharedCache',
            'testFactoryPassesProvidedDbToBe010StateResolution',
            'testFactoryReusesExactInjectedLegacyResolver',
            'testAdapterDelegatesThreeReadsWithoutMutation',
            'testFactoryDoesNotReadDatabaseOnConstruction',
        ],
        'BeReorg012InitialStateRulesTest' => [
            'testNativeMissingStatusDefaultsActiveWhenCatalogPermits',
            'testNativeWithoutActiveDefaultLeavesPayloadUnchanged',
            'testNativeExplicitStatusIsPreservedVerbatim',
            'testLifecycleInitialAcceptsArbitraryValidCode',
            'testMatchingClientHintIsCanonicalizedToInitial',
            'testMismatchingOrNonStringClientHintIsRejected',
            'testUnsupportedInitialCodeIsRejectedWithExistingContext',
        ],
        'BeReorg012WriteBoundaryDomainTest' => [
            'testUpdateWithoutStatusReturnsBeforeStateLookup',
            'testSameStatusCaseAndWhitespaceRequiresNoStateLookup',
            'testBoundDirectStatusMutationThrowsTransitionRequired',
            'testUnboundDirectStatusMutationRemainsPassThrough',
            'testUnboundCreateReturnsPayloadUnchanged',
            'testBoundCreateUsesPersistedInitialAndRejectsOverride',
            'testStatusNormalizationUsesMemberOrNativeVocabulary',
        ],
        'BeReorg012WriteBoundaryDatabaseTest' => [
            'testUnknownResourceOnReachablePathsFailsClosed',
            'testTenNativeResourcesRemainIndependentAndRejectLinkage',
            'testBoundInitialStatePersistsWithoutClientOverride',
            'testInactiveOrNonmemberWorkflowStateIsRejected',
            'testNativeStatusCodesAndUnboundUpdatesRemainCompatible',
        ],
        'BeReorg012WriteBoundaryHttpTest' => [
            'testApiCreateUsesConfiguredInitialAndRejectsForgedStatus',
            'testApiUpdateRejectsDirectStatusMutationWithoutAudit',
        ],
    ];
}

/** @return array<string,string> */
function be012TestFiles(): array
{
    return [
        'BeReorg012WriteBoundaryBoundaryTest' => 'tests/unit/BeReorg012WriteBoundaryBoundaryTest.php',
        'BeReorg012WriteBoundaryCompatibilityTest' => 'tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php',
        'BeReorg012WriteBoundaryFactoryTest' => 'tests/unit/BeReorg012WriteBoundaryFactoryTest.php',
        'BeReorg012InitialStateRulesTest' => 'tests/unit/BeReorg012InitialStateRulesTest.php',
        'BeReorg012WriteBoundaryDomainTest' => 'tests/unit/BeReorg012WriteBoundaryDomainTest.php',
        'BeReorg012WriteBoundaryDatabaseTest' => 'tests/database/BeReorg012WriteBoundaryDatabaseTest.php',
        'BeReorg012WriteBoundaryHttpTest' => 'tests/database/BeReorg012WriteBoundaryHttpTest.php',
    ];
}

/** Static source and exact public test inventory, before any database access. */
function be012Preflight(string $backend, array $required): void
{
    $files = [
        'app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowWriteBoundaryPort.php',
        'app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowStateLookupPort.php',
        'app/Modules/Workflow/WriteBoundary/Application/WorkflowWriteBoundaryService.php',
        'app/Modules/Workflow/WriteBoundary/Application/InitialStateRules.php',
        'app/Modules/Workflow/WriteBoundary/Contracts/WorkflowWriteBoundaryErrors.php',
        'app/Modules/Workflow/WriteBoundary/Infrastructure/Adapters/ExistingStateResolutionLookupAdapter.php',
        'app/Bootstrap/WorkflowWriteBoundaryFactory.php',
        'app/Services/BusinessWorkflowWriteBoundary.php',
        'app/Services/WorkflowBusinessStatus.php',
        'app/Services/InitialCreateStateNormalizer.php',
        'tests/_support/Workflow/BeReorg012WriteBoundaryFixture.php',
        'tools/test-business-workflow-actions.php',
    ];
    foreach ($files as $file) {
        if (! is_file($backend . '/' . $file)) {
            throw new RuntimeException('BE012 missing prerequisite: ' . $file);
        }
    }
    if (count($required) !== 7 || array_sum(array_map('count', $required)) !== 36) {
        throw new RuntimeException('BE012 requires seven classes and 36 exact tests.');
    }
    $seen = [];
    foreach (be012TestFiles() as $class => $relative) {
        $file = $backend . '/' . $relative;
        if (! isset($required[$class]) || ! is_file($file)) {
            throw new RuntimeException('BE012 missing test file: ' . $relative);
        }
        $source = file_get_contents($file);
        if (! is_string($source) || preg_match('/\bfinal\s+class\s+' . preg_quote($class, '/') . '\b/', $source) !== 1) {
            throw new RuntimeException('BE012 unexpected test class: ' . $relative);
        }
        preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', $source, $matches);
        $actual = $matches[1];
        $expected = $required[$class];
        sort($actual);
        sort($expected);
        if ($actual !== $expected || count(array_unique($actual)) !== count($actual)) {
            throw new RuntimeException('BE012 exact test-method inventory mismatch: ' . $class);
        }
        foreach ($actual as $method) {
            if (isset($seen[$class . '::' . $method])) {
                throw new RuntimeException('BE012 duplicate test identity.');
            }
            $seen[$class . '::' . $method] = true;
        }
    }
    if (count($seen) !== 36) {
        throw new RuntimeException('BE012 method inventory does not total 36 unique identities.');
    }
}

/** Exactly 1048 uniquely named, passing JUnit cases including the frozen BE012 methods. */
function be012AssertJUnit(string $junit, array $required): void
{
    if (! is_file($junit)) {
        throw new RuntimeException('BE012 missing inherited JUnit.');
    }
    $xml = new DOMDocument();
    if (! $xml->load($junit, LIBXML_NONET) || $xml->documentElement === null) {
        throw new RuntimeException('BE012 malformed JUnit.');
    }
    $xpath = new DOMXPath($xml);
    $cases = $xpath->query('//testcase');
    if ($cases === false || $cases->length !== 1048) {
        throw new RuntimeException('BE012 requires exactly 1048 JUnit testcases (1012 inherited + 36 BE012).');
    }
    $identities = [];
    $byClass = [];
    foreach ($cases as $case) {
        if (! $case instanceof DOMElement) {
            throw new RuntimeException('BE012 non-element testcase.');
        }
        $class = $case->getAttribute('class');
        $name = $case->getAttribute('name');
        if ($class === '' || $name === '') {
            throw new RuntimeException('BE012 unnamed testcase/class.');
        }
        $id = $class . '::' . $name;
        if (isset($identities[$id])) {
            throw new RuntimeException('BE012 duplicate JUnit identity: ' . $id);
        }
        $identities[$id] = true;
        $byClass[$class][] = $name;
        foreach (['failure', 'error', 'skipped'] as $status) {
            if ($case->getElementsByTagName($status)->length > 0) {
                throw new RuntimeException('BE012 JUnit ' . $status . ': ' . $id);
            }
        }
    }
    foreach ($required as $class => $expected) {
        $actual = $byClass[$class] ?? [];
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException('BE012 JUnit method mismatch: ' . $class);
        }
    }
}

/** Explicit DB confirmation, one inherited runner, and independent JUnit/log checks. */
function be012Run(array $argv): int
{
    $args = array_slice($argv, 1);
    if ($args === ['--help']) {
        fwrite(STDOUT, "Usage: php tools/test-workflow-write-boundary.php --confirm-test-db\n"
            . "Requires disposable 127.0.0.1:3306/erp_test (prefix dinktech_), serial execution.\n"
            . "Runs inherited BE011 once; exact 1048 tests (36 BE012); preserves PHPStan raw exit.\n");
        return 0;
    }
    if ($args !== ['--confirm-test-db']) {
        fwrite(STDERR, "BE012 refuses database work without --confirm-test-db. Use --help.\n");
        return 2;
    }
    $backend = dirname(__DIR__);
    try {
        $required = be012RequiredMethods();
        be012Preflight($backend, $required);
        if (! function_exists('proc_open') || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('BE012 requires proc_open and PHP DOM.');
        }
        $junit = $backend . '/build/logs/be-reorg-012.xml';
        $inherited = $backend . '/build/logs/be-reorg-011.xml';
        $log = dirname($backend) . '/test-result.log';
        if (is_file($junit) && ! unlink($junit)) {
            throw new RuntimeException('BE012 cannot remove stale own JUnit.');
        }
        $started = time();
        $spool = tempnam(sys_get_temp_dir(), 'be012-');
        if ($spool === false) {
            throw new RuntimeException('BE012 unable to allocate process output spool.');
        }
        $reader = null;
        $process = null;
        $exit = 2;
        try {
            $process = proc_open(
                [PHP_BINARY, 'tools/test-business-workflow-actions.php', '--confirm-test-db'],
                [0 => ['pipe', 'r'], 1 => ['file', $spool, 'ab'], 2 => ['redirect', 1]],
                $pipes, $backend, null, ['bypass_shell' => true],
            );
            if (! is_resource($process)) {
                throw new RuntimeException('BE012 could not start inherited BE011 runner.');
            }
            fclose($pipes[0]);
            $reader = fopen($spool, 'rb');
            if ($reader === false) {
                throw new RuntimeException('BE012 could not read child output.');
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
            throw new RuntimeException('BE012 inherited BE011 runner blocked with exit ' . $exit);
        }
        clearstatcache(true, $inherited);
        if (! is_file($inherited) || filemtime($inherited) < $started) {
            throw new RuntimeException('BE012 inherited BE011 JUnit missing/stale.');
        }
        clearstatcache(true, $log);
        if (! is_file($log) || filemtime($log) < $started) {
            throw new RuntimeException('BE012 fresh test-result.log missing/stale.');
        }
        // BE007 foundation rewrites this file on every run: read the whole new log.
        $fresh = file_get_contents($log);
        if (! is_string($fresh)) {
            throw new RuntimeException('BE012 could not read inherited log.');
        }
        foreach ([
            'PHPUnit exit code: 0', 'Foundation guard: 17 checks PASS.',
            'BE-REORG-008 decision coverage gate:',
            'BE-REORG-009 Lifecycle coverage gate:',
            'BE-REORG-010 State Resolution gate:',
            'BE-REORG-011 Business Actions gate:',
        ] as $marker) {
            if (! str_contains($fresh, $marker)) {
                throw new RuntimeException('BE012 inherited gate missing: ' . $marker);
            }
        }
        if (preg_match('/PHPStan exit code: ([01])\b/', $fresh, $matches) !== 1 || (int) $matches[1] !== $exit) {
            throw new RuntimeException('BE012 child exit is inconsistent with PHPStan raw status.');
        }
        require_once $backend . '/tools/test-business-workflow-actions.php';
        require_once $backend . '/tools/test-resource-state-resolution.php';
        be010AssertJUnit($inherited, be010RequiredMethods());
        be011AssertJUnit($inherited, be011RequiredMethods());
        be012AssertJUnit($inherited, $required);
        if (! copy($inherited, $junit)) {
            throw new RuntimeException('BE012 unable to save verified JUnit.');
        }
        $summary = "\nBE-REORG-012 Write Boundary gate: all 36 exact cases executed; none failed, errored or skipped.\n"
            . "BE011 34, BE010 30, BE009 32, BE008 26, BE007 24, PBAC 91 and Foundation 17 checks retained.\n"
            . "BE012 runner child exit code: " . $exit . "\n"
            . ($exit === 0
                ? "RESULT: Full PHPUnit PASS; PHPStan raw exit 0; require independent review before acceptance.\n"
                : "RESULT: Full PHPUnit PASS; PHPStan raw exit 1; compare inherited 120 diagnostic identities.\n");
        if (file_put_contents($log, $summary, FILE_APPEND) === false) {
            throw new RuntimeException('BE012 unable to append test-result.log.');
        }
        fwrite(STDOUT, $summary . 'Log: ' . $log . PHP_EOL);
        return $exit;
    } catch (Throwable $error) {
        fwrite(STDERR, 'BE012 BLOCKED: ' . $error->getMessage() . PHP_EOL);
        return 2;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(be012Run($argv));
}

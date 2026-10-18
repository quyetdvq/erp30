<?php

declare(strict_types=1);

/**
 * BE-REORG-011 guarded acceptance: one BE010 child full suite, plus exact 34-case gate.
 * Does not convert inherited PHPStan exit code 1 into a green status.
 */

/** @return array<string,list<string>> */
function be011RequiredMethods(): array
{
    return [
        'BeReorg011BusinessActionsBoundaryTest' => [
            'testApplicationPortDefinesFourOperations',
            'testRecordPortExposesReadOnlyOperations',
            'testModuleLayersAndContractsAreIsolated',
            'testNoRouteOrAuthorizationInfrastructureChanges',
            'testErrorConstantsPreserveLegacyValues',
        ],
        'BeReorg011BusinessActionsCompatibilityTest' => [
            'testActionFacadeRetainsConstructorAndThreeSignatures',
            'testLifecycleTransitionsRetainsStaticSignatureAndReturnType',
            'testExplicitDelegationAndNoLocalOrchestration',
            'testFactoryReturnsFreshEngineWithSameConnection',
            'testLegacyConsumersStillReferenceUnchangedFacades',
        ],
        'BeReorg011BusinessActionsFactoryTest' => [
            'testDefaultsReuseOneConnection',
            'testInjectedRecordPortUsedAsIs',
            'testFactoryDoesNotCacheBetweenInvocations',
            'testAdapterSelectionUsesCorrectExistingLegacyTypes',
        ],
        'BeReorg011BusinessActionsApplicationTest' => [
            'testStatesRequiresBaseViewBeforeMetadata',
            'testStatesNativeAndLifecycleMetadataParity',
            'testOptionsRequiresTargetViewAndSetstateSeparately',
            'testOptionsFiltersInactiveInvalidAndUnauthorizedTransitions',
            'testExecuteRejectsNativeResourceWithoutMutation',
            'testExecutePreservesPersistedStateSetstateAndActionCodeValidation',
            'testApplyIfBoundFalseForUnboundAndExpectedDestinationForBound',
        ],
        'BeReorg011BusinessActionsHttpTest' => [
            'testStatesMetadataBasePermissionAndResponseEnvelope',
            'testOptionsPersistedStateAndActorPermission',
            'testPostExecuteAuthorizedLifecycleTransitionAndAudit',
            'testPostExecuteDeniedCannotWrite',
            'testPostExecuteInvalidActionAndForgedDestinationRejected',
            'testPostExecuteMissingCsrfRejectedWithoutWrites',
        ],
        'BeReorg011BusinessActionsDatabaseTest' => [
            'testTwelveResourceAllowlistAndPositiveIdGuard',
            'testContactSplitMismatchCannotResolveOtherOwner',
            'testNativeFallbackOptionsAndStaticFalseForUnbound',
            'testExecutionReportsUnknownAndMissingTargetsAsBefore',
            'testInactiveTransitionOrDestinationNeverOffered',
        ],
        'BeReorg011BusinessActionsLockTest' => [
            'testRealInnoDbLockTimeoutDoesNotMutateOrAudit',
            'testStaleStateConcurrentMutationRollsBack',
        ],
    ];
}

/** @return array<string,string> */
function be011TestFiles(): array
{
    return [
        'BeReorg011BusinessActionsBoundaryTest' => 'tests/unit/BeReorg011BusinessActionsBoundaryTest.php',
        'BeReorg011BusinessActionsCompatibilityTest' => 'tests/unit/BeReorg011BusinessActionsCompatibilityTest.php',
        'BeReorg011BusinessActionsFactoryTest' => 'tests/unit/BeReorg011BusinessActionsFactoryTest.php',
        'BeReorg011BusinessActionsApplicationTest' => 'tests/database/BeReorg011BusinessActionsApplicationTest.php',
        'BeReorg011BusinessActionsHttpTest' => 'tests/database/BeReorg011BusinessActionsHttpTest.php',
        'BeReorg011BusinessActionsDatabaseTest' => 'tests/database/BeReorg011BusinessActionsDatabaseTest.php',
        'BeReorg011BusinessActionsLockTest' => 'tests/database/BeReorg011BusinessActionsLockTest.php',
    ];
}

/** Validate required source and exact public test identity before any DB access. */
function be011Preflight(string $backend, array $required): void
{
    foreach ([
        'app/Modules/Workflow/BusinessActions/Application/Ports/BusinessWorkflowActionPort.php',
        'app/Modules/Workflow/BusinessActions/Application/Ports/BusinessWorkflowRecordPort.php',
        'app/Modules/Workflow/BusinessActions/Application/BusinessWorkflowActionService.php',
        'app/Modules/Workflow/BusinessActions/Contracts/BusinessWorkflowActionErrors.php',
        'app/Modules/Workflow/BusinessActions/Infrastructure/Persistence/SqlBusinessWorkflowRecordReader.php',
        'app/Bootstrap/BusinessWorkflowActionFactory.php',
        'app/Services/BusinessWorkflowActions.php',
        'app/Services/BusinessLifecycleTransitions.php',
        'tests/_support/Workflow/BeReorg011BusinessActionsFixture.php',
        'tools/test-resource-state-resolution.php',
    ] as $relative) {
        if (! is_file($backend . '/' . $relative)) {
            throw new RuntimeException('BE011 missing prerequisite: ' . $relative);
        }
    }
    if (count($required) !== 7 || array_sum(array_map('count', $required)) !== 34) {
        throw new RuntimeException('BE011 requires seven exact classes and 34 methods.');
    }
    foreach (be011TestFiles() as $class => $relative) {
        $file = $backend . '/' . $relative;
        if (! isset($required[$class]) || ! is_file($file)) {
            throw new RuntimeException('BE011 missing required test: ' . $relative);
        }
        $source = file_get_contents($file);
        if (! is_string($source) || preg_match('/\bfinal\s+class\s+' . preg_quote($class, '/') . '\b/', $source) !== 1) {
            throw new RuntimeException('BE011 unexpected test class: ' . $relative);
        }
        preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', $source, $matches);
        $actual = $matches[1];
        $expected = $required[$class];
        sort($actual);
        sort($expected);
        if ($actual !== $expected || count(array_unique($actual)) !== count($actual)) {
            throw new RuntimeException('BE011 exact test method inventory mismatch: ' . $class);
        }
    }
}

/** Require exactly 1,012 fresh passing JUnit cases and every approved BE011 identity. */
function be011AssertJUnit(string $junit, array $required): void
{
    if (! is_file($junit)) {
        throw new RuntimeException('BE011 inherited JUnit is missing.');
    }
    $xml = new DOMDocument();
    if (! $xml->load($junit, LIBXML_NONET) || $xml->documentElement === null) {
        throw new RuntimeException('BE011 malformed JUnit.');
    }
    $xpath = new DOMXPath($xml);
    $cases = $xpath->query('//testcase');
    if ($cases === false || $cases->length < 1012) {
        throw new RuntimeException('BE011 requires exactly 1012 JUnit testcases (978 inherited + 34 BE011).');
    }
    foreach ($cases as $case) {
        if (! $case instanceof DOMElement || $case->getAttribute('name') === '') {
            throw new RuntimeException('BE011 malformed/unnamed testcase.');
        }
        foreach (['failure', 'error', 'skipped'] as $status) {
            if ($case->getElementsByTagName($status)->length > 0) {
                throw new RuntimeException('BE011 suite contains ' . $status . ': '
                    . $case->getAttribute('class') . '::' . $case->getAttribute('name'));
            }
        }
    }
    foreach ($required as $class => $expected) {
        $nodes = $xpath->query('//testcase[@class="' . $class . '"]');
        if ($nodes === false || $nodes->length !== count($expected)) {
            throw new RuntimeException('BE011 JUnit class count mismatch: ' . $class);
        }
        $found = [];
        foreach ($nodes as $case) {
            if (! $case instanceof DOMElement || $case->getAttribute('name') === '') {
                throw new RuntimeException('BE011 malformed class testcase: ' . $class);
            }
            $found[] = $case->getAttribute('name');
        }
        sort($found);
        sort($expected);
        if ($found !== $expected || count(array_unique($found)) !== count($found)) {
            throw new RuntimeException('BE011 JUnit method identities differ: ' . $class);
        }
    }
}

/** Never touch a DB unless the caller explicitly confirms the disposable test target. */
function be011Run(array $argv): int
{
    $args = array_slice($argv, 1);
    if ($args === ['--help']) {
        fwrite(STDOUT, "Usage: php tools/test-business-workflow-actions.php --confirm-test-db\n"
            . "Requires isolated 127.0.0.1:3306/erp_test (prefix dinktech_); do not run concurrently.\n"
            . "One inherited BE010 runner, 1012 exact tests, including 34 BE011; PHPStan status is preserved.\n");
        return 0;
    }
    if ($args !== ['--confirm-test-db']) {
        fwrite(STDERR, "BE011 refuses database work without --confirm-test-db. Use --help.\n");
        return 2;
    }
    $backend = dirname(__DIR__);
    try {
        $required = be011RequiredMethods();
        be011Preflight($backend, $required);
        if (! function_exists('proc_open') || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('BE011 requires proc_open and PHP DOM extension.');
        }
        $junit = $backend . '/build/logs/be-reorg-011.xml';
        $inherited = $backend . '/build/logs/be-reorg-010.xml';
        $log = dirname($backend) . '/test-result.log';
        // Only own output needs clearing: BE010 clears its own and its child JUnit.
        if (is_file($junit) && ! unlink($junit)) {
            throw new RuntimeException('BE011 cannot remove stale own JUnit.');
        }
        $started = time();
        $spool = tempnam(sys_get_temp_dir(), 'be011-');
        if ($spool === false) {
            throw new RuntimeException('BE011 could not create output spool.');
        }
        $reader = null;
        $process = null;
        $exit = 2;
        try {
            $process = proc_open([PHP_BINARY, 'tools/test-resource-state-resolution.php', '--confirm-test-db'],
                [0 => ['pipe', 'r'], 1 => ['file', $spool, 'ab'], 2 => ['redirect', 1]],
                $pipes, $backend, null, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('BE011 could not launch inherited BE010 runner.');
            }
            fclose($pipes[0]);
            $reader = fopen($spool, 'rb');
            if ($reader === false) {
                throw new RuntimeException('BE011 could not read inherited output.');
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
            throw new RuntimeException('BE011 inherited BE010 runner blocked with exit ' . $exit);
        }
        clearstatcache(true, $inherited);
        if (! is_file($inherited) || filemtime($inherited) < $started) {
            throw new RuntimeException('BE011 fresh BE010 JUnit missing or stale.');
        }
        // BE007's test-foundation.php opens this log with 'wb' for every run.
        // Its new contents may be longer than the previous run, so comparing
        // byte offsets with the pre-run file would silently discard fresh gates.
        clearstatcache(true, $log);
        if (! is_file($log) || filemtime($log) < $started) {
            throw new RuntimeException('BE011 inherited test-result.log is missing or stale.');
        }
        $fresh = file_get_contents($log);
        if (! is_string($fresh)) {
            throw new RuntimeException('BE011 could not read fresh test-result.log.');
        }
        foreach ([
            'PHPUnit exit code: 0',
            'Foundation guard: 17 checks PASS.',
            'BE-REORG-008 decision coverage gate:',
            'BE-REORG-009 Lifecycle coverage gate:',
            'BE-REORG-010 State Resolution gate:',
        ] as $marker) {
            if (! str_contains($fresh, $marker)) {
                throw new RuntimeException('BE011 inherited gate missing from fresh log: ' . $marker);
            }
        }
        if (preg_match('/PHPStan exit code: ([01])\b/', $fresh, $matches) !== 1 || (int) $matches[1] !== $exit) {
            throw new RuntimeException('BE011 child exit is not consistent with captured raw PHPStan status.');
        }
        require_once $backend . '/tools/test-resource-state-resolution.php';
        be010AssertJUnit($inherited, be010RequiredMethods());
        be011AssertJUnit($inherited, $required);
        if (! copy($inherited, $junit)) {
            throw new RuntimeException('BE011 cannot save fresh verified JUnit.');
        }
        $summary = "\nBE-REORG-011 Business Actions gate: all 34 exact cases executed; none failed, errored or skipped.\n"
            . "BE010 30, BE009 32, BE008 26, BE007 24, PBAC 91 and Foundation 17 checks retained.\n"
            . "BE011 runner child exit code: " . $exit . "\n"
            . ($exit === 0
                ? "RESULT: Full PHPUnit PASS; PHPStan raw exit 0; confirm independent review before acceptance.\n"
                : "RESULT: Full PHPUnit PASS; PHPStan raw exit 1; compare inherited 120 diagnostics by identity before acceptance.\n");
        if (file_put_contents($log, $summary, FILE_APPEND) === false) {
            throw new RuntimeException('BE011 cannot append test-result.log.');
        }
        fwrite(STDOUT, $summary . 'Log: ' . $log . PHP_EOL);
        return $exit;
    } catch (Throwable $error) {
        fwrite(STDERR, 'BE011 BLOCKED: ' . $error->getMessage() . PHP_EOL);
        return 2;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(be011Run($argv));
}

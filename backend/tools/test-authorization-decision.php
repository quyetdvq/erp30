<?php

declare(strict_types=1);

/**
 * BE-REORG-008: compose the accepted BE007 guarded full-suite runner once,
 * then require its fresh JUnit to include all 26 canonical decision cases.
 * Does not bypass the BE001 DB guard or rewrite the PHPStan exit code.
 */

/** @return array<string, int> */
function be008RequiredCases(): array
{
    return [
        'BeReorg008DecisionBoundaryTest' => 4,
        'BeReorg008DecisionCompatibilityTest' => 4,
        'BeReorg008DecisionFactoryTest' => 4,
        'BeReorg008DecisionParityTest' => 7,
        'BeReorg008DecisionBatchTest' => 5,
        'BeReorg008DecisionHttpTest' => 2,
    ];
}

/** @return array<string, string> */
function be008TestFiles(): array
{
    return [
        'BeReorg008DecisionBoundaryTest' => 'tests/unit/BeReorg008DecisionBoundaryTest.php',
        'BeReorg008DecisionCompatibilityTest' => 'tests/unit/BeReorg008DecisionCompatibilityTest.php',
        'BeReorg008DecisionFactoryTest' => 'tests/unit/BeReorg008DecisionFactoryTest.php',
        'BeReorg008DecisionParityTest' => 'tests/database/BeReorg008DecisionParityTest.php',
        'BeReorg008DecisionBatchTest' => 'tests/database/BeReorg008DecisionBatchTest.php',
        'BeReorg008DecisionHttpTest' => 'tests/database/BeReorg008DecisionHttpTest.php',
    ];
}

/**
 * Check required files and public method inventory BEFORE any DB work.
 *
 * @param array<string, int> $required
 * @return array<string, list<string>> Class -> exact known BE008 test method names.
 */
function be008Preflight(string $backend, array $required): array
{
    foreach ([
        'app/Authorization/AuthorizationService.php',
        'app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php',
        'app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php',
        'app/Bootstrap/AuthorizationDecisionFactory.php',
        'tests/_support/Authorization/BeReorg008DecisionFixture.php',
        'tools/test-authorization-administration.php',
    ] as $relativePath) {
        if (! is_file($backend . '/' . $relativePath)) {
            throw new RuntimeException('BE008 missing prerequisite: ' . $relativePath);
        }
    }
    $found = [];
    foreach (be008TestFiles() as $class => $relativePath) {
        if (! isset($required[$class]) || ! is_file($backend . '/' . $relativePath)) {
            throw new RuntimeException('BE008 missing test fixture: ' . $relativePath);
        }
        $source = file_get_contents($backend . '/' . $relativePath);
        if (! is_string($source)
            || preg_match('/\bfinal\s+class\s+' . preg_quote($class, '/') . '\b/', $source) !== 1) {
            throw new RuntimeException('BE008 unexpected test class in ' . $relativePath);
        }
        preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', $source, $matches);
        /** @var list<string> $methods */
        $methods = $matches[1];
        if (count($methods) !== $required[$class] || count(array_unique($methods)) !== count($methods)) {
            throw new RuntimeException('BE008 test method inventory mismatch: ' . $class);
        }
        sort($methods);
        $found[$class] = $methods;
    }
    if (count($found) !== count($required) || array_sum($required) !== 26) {
        throw new RuntimeException('BE008 requires exactly 26 named new test methods.');
    }
    return $found;
}

/**
 * Pure structural JUnit verification (no DB access, no second PHPUnit run).
 * The optional third argument binds JUnit names to the static source inventory.
 *
 * @param array<string, int> $required
 * @param array<string, list<string>> $expectedNames
 */
function be008AssertJUnit(string $path, array $required, array $expectedNames = []): void
{
    if (! is_file($path)) {
        throw new RuntimeException('BE008 missing fresh JUnit report.');
    }
    $document = new DOMDocument();
    if (! $document->load($path, LIBXML_NONET) || $document->documentElement === null) {
        throw new RuntimeException('BE008 JUnit cannot be parsed.');
    }
    $xpath = new DOMXPath($document);
    foreach ($required as $class => $count) {
        $nodes = $xpath->query('//testcase[@class="' . $class . '"]');
        if ($nodes === false || $nodes->length !== $count) {
            throw new RuntimeException('BE008 missing required JUnit cases: ' . $class);
        }
        $names = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                throw new RuntimeException('BE008 JUnit malformed testcase.');
            }
            foreach (['failure', 'error', 'skipped'] as $status) {
                if ($node->getElementsByTagName($status)->length !== 0) {
                    throw new RuntimeException('BE008 failed/skipped testcase: ' . $class . '::' . $node->getAttribute('name'));
                }
            }
            $method = $node->getAttribute('name');
            if ($method === '' || in_array($method, $names, true)) {
                throw new RuntimeException('BE008 duplicate/unnamed testcase: ' . $class);
            }
            $names[] = $method;
        }
        if (isset($expectedNames[$class])) {
            sort($names);
            if ($names !== $expectedNames[$class]) {
                throw new RuntimeException('BE008 JUnit name mismatch: ' . $class);
            }
        }
    }
    $all = $xpath->query('//testcase');
    if ($all === false || $all->length < 916) {
        throw new RuntimeException('BE008 did not execute the expected full suite (916 baseline cases).');
    }
    foreach ($all as $node) {
        if ($node instanceof DOMElement && $node->getElementsByTagName('skipped')->length !== 0) {
            throw new RuntimeException('BE008 detected a skipped full-suite test.');
        }
    }
}

/** @return int 0 only if all tools clean, 1 for inherited PHPStan or tests failed, 2 for blocked preflight. */
function be008Run(array $argv): int
{
    $arguments = array_slice($argv, 1);
    if ($arguments === ['--help']) {
        fwrite(STDOUT, "Usage: php tools/test-authorization-decision.php --confirm-test-db\n"
            . "Requires disposable 127.0.0.1:3306/erp_test with prefix dinktech_.\n"
            . "May refresh DB tables; never run concurrent test processes on erp_test.\n"
            . "Delegates BE007 full PHPUnit once, then PHPStan once only after PHPUnit passes.\n"
            . "Reports 26 BE008 decision cases in the fresh JUnit, appends to ../test-result.log.\n");
        return 0;
    }
    if ($arguments !== ['--confirm-test-db']) {
        fwrite(STDERR, "BE008 requires --confirm-test-db; use --help for safety information.\n");
        return 2;
    }
    $backend = dirname(__DIR__);
    try {
        $required = be008RequiredCases();
        $expectedNames = be008Preflight($backend, $required);
        if (! function_exists('proc_open') || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('BE008 requires proc_open and the PHP DOM extension.');
        }
        $junit = $backend . '/build/logs/be-reorg-001a.xml';
        $log = dirname($backend) . '/test-result.log';
        // The accepted runner deletes its own JUnit. Delete first as well to
        // prevent accidentally accepting a stale report when its child exits early.
        if (is_file($junit) && ! unlink($junit)) {
            throw new RuntimeException('Cannot clear stale JUnit report.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'be008-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate BE008 output spool.');
        }
        $process = null;
        $reader = null;
        $exit = 2;
        try {
            $process = proc_open([PHP_BINARY, 'tools/test-authorization-administration.php', '--confirm-test-db'],
                [0 => ['pipe', 'r'], 1 => ['file', $temporary, 'ab'], 2 => ['redirect', 1]],
                $pipes, $backend, null, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('Cannot launch guarded BE007 runner.');
            }
            fclose($pipes[0]);
            $reader = fopen($temporary, 'rb');
            if ($reader === false) {
                throw new RuntimeException('Cannot open BE008 output spool.');
            }
            do {
                $output = stream_get_contents($reader);
                if ($output !== false && $output !== '') {
                    fwrite(STDOUT, $output);
                }
                $status = proc_get_status($process);
                if ($status['running']) {
                    usleep(100000);
                }
            } while ($status['running']);
            $output = stream_get_contents($reader);
            if ($output !== false && $output !== '') {
                fwrite(STDOUT, $output);
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
            unlink($temporary);
        }
        $contents = is_file($log) ? file_get_contents($log) : false;
        if (! is_string($contents) || ! str_contains($contents, 'PHPUnit exit code: 0')) {
            throw new RuntimeException('Inherited full-suite runner did not complete passing PHPUnit; see test-result.log.');
        }
        foreach (['Foundation coverage gate:', 'Organization coverage gate:',
            'Contact coverage gate:', 'Employee Structure coverage gate:',
            'Settings coverage gate:', 'Numbering coverage gate:',
            'Authorization Administration coverage gate:'] as $line) {
            if (! str_contains($contents, $line)) {
                throw new RuntimeException('Inherited regression gate absent: ' . $line);
            }
        }
        if (! str_contains($contents, 'PHPStan exit code: ')) {
            throw new RuntimeException('Inherited PHPStan process did not finish.');
        }
        if ($exit !== 0 && $exit !== 1) {
            throw new RuntimeException('Inherited runner blocked with exit code ' . $exit);
        }
        be008AssertJUnit($junit, $required, $expectedNames);
        $summary = "\nBE-REORG-008 decision coverage gate: all 26 new cases executed, none skipped "
            . "(4 boundary + 4 compatibility + 4 factory + 7 parity + 5 batch + 2 HTTP).\n"
            . "Existing BE007: 24 cases; PBAC authority: 91 cases; inherited full-suite gates preserved.\n"
            . "BE008 runner child exit code: " . $exit . "\n"
            . ($exit === 0
                ? "RESULT: PHPUnit and PHPStan raw exit codes both 0; review complete JUnit/log.\n"
                : "RESULT: PHPUnit PASS; PHPStan raw exit 1; compare all diagnostics to 121-error BE007 baseline before acceptance.\n");
        if (file_put_contents($log, $summary, FILE_APPEND) === false) {
            throw new RuntimeException('Cannot append BE008 coverage gate to test-result.log.');
        }
        fwrite(STDOUT, $summary);
        fwrite(STDOUT, 'Log: ' . $log . PHP_EOL);
        return $exit;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'BE008 BLOCKED: ' . $exception->getMessage() . PHP_EOL);
        return 2;
    }
}

// Safe to include for synthetic JUnit tests without launching PHPUnit/DB work.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(be008Run($argv));
}

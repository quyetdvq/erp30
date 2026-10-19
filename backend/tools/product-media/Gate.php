<?php

declare(strict_types=1);

namespace DinkERP\ProductMediaTools;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/** Runner-only checks; never part of application autoload or production runtime. */
final class Gate
{
    /** @return array{mode:string,suite:string,target:string,prefix:string} */
    public static function arguments(array $args): array
    {
        if ($args === ['--preflight']) { return ['mode' => 'preflight', 'suite' => 'pilot', 'target' => '', 'prefix' => '']; }
        $out = ['mode' => 'run', 'suite' => 'pilot', 'target' => '', 'prefix' => ''];
        $seen = [];
        foreach ($args as $arg) {
            $key = explode('=', $arg, 2)[0];
            if (isset($seen[$key])) { throw new RuntimeException('Duplicate argument: ' . $key); }
            $seen[$key] = true;
            if ($arg === '--confirm-test-db') { continue; }
            if (str_starts_with($arg, '--target=')) { $out['target'] = substr($arg, 9); continue; }
            if (str_starts_with($arg, '--prefix=')) { $out['prefix'] = substr($arg, 9); continue; }
            if (str_starts_with($arg, '--suite=')) { $out['suite'] = substr($arg, 8); continue; }
            throw new RuntimeException('Unknown argument: ' . $arg);
        }
        if (! isset($seen['--confirm-test-db'], $seen['--target'], $seen['--prefix'])
            || ! in_array($out['suite'], ['pilot', 'regression'], true)) {
            throw new RuntimeException('Requires --confirm-test-db --target=<loopback>:<port>/erp_test --prefix=<prefix> [--suite=pilot|regression].');
        }
        self::target($out['target'], $out['prefix']);
        return $out;
    }

    /** @return array<string,mixed> */
    public static function target(string $target, string $prefix): array
    {
        if (preg_match('/\A(127\.0\.0\.1|localhost|::1):([0-9]{1,5})\/erp_test\z/D', $target, $m) !== 1
            || preg_match('/\A[A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new RuntimeException('Only explicit loopback / erp_test and a safe prefix are allowed.');
        }
        $port = (int) $m[2];
        if ($port < 1 || $port > 65535 || (string) $port !== $m[2]) { throw new RuntimeException('Invalid port.'); }
        return ['hostname' => $m[1], 'port' => $port, 'database' => 'erp_test', 'DBDriver' => 'MySQLi', 'DBPrefix' => $prefix,
            'DSN' => '', 'failover' => [], 'pConnect' => false];
    }

    public static function verifySources(string $repository, array $hashes): int
    {
        if ($hashes === []) { throw new RuntimeException('Empty source manifest.'); }
        foreach ($hashes as $relative => $expected) {
            if (! is_string($relative) || str_starts_with($relative, '/') || str_contains($relative, '..')
                || str_contains($relative, '\\') || ! is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/', $expected) !== 1) {
                throw new RuntimeException('Invalid source manifest entry.');
            }
            $path = $repository . '/' . $relative;
            if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $expected) {
                throw new RuntimeException('Baseline mismatch: ' . $relative . '. Stop; do not overwrite local changes.');
            }
        }
        return count($hashes);
    }

    /** Exact CLI discovery identities, including each data-set suffix. Fail closed on unknown/empty format. */
    public static function discovery(string $output): array
    {
        $ids = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^\s*-\s+([A-Za-z0-9_\\\\]+)::(.+)$/D', $line, $m) === 1) {
                // PHPUnit --list-tests uses testMethod"dataset" while JUnit uses
                // testMethod with data set "dataset". Preserve the dataset key.
                $ids[] = self::canonicalIdentity($m[1] . '::' . $m[2]);
            }
        }
        self::unique($ids, 'discovery');
        return $ids;
    }

    public static function assertRequiredMethods(array $identities, array $required): void
    {
        self::unique($identities, 'discovery'); self::unique($required, 'required methods');
        $methods = [];
        foreach ($identities as $identity) {
            $canonical = self::canonicalIdentity($identity);
            $methods[explode(' with data set ', $canonical, 2)[0]] = true;
        }
        foreach ($required as $method) {
            if (! isset($methods[$method])) { throw new RuntimeException('Required inherited/pilot method missing: ' . $method); }
        }
    }

    /** @param list<array{id:string,status:string}> $cases */
    public static function assertCases(array $cases, array $expected): void
    {
        self::unique($expected, 'expected');
        $actual = [];
        foreach ($cases as $case) {
            if (! is_array($case) || ! isset($case['id'], $case['status']) || $case['status'] !== 'PASS') {
                throw new RuntimeException('JUnit case failed, errored, skipped, or malformed: ' . json_encode($case));
            }
            $actual[] = $case['id'];
        }
        self::unique($actual, 'JUnit');
        $canonicalExpected = array_map(self::canonicalIdentity(...), $expected);
        $canonicalActual = array_map(self::canonicalIdentity(...), $actual);
        sort($canonicalExpected);
        sort($canonicalActual);
        if ($canonicalExpected !== $canonicalActual) {
            throw new RuntimeException('JUnit identities differ from discovery; missing=' . json_encode(array_values(array_diff($canonicalExpected, $canonicalActual)))
                . '; unexpected=' . json_encode(array_values(array_diff($canonicalActual, $canonicalExpected))));
        }
    }

    /** @return list<array{id:string,status:string}> */
    public static function readJUnit(string $path): array
    {
        if (! class_exists(DOMDocument::class) || ! is_file($path)) { throw new RuntimeException('Missing DOM or JUnit file.'); }
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || stripos($bytes, '<!DOCTYPE') !== false || stripos($bytes, '<!ENTITY') !== false) {
            throw new RuntimeException('Unsafe/missing JUnit XML.');
        }
        $xml = new DOMDocument();
        if (! $xml->loadXML($bytes, LIBXML_NONET)) { throw new RuntimeException('Malformed JUnit XML.'); }
        $nodes = (new DOMXPath($xml))->query('//testcase');
        if ($nodes === false || $nodes->length === 0) { throw new RuntimeException('No JUnit cases.'); }
        $cases = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) { throw new RuntimeException('Unexpected JUnit node.'); }
            $class = $node->getAttribute('class'); $name = $node->getAttribute('name');
            if ($class === '' || $name === '') { throw new RuntimeException('Unnamed JUnit case.'); }
            $status = 'PASS';
            foreach (['failure', 'error', 'skipped'] as $tag) {
                if ($node->getElementsByTagName($tag)->length !== 0) { $status = strtoupper($tag); }
            }
            $cases[] = ['id' => $class . '::' . $name, 'status' => $status];
        }
        return $cases;
    }

    /**
     * Normalize exactly the two PHPUnit 10 representations of a data set.
     * Never remove its name or index: JUnit must attest every discovered case.
     */
    private static function canonicalIdentity(string $id): string
    {
        if (preg_match('/\A([A-Za-z0-9_\\\\]+::test[A-Za-z0-9_]+)(?:((?: with data set )?)(#[0-9]+|"[^"\r\n]*"))?\z/D', $id, $matches) !== 1) {
            throw new RuntimeException('Invalid PHPUnit case identity: ' . $id);
        }
        if (! isset($matches[3])) { return $matches[1]; }
        return $matches[1] . ' with data set ' . $matches[3];
    }

    private static function unique(array $ids, string $label): void
    {
        if ($ids === []) { throw new RuntimeException('Empty ' . $label . ' inventory.'); }
        $seen = [];
        foreach ($ids as $id) {
            if (! is_string($id)) { throw new RuntimeException('Invalid ' . $label . ' identity.'); }
            $canonical = self::canonicalIdentity($id);
            if (isset($seen[$canonical])) { throw new RuntimeException('Duplicate ' . $label . ' identity: ' . $canonical); }
            $seen[$canonical] = true;
        }
    }
}

<?php

declare(strict_types=1);

namespace DinkERP\TestTools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use FilesystemIterator;

/**
 * Read-only, fail-closed source + PHPUnit suite validation. No CI4 bootstrap,
 * database connection or mutation is required for this validation.
 */
final class UnitNoDbSuite
{
    /**
     * @param array<string,mixed> $manifest
     * @return array{classes:list<string>,paths:list<string>,methods:int}
     */
    public static function validate(string $backend, array $manifest, string $xmlPath): array
    {
        $approved = $manifest['unit_no_db'] ?? null;
        $fast = $manifest['legacy_fast'] ?? null;
        $db = $manifest['rollback_db'] ?? null;
        $quarantine = $manifest['quarantine'] ?? null;
        $expected = $manifest['unit_expected'] ?? null;
        $expectedXmlHash = $manifest['unit_suite_sha256_lf'] ?? null;
        if (! is_array($approved) || $approved === [] || ! is_array($fast)
            || ! is_array($db) || ! is_array($quarantine) || ! is_array($expected)
            || ! is_int($expected['classes'] ?? null) || ! is_int($expected['methods'] ?? null)
            || ! is_string($expectedXmlHash)
            || preg_match('/\A[a-f0-9]{64}\z/D', $expectedXmlHash) !== 1) {
            throw new RuntimeException('UNIT NO-DB: invalid central manifest.');
        }
        $blocked = [];
        foreach ($db as $moduleClasses) {
            if (! is_array($moduleClasses)) {
                throw new RuntimeException('UNIT NO-DB: invalid DB lane.');
            }
            foreach ($moduleClasses as $name) {
                if (! is_string($name)) {
                    throw new RuntimeException('UNIT NO-DB: malformed DB lane class.');
                }
                $blocked[$name] = true;
            }
        }
        foreach ($quarantine as $name => $_entry) {
            $blocked[$name] = true;
        }
        foreach ($fast as $name) {
            if (! is_string($name)) {
                throw new RuntimeException('UNIT NO-DB: invalid legacy fast class.');
            }
            $blocked[$name] = true;
        }

        $classes = [];
        $paths = [];
        $methods = 0;
        $normalizedTests = str_replace('\\', '/', (string) realpath($backend . '/tests'));
        if ($normalizedTests === '' || $normalizedTests === '.') {
            throw new RuntimeException('UNIT NO-DB: missing tests directory.');
        }

        foreach ($approved as $name => $entry) {
            if (! is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $name) !== 1
                || isset($blocked[$name]) || ! is_array($entry)) {
                throw new RuntimeException('UNIT NO-DB: invalid, quarantined or cross-lane class: ' . (string) $name);
            }
            $relative = $entry['path'] ?? null;
            $expectedHash = $entry['sha256_lf'] ?? null;
            $expectedMethods = $entry['methods'] ?? null;
            if (! is_string($relative)
                || preg_match('~\Atests/(?:unit|database|Infrastructure/Import/Reader)/[A-Za-z0-9_/]+Test\.php\z~D', $relative) !== 1
                || str_contains($relative, '//') || str_contains($relative, '/../')
                || basename($relative) !== $name . '.php'
                || ! is_string($expectedHash)
                || preg_match('/\A[a-f0-9]{64}\z/D', $expectedHash) !== 1
                || ! is_int($expectedMethods) || $expectedMethods < 1) {
                throw new RuntimeException('UNIT NO-DB: invalid source pin for ' . $name);
            }
            $path = realpath($backend . '/' . $relative);
            if ($path === false || ! str_starts_with(str_replace('\\', '/', $path), $normalizedTests . '/')
                || ! is_file($path)) {
                throw new RuntimeException('UNIT NO-DB: source missing or outside tests: ' . $name);
            }
            $source = file_get_contents($path);
            if (! is_string($source)) {
                throw new RuntimeException('UNIT NO-DB: cannot inspect source: ' . $name);
            }
            $normalized = str_replace("\r", "\n", str_replace("\r\n", "\n", $source));
            if (! hash_equals($expectedHash, hash('sha256', $normalized))) {
                throw new RuntimeException('UNIT NO-DB: reviewed source hash mismatch: ' . $name);
            }
            if (preg_match('/\bclass\s+' . preg_quote($name, '/') . '\s+extends\s+(?:TestCase|CIUnitTestCase)\b/', $source) !== 1
                || preg_match('/\buse\s+(?:[\\\\A-Za-z0-9_]+\\\\)?(?:DatabaseTestTrait|TransactionalDatabaseTestTrait|FeatureTestTrait|ControllerTestTrait)\s*;/', $source) === 1
                || preg_match('/\b(?:db_connect|mysqli_connect|proc_open|shell_exec|passthru|curl_exec)\s*\(|\bDatabase::connect\s*\(/i', $source) === 1) {
                throw new RuntimeException('UNIT NO-DB: unreviewed DB/HTTP/process behavior: ' . $name);
            }
            $count = preg_match_all('/\bfunction\s+test[A-Za-z0-9_]+\s*\(/', $source);
            if (! is_int($count) || $count !== $expectedMethods) {
                throw new RuntimeException('UNIT NO-DB: test method count mismatch: ' . $name);
            }
            $classes[] = $name;
            $paths[] = $relative;
            $methods += $count;
        }
        if (count($classes) !== $expected['classes'] || $methods !== $expected['methods']) {
            throw new RuntimeException('UNIT NO-DB: expected lane coverage mismatch.');
        }

        // Detect a second file with the same approved class basename anywhere
        // under tests, even if the authored XML is otherwise exact.
        $found = array_fill_keys($classes, 0);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $backend . '/tests', FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $candidate) {
            if ($candidate->isFile() && str_ends_with($candidate->getFilename(), '.php')) {
                $candidateName = substr($candidate->getFilename(), 0, -4);
                if (isset($found[$candidateName])) {
                    $found[$candidateName]++;
                }
            }
        }
        foreach ($found as $name => $count) {
            if ($count !== 1) {
                throw new RuntimeException('UNIT NO-DB: duplicate or missing class file: ' . $name);
            }
        }
        self::validateXml($xmlPath, $paths, $expectedXmlHash);
        return ['classes' => $classes, 'paths' => $paths, 'methods' => $methods];
    }

    /** @param list<string> $paths */
    private static function validateXml(string $xmlPath, array $paths, string $expectedHash): void
    {
        $contents = file_get_contents($xmlPath);
        if (! is_string($contents) || stripos($contents, '<!DOCTYPE') !== false
            || stripos($contents, '<!ENTITY') !== false) {
            throw new RuntimeException('UNIT NO-DB: unsafe or missing PHPUnit suite XML.');
        }
        $normalized = str_replace("\r", "\n", str_replace("\r\n", "\n", $contents));
        if (! hash_equals($expectedHash, hash('sha256', $normalized))) {
            throw new RuntimeException('UNIT NO-DB: reviewed PHPUnit XML changed.');
        }
        // The XML is byte-for-byte audited (LF-normalized); also enforce the
        // exact file-list structure instead of relying solely on a checksum.
        if (preg_match('~<phpunit\b[^>]*\bbootstrap="tests/_support/UnitNoDb/bootstrap\.php"~s', $contents) !== 1
            || substr_count($contents, '<testsuite ') !== 1
            || str_contains($contents, '<directory')
            || preg_match_all('~<file>([A-Za-z0-9_/.-]+)</file>~', $contents, $match) === false
            || $match[1] !== $paths || substr_count($contents, '<file>') !== count($paths)
            || substr_count($contents, '</file>') !== count($paths)) {
            throw new RuntimeException('UNIT NO-DB: PHPUnit suite is not explicitly isolated.');
        }
    }
}

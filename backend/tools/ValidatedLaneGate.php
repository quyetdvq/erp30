<?php

declare(strict_types=1);

namespace DinkERP\TestTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Read-only preflight for the TWO XAMPP-accepted lanes at checkpoint 003E-01.
 * No CI4/bootstrap/vendor/database connection is needed. This is not a test
 * runner and cannot add classes to either lane.
 */
final class ValidatedLaneGate
{
    private const UNIT_BOOTSTRAP_SHA256_LF = '10480e14f5493a66083e85e1c8b6714c10e4411e8d6d33d51e919d35f3bef561';

    /**
     * @param array<string,mixed> $lanes
     * @return array{unit_classes:int,unit_methods:int,db_classes:int,db_methods:int,legacy_fast_reserved:int,quarantined:int}
     */
    public static function validate(string $backend, array $lanes): array
    {
        require_once __DIR__ . '/UnitNoDbSuite.php';
        $unit = UnitNoDbSuite::validate($backend, $lanes, $backend . '/phpunit.unit-no-db.xml');
        if (count($unit['classes']) !== 13 || $unit['methods'] !== 104) {
            throw new RuntimeException('VALIDATED LANES: unit checkpoint changed; re-review required.');
        }

        $db = $lanes['rollback_db'] ?? null;
        $fast = $lanes['legacy_fast'] ?? null;
        $quarantine = $lanes['quarantine'] ?? null;
        if (! is_array($db) || $db === [] || ! is_array($fast) || ! is_array($quarantine)
            || count($fast) !== 14 || count($quarantine) !== 1
            || ! isset($quarantine['P1Prd002ProductUomMasterServiceTest'])) {
            throw new RuntimeException('VALIDATED LANES: lane manifest or quarantine checkpoint mismatch.');
        }

        $classPattern = '/\A[A-Za-z][A-Za-z0-9_]*Test\z/D';
        $used = array_fill_keys($unit['classes'], 'unit_no_db');
        foreach ($fast as $class) {
            if (! is_string($class) || preg_match($classPattern, $class) !== 1 || isset($used[$class])) {
                throw new RuntimeException('VALIDATED LANES: invalid or overlapping legacy fast reservation.');
            }
            $used[$class] = 'legacy_fast';
        }
        foreach ($quarantine as $class => $reason) {
            if (! is_string($class) || preg_match($classPattern, $class) !== 1
                || ! is_array($reason) || isset($used[$class])) {
                throw new RuntimeException('VALIDATED LANES: invalid or overlapping quarantine class.');
            }
            $used[$class] = 'quarantine';
        }

        // Build a unique filename index; no glob-to-PHPUnit promotion is made.
        $testsDir = realpath($backend . '/tests');
        if (! is_string($testsDir)) {
            throw new RuntimeException('VALIDATED LANES: missing tests directory.');
        }
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $testsDir, FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }
            $name = substr($file->getFilename(), 0, -4);
            $files[$name][] = $file->getPathname();
        }

        $dbClasses = 0;
        $dbMethods = 0;
        foreach ($db as $module => $classes) {
            if (! is_string($module) || preg_match('/\A[a-z][a-z0-9_-]*\z/D', $module) !== 1
                || ! is_array($classes) || $classes === []) {
                throw new RuntimeException('VALIDATED LANES: invalid rollback DB module.');
            }
            foreach ($classes as $class) {
                if (! is_string($class) || preg_match($classPattern, $class) !== 1
                    || isset($used[$class])) {
                    throw new RuntimeException('VALIDATED LANES: invalid, duplicate or cross-lane DB class.');
                }
                $used[$class] = 'rollback_db';
                $paths = $files[$class] ?? [];
                if (count($paths) !== 1) {
                    throw new RuntimeException('VALIDATED LANES: missing/duplicate reviewed DB class: ' . $class);
                }
                $source = file_get_contents($paths[0]);
                if (! is_string($source)
                    || preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $source) !== 1
                    || preg_match('/\buse\s+Tests\\\\Support\\\\Performance\\\\TransactionalDatabaseTestTrait\s*;/', $source) !== 1
                    || preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $source) !== 1
                    || preg_match('/\buse\s+DatabaseTestTrait\s*;/', $source) === 1) {
                    throw new RuntimeException('VALIDATED LANES: DB trait/source mismatch: ' . $class);
                }
                $count = preg_match_all('/\bfunction\s+test[A-Za-z0-9_]+\s*\(/', $source);
                if (! is_int($count) || $count < 1) {
                    throw new RuntimeException('VALIDATED LANES: missing methods in DB class: ' . $class);
                }
                $dbClasses++;
                $dbMethods += $count;
            }
        }
        if ($dbClasses !== 35 || $dbMethods !== 191) {
            throw new RuntimeException('VALIDATED LANES: rollback DB checkpoint changed (35 classes / 191 methods required).');
        }

        $bootstrap = $backend . '/tests/_support/UnitNoDb/bootstrap.php';
        $source = is_file($bootstrap) ? file_get_contents($bootstrap) : false;
        if (! is_string($source) || ! hash_equals(self::UNIT_BOOTSTRAP_SHA256_LF, self::lfHash($source))) {
            throw new RuntimeException('VALIDATED LANES: approved unit DB-blocking bootstrap changed.');
        }

        return [
            'unit_classes' => count($unit['classes']),
            'unit_methods' => $unit['methods'],
            'db_classes' => $dbClasses,
            'db_methods' => $dbMethods,
            'legacy_fast_reserved' => count($fast),
            'quarantined' => count($quarantine),
        ];
    }

    private static function lfHash(string $source): string
    {
        $normalized = str_replace("\r", "\n", str_replace("\r\n", "\n", $source));
        return hash('sha256', $normalized);
    }
}

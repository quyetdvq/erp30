<?php

declare(strict_types=1);

namespace DinkERP\TestTools;

use RuntimeException;

/**
 * Source-pinned, fail-closed selection of unit tests for an immutable test schema.
 * No database connection, migrations, fixture mutation or PHPUnit bootstrap.
 *
 * Verified converted classes can join only the unit-safe lane after an explicit
 * reviewed, source-pinned manifest change. The remaining 13 classes run only in the separate UNIT-STANDARD suite.
 * No candidate can enter NO-DROP by accident.
 */
final class UnitNoDropSelection
{
    /**
     * @param list<string> $approved The current reviewed rollback DB cohort.
     * @return array{ready:list<string>,candidates:list<string>,blocked:array<string,string>,pure_count:int,approved_count:int}
     */
    public static function resolve(string $backend, array $approved): array
    {
        $manifest = require __DIR__ . '/unit-no-drop-inventory.php';
        $pure = $manifest['non_database_trait'] ?? null;
        $verified = $manifest['candidates_verified'] ?? null;
        $candidates = $manifest['candidates_unverified'] ?? null;
        $blocked = $manifest['blocked'] ?? null;
        if (! is_array($pure) || ! is_array($verified) || ! is_array($candidates) || ! is_array($blocked)
            || count($pure) !== 53 || count($verified) !== 18
            || count($candidates) !== 0 || count($blocked) !== 13) {
            throw new RuntimeException('UNIT NO-DROP: inventory changed; review required.');
        }
        $reviewed = array_fill_keys($approved, true);
        $ready = [];
        $unverified = [];
        $found = [];
        $approvedCount = 0;
        $paths = glob($backend . '/tests/unit/*Test.php');
        if (! is_array($paths) || count($paths) !== 104) {
            throw new RuntimeException('UNIT NO-DROP: unit test count changed; review required.');
        }
        foreach ($paths as $path) {
            $class = basename($path, '.php');
            if (isset($found[$class])) {
                throw new RuntimeException('UNIT NO-DROP: duplicate class: ' . $class);
            }
            $found[$class] = true;
            $source = file_get_contents($path);
            if (! is_string($source)
                || preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $source) !== 1) {
                throw new RuntimeException('UNIT NO-DROP: unreadable or invalid class: ' . $class);
            }
            $usesLegacy = preg_match('/\buse\s+DatabaseTestTrait\s*;/', $source) === 1;
            $usesTransaction = preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $source) === 1;
            $importsTransaction = preg_match('/\buse\s+Tests\\\\Support\\\\Performance\\\\TransactionalDatabaseTestTrait\s*;/', $source) === 1;
            if (isset($reviewed[$class])) {
                if ($usesLegacy || ! $usesTransaction || ! $importsTransaction) {
                    throw new RuntimeException('UNIT NO-DROP: approved class trait changed: ' . $class);
                }
                $ready[] = $class;
                $approvedCount++;
                continue;
            }
            if (isset($pure[$class])) {
                self::assertPinned($class, $source, $pure[$class]);
                if ($usesLegacy || $usesTransaction) {
                    throw new RuntimeException('UNIT NO-DROP: a pure class gained database setup: ' . $class);
                }
                $ready[] = $class;
                continue;
            }
            if (isset($verified[$class])) {
                self::assertPinned($class, $source, $verified[$class]);
                if ($usesLegacy || ! $usesTransaction || ! $importsTransaction) {
                    throw new RuntimeException('UNIT NO-DROP: verified class trait mismatch: ' . $class);
                }
                $ready[] = $class;
                continue;
            }
            if (isset($candidates[$class])) {
                self::assertPinned($class, $source, $candidates[$class]);
                if ($usesLegacy || ! $usesTransaction || ! $importsTransaction) {
                    throw new RuntimeException('UNIT NO-DROP: candidate trait mismatch: ' . $class);
                }
                $unverified[] = $class;
                continue;
            }
            if (isset($blocked[$class])) {
                if ($class === 'P1Emp001EmployeeRepositoryTest') {
                    if ($usesLegacy || $usesTransaction || ! str_contains($source, 'CREATE TEMPORARY TABLE')) {
                        throw new RuntimeException('UNIT NO-DROP: temporary-table quarantine changed: ' . $class);
                    }
                } elseif (! $usesLegacy || $usesTransaction) {
                    throw new RuntimeException('UNIT NO-DROP: legacy quarantine changed: ' . $class);
                }
                continue;
            }
            throw new RuntimeException('UNIT NO-DROP: unclassified class, no auto-approval: ' . $class);
        }
        foreach (array_merge(array_keys($pure), array_keys($verified), array_keys($candidates), array_keys($blocked)) as $class) {
            if (! isset($found[$class]) || isset($reviewed[$class])) {
                throw new RuntimeException('UNIT NO-DROP: duplicate or missing inventory class: ' . $class);
            }
        }
        sort($ready, SORT_STRING);
        sort($unverified, SORT_STRING);
        ksort($blocked, SORT_STRING);
        if (count($ready) !== 53 + 18 + $approvedCount || count($unverified) !== 0 || $approvedCount !== 20) {
            throw new RuntimeException('UNIT NO-DROP: audited unit coverage count changed.');
        }
        return [
            'ready' => $ready,
            'candidates' => $unverified,
            'blocked' => $blocked,
            'pure_count' => count($pure),
            'approved_count' => $approvedCount,
        ];
    }

    /**
     * Validate a deliberately enumerated candidate batch. Selection cannot
     * broaden the source-pinned candidate allowlist, even via direct PHP CLI.
     *
     * @param list<string> $candidates
     * @return list<string>
     */
    public static function candidateBatch(string $csv, array $candidates): array
    {
        if ($csv === '' || strlen($csv) > 2048) {
            throw new RuntimeException('UNIT NO-DROP: empty or oversized candidate batch.');
        }
        $selected = explode(',', $csv);
        if (count($selected) < 2 || count($selected) > 5) {
            throw new RuntimeException('UNIT NO-DROP: batch requires 2..5 named candidate classes.');
        }
        $seen = [];
        foreach ($selected as $class) {
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $class) !== 1
                || isset($seen[$class]) || ! in_array($class, $candidates, true)) {
                throw new RuntimeException('UNIT NO-DROP: invalid, duplicate or unreviewed batch class: ' . $class);
            }
            $seen[$class] = true;
        }
        return $selected;
    }

    /**
     * A diagnostic single-method selector must remain inside the already
     * source-pinned candidate class, never a bare PHPUnit regex supplied
     * directly by the caller.
     */
    public static function candidateMethod(string $method, string $source): void
    {
        if (preg_match('/\Atest[A-Z][A-Za-z0-9_]*\z/D', $method) !== 1
            || preg_match('/\bpublic\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $source) !== 1) {
            throw new RuntimeException('UNIT NO-DROP: unknown or invalid candidate test method.');
        }
    }

    /** @param mixed $expected */
    private static function assertPinned(string $class, string $source, mixed $expected): void
    {
        $normalized = str_replace("\r", "\n", str_replace("\r\n", "\n", $source));
        if (! is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/D', $expected) !== 1
            || ! hash_equals($expected, hash('sha256', $normalized))) {
            throw new RuntimeException('UNIT NO-DROP: source changed, review before running: ' . $class);
        }
    }
}

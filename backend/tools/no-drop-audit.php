<?php

declare(strict_types=1);

/**
 * Read-only test inventory and conservative NO-DROP risk triage.
 *
 * This scanner NEVER grants approval, changes a test, connects to MySQL,
 * changes a manifest, or edits the source tree. Dependency call paths require
 * human inspection even when this scanner reports REVIEW_CANDIDATE.
 */
$backend = dirname(__DIR__);
$testRoot = $backend . '/tests';

try {
    $format = 'table';
    $limit = 30;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--format=json') {
            $format = 'json';
        } elseif ($arg === '--format=table') {
            $format = 'table';
        } elseif (preg_match('/\A--limit=([1-9][0-9]{0,2})\z/D', $arg, $matches) === 1) {
            $limit = min(300, (int) $matches[1]);
        } else {
            throw new InvalidArgumentException('Usage: php tools/no-drop-audit.php [--format=table|json] [--limit=30]');
        }
    }

    $approved = require __DIR__ . '/no-drop-approved.php';
    if (! is_array($approved)) {
        throw new RuntimeException('Invalid reviewed NO-DROP allowlist.');
    }
    $approvedByName = [];
    foreach ($approved as $module => $names) {
        if (! is_string($module) || ! is_array($names)) {
            throw new RuntimeException('Invalid reviewed NO-DROP allowlist entry.');
        }
        foreach ($names as $name) {
            if (! is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $name) !== 1
                || isset($approvedByName[$name])) {
                throw new RuntimeException('Duplicate or invalid approved class name.');
            }
            $approvedByName[$name] = $module;
        }
    }

    $quarantined = require __DIR__ . '/no-drop-quarantined.php';
    if (! is_array($quarantined)) {
        throw new RuntimeException('Invalid NO-DROP quarantine manifest.');
    }
    foreach ($quarantined as $name => $entry) {
        if (! is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $name) !== 1
            || ! is_array($entry) || ! is_string($entry['path'] ?? null)
            || ! is_string($entry['sha256_lf'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $entry['sha256_lf']) !== 1
            || ! is_string($entry['reason'] ?? null) || trim($entry['reason']) === ''
            || isset($approvedByName[$name])) {
            throw new RuntimeException('Invalid quarantine entry or quarantined class was approved: ' . $name);
        }
    }
    $seenQuarantined = [];

    $rules = [
        // Heuristics only: matches are triage warnings, not safety proofs.
        'DDL_OR_MIGRATION' => '~\b(?:DROP|TRUNCATE|ALTER|CREATE)\s+(?:TABLE|DATABASE|INDEX)\b|->\s*(?:dropTable|createTable|dropColumn|addColumn|forge|migrateDatabase|regressDatabase)\s*\(|\bMigrationRunner\b~i',
        'TRANSACTION_OR_LOCK' => '~\btrans(?:Begin|Start|Commit|Rollback|Complete|Off|Strict|Exception|Status)\s*\(|->\s*(?:beginTransaction|commit|rollBack)\s*\(|\b(?:FOR UPDATE|GET_LOCK|RELEASE_LOCK)\b~i',
        'SECOND_CONNECTION' => '~(?:Database::connect|db_connect\s*\(|new\s+(?:\\?Config\\Database|\\?mysqli|\\?PDO)\s*\()~i',
        'SEEDER' => '~\b(?:[A-Za-z0-9_]*Seeder|->\s*seed\s*\()\b~i',
        'HTTP_OR_PROCESS' => '~\b(?:proc_open|shell_exec|passthru|curl_exec|GuzzleHttp|FeatureTestTrait|ControllerTestTrait|Pm3WireHttp|PhpServer)\b~i',
        'SQL_ERROR_SEMANTICS' => '~\b(?:duplicate|SQLSTATE|deadlock|unique\s+(?:key|index)|constraint\s+violation)\b~i',
        'EXTERNAL_IO' => '~\b(?:file_put_contents|fwrite|unlink|rename|copy|mkdir|rmdir)\s*\(~i',
        'NESTED_SERVICE' => '~\bnew\s+(?:\\?[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z0-9_]*(?:Service|Repository|Factory)\s*\(~',
    ];
    $rows = [];
    $seen = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (! $entry->isFile() || preg_match('/Test\.php\z/D', $entry->getFilename()) !== 1) {
            continue;
        }
        $path = $entry->getPathname();
        $source = file_get_contents($path);
        if (! is_string($source)) {
            throw new RuntimeException('Cannot inspect ' . $path);
        }
        $name = basename($path, '.php');
        $relativePath = 'backend/tests/' . str_replace('\\', '/', substr($path, strlen($testRoot) + 1));
        $testCount = preg_match_all('/\bfunction\s+test[A-Za-z0-9_]+\s*\(/', $source);
        $isTransactional = preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $source) === 1;
        $isLegacy = preg_match('/\buse\s+DatabaseTestTrait\s*;/', $source) === 1;
        $indicators = [];
        foreach ($rules as $label => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $indicators[] = $label;
            }
        }
        if (preg_match('/(?:Migration|Migrate|Seed|Schema|Lock|Concurrency|WireHttp|Api|Controller|AutoNumbering|CounterAllocator|ProductUom|UomMaster)Test\.php\z/i', basename($path)) === 1) {
            $indicators[] = 'CLASS_NAME_REVIEW';
        }
        $approvedModule = $approvedByName[$name] ?? null;
        if ($approvedModule !== null) {
            $status = $isTransactional && ! $isLegacy ? 'APPROVED' : 'APPROVED_SOURCE_MISMATCH';
            $seen[$name] = ($seen[$name] ?? 0) + 1;
        } elseif (isset($quarantined[$name])) {
            $seenQuarantined[$name] = ($seenQuarantined[$name] ?? 0) + 1;
            $quarantine = $quarantined[$name];
            // Normalize line endings only: Windows CRLF and Linux LF must
            // identify the same reviewed source, but changed PHP must fail.
            $lfSource = str_replace("\r", "\n", str_replace("\r\n", "\n", $source));
            $status = $relativePath === $quarantine['path']
                && $isTransactional && ! $isLegacy
                && hash_equals($quarantine['sha256_lf'], hash('sha256', $lfSource))
                ? 'QUARANTINED_UNSAFE'
                : 'QUARANTINE_SOURCE_MISMATCH';
        } elseif ($isTransactional) {
            $status = 'UNAPPROVED_TRANSACTION_TRAIT';
        } elseif ($isLegacy) {
            $blockers = ['DDL_OR_MIGRATION', 'TRANSACTION_OR_LOCK', 'SECOND_CONNECTION', 'SEEDER',
                'HTTP_OR_PROCESS', 'SQL_ERROR_SEMANTICS', 'EXTERNAL_IO', 'CLASS_NAME_REVIEW'];
            $status = array_intersect($indicators, $blockers) !== [] ? 'SPECIAL_REVIEW' : 'REVIEW_CANDIDATE';
        } else {
            // No direct DatabaseTestTrait does NOT imply database-free.
            $status = 'OTHER_TEST';
        }
        $rows[] = [
            'class' => $name,
            'path' => $relativePath,
            'test_methods' => is_int($testCount) ? $testCount : 0,
            'status' => $status,
            'approved_module' => $approvedModule,
            'quarantine_reason' => isset($quarantined[$name]) ? $quarantined[$name]['reason'] : null,
            'signals' => array_values(array_unique($indicators)),
            'sha256' => hash('sha256', $source),
        ];
    }
    foreach ($approvedByName as $name => $module) {
        if (($seen[$name] ?? 0) !== 1) {
            throw new RuntimeException('Approved class is missing or duplicated: ' . $name . ' (' . $module . ').');
        }
    }
    foreach ($quarantined as $name => $_entry) {
        if (($seenQuarantined[$name] ?? 0) !== 1) {
            throw new RuntimeException('Quarantined test class missing or duplicated: ' . $name);
        }
    }
    usort($rows, static function (array $a, array $b): int {
        $priority = ['QUARANTINE_SOURCE_MISMATCH' => -3, 'APPROVED_SOURCE_MISMATCH' => -2,
            'UNAPPROVED_TRANSACTION_TRAIT' => -1, 'QUARANTINED_UNSAFE' => 1,
            'REVIEW_CANDIDATE' => 0, 'SPECIAL_REVIEW' => 1, 'APPROVED' => 2, 'OTHER_TEST' => 3];
        return ($priority[$a['status']] <=> $priority[$b['status']])
            ?: ($b['test_methods'] <=> $a['test_methods'])
            ?: strcmp($a['class'], $b['class']);
    });
    $summary = ['file_count' => count($rows), 'approved_classes' => count($approvedByName), 'status_counts' => []];
    foreach ($rows as $row) {
        $key = $row['status'];
        $summary['status_counts'][$key] = ($summary['status_counts'][$key] ?? 0) + 1;
    }
    ksort($summary['status_counts']);
    $report = [
        'policy' => 'AUDIT_ONLY_NO_APPROVAL',
        'warning' => 'Static signals are conservative and cannot prove transaction isolation or service call behavior.',
        'summary' => $summary,
        'classes' => $rows,
    ];
    if ($format === 'json') {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        printf("NO-DROP read-only inventory: %d test files, %d approved classes\n", $summary['file_count'], $summary['approved_classes']);
        foreach ($summary['status_counts'] as $state => $total) {
            printf("  %-31s %3d\n", $state, $total);
        }
        echo "Review candidates (not automatically safe or approved):\n";
        $shown = 0;
        foreach ($rows as $row) {
            if ($row['status'] !== 'REVIEW_CANDIDATE') {
                continue;
            }
            printf("  %3d tests  %-55s %s\n", $row['test_methods'], $row['class'], implode(',', $row['signals']));
            if (++$shown >= $limit) {
                break;
            }
        }
        echo "For full evidence: php tools/no-drop-audit.php --format=json > no-drop-audit.json\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'NO-DROP AUDIT STOP: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}

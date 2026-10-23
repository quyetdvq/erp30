<?php

declare(strict_types=1);

/**
 * BE-REORG-014M: vendor-free characterization of the single employee-contact
 * primary-scope rule shared by per-target PBAC and batched Contact lists.
 * No DB connection, SQL, migrations or Shield runtime.
 * Run: php backend/tools/test-be-reorg-014m-employee-primary-scope.php
 */

$backend = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($backend): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $backend . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Authorization\EmployeeContactScopeResolver;

function be014mAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$primary = static fn (mixed $flag = 1, mixed $status = 'ACTIVE', mixed $organization = 51): array => [
    'id' => 10, 'employee_id' => 7, 'organization_id' => $organization,
    'is_primary' => $flag, 'status' => $status, 'effective_from' => '2026-01-01',
];

$checks = [
    'valid primary supports canonical integer and DB string representation' => static function () use ($primary): void {
        foreach ([1, '1'] as $flag) {
            $row = $primary($flag);
            be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary([$row]) === $row,
                'Canonical primary row was changed');
        }
    },
    'missing and ambiguous primaries fail closed' => static function () use ($primary): void {
        be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary([]) === null, 'Missing primary was admitted');
        be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary([$primary(), $primary()]) === null,
            'Ambiguous primaries were admitted');
    },
    'noncanonical status and primary flags fail closed' => static function () use ($primary): void {
        foreach (['active', 'Active', 'INACTIVE', '', null, true] as $status) {
            be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary([$primary(1, $status)]) === null,
                'Invalid State was accepted');
        }
        foreach ([0, '0', '01', true, false, 'true', null] as $flag) {
            be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary([$primary($flag)]) === null,
                'Invalid primary flag was accepted');
        }
    },
    'batch primary candidates preserve legacy strict selection' => static function () use ($primary): void {
        $rows = [$primary(0), $primary('1')];
        $filtered = array_values(array_filter($rows, static fn (array $row): bool =>
            in_array($row['is_primary'] ?? null, [1, '1'], true)));
        be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary($filtered) === $rows[1],
            'Non-primary effective assignments changed the selected scope');
        $rows[] = $primary(1);
        $filtered = array_values(array_filter($rows, static fn (array $row): bool =>
            in_array($row['is_primary'] ?? null, [1, '1'], true)));
        be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary($filtered) === null,
            'Multiple valid primaries must fail closed');
    },
    'extracted rule matches frozen original across the primary/status matrix' => static function () use ($primary): void {
        // Frozen pre-014M rule: this is intentionally independent of the new helper.
        $previous = static fn (array $rows): ?array =>
            count($rows) === 1 && ($rows[0]['status'] ?? null) === 'ACTIVE'
                && in_array($rows[0]['is_primary'] ?? null, [1, '1'], true) ? $rows[0] : null;
        foreach ([1, '1', 0, '0', '01', true, false, null] as $flag) {
            foreach (['ACTIVE', 'active', 'INACTIVE', '', null] as $status) {
                $row = $primary($flag, $status);
                foreach ([[], [$row], [$row, $primary(1)]] as $rows) {
                    be014mAssert(EmployeeContactScopeResolver::uniqueCanonicalPrimary($rows) === $previous($rows),
                        'Primary parity diverged from the frozen predecessor');
                }
            }
        }
    },
    'real PBAC resolver and batch compiler share the same rule' => static function () use ($backend): void {
        $resolver = (string) file_get_contents($backend . '/app/Authorization/EmployeeContactScopeResolver.php');
        $compiler = (string) file_get_contents($backend . '/app/Authorization/OwnerAwareListBucketCompiler.php');
        be014mAssert(str_contains($resolver, 'self::uniqueCanonicalPrimary($primaries)')
            && str_contains($compiler, 'EmployeeContactScopeResolver::uniqueCanonicalPrimary($primaries)'),
            'Per-target and batch readers no longer share the primary rule');
        be014mAssert(str_contains($resolver, "!== 'ACTIVE'") && ! str_contains($compiler, "!== 'ACTIVE'"),
            'Canonical status check duplicated or moved outside the primary rule');
        be014mAssert(str_contains($compiler, "in_array($" . "row['is_primary'] ?? null, [1, '1'], true)"),
            'Batch must continue extracting strict primary candidates');
    },
    'employee existence UTC date and field errors are preserved' => static function () use ($backend): void {
        $resolver = (string) file_get_contents($backend . '/app/Authorization/EmployeeContactScopeResolver.php');
        $compiler = (string) file_get_contents($backend . '/app/Authorization/OwnerAwareListBucketCompiler.php');
        foreach ([
            '$this->employees->find($employeeId) === null',
            'findEffective($employeeId, $date, true)',
            "businessDate(new DateTimeZone('UTC'))",
            "'organization_id', self::ERROR_PRIMARY_SCOPE_UNAVAILABLE",
        ] as $invariant) {
            be014mAssert(str_contains($resolver, $invariant), 'Per-target scope invariant lost: ' . $invariant);
        }
        be014mAssert(str_contains($compiler, 'findEffectiveForEmployees(array_keys($existing), $date)')
            && str_contains($compiler, "businessDate(new DateTimeZone('UTC'))")
            && str_contains($compiler, 'FILTER_VALIDATE_INT'),
            'Batch read/UTC/date/invalid organization behavior changed');
    },
    'other scope resolvers and DB differential coverage remain' => static function () use ($backend): void {
        $context = (string) file_get_contents($backend . '/app/Authorization/EvaluationOrganizationResolver.php');
        $index = (string) file_get_contents($backend . '/app/Authorization/EffectiveOrganizationScopeIndex.php');
        $parity = (string) file_get_contents($backend . '/tests/unit/P1Perf006OwnerAwareSqlPaginationTest.php');
        be014mAssert(str_contains($context, "'employee_contact' => new EmployeeContactScopeResolver(")
            && str_contains($context, 'ERROR_HINT_MISMATCH'), 'Evaluation context routing changed');
        be014mAssert(str_contains($index, 'findEffective($employeeId, $date)')
            && str_contains($index, 'return null; // orphan or cycle')
            && str_contains($index, 'MAX_BOUNDARIES'), 'Conservative Scope Index invariants lost');
        be014mAssert(str_contains($parity, 'testPbacScopeSignaturesAndSqlBucketsMatchOriginalEngine')
            && str_contains($parity, "'EMPLOYEE', 'contact_ref_id' => $" . "ownerEmployee")
            && str_contains($parity, '$this->assertDifferential(\'contact\''),
            'MariaDB/Shield differential parity evidence removed');
    },
];

try {
    foreach ($checks as $label => $check) {
        $check();
        echo "  {$label}: PASS\n";
    }
    echo 'BE-REORG-014M PRIMARY SCOPE SOURCE GATE PASS: ' . count($checks)
        . " checks; real canonical rule; 0 DB connections/SQL. MariaDB/Shield/HTTP NOT_RUN.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'BE-REORG-014M SOURCE GATE FAIL: ' . $exception->getMessage() . "\n");
    exit(2);
}

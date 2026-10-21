<?php

declare(strict_types=1);

/**
 * 03C-02: pure, source-pinned policy for a ONE-OPERATION scratch canary.
 * No SQL executor, migrations or cleanup-by-table exists in this class.
 */
final class ScratchNumberingPilotPolicy
{
    public const TARGET = '127.0.0.1:3306/erp_test_scratch';
    public const PREFIX = 'dinktech_';
    public const TABLES = [
        'dinktech_auto_numbering_counters',
        'dinktech_auto_numbering_series',
        'dinktech_organizations',
        'dinktech_products',
        'dinktech_resources',
    ];
    public const DELETION_ORDER = [
        'dinktech_auto_numbering_counters',
        'dinktech_products',
        'dinktech_auto_numbering_series',
        'dinktech_resources',
        'dinktech_organizations',
    ];

    /**
     * Pure fixture contract for the narrow scratch pilot. In particular,
     * auto_numbering_series.created_at is NOT NULL with no default in the
     * committed migration. All rows must carry a deterministic timestamp.
     * This gate runs BEFORE any INSERT; it does not issue SQL.
     *
     * @param array<string,mixed> $payload
     */
    public static function assertFixturePayload(string $table, array $payload): void
    {
        $required = match ($table) {
            'resources' => ['type', 'name', 'created_at'],
            'organizations' => ['type', 'code', 'name', 'status', 'created_at'],
            'auto_numbering_series' => [
                'resource_id', 'series_code', 'name', 'scope_type', 'pattern',
                'reset_policy', 'generation_mode', 'start_number', 'increment_by',
                'is_default', 'status', 'created_at',
            ],
            'products' => [
                'code', 'sku', 'product_type', 'serial_management_enabled',
                'status', 'created_at',
            ],
            default => throw new RuntimeException('03C-02 STOP: unapproved fixture table.'),
        };
        foreach ($required as $column) {
            if (! array_key_exists($column, $payload) || $payload[$column] === null) {
                throw new RuntimeException('03C-02 STOP: incomplete fixture payload.');
            }
        }
        if ($payload['created_at'] !== '2026-10-06 04:05:06') {
            throw new RuntimeException('03C-02 STOP: fixture timestamp is not pinned.');
        }
    }

    /** Source hash is LF-normalized, matching unit-no-drop-inventory.php. */
    private const PINNED_SOURCES = [
        'app/Services/AutoNumberService.php' => '49f5471e08a090102432c95e228a374eb4ef740468aa4d49c46d1d4d93fd3ff7',
        'app/Modules/Numbering/Issuance/Application/AutoNumberService.php' => '2ab91dcc79e219b1f0863284a97af1ffc9d9dac4959cff3cf802f0a608051f0e',
        'app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php' => '519bdfccb26a5803aee7e05409e80588500fe55d64f054d90a9f2d6d2a81d9ed',
        'app/Modules/Product/Integration/Numbering/ProductIdentifierAdapter.php' => '94de30a17fef59fdfb1c0e4fc9f6971e0d5eb4c45b8c797e4c0bdd3f0e3810f2',
        'app/Modules/Numbering/Scope/Application/AutoNumberingScopePeriodResolver.php' => '8e02a58b754a4e9bcbcc8e4359dae33fd2ee01b797bfc3b46e7c2b9545e60f0d',
    ];

    /** @param list<string> $args */
    public static function assertInvocation(array $args): bool
    {
        if ($args === ['--plan']) {
            return false;
        }
        if ($args !== ['--execute', '--target=' . self::TARGET, '--prefix=' . self::PREFIX]) {
            throw new RuntimeException('03C-02 STOP: only explicit, fixed-target --execute is allowed.');
        }
        return true;
    }

    /** @param list<string> $args */
    public static function assertDiagnosticInvocation(array $args): bool
    {
        if ($args === ['--plan']) {
            return false;
        }
        if ($args !== [
            '--read-only',
            '--target=' . self::TARGET,
            '--prefix=' . self::PREFIX,
        ]) {
            throw new RuntimeException('03C-02D STOP: only an explicit read-only scratch target is permitted.');
        }
        return true;
    }

    public static function assertSources(string $backend): void
    {
        foreach (self::PINNED_SOURCES as $relative => $expected) {
            $source = @file_get_contents($backend . '/' . $relative);
            if (! is_string($source)
                || ! hash_equals($expected, hash('sha256', str_replace(["\r\n", "\r"], "\n", $source)))) {
                throw new RuntimeException('03C-02 STOP: reviewed production dependency changed: ' . $relative);
            }
        }
    }

    /**
     * Enforce child-first deletion for every FK between owned tables. Other
     * tables must have ZERO rows before any deletion (checked separately).
     *
     * @param array<string,mixed> $metadata
     */
    public static function assertRelationalDeletionOrder(array $metadata): void
    {
        $references = $metadata['foreign_keys'] ?? null;
        if (! is_array($references) || count($references) !== 50) {
            throw new RuntimeException('03C-02 STOP: FK metadata incomplete.');
        }
        $positions = array_flip(self::DELETION_ORDER);
        foreach ($references as $fk) {
            if (! is_array($fk)) {
                throw new RuntimeException('03C-02 STOP: invalid FK metadata.');
            }
            $child = $fk['TABLE_NAME'] ?? null;
            $parent = $fk['REFERENCED_TABLE_NAME'] ?? null;
            if (! is_string($child) || ! is_string($parent)) {
                throw new RuntimeException('03C-02 STOP: incomplete FK metadata.');
            }
            if ($child !== $parent && isset($positions[$child], $positions[$parent])
                && $positions[$child] >= $positions[$parent]) {
                throw new RuntimeException('03C-02 STOP: deletion order violates FK ' . $child . '.');
            }
        }
    }

    /**
     * This is NOT a generic cleanup planner: only individually registered
     * rows from the current operation may be deleted. Unknown rows block it.
     *
     * @param array<string,int> $counts
     * @param array<string,list<int>> $ledger
     * @param list<string> $tableNames
     */
    public static function assertOwnedInventory(array $counts, array $ledger, array $tableNames, int $migrationRows): void
    {
        if (count($tableNames) !== 41 || count($counts) !== 41 || $migrationRows < 0) {
            throw new RuntimeException('03C-02 STOP: incomplete scratch inventory.');
        }
        $sorted = $tableNames;
        sort($sorted, SORT_STRING);
        $actual = array_keys($counts);
        sort($actual, SORT_STRING);
        if ($sorted !== $actual || array_diff(array_keys($ledger), self::TABLES) !== []
            || count(array_unique(array_keys($ledger))) !== count($ledger)) {
            throw new RuntimeException('03C-02 STOP: inventory/ledger table mismatch.');
        }
        $seen = [];
        foreach ($ledger as $table => $ids) {
            if (! is_array($ids) || ! array_is_list($ids)) {
                throw new RuntimeException('03C-02 STOP: malformed row ownership ledger.');
            }
            foreach ($ids as $id) {
                if (! is_int($id) || $id < 1 || isset($seen[$table][$id])) {
                    throw new RuntimeException('03C-02 STOP: invalid or repeated owned PK.');
                }
                $seen[$table][$id] = true;
            }
        }
        foreach ($counts as $table => $count) {
            $expected = $table === 'dinktech_migrations' ? $migrationRows : count($ledger[$table] ?? []);
            if (! is_int($count) || $count !== $expected) {
                throw new RuntimeException('03C-02 STOP: scratch contains unowned or unexpected rows in ' . $table . '.');
            }
        }
    }
}

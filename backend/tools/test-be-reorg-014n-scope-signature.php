<?php

declare(strict_types=1);

/**
 * BE-REORG-014N: vendor-free parity and isolation gate for the immutable
 * EffectiveOrganizationScopeIndex signature memo. No DB connections or SQL.
 * Run: php backend/tools/test-be-reorg-014n-scope-signature.php
 */

require dirname(__DIR__) . '/app/Authorization/EffectiveOrganizationScopeIndex.php';

use App\Authorization\EffectiveOrganizationScopeIndex;

function be014nAssert(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

/**
 * Construct exactly the production class, without calling its DB-backed build().
 * Only the immutable projection inputs are substituted for these source tests.
 * @param array<int,array{id:int,parent_id:?int,type:string,status:string}> $nodes
 * @param array<int,true> $boundaries
 */
function be014nIndex(array $nodes, array $boundaries, int $groupId = 1): EffectiveOrganizationScopeIndex
{
    $reflection = new ReflectionClass(EffectiveOrganizationScopeIndex::class);
    $index = $reflection->newInstanceWithoutConstructor();
    $reflection->getConstructor()->invoke($index, $nodes, $boundaries, $groupId);
    return $index;
}

/**
 * Frozen pre-014N algorithm: deliberately independent of the cached method.
 * @param array<int,array{id:int,parent_id:?int,type:string,status:string}> $nodes
 * @param array<int,true> $boundaries
 */
function be014nLegacySignature(array $nodes, array $boundaries, int $groupId, int $id): ?string
{
    if (! isset($nodes[$id])) {
        return null;
    }
    $seen = [];
    $path = [];
    $cursor = $id;
    while (true) {
        if (isset($seen[$cursor]) || ! isset($nodes[$cursor])) {
            return null;
        }
        $seen[$cursor] = true;
        $path[] = $cursor;
        $parent = $nodes[$cursor]['parent_id'];
        if ($parent === null) {
            if ($cursor !== $groupId) {
                return null;
            }
            break;
        }
        $cursor = $parent;
    }
    $path = array_reverse($path);
    return implode(',', array_values(array_filter($path, static fn (int $nodeId): bool => isset($boundaries[$nodeId]))));
}

/** @return array<int,array{id:int,parent_id:?int,type:string,status:string}> */
function be014nTree(int $length, bool $chain = true): array
{
    $nodes = [];
    for ($id = 1; $id <= $length; $id++) {
        $nodes[$id] = [
            'id' => $id,
            'parent_id' => $id === 1 ? null : ($chain ? $id - 1 : intdiv($id, 2)),
            'type' => $id === 1 ? 'GROUP' : 'COMPANY',
            'status' => $id % 2 === 0 ? 'ACTIVE' : 'INACTIVE',
        ];
    }
    return $nodes;
}

$checks = [
    'exact legacy signature strings for organization ancestor boundaries' => static function (): void {
        $nodes = be014nTree(64, false);
        $boundaries = array_fill_keys([1, 2, 4, 5, 10, 18, 32], true);
        $index = be014nIndex($nodes, $boundaries);
        foreach (array_keys($nodes) as $id) {
            be014nAssert($index->signature($id) === be014nLegacySignature($nodes, $boundaries, 1, $id),
                'Mismatch for balanced node ' . $id);
        }
        be014nAssert($index->signature(1) === '1' && $index->signature(10) === '1,2,5,10',
            'Boundary order or delimiter changed');
        be014nAssert($index->signature(999) === null, 'Unknown node must fail closed');
    },
    'sparse/no-boundary branches and repeated reads preserve exact equality' => static function (): void {
        $nodes = be014nTree(128, false);
        foreach ([[], [5 => true, 12 => true], [1 => true, 8 => true]] as $boundaries) {
            $index = be014nIndex($nodes, $boundaries);
            foreach (array_reverse(array_keys($nodes)) as $id) {
                $original = be014nLegacySignature($nodes, $boundaries, 1, $id);
                be014nAssert($index->signature($id) === $original
                    && $index->signature($id) === $original, 'Repeated lookup diverged');
            }
        }
    },
    'deep hierarchy and dense boundaries are memoized without recursion' => static function (): void {
        $nodes = be014nTree(12000);
        $boundaries = [];
        for ($id = 1; $id <= 12000; $id += 17) {
            $boundaries[$id] = true; // 706 boundaries, below MAX_BOUNDARIES.
        }
        $index = be014nIndex($nodes, $boundaries);
        // Read the deepest node before the root to verify bottom-up memoization.
        $last = $index->signature(12000);
        be014nAssert($last === be014nLegacySignature($nodes, $boundaries, 1, 12000),
            'Deepest scope differs from legacy');
        foreach ([1, 2, 17, 18, 118, 997, 6011, 11999, 12000] as $id) {
            be014nAssert($index->signature($id) === be014nLegacySignature($nodes, $boundaries, 1, $id),
                'Deep node signature mismatch: ' . $id);
        }
        $cache = (new ReflectionClass($index))->getProperty('resolvedSignatures')->getValue($index);
        be014nAssert(count($cache) === 12000, 'Deep tree was not cached once per node');
        be014nAssert($index->signature(12000) === $last, 'Memoized result changed');
    },
    'orphan and cycle remain fail closed even after caching valid paths' => static function (): void {
        $nodes = be014nTree(4, false);
        $nodes[5] = ['id' => 5, 'parent_id' => 999, 'type' => 'BRANCH', 'status' => 'ACTIVE'];
        $nodes[6] = ['id' => 6, 'parent_id' => 7, 'type' => 'BRANCH', 'status' => 'ACTIVE'];
        $nodes[7] = ['id' => 7, 'parent_id' => 6, 'type' => 'BRANCH', 'status' => 'ACTIVE'];
        $index = be014nIndex($nodes, [1 => true, 5 => true, 6 => true]);
        be014nAssert($index->signature(4) === '1', 'Valid node unexpected signature');
        foreach ([5, 6, 7] as $id) {
            be014nAssert($index->signature($id) === null
                && $index->signature($id) === be014nLegacySignature($nodes, [1 => true, 5 => true, 6 => true], 1, $id),
                'Invalid path was admitted: ' . $id);
        }
        be014nAssert($index->signature(2) === '1', 'Invalid branch poisoned valid cache');
    },
    'wrong root and disconnected tree remain rejected' => static function (): void {
        $nodes = be014nTree(3);
        $nodes[4] = ['id' => 4, 'parent_id' => null, 'type' => 'COMPANY', 'status' => 'ACTIVE'];
        $index = be014nIndex($nodes, [1 => true]);
        be014nAssert($index->signature(4) === null, 'Disconnected root was admitted');
        $wrongGroup = be014nIndex($nodes, [1 => true], 999);
        be014nAssert($wrongGroup->signature(1) === null && $wrongGroup->signature(3) === null,
            'Unmatched GROUP root was admitted');
    },
    'signature memo never escapes its index or changes exposed nodes' => static function (): void {
        $nodes = be014nTree(5);
        $first = be014nIndex($nodes, [1 => true]);
        $second = be014nIndex($nodes, [2 => true]);
        be014nAssert($first->signature(5) === '1' && $second->signature(5) === '2',
            'Indexes from different operation snapshots shared a signature');
        $exposed = $first->nodes();
        $exposed[5]['parent_id'] = 999;
        be014nAssert($first->signature(5) === '1' && $first->contains(5),
            'Caller changed an immutable index through nodes()');
        $property = (new ReflectionClass($first))->getProperty('resolvedSignatures');
        be014nAssert(! $property->isStatic(), 'Signature memo must not be service/request global');
    },
    'organization status and resource type remain separate compiler dimensions' => static function (): void {
        // Static guard: this optimization must not change PBAC grouping or SQL.
        $backend = dirname(__DIR__);
        $compiler = (string) file_get_contents($backend . '/app/Authorization/OwnerAwareListBucketCompiler.php');
        be014nAssert(str_contains($compiler, '$key = $signature . \':\' . bin2hex($row[\'status\'])')
            && str_contains($compiler, '$key = $bucket[\'resource\'] . \':\' . $signature . \':\' . bin2hex($bucket[\'status\'])'),
            'Scope-only signature must not collapse State or Resource grouping');
    },
    'original SQL sources, limits, UTC assignment and differential PBAC gate remain' => static function (): void {
        $backend = dirname(__DIR__);
        $index = (string) file_get_contents($backend . '/app/Authorization/EffectiveOrganizationScopeIndex.php');
        $suite = (string) file_get_contents($backend . '/tests/unit/P1Perf006OwnerAwareSqlPaginationTest.php');
        foreach ([
            'MAX_NODES = 12000', 'MAX_BOUNDARIES = 768', 'businessDate(new DateTimeZone(\'UTC\'))',
            "->table('organizations')", "->table('resources')", "->table('policies')",
            'return null; // orphan or cycle',
        ] as $invariant) {
            be014nAssert(str_contains($index, $invariant), 'Source invariant removed: ' . $invariant);
        }
        be014nAssert(str_contains($suite, 'testPbacScopeSignaturesAndSqlBucketsMatchOriginalEngine')
            && str_contains($suite, '$this->assertDifferential(\'contact\'')
            && str_contains($suite, '$this->assertDifferential(\'organization\''),
            'MariaDB/Shield PBAC differential test was removed');
    },
];

try {
    foreach ($checks as $label => $check) {
        $check();
        echo "  {$label}: PASS\n";
    }
    echo 'BE-REORG-014N SCOPE SIGNATURE PARITY PASS: ' . count($checks)
        . " checks; no DB connections, no SQL. MariaDB/Shield/HTTP NOT_RUN.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'BE-REORG-014N SCOPE SIGNATURE GATE FAILED: ' . $exception->getMessage() . "\n");
    exit(1);
}

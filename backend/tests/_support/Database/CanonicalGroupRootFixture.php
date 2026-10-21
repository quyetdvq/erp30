<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use CodeIgniter\Database\ConnectionInterface;
use RuntimeException;

/**
 * Reuse the single canonical GROUP root, if one is already present in the
 * transaction's baseline; otherwise create it with the caller's own fixture.
 *
 * The returned root is never deleted or mutated here. Every test remains
 * responsible for running within its existing NO-DROP rollback transaction.
 * Tests intentionally exercising duplicate roots must bypass this helper.
 */
final class CanonicalGroupRootFixture
{
    /**
     * @param ConnectionInterface<mixed, mixed> $db
     * @param callable(): int $create
     */
    public static function findOrCreate(ConnectionInterface $db, callable $create): int
    {
        $roots = $db->table('organizations')
            ->select('id')
            ->where('type', 'GROUP')
            ->where('parent_id', null)
            ->get()->getResultArray();

        if (count($roots) > 1) {
            throw new RuntimeException('NO-DROP fixture: multiple canonical GROUP roots.');
        }

        $id = $roots === [] ? $create() : (int) $roots[0]['id'];
        if ($id <= 0) {
            throw new RuntimeException('NO-DROP fixture: invalid canonical GROUP root ID.');
        }

        return $id;
    }
}

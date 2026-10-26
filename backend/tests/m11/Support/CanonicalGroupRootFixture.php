<?php

declare(strict_types=1);

namespace Tests\M11\Support;

use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/**
 * M11 counterpart of backend_baseline/tests/_support/Database/CanonicalGroupRootFixture.php.
 *
 * PBAC requires exactly ONE GROUP organization with NULL parent. Reuse the
 * canonical row in a populated erp_test database; create it only if absent.
 * Never modify/delete an existing root. The caller owns the rollback transaction.
 */
final class CanonicalGroupRootFixture
{
    /** @param callable():int|string $create */
    public static function findOrCreate(BaseConnection $db, callable $create): string
    {
        $result = $db->table('organizations')
            ->select('id')
            ->where('type', 'GROUP')
            ->where('parent_id', null)
            ->orderBy('id', 'ASC')
            ->get();
        if ($result === false) {
            throw new RuntimeException('M11 fixture cannot read canonical GROUP roots.');
        }

        return self::resolve($result->getResultArray(), $create);
    }

    /**
     * Pure decision, also covered by PHPUnit without a database connection.
     *
     * @param list<array<string,mixed>> $roots
     * @param callable():int|string $create
     */
    public static function resolve(array $roots, callable $create): string
    {
        if (count($roots) > 1) {
            throw new RuntimeException('M11 fixture: multiple canonical GROUP roots; refusing to change the test database.');
        }

        $id = $roots === [] ? $create() : ($roots[0]['id'] ?? null);
        if (! is_int($id) && ! is_string($id)) {
            throw new RuntimeException('M11 fixture: invalid canonical GROUP root ID.');
        }

        try {
            return PositiveId::value($id);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('M11 fixture: invalid canonical GROUP root ID.', 0, $e);
        }
    }
}

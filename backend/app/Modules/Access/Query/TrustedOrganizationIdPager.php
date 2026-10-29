<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use UnexpectedValueException;

/**
 * Trusted Organization metadata enumeration, keyset-paged and strictly bounded.
 * The callback MUST read the persisted organizations table with (id > last),
 * ORDER BY id ASC LIMIT size. This is NOT a permission shortcut: every returned
 * candidate still goes through the canonical actor/State/policy evaluation.
 */
final class TrustedOrganizationIdPager
{
    public const BATCH_SIZE = 256;
    public const MAX_ORGANIZATIONS = 4096;

    /** @param callable(?string,int):list<array<string,mixed>> $readPage @return list<string> */
    public static function collect(callable $readPage, int $limit = self::MAX_ORGANIZATIONS): array
    {
        if ($limit < 1 || $limit > self::MAX_ORGANIZATIONS) {
            throw new UnexpectedValueException('Unreviewed Organization metadata budget.');
        }
        $ids = [];
        $last = null;
        while (true) {
            // One extra row detects overflow without truncating an authorized scope.
            $size = min(self::BATCH_SIZE, $limit - count($ids) + 1);
            $rows = $readPage($last, $size);
            if (!is_array($rows) || !array_is_list($rows) || count($rows) > $size) {
                throw new UnexpectedValueException('Invalid Organization metadata page.');
            }
            if ($rows === []) return $ids;
            foreach ($rows as $row) {
                $id = is_array($row) ? (string)($row['id'] ?? '') : '';
                if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
                    || ($last !== null && self::compare($id, $last) <= 0)) {
                    throw new UnexpectedValueException('Invalid or non-increasing Organization metadata ID.');
                }
                if (count($ids) === $limit) {
                    throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: Organization metadata budget exceeded.');
                }
                $ids[] = $id;
                $last = $id;
            }
            if (count($rows) < $size) return $ids;
        }
    }

    /** Numeric BIGINT string order; no int cast/overflow, IDs must be canonical. */
    private static function compare(string $a, string $b): int
    {
        return (strlen($a) <=> strlen($b)) ?: strcmp($a, $b);
    }
}

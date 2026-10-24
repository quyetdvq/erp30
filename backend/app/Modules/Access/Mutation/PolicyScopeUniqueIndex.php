<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use UnexpectedValueException;

/**
 * Read-only schema check for the five-dimensional UNIQUE constraint created
 * by baseline migration EnforcePolicyScopeUniqueness.
 *
 * The generated normalized_state_id column makes NULL (All States) scopes
 * unique as well. Never replace this DB constraint with only a PHP pre-check.
 */
final class PolicyScopeUniqueIndex
{
    public const NAME = 'uq_policies_scope';

    public const SQL = <<<'SQL'
SELECT SEQ_IN_INDEX AS seq_in_index,
       COLUMN_NAME AS column_name,
       NON_UNIQUE AS non_unique,
       SUB_PART AS sub_part
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = ?
  AND INDEX_NAME = ?
ORDER BY SEQ_IN_INDEX ASC
SQL;

    private const COLUMNS = [
        'organization_id',
        'resource_id',
        'normalized_state_id',
        'participant_type',
        'participant_ref_id',
    ];

    /**
     * @param list<array<string,mixed>> $rows Rows returned by SQL above.
     */
    public static function assertValid(array $rows): void
    {
        if (count($rows) !== count(self::COLUMNS)) {
            throw new UnexpectedValueException(
                'Missing/invalid ' . self::NAME . ' UNIQUE key: expected 5 columns, found '
                . count($rows) . '. Do not enable Policy mutations.',
            );
        }

        foreach ($rows as $index => $row) {
            $expectedPosition = $index + 1;
            if ((string) ($row['seq_in_index'] ?? '') !== (string) $expectedPosition
                || ($row['column_name'] ?? null) !== self::COLUMNS[$index]
                || (string) ($row['non_unique'] ?? '') !== '0'
                || ($row['sub_part'] ?? null) !== null) {
                throw new UnexpectedValueException(
                    'Missing/invalid ' . self::NAME . ' UNIQUE key at column '
                    . $expectedPosition . ' (' . self::COLUMNS[$index]
                    . '); expected full-column UNIQUE index in baseline order. '
                    . 'Do not enable Policy mutations.',
                );
            }
        }
    }
}

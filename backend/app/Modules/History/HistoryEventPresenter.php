<?php

declare(strict_types=1);

namespace App\Modules\History;

use UnexpectedValueException;

/** History wire payload: unambiguous UTC timestamp, current Shield user identity. */
final class HistoryEventPresenter
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function present(array $row): array
    {
        $rawTime = $row['created_at'] ?? null;
        if (!is_string($rawTime) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/D', $rawTime) !== 1) {
            throw new UnexpectedValueException('HISTORY_INVALID_UTC_INSTANT');
        }
        // HistoryRecorder always stores gmdate() in UTC; never infer server locale.
        $row['created_at'] = str_replace(' ', 'T', $rawTime) . 'Z';
        $row['id'] = (string) $row['id'];
        $row['resource_id'] = (string) $row['resource_id'];
        $row['actor_user_id'] = $row['actor_user_id'] === null ? null : (string) $row['actor_user_id'];
        foreach (['actor_username','actor_full_name','resource_code','resource_name'] as $name) {
            $value = $row[$name] ?? null;
            $row[$name] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        // NEW-ONLY HIS-POL-002 reader. Legacy {before,after},
        // {before_count,after_count} and {changed:true} are NOT accepted.
        // DEV old rows must be explicitly purged before upgrading.
        $row['changes'] = self::compactChanges($row['changes_json'] ?? null);
        $row['checkpoint'] = $row['checkpoint_json'] === null ? null
            : json_decode((string) $row['checkpoint_json'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['changes_json'], $row['checkpoint_json']);
        return $row;
    }

    /** @return array<string, array<int,scalar|null>|\stdClass>|null */
    private static function compactChanges(mixed $raw): ?array
    {
        if ($raw === null) return null;
        $decoded = json_decode((string) $raw, false, 64, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) {
            throw new UnexpectedValueException('HISTORY_COMPACT_CHANGES_INVALID');
        }
        $changes = [];
        foreach (get_object_vars($decoded) as $key => $value) {
            if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', (string) $key) !== 1) {
                throw new UnexpectedValueException('HISTORY_COMPACT_CHANGES_INVALID');
            }
            if ($value instanceof \stdClass && get_object_vars($value) === []) {
                $changes[$key] = new \stdClass();
            } elseif (is_array($value) && count($value) === 2 && array_is_list($value)
                && (is_scalar($value[0]) || $value[0] === null)
                && (is_scalar($value[1]) || $value[1] === null)) {
                $changes[$key] = $value;
            } else {
                // Legacy payloads and malformed tuples fail closed. Do not
                // reinterpret an old format's keys as user-facing field values.
                throw new UnexpectedValueException('HISTORY_COMPACT_CHANGES_INVALID');
            }
        }
        if (count($changes) > 100) {
            throw new UnexpectedValueException('HISTORY_COMPACT_CHANGES_INVALID');
        }
        return $changes;
    }
}

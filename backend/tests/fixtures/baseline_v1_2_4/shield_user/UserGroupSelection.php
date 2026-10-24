<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Exceptions\DomainException;

/** Validate exact canonical group IDs, without coercion or duplicates. */
final class UserGroupSelection
{
    /** @return list<string> */
    public static function normalize(mixed $ids): array
    {
        if (! is_array($ids) || ! array_is_list($ids)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'group_ids']);
        }
        $seen = [];
        $normalized = [];
        foreach ($ids as $id) {
            if (! is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
                || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                || isset($seen[$id])) {
                throw new DomainException('VALIDATION_ERROR', ['field' => 'group_ids']);
            }
            $seen[$id] = true;
            $normalized[] = $id;
        }
        return $normalized;
    }
}

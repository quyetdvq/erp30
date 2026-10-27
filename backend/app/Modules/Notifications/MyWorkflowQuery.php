<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;

/** Strict input for an own-user, read-only Workflow action-candidates view. */
final class MyWorkflowQuery
{
    /** @param array<string,mixed> $query @return array{resource_type:string,page_size:int,cursor:?string} */
    public static function parse(array $query): array
    {
        foreach (array_keys($query) as $key) {
            if (! in_array($key, ['resource_type', 'page_size', 'cursor'], true)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }
        // A missing optional key may use its default; an explicitly null key is invalid.
        // Null coalescing (??) would silently accept ?resource_type with a null value.
        $type = array_key_exists('resource_type', $query) ? $query['resource_type'] : 'all';
        $size = array_key_exists('page_size', $query) ? $query['page_size'] : '10';
        $cursorProvided = array_key_exists('cursor', $query);
        $cursor = $cursorProvided ? $query['cursor'] : null;
        if (! is_string($type) || ! is_string($size)
            || preg_match('/\A(?:[1-9]|1[0-9]|20)\z/D', $size) !== 1
            || ($cursorProvided && (! is_string($cursor) || $cursor === '' || strlen($cursor) > 1024))) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        if ($type !== 'all') {
            try {
                BusinessWorkflowRules::table($type);
            } catch (DomainException) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }
        return ['resource_type' => $type, 'page_size' => (int) $size, 'cursor' => $cursor];
    }
}

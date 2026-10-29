<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/**
 * Bounds browser-initiated Outbox processing to events addressed to the
 * authenticated Shield User. The cron Dispatcher keeps its global scope.
 *
 * All recipient values are SQL parameters, never interpolated or inferred
 * from the browser request body. Producer snapshots remain immutable.
 */
final class NotificationOnDemandScope
{
    /** @return array{sql:string,binds:list<string>} */
    public static function forUser(string $userId, string $outbox, string $jobs): array
    {
        if (! preg_match('/\A[1-9][0-9]{0,9}\z/D', $userId)) {
            throw new \InvalidArgumentException('M11_SYNC_INVALID_USER');
        }
        // Both identifiers are generated via BaseConnection::prefixTable and
        // quoted by NotificationDispatcher, never supplied by HTTP clients.
        foreach ([$outbox, $jobs] as $table) {
            if (! preg_match('/\A`[A-Za-z_][A-Za-z0-9_]*`\z/D', $table)) {
                throw new \InvalidArgumentException('M11_SYNC_INVALID_TABLE');
            }
        }
        $sql = " AND created_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 7 DAY) AND ("
            . "(source_type='import_job' AND event_type IN "
            . "('import.completed','import.completed_with_errors','import.failed')"
            . " AND EXISTS (SELECT 1 FROM {$jobs} j WHERE j.id={$outbox}.source_id AND j.created_by=?))"
            . " OR (source_type='workflow_transition' AND event_type='workflow.action_required'"
            . " AND JSON_CONTAINS(payload_json,JSON_QUOTE(?),'$.recipient_user_ids')=1)"
            . ')';
        return ['sql' => $sql, 'binds' => [$userId, $userId]];
    }
}

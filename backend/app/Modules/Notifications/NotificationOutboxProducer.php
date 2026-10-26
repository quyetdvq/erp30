<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use RuntimeException;

/** Transactional event producers; must execute inside each owning business write transaction. */
final class NotificationOutboxProducer
{
    public static function enqueueImportTerminal(BaseConnection $db, string $id, string $terminal): void
    {
        if (! NotificationFlags::producerEnabled($db)) {
            return;
        }
        $type = match ($terminal) {
            'COMPLETED' => 'import.completed',
            'COMPLETED_WITH_ERRORS' => 'import.completed_with_errors',
            'FAILED' => 'import.failed',
            default => throw new RuntimeException('M11_IMPORT_INVALID_TERMINAL'),
        };
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            throw new RuntimeException('M11_IMPORT_INVALID_ID');
        }
        // Never take a recipient from the HTTP request or arbitrary JSON payload.
        // The dispatcher resolves ImportJob.created_by from the database.
        $ok = $db->table('notification_outbox')
            ->set('available_at', 'UTC_TIMESTAMP(6)', false)
            ->set('created_at', 'UTC_TIMESTAMP(6)', false)
            ->insert([
                'event_key' => 'import-job:' . $id . ':terminal',
                'event_type' => $type,
                'source_type' => 'import_job',
                'source_id' => $id,
                'payload_json' => '{"schema":1}',
                'status' => 'PENDING',
                'attempts' => 0,
            ]);
        if (! $ok) {
            throw new RuntimeException('M11_OUTBOX_WRITE_FAILED');
        }
    }

    /** Called only after the successful target-state UPDATE, before the owning COMMIT. */
    public static function enqueueWorkflowTransition(
        BaseConnection $db, string $resource, string $id, string $from, string $to, string $action,
    ): void {
        if (! NotificationFlags::workflowProducerEnabled($db)) {
            return;
        }
        BusinessWorkflowRules::table($resource);
        BusinessWorkflowRules::id($id);
        $definition = (new ResourceStateRepository($db))->definitionFor($resource);
        $toStateId = $definition['by_code'][$to] ?? null;
        $lifecycle = $definition['lifecycle_id'] ?? null;
        if ($toStateId === null || $lifecycle === null) {
            throw new RuntimeException('M11_WORKFLOW_DESTINATION_NOT_MAPPED');
        }
        // A state with no active outgoing transition is terminal: no action is needed.
        $outgoing = $db->table('lifecycle_transitions')->where('lifecycle_id', $lifecycle)
            ->where('from_state_id', $toStateId)->where('status', 'ACTIVE')->countAllResults();
        if ($outgoing === 0) {
            return;
        }
        // The business row is locked by the caller. A concurrent assignment PUT
        // locks the same row first, so this is a transactionally stable snapshot.
        $assigned = $db->table('workflow_notification_assignees')->select('recipient_user_id')
            ->where('resource_type', $resource)->where('resource_id', $id)
            ->orderBy('recipient_user_id', 'ASC')->get()->getResultArray();
        if ($assigned === []) {
            return;
        }
        if (count($assigned) > WorkflowNotificationRules::MAX_ASSIGNEES) {
            throw new RuntimeException('M11_WORKFLOW_RECIPIENT_LIMIT_EXCEEDED');
        }
        $ids = array_map(static fn (array $row): string => (string) $row['recipient_user_id'], $assigned);
        usort($ids, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        $json = WorkflowNotificationRules::eventPayload($resource, $from, $to, $action, $ids);
        $key = 'workflow:' . $resource . ':' . $id . ':' . bin2hex(random_bytes(16));
        $ok = $db->table('notification_outbox')->set('available_at', 'UTC_TIMESTAMP(6)', false)
            ->set('created_at', 'UTC_TIMESTAMP(6)', false)
            ->insert(['event_key' => $key, 'event_type' => 'workflow.action_required',
                'source_type' => 'workflow_transition', 'source_id' => $id,
                'payload_json' => $json, 'status' => 'PENDING', 'attempts' => 0]);
        if (! $ok) {
            throw new RuntimeException('M11_WORKFLOW_OUTBOX_WRITE_FAILED');
        }
    }
}

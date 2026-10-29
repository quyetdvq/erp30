<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/** Minimal non-PII event facts for opted-in lock-screen notifications. */
final class NotificationPushPresentation
{
    /** @param array<string,mixed> $event @param array<string,mixed>|null $job */
    public static function payload(array $event, ?array $job = null): string
    {
        $kind = (string) ($event['event_type'] ?? '');
        $id = (string) ($event['source_id'] ?? '');
        if (! preg_match('/\A[1-9][0-9]{0,19}\z/D', $id)) {
            throw new RuntimeException('M11_PUSH_PRESENTATION_INVALID_ID');
        }
        if (in_array($kind, ['import.completed', 'import.completed_with_errors', 'import.failed'], true)
            && ($event['source_type'] ?? '') === 'import_job' && $job !== null) {
            return json_encode(['v' => 1, 'event' => $kind, 'id' => $id,
                'success' => min(999999, max(0, (int) ($job['success_rows'] ?? 0))),
                'errors' => min(999999, max(0, (int) ($job['error_rows'] ?? 0)))], JSON_THROW_ON_ERROR);
        }
        if ($kind === 'workflow.action_required' && ($event['source_type'] ?? '') === 'workflow_transition') {
            $data = json_decode((string) ($event['payload_json'] ?? ''), true);
            $resource = is_array($data) ? ($data['resource_type'] ?? null) : null;
            $state = is_array($data) ? ($data['to_state'] ?? null) : null;
            // Only known codes from the immutable Workflow taxonomy; no user-supplied text.
            if (is_string($resource) && preg_match('/\A[a-z][a-z0-9_]{0,40}\z/D', $resource)
                && is_string($state) && preg_match('/\A[A-Z][A-Z0-9_]{0,39}\z/D', $state)) {
                return json_encode(['v' => 1, 'event' => $kind, 'id' => $id,
                    'resource' => $resource, 'state' => $state], JSON_THROW_ON_ERROR);
            }
        }
        throw new RuntimeException('M11_PUSH_PRESENTATION_UNSUPPORTED_EVENT');
    }
}

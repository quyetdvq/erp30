<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use Throwable;

/** Exact projection of the historical action-required notification (not a task record). */
final class WorkflowInboxProjector implements NotificationInboxProjector
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public function project(array $row): array
    {
        $type = (string) $row['type'];
        if ($type !== 'workflow.action_required') {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $params = json_decode((string) $row['params_json'], true);
        if (! is_array($params) || array_keys($params) !== ['resource_type', 'state_code']
            || ! is_string($params['resource_type']) || ! is_string($params['state_code'])
            || $params['state_code'] === '' || strlen($params['state_code']) > 50
            || (string) $row['target_type'] !== 'workflow_target'
            || (string) $row['title_key'] !== 'notifications.workflow.actionRequired.title'
            || (string) $row['body_key'] !== 'notifications.workflow.actionRequired.body'
            || ! NotificationRules::version((string) $row['target_id'])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        try {
            BusinessWorkflowRules::table($params['resource_type']);
        } catch (Throwable) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return NotificationInboxProjectionFields::envelope($row, $type, $params, [
            'type' => 'workflow_target', 'resource_type' => $params['resource_type'],
            'id' => (string) $row['target_id'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationPermanentFailure;
use App\Modules\Notifications\NotificationPolicyResolver;
use App\Modules\Notifications\WorkflowNotificationRules;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use App\Modules\Workflow\Execution\BusinessWorkflowService;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/** Existing Workflow fanout with target lock and live PBAC/action recheck.
 * Called only inside the Dispatcher-owned Outbox ACK transaction.
 */
final class WorkflowActionRequiredHandler extends AbstractNotificationEventHandler
{
    /** @param array<string,mixed> $event */
    public function deliver(array $event): NotificationDeliveryResult
    {
        $id = (string) $event['source_id'];
        if ((string) $event['source_type'] !== 'workflow_transition'
            || (string) $event['event_type'] !== 'workflow.action_required') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
        }
        try {
            BusinessWorkflowRules::id($id);
            $payload = WorkflowNotificationRules::decode((string) $event['payload_json']);
            $resource = $payload['resource_type'];
            $table = $this->table(BusinessWorkflowRules::table($resource));
        } catch (Throwable $e) {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT', 0, $e);
        }
        if (preg_match('/\Aworkflow:' . preg_quote($resource, '/') . ':'
                . preg_quote($id, '/') . ':[a-f0-9]{32}\z/D', (string) $event['event_key']) !== 1) {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT');
        }
        // Lock target once for the entire delivery, so status and PBAC decisions
        // cannot race a subsequent Workflow transition.
        $target = $this->select("SELECT * FROM {$table} WHERE id=? FOR UPDATE", [$id])->getRowArray();
        if ($target === null) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_NOT_FOUND');
        }
        if (str_ends_with($resource, '_contact') && strtoupper((string) ($target['contact_type'] ?? ''))
            !== ($resource === 'organization_contact' ? 'ORGANIZATION' : 'EMPLOYEE')) {
            throw new NotificationPermanentFailure('NOTIFICATION_SOURCE_NOT_FOUND');
        }
        if (strtoupper((string) $target['status']) !== $payload['to_state']) {
            return NotificationDeliveryResult::suppressed('NOTIFICATION_WORKFLOW_STATE_CHANGED');
        }
        $key = 'workflow.action_required.in_app';
        $definition = NotificationCatalog::definitions()[$key] ?? null;
        if ($definition === null || $definition['capability'] !== 'AVAILABLE') {
            throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT_TYPE');
        }
        $ids = $payload['recipient_user_ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // One SELECT yields the SAME SYSTEM+USER policy snapshot for the entire
        // fanout at READ-COMMITTED, even when an admin edits policy concurrently.
        $rows = $this->select("SELECT u.id AS recipient_user_id,s.*,"
            . 'p.override_value AS preference_value,p.override_epoch AS preference_epoch,'
            . 'p.user_revision AS preference_revision'
            . " FROM {$this->settings} s JOIN {$this->users} u ON u.id IN ({$placeholders})"
            . " LEFT JOIN {$this->preferences} p ON p.setting_key=s.setting_key AND p.user_id=u.id"
            . ' WHERE s.setting_key=?', [...$ids, $key])->getResultArray();
        if ($rows === []) {
            throw new NotificationPermanentFailure('NOTIFICATION_POLICY_MISSING');
        }
        $policies = [];
        foreach ($rows as $row) {
            $policies[(string) $row['recipient_user_id']] = $row;
        }
        $inserted = 0;
        foreach ($ids as $userId) {
            if (! isset($policies[$userId])) {
                throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
            }
            $policy = $policies[$userId];
            $userRow = $this->select("SELECT * FROM {$this->users} WHERE id=? FOR UPDATE", [$userId])->getRowArray();
            if ($userRow === null) {
                throw new NotificationPermanentFailure('NOTIFICATION_USER_NOT_FOUND');
            }
            if (! WorkflowNotificationRules::activeUser($userRow)) {
                continue;
            }
            $pref = $policy['preference_revision'] === null ? null : [
                'override_value' => $policy['preference_value'],
                'override_epoch' => $policy['preference_epoch'],
                'user_revision' => $policy['preference_revision'],
            ];
            try {
                $effective = NotificationPolicyResolver::preferenceView($key, $policy, $definition, $pref);
            } catch (Throwable $e) {
                throw new NotificationPermanentFailure('NOTIFICATION_POLICY_INVALID', 0, $e);
            }
            if (! $effective['effective_value']) {
                continue;
            }
            // Explicit assignment is necessary, but never sufficient: recheck
            // the target's CURRENT per-user VIEW and executable next actions.
            try {
                $options = (new BusinessWorkflowService(new User($userRow), $this->db))
                    ->options($resource, $id);
            } catch (DomainException $e) {
                if ($e->getErrorCode() === 'AUTH_POLICY_DENIED') {
                    continue;
                }
                throw $e;
            }
            if ($options['options'] === []) {
                continue;
            }
            $params = json_encode(['resource_type' => $resource, 'state_code' => $payload['to_state']], JSON_THROW_ON_ERROR);
            $this->exec("INSERT INTO {$this->inbox}"
                . ' (recipient_user_id,event_key,type,priority,title_key,body_key,params_json,target_type,target_id,created_at)'
                . ' VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=id',
                [$userId, (string) $event['event_key'], 'workflow.action_required', 'NORMAL',
                    'notifications.workflow.actionRequired.title', 'notifications.workflow.actionRequired.body',
                    $params, 'workflow_target', $id]);
            ++$inserted;
        }
        return $inserted > 0
            ? NotificationDeliveryResult::processed()
            : NotificationDeliveryResult::suppressed('NOTIFICATION_NO_ELIGIBLE_ASSIGNEE');
    }

}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * Snapshot of *actionable workflow notification recipients*, not a task/ownership ledger.
 * No writes, no read_at dependency, no global permission broadcast and no raw record data.
 */
final class MyWorkflowService
{
    // Each page probes at most this many own assignments for live Workflow/PBAC options.
    public const SCAN_BUDGET = 80;
    private readonly BaseConnection $db;
    private readonly User $actor;
    private readonly string $userId;
    private readonly string $table;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        if (! WorkflowNotificationRules::activeUser([
            'active' => $actor->active, 'deleted_at' => $actor->deleted_at, 'status' => $actor->status,
        ])) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $this->userId = (string) $actor->id;
        if (NotificationDeliveryRules::shieldUserId($this->userId) !== $this->userId) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $this->db = $db ?? db_connect();
        $this->actor = $actor;
        $name = $this->db->prefixTable('workflow_notification_assignees');
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $this->table = '`' . $name . '`';
    }

    /** @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,next_cursor:?string,execution_enabled:bool}
     */
    public function listing(array $query): array
    {
        $input = MyWorkflowQuery::parse($query);
        $type = $input['resource_type'];
        $size = $input['page_size'];
        $cursor = $input['cursor'];
        $secret = (string) env('DINKERP_M11_CURSOR_SECRET', '');
        if (strlen($secret) < 32) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        $position = $cursor === null ? null : MyWorkflowCursor::decode(
            $cursor, $this->userId, $type, $size, $secret,
        );
        // No "actionable" claim when the existing Workflow mutation gate is OFF.
        $executionEnabled = strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) === 'enabled'
            || NotificationFlags::workflowWriteEnabled($this->db);
        if (! $executionEnabled) {
            return ['items' => [], 'next_cursor' => null, 'execution_enabled' => false];
        }

        $where = 'recipient_user_id=?';
        $binds = [$this->userId];
        if ($type !== 'all') {
            $where .= ' AND resource_type=?';
            $binds[] = $type;
        }
        if ($position !== null) {
            // Stable total ordering (descending time, ascending type, descending numeric ID).
            $where .= ' AND (created_at<? OR (created_at=? AND (resource_type>?'
                . ' OR (resource_type=? AND resource_id<?))))';
            array_push($binds, $position['created_at'], $position['created_at'],
                $position['resource_type'], $position['resource_type'], $position['resource_id']);
        }
        $result = $this->db->query("SELECT resource_type,resource_id,created_at FROM {$this->table}"
            . " WHERE {$where} ORDER BY created_at DESC,resource_type ASC,resource_id DESC LIMIT "
            . (self::SCAN_BUDGET + 1), $binds);
        if ($result === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $candidates = $result->getResultArray();
        // UnifiedAccessService snapshots PBAC grants when BusinessWorkflowService is
        // constructed. Rebuild this evaluator for EACH listing() call so a
        // subsequent read observes permission revocation/grants made since the
        // previous call, even when the MyWorkflowService instance is reused.
        // Construct once per listing, not once per candidate (bounded N+1).
        $workflow = new BusinessWorkflowService($this->actor, $this->db);
        $items = [];
        $scanned = 0;
        $last = null;
        foreach ($candidates as $row) {
            if ($scanned === self::SCAN_BUDGET || count($items) >= $size) {
                break;
            }
            ++$scanned;
            $last = $row; // Advance on *examined candidate*, including unauthorized ones.
            try {
                $details = $workflow->options((string) $row['resource_type'], (string) $row['resource_id']);
            } catch (DomainException $e) {
                // The actor cannot see this target, or it no longer has a usable adapter/state.
                // Do NOT return the reason, even as an aggregate or a count.
                if (! in_array($e->getErrorCode(), [
                    'AUTH_POLICY_DENIED', 'WF_TARGET_NOT_FOUND', 'WF_TARGET_ADAPTER_UNAVAILABLE',
                    'WF_TRANSITION_REQUIRED', 'WF_STATE_NOT_MAPPED',
                ], true)) {
                    throw $e;
                }
                continue;
            }
            if ($details['options'] === []) {
                continue;
            }
            $items[] = [
                'resource_type' => (string) $row['resource_type'],
                'resource_id' => (string) $row['resource_id'],
                'current_state' => (string) $details['current'],
                'available_actions' => $details['options'],
                'selected_at' => str_replace(' ', 'T', (string) $row['created_at']) . 'Z',
            ];
        }
        $more = $last !== null && count($candidates) > $scanned;
        $next = $more ? MyWorkflowCursor::encode($this->userId, $type, $size,
            (string) $last['created_at'], (string) $last['resource_type'],
            (string) $last['resource_id'], $secret) : null;
        return ['items' => $items, 'next_cursor' => $next, 'execution_enabled' => true];
    }
}

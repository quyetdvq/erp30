<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use App\Modules\Workflow\Execution\BusinessWorkflowContactScope;
use App\Shared\Http\RequestMetadata;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** Explicit, CAS-protected per-record Workflow assignment; never inferred from global VIEW grants. */
final class WorkflowNotificationAssigneesService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        if (! WorkflowNotificationRules::activeUser(['active' => $actor->active,
            'deleted_at' => $actor->deleted_at, 'status' => $actor->status])) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $this->actorId = (string) $actor->id;
        if (NotificationDeliveryRules::shieldUserId($this->actorId) !== $this->actorId) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
    }

    /** @return array{revision:string,user_ids:list<string>} */
    public function listing(string $resource, string $id): array
    {
        $row = $this->target($resource, $id, false);
        $this->authorize($resource, $row);
        return $this->current($resource, $id, false);
    }

    /**
     * Search active Users for an already-authorized per-record assignment.
     * This is NOT a permission grant or a promise that a User can approve;
     * effective next-action PBAC is checked by the notification dispatcher.
     *
     * @param array<string,mixed> $query
     * @return array{data:list<array{id:string,username:string,name:?string}>,page:int,page_size:int,total:int}
     */
    public function candidates(string $resource, string $id, array $query): array
    {
        $row = $this->target($resource, $id, false);
        $this->authorize($resource, $row);
        $params = WorkflowAssigneeCandidateQuery::parse($query);
        $builder = $this->db->table('users')
            ->where('active', 1)->where('deleted_at', null)
            ->where("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('inactive','locked','suspended','banned','disabled')", null, false);
        if ($params['ids'] !== []) {
            $builder->whereIn('id', $params['ids']);
        } elseif ($params['q'] !== '') {
            // CI Query Builder escapes LIKE wildcards and binds user input.
            $builder->groupStart()->like('username', $params['q'])
                ->orLike('full_name', $params['q'])->groupEnd();
        }
        $total = (clone $builder)->countAllResults();
        $rows = $builder->select('id,username,full_name')
            ->orderBy('username', 'ASC')->orderBy('id', 'ASC')
            ->limit($params['page_size'], ($params['page'] - 1) * $params['page_size'])
            ->get()->getResultArray();
        return [
            'data' => array_map(static fn (array $user): array => [
                'id' => (string) $user['id'],
                'username' => (string) $user['username'],
                'name' => $user['full_name'] === null ? null : (string) $user['full_name'],
            ], $rows),
            'page' => $params['page'],
            'page_size' => $params['page_size'],
            'total' => (int) $total,
        ];
    }

    /** @param array<string,mixed> $payload @return array{revision:string,user_ids:list<string>} */
    public function replace(string $resource, string $id, array $payload): array
    {
        $desired = WorkflowNotificationRules::replacement($payload);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_TRANSACTION_FAILED');
        }
        try {
            // Serialize with BusinessWorkflowService::execute() and competing PUTs.
            // Both lock the business row FIRST, before assignment-set/recipient rows.
            $row = $this->target($resource, $id, true);
            $this->authorize($resource, $row);
            $current = $this->current($resource, $id, true);
            if ($current['revision'] !== $desired['expected_revision']) {
                throw new DomainException('NOTIFICATION_REVISION_CONFLICT');
            }
            if ($current['user_ids'] === $desired['user_ids']) {
                $this->commit();
                return $current;
            }
            $this->validateRecipients($desired['user_ids']);
            $sets = $this->db->table('workflow_notification_assignment_sets');
            if ($current['revision'] === '0') {
                $ok = $sets->insert(['resource_type' => $resource, 'resource_id' => $id,
                    'revision' => '1', 'updated_by' => $this->actorId,
                    'updated_at' => gmdate('Y-m-d H:i:s')]);
            } else {
                $ok = $sets->where('resource_type', $resource)->where('resource_id', $id)
                    ->set('revision', 'revision + 1', false)->set('updated_by', $this->actorId)
                    ->set('updated_at', 'UTC_TIMESTAMP(6)', false)->update();
            }
            if (! $ok || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_WRITE_FAILED');
            }
            if (! $this->db->table('workflow_notification_assignees')
                ->where('resource_type', $resource)->where('resource_id', $id)->delete()) {
                throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_DELETE_FAILED');
            }
            foreach ($desired['user_ids'] as $userId) {
                if (! $this->db->table('workflow_notification_assignees')->insert([
                    'resource_type' => $resource, 'resource_id' => $id,
                    'recipient_user_id' => $userId,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_INSERT_FAILED');
                }
            }
            $result = $this->current($resource, $id, false);
            if (! $this->db->table('configuration_audit_events')->insert([
                'configuration_type' => 'WORKFLOW_NOTIFICATION_ASSIGNMENT',
                'context_type' => 'RESOURCE', 'organization_id' => null,
                'setting_key' => 'workflow:' . $resource . ':' . $id,
                'operation' => 'SET',
                'old_value_json' => json_encode($current, JSON_THROW_ON_ERROR),
                'new_value_json' => json_encode($result, JSON_THROW_ON_ERROR),
                'actor_user_id' => $this->actorId,
                'request_id' => RequestMetadata::id(),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_AUDIT_FAILED');
            }
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function target(string $resource, string $id, bool $lock): array
    {
        // M11 may read/lock Supplier without granting a generic Workflow write adapter.
        $supplier = $resource === 'supplier';
        $table = $supplier ? SupplierNotificationTarget::TABLE : BusinessWorkflowRules::table($resource);
        $id = BusinessWorkflowRules::id($id);
        $builder = $this->db->table($table)->where('id', $id);
        if ($supplier) {
            // This restriction precedes even the row lock and PBAC evaluation.
            $builder->select('id,group_organization_id,status,row_version')
                ->where('group_organization_id', $this->access->trustedGroupRootId());
        }
        $result = $lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get();
        if ($result === false) {
            throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_TARGET_READ_FAILED');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        if (!BusinessWorkflowRules::matchesTargetOwner($resource, $row)) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        if ($resource === 'customer_contact') {
            $parent = $this->db->table('customers')->where('id', $row['contact_ref_id'])->get()->getRowArray();
            if ($parent === null) {
                throw new DomainException('WF_TARGET_NOT_FOUND');
            }
            if (!$this->access->canView('customer', $parent)) {
                throw new DomainException('AUTH_POLICY_DENIED');
            }
        }
        if ($resource === 'supplier_contact') {
            $parentQuery = $this->db->table('suppliers')->select('id,group_organization_id,status')
                ->where('id', $row['contact_ref_id'])->get();
            $parent = $parentQuery === false ? null : $parentQuery->getRowArray();
            if (!\App\Modules\Contact\SupplierContactParentView::allowed($this->access, $parent)) {
                throw new DomainException('WF_TARGET_NOT_FOUND');
            }
        }
        // Workflow OPTIONS and M11 assignee mutations must evaluate exactly
        // the same trusted Contact scope; the contacts table has no org ID.
        return BusinessWorkflowContactScope::fromPersisted($resource, $row, $this->db);
    }

    /** @param array<string,mixed> $row */
    private function authorize(string $resource, array $row): void
    {
        if (! $this->access->canView($resource, $row)
            || ! $this->access->decideMutation($resource, 'setstate', $row)['allowed']) {
            throw new DomainException('AUTH_POLICY_DENIED');
        }
    }

    /** @return array{revision:string,user_ids:list<string>} */
    private function current(string $resource, string $id, bool $lock): array
    {
        $builder = $this->db->table('workflow_notification_assignment_sets')
            ->where('resource_type', $resource)->where('resource_id', $id);
        $result = $lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get();
        if ($result === false) {
            throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_READ_FAILED');
        }
        $row = $result->getRowArray();
        $members = $this->db->table('workflow_notification_assignees')
            ->select('recipient_user_id')->where('resource_type', $resource)->where('resource_id', $id)
            ->orderBy('recipient_user_id', 'ASC')->get()->getResultArray();
        $ids = array_map(static fn (array $item): string => (string) $item['recipient_user_id'], $members);
        usort($ids, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        return ['revision' => $row === null ? '0' : (string) $row['revision'], 'user_ids' => $ids];
    }

    /** @param list<string> $ids */
    private function validateRecipients(array $ids): void
    {
        foreach ($ids as $id) {
            $row = $this->db->table('users')->select('id,active,deleted_at,status')->where('id', $id)->get()->getRowArray();
            if ($row === null || ! WorkflowNotificationRules::activeUser($row)) {
                throw new DomainException('NOTIFICATION_RECIPIENT_INVALID', ['field' => 'user_ids']);
            }
        }
    }

    private function commit(): void
    {
        if (! $this->db->transStatus() || ! $this->db->transCommit()) {
            throw new RuntimeException('M11_WORKFLOW_ASSIGNMENT_COMMIT_FAILED');
        }
    }
}

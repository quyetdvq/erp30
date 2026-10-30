<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/**
 * M11 read/notification adapter ONLY. Supplier must never enter the generic
 * BusinessWorkflowRules::table() / execute() route (which has no row-version CAS).
 * Callers own their transaction: neither a nested transaction nor a write here.
 */
final class SupplierNotificationTarget
{
    public const RESOURCE = 'supplier';
    public const TABLE = 'suppliers';

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>,record_display:array{name:?string,code:?string}} */
    public static function options(User $actor, BaseConnection $db, string $id, ?array $lockedRow = null): array
    {
        $id = BusinessWorkflowRules::id($id);
        $access = new UnifiedAccessService($actor, $db);
        $group = $access->trustedGroupRootId();
        // Persisted GROUP root always limits the SQL read, even for Users who
        // have a base supplier.view permission. Company Usage is NOT a grant.
        $row = $lockedRow;
        if ($row === null) {
            $result = $db->table(self::TABLE)
                ->select('id,group_organization_id,supplier_code,name,status,row_version')
                ->where('id', $id)->where('group_organization_id', $group)->get();
            $row = $result === false ? null : $result->getRowArray();
        }
        if ($row === null || (string) ($row['id'] ?? '') !== $id
            || (string) ($row['group_organization_id'] ?? '') !== $group) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        if (! $access->canView(self::RESOURCE, $row)) {
            throw new DomainException('AUTH_POLICY_DENIED');
        }
        $options = [];
        if ($access->decideMutation(self::RESOURCE, 'setstate', $row)['allowed']) {
            try {
                if ($access->decideTransitionAction(self::RESOURCE, 'setstate', $row)['allowed']) {
                    $to = (new ResourceStateRepository($db))->transitionTarget(self::RESOURCE, $row, 'setstate');
                    if ($to !== (string) $row['status']) {
                        $options[] = ['action_code' => 'setstate', 'status' => $to];
                    }
                }
            } catch (UnexpectedValueException $error) {
                if ($error->getMessage() !== 'Transition not allowed or ambiguous for current State.') {
                    throw $error;
                }
                // No unique configured transition: not an actionable assignment.
            } catch (DomainException) {
                // A policy that no longer allows a transition never creates an action.
            }
        }
        return ['mode' => 'LIFECYCLE', 'current' => (string) $row['status'], 'options' => $options,
            'record_display' => [
                'name' => is_string($row['name'] ?? null) ? (string) $row['name'] : null,
                'code' => is_string($row['supplier_code'] ?? null) ? (string) $row['supplier_code'] : null,
            ]];
    }
}

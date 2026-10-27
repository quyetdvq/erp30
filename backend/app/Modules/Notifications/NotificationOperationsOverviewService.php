<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/** NFX-E1.1 System-only read gate. PBAC executes before queue aggregation. */
final class NotificationOperationsOverviewService
{
    private readonly BaseConnection $db;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $id = (string) $actor->id;
        if (! ctype_digit($id) || $id === '0' || ! (bool) $actor->active || $actor->deleted_at !== null) {
            throw new DomainException('UNAUTHENTICATED');
        }
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        $this->assertAuthorized();
        return (new NotificationOperationsOverviewReader($this->db))->snapshot();
    }

    /** Shared E1.2A guard. Used by both read-only Operations endpoints. */
    public function assertAuthorized(): void
    {
        // E1.2A: dedicated SYSTEM capability, never inherited from Settings.
        // The trusted Resource catalog row is an ALL_STATES target for PBAC.
        // Missing/invalid provisioning must deny rather than expose metrics.
        $anchor = $this->db->table('resources')
            ->select('id, type, domain, status, lifecycle_id')
            ->where('type', 'notification_operations')->get()->getRowArray();
        if ($anchor === null || $anchor['type'] !== 'notification_operations'
            || $anchor['domain'] !== 'SYSTEM' || $anchor['status'] !== 'ACTIVE'
            || $anchor['lifecycle_id'] !== null) {
            throw new DomainException('NOTIFICATION_ACCESS_DENIED');
        }
        // Do not treat a missing Action as a valid grant or leak an exception.
        $actions = $this->db->table('resource_actions')
            ->where('resource_id', $anchor['id'])->where('code', 'view')->countAllResults();
        if ($actions !== 1) {
            throw new DomainException('NOTIFICATION_ACCESS_DENIED');
        }
        $decision = (new UnifiedAccessService($this->actor, $this->db))
            ->decidePersisted('notification_operations', 'view', $anchor);
        if (! $decision['allowed']) {
            throw new DomainException('NOTIFICATION_ACCESS_DENIED');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/** Same persisted SYSTEM PBAC gate as Operations Overview, before any queue reads. */
final class NotificationOperationsDiagnosticsService
{
    private readonly BaseConnection $db;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string,mixed> */
    public function diagnostics(): array
    {
        (new NotificationOperationsOverviewService($this->actor, $this->db))->assertAuthorized();
        return (new NotificationOperationsDiagnosticsReader($this->db))->snapshot();
    }
}

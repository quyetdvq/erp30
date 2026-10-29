<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/** Best-effort, user-scoped In-App materialization on F5 or navigation. */
final class NotificationOnDemandSync
{
    public function __construct(private readonly BaseConnection $db) {}

    /** @return array{status:string,processed:int} */
    public function refresh(User $user): array
    {
        $raw = $user->toRawArray();
        $id = (string) $user->id;
        if (! WorkflowNotificationRules::activeUser($raw)
            || ! preg_match('/\A[1-9][0-9]{0,9}\z/D', $id)) {
            throw new DomainException('UNAUTHENTICATED');
        }
        if (! NotificationFlags::deliveryEnabled($this->db)) {
            return ['status' => 'DISABLED', 'processed' => 0];
        }
        // Session-scoped throttle in addition to client single-flight. It
        // protects the database from F5/navigation loops without creating
        // a new table or relying on a client-controlled timestamp.
        $session = session();
        $key = 'm11_inbox_sync_' . $id;
        $now = time();
        $last = (int) $session->get($key);
        if ($last > 0 && $now >= $last && ($now - $last) < 2) {
            return ['status' => 'THROTTLED', 'processed' => 0];
        }
        $session->set($key, $now);
        $stats = (new NotificationDispatcher($this->db))->runForUserOnce($id, 2);
        return ['status' => 'CHECKED', 'processed' => $stats['processed']];
    }
}

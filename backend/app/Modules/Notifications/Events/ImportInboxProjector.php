<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationRules;

/** Exact projection of the three historical Import terminal notifications. */
final class ImportInboxProjector implements NotificationInboxProjector
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public function project(array $row): array
    {
        $type = (string) $row['type'];
        $known = [
            'import.completed' => 'completed',
            'import.completed_with_errors' => 'completedWithErrors',
            'import.failed' => 'failed',
        ];
        $slug = $known[$type] ?? null;
        if ($slug === null || (string) $row['target_type'] !== 'import_job'
            || (string) $row['title_key'] !== "notifications.import.{$slug}.title"
            || (string) $row['body_key'] !== "notifications.import.{$slug}.body"
            || ! NotificationRules::version((string) $row['target_id'])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $params = json_decode((string) $row['params_json'], true);
        if (! is_array($params) || array_keys($params) !== ['success_rows', 'error_rows']
            || ! is_int($params['success_rows']) || ! is_int($params['error_rows'])) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return NotificationInboxProjectionFields::envelope(
            $row, $type, $params, ['type' => 'import_job', 'id' => (string) $row['target_id']],
        );
    }
}

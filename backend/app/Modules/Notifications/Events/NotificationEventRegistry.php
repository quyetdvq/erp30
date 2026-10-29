<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use LogicException;

/**
 * Static registry of events with implemented transactional producers and Inbox DTOs.
 * Deliberately excludes security.permission_changed (producer UNAVAILABLE).
 *
 * NFX-C0.2: Dispatcher resolves event+source through this registry and routes
 * symbolic handler IDs; producers and Inbox projectors retain legacy behavior.
 */
final class NotificationEventRegistry
{
    /** @return array<string,NotificationEventDefinition> Indexed by unique event type. */
    public static function all(): array
    {
        static $registry = null;
        if ($registry === null) {
            $entries = [
                new NotificationEventDefinition('import.completed', 'import_job', 1,
                    'import_terminal', 'import_inbox', ['in_app', 'email', 'push'], 'import_job'),
                new NotificationEventDefinition('import.completed_with_errors', 'import_job', 1,
                    'import_terminal', 'import_inbox', ['in_app', 'email', 'push'], 'import_job'),
                new NotificationEventDefinition('import.failed', 'import_job', 1,
                    'import_terminal', 'import_inbox', ['in_app', 'email', 'push'], 'import_job'),
                new NotificationEventDefinition('workflow.action_required', 'workflow_transition', 2,
                    'workflow_action_required', 'workflow_inbox', ['in_app', 'push'], 'workflow_target'),
            ];
            $indexed = [];
            foreach ($entries as $entry) {
                if (isset($indexed[$entry->type])) {
                    throw new LogicException('NOTIFICATION_DUPLICATE_EVENT_DEFINITION');
                }
                $indexed[$entry->type] = $entry;
            }
            $registry = $indexed;
        }
        return $registry;
    }

    /** An exact event/source pair is required; never infer a source from event input. */
    public static function find(string $eventType, string $sourceType): ?NotificationEventDefinition
    {
        $definition = self::all()[$eventType] ?? null;
        return $definition !== null && $definition->sourceType === $sourceType
            ? $definition : null;
    }
}

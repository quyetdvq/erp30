<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Exceptions\DomainException;

/**
 * Static event -> symbolic projector routing, never a request/DB-controlled class name.
 * Inbox rows have event type + target kind, NOT the Outbox source_type.
 */
final class NotificationInboxProjectorRegistry
{
    public static function resolve(NotificationEventDefinition $definition): NotificationInboxProjector
    {
        return match ($definition->projectorId) {
            'import_inbox' => new ImportInboxProjector(),
            'workflow_inbox' => new WorkflowInboxProjector(),
            default => throw new DomainException('NOTIFICATION_STORAGE_FAILED'),
        };
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function project(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $definition = NotificationEventRegistry::all()[$type] ?? null;
        if ($definition === null || ! $definition->supports('in_app')
            || (string) ($row['target_type'] ?? '') !== $definition->targetKind) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return self::resolve($definition)->project($row);
    }
}

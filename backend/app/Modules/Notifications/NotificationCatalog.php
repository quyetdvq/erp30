<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** Immutable deploy-time capabilities, separate from System policy and delivery switches. */
final class NotificationCatalog
{
    /** @return array<string,array{default:bool,override:bool,mandatory:bool,capability:string,producer_state:string}> */
    public static function definitions(): array
    {
        return [
            'import.completed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState()],
            'import.completed_with_errors.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState()],
            'import.failed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState()],
            'workflow.action_required.in_app' => ['default' => true, 'override' => false, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::workflowState()],
            'security.permission_changed.in_app' => ['default' => true, 'override' => false, 'mandatory' => true, 'capability' => 'UNAVAILABLE', 'producer_state' => 'INACTIVE'],
            'import.completed.email' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::emailState()],
            'import.completed.push' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::pushState()],
        ];
    }

    private static function emailState(): string
    {
        return NotificationFlags::producerEnabled() && NotificationFlags::emailQueueEnabled()
            ? 'ACTIVE' : 'INACTIVE';
    }

    private static function pushState(): string
    {
        return NotificationFlags::producerEnabled() && NotificationFlags::pushQueueEnabled()
            ? 'ACTIVE' : 'INACTIVE';
    }

    private static function workflowState(): string
    {
        return NotificationFlags::workflowProducerEnabled() ? 'ACTIVE' : 'INACTIVE';
    }

    private static function importState(): string
    {
        return NotificationFlags::producerEnabled() ? 'ACTIVE' : 'INACTIVE';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** Immutable deploy-time capabilities, separate from System policy and delivery switches. */
final class NotificationCatalog
{
    /** @return array<string,array{default:bool,override:bool,mandatory:bool,capability:string,producer_state:string,requires_explicit_consent:bool}> */
    public static function definitions(): array
    {
        return [
            'import.completed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState(), 'requires_explicit_consent' => false],
            'import.completed_with_errors.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState(), 'requires_explicit_consent' => false],
            'import.failed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::importState(), 'requires_explicit_consent' => false],
            'workflow.action_required.in_app' => ['default' => true, 'override' => false, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::workflowState(), 'requires_explicit_consent' => false],
            'security.permission_changed.in_app' => ['default' => true, 'override' => false, 'mandatory' => true, 'capability' => 'UNAVAILABLE', 'producer_state' => 'INACTIVE', 'requires_explicit_consent' => false],
            'import.completed.email' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::emailState(), 'requires_explicit_consent' => true],
            'import.completed_with_errors.email' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::emailState(), 'requires_explicit_consent' => true],
            'import.failed.email' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::emailState(), 'requires_explicit_consent' => true],
            'import.completed.push' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::pushState(), 'requires_explicit_consent' => true],
            'import.completed_with_errors.push' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::pushState(), 'requires_explicit_consent' => true],
            'import.failed.push' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::pushState(), 'requires_explicit_consent' => true],
            'workflow.action_required.push' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => self::workflowPushState(), 'requires_explicit_consent' => true],
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

    private static function workflowPushState(): string
    {
        return NotificationFlags::workflowProducerEnabled() && NotificationFlags::pushQueueEnabled()
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

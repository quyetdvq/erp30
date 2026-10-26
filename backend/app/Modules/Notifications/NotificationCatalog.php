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
            'import.completed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => 'INACTIVE'],
            'import.completed_with_errors.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => 'INACTIVE'],
            'import.failed.in_app' => ['default' => true, 'override' => true, 'mandatory' => false, 'capability' => 'AVAILABLE', 'producer_state' => 'INACTIVE'],
            'workflow.action_required.in_app' => ['default' => true, 'override' => false, 'mandatory' => false, 'capability' => 'UNAVAILABLE', 'producer_state' => 'INACTIVE'],
            'security.permission_changed.in_app' => ['default' => true, 'override' => false, 'mandatory' => true, 'capability' => 'UNAVAILABLE', 'producer_state' => 'INACTIVE'],
            'import.completed.email' => ['default' => false, 'override' => true, 'mandatory' => false, 'capability' => 'UNAVAILABLE', 'producer_state' => 'INACTIVE'],
        ];
    }
}

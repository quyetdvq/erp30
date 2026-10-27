<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\NotificationPermanentFailure;
use CodeIgniter\Database\BaseConnection;

/** Maps deploy-time symbolic handler IDs to known classes; never dynamic class loading. */
final class NotificationEventHandlerFactory
{
    public static function resolve(NotificationEventDefinition $definition, BaseConnection $db): NotificationEventHandler
    {
        return match ($definition->handlerId) {
            'import_terminal' => new ImportTerminalHandler($db),
            'workflow_action_required' => new WorkflowActionRequiredHandler($db),
            default => throw new NotificationPermanentFailure('NOTIFICATION_INVALID_EVENT'),
        };
    }
}

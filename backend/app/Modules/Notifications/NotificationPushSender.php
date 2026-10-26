<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** The Push service accepted a signal, not proof that a browser displayed it. */
interface NotificationPushSender
{
    /** @return 'sent'|'gone'|'retry'|'dead' */
    public function send(string $endpoint): string;
}

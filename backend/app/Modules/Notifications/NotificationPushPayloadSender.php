<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** Optional encrypted-content extension; existing test doubles still implement NotificationPushSender. */
interface NotificationPushPayloadSender extends NotificationPushSender
{
    /** @return 'sent'|'gone'|'retry'|'dead' */
    public function sendEncrypted(string $endpoint, string $payload, string $p256dh, string $auth): string;
}

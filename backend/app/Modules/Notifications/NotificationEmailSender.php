<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/** Injection seam for PHPUnit; the production adapter never runs under CI_ENVIRONMENT=testing. */
interface NotificationEmailSender
{
    public function send(string $recipientAddress, string $subject, string $body): void;
}

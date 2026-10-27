<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

/** Business delivery outcome only. RETRY/DEAD/PAUSED remain Dispatcher-owned. */
final readonly class NotificationDeliveryResult
{
    private function __construct(public string $status, public ?string $reason) {}

    public static function processed(): self
    {
        return new self('PROCESSED', null);
    }

    public static function suppressed(string $reason): self
    {
        return new self('SUPPRESSED', $reason);
    }
}

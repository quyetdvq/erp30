<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

final class NotificationPermanentFailure extends RuntimeException
{
    public function __construct(public readonly string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($reason, $code, $previous);
    }
}

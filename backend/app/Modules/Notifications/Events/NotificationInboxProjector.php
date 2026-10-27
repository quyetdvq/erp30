<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

/** Read-only transformation of a persisted Inbox row into its public DTO. */
interface NotificationInboxProjector
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public function project(array $row): array;
}

<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

/** Handler is constructed with the Dispatcher's BaseConnection, never with a new DB connection.
 * The Dispatcher owns begin/commit/rollback, claim, lease, ACK and backoff.
 */
interface NotificationEventHandler
{
    /** @param array<string,mixed> $event Row already locked by Dispatcher in its transaction. */
    public function deliver(array $event): NotificationDeliveryResult;
}

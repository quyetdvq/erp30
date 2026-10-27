<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use InvalidArgumentException;

/**
 * Deploy-time metadata only. This is NOT a runtime permission or consent grant.
 * Handler/projector IDs are symbolic allowlist identifiers, not PHP class names.
 * No database access, callbacks, or transport operations belong in this object.
 */
final readonly class NotificationEventDefinition
{
    /** @param list<'in_app'|'email'|'push'> $supportedChannels */
    public function __construct(
        public string $type,
        public string $sourceType,
        public int $payloadSchemaVersion,
        public string $handlerId,
        public string $projectorId,
        public array $supportedChannels,
        public string $targetKind,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/D', $type) !== 1
            || ! self::identifier($sourceType)
            || $payloadSchemaVersion < 1
            || ! self::identifier($handlerId)
            || ! self::identifier($projectorId)
            || ! self::identifier($targetKind)
            || $supportedChannels === []
            || ! array_is_list($supportedChannels)) {
            throw new InvalidArgumentException('NOTIFICATION_INVALID_EVENT_DEFINITION');
        }

        $allowed = ['in_app', 'email', 'push'];
        $lastPosition = -1;
        foreach ($supportedChannels as $channel) {
            if (! is_string($channel)) {
                throw new InvalidArgumentException('NOTIFICATION_INVALID_EVENT_DEFINITION');
            }
            $position = array_search($channel, $allowed, true);
            // A canonical strictly increasing list prevents duplicates and unknown channels.
            if ($position === false || $position <= $lastPosition) {
                throw new InvalidArgumentException('NOTIFICATION_INVALID_EVENT_DEFINITION');
            }
            $lastPosition = $position;
        }
    }

    public function supports(string $channel): bool
    {
        return in_array($channel, $this->supportedChannels, true);
    }

    private static function identifier(string $value): bool
    {
        return preg_match('/\A[a-z][a-z0-9_]*\z/D', $value) === 1;
    }
}

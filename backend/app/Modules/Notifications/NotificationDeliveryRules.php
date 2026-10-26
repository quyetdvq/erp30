<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use InvalidArgumentException;

/** Pure, testable delivery retry/validation decisions. */
final class NotificationDeliveryRules
{
    private const DELAYS_MINUTES = [1, 5, 15, 60, 240];

    /** @return array{status:string,minutes:int} */
    public static function afterFailure(int $attempts): array
    {
        if ($attempts <= 0) {
            throw new InvalidArgumentException('Attempts must start at one.');
        }
        if ($attempts >= 6) {
            return ['status' => 'DEAD', 'minutes' => 0];
        }
        return ['status' => 'RETRY', 'minutes' => self::DELAYS_MINUTES[$attempts - 1]];
    }

    public static function importEventType(string $terminal): string
    {
        return match ($terminal) {
            'COMPLETED' => 'import.completed',
            'COMPLETED_WITH_ERRORS' => 'import.completed_with_errors',
            'FAILED' => 'import.failed',
            default => throw new InvalidArgumentException('Invalid import terminal state.'),
        };
    }

    public static function shieldUserId(mixed $raw): ?string
    {
        // Must match actual Shield INT UNSIGNED user PK. Overflow is a corrupt
        // source error, NOT an absent recipient eligible for SUPPRESSED.
        if ($raw === null) {
            return null;
        }
        $id = (string) $raw;
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
            || strlen($id) > 10 || (strlen($id) === 10 && strcmp($id, '4294967295') > 0)) {
            throw new InvalidArgumentException('Invalid source user reference.');
        }
        return $id;
    }
}

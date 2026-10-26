<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/**
 * Parse CodeIgniter Spark options, which arrive in BaseCommand::run() as
 * associative entries (e.g. ['once' => null, 'batch=25' => null]).
 */
final class NotificationDispatchOptions
{
    /** @param array<array-key, string|null> $params */
    public static function batch(array $params): int
    {
        $batch = 25;
        $batchSeen = false;

        foreach ($params as $key => $value) {
            if (($key === 'once' && $value === null)
                || (is_int($key) && $value === '--once')) {
                continue;
            }

            // Spark 4.7 parses --batch=25 as ['batch=25' => null] and
            // --batch 25 as ['batch' => '25']. Also support direct calls.
            if ($key === 'batch' && is_string($value)) {
                $rawBatch = $value;
            } elseif (is_string($key) && $value === null && str_starts_with($key, 'batch=')) {
                $rawBatch = substr($key, strlen('batch='));
            } elseif (is_int($key) && is_string($value) && str_starts_with($value, '--batch=')) {
                $rawBatch = substr($value, strlen('--batch='));
            } else {
                throw new RuntimeException('Usage: php spark notifications:dispatch --once --batch=25');
            }

            if ($batchSeen || preg_match('/\A[1-9][0-9]{0,2}\z/D', $rawBatch) !== 1
                || (int) $rawBatch > 100) {
                throw new RuntimeException('Batch must be between 1 and 100 and specified once.');
            }
            $batchSeen = true;
            $batch = (int) $rawBatch;
        }

        return $batch;
    }
}

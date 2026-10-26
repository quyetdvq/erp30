<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/**
 * Snapshot-only hints. Never authorizes worker execution: the scoped
 * notifications:push-acceptance gate must be rerun before every next step.
 */
final class NotificationPushStatusAdvice
{
    /**
     * @param array{status:string}|null $job
     * @param array<string,int> $receipts
     * @return 'created'|'queued'|'sent'|null
     */
    public static function candidateStage(
        ?array $job,
        ?string $outboxStatus,
        array $receipts,
        int $activeDevices,
        bool $queueEnabled,
        bool $sendEnabled,
        int $unfinishedOutbox,
        int $unfinishedPush
    ): ?string {
        if ($job === null || ($job['status'] ?? '') !== 'COMPLETED'
            || $activeDevices !== 1 || $sendEnabled) {
            return null;
        }
        $total = array_sum($receipts);
        // A sent receipt remains inspectable after the operator turns Queue off.
        if ($outboxStatus === 'PROCESSED' && $total === 1
            && ($receipts['SENT'] ?? 0) === 1) {
            return 'sent';
        }
        if (! $queueEnabled) {
            return null;
        }
        if ($outboxStatus === 'PENDING' && $total === 0
            && $unfinishedOutbox === 1 && $unfinishedPush === 0) {
            return 'created';
        }
        if ($outboxStatus === 'PROCESSED' && $total === 1
            && ($receipts['PENDING'] ?? 0) === 1 && $unfinishedPush === 1) {
            return 'queued';
        }
        return null;
    }
}

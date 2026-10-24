<?php

declare(strict_types=1);

namespace App\Modules\Access;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * Controlled M2.1.1 -> M3.2 cutover, never a permissive fallback:
 * legacy (default) maintains existing behavior; shadow compares and returns
 * legacy; enforce uses only the new runtime evaluator and fails closed.
 */
final class ReadAccessBridge
{
    private readonly string $mode;
    private ?ReadViewGate $legacy = null;
    private ?UnifiedAccessService $unified = null;
    private int $loggedMismatches = 0;
    private readonly DateTimeImmutable $operationInstant;

    public function __construct(
        private readonly User $user,
        private readonly ?BaseConnection $db = null,
        private readonly ?DateTimeImmutable $instant = null,
        ?string $mode = null,
    ) {
        $selected = strtolower(trim($mode ?? (string) env('DINKERP_M32_READ_MODE', 'legacy')));
        if (! in_array($selected, ['legacy', 'shadow', 'enforce'], true)) {
            throw new UnexpectedValueException('Invalid M3.2 read mode; refusing authorization.');
        }
        $this->mode = $selected;
        // Share exactly one UTC evaluation instant between both engines,
        // including on detail/tree reads invoked around UTC midnight.
        $this->operationInstant = ($instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $persistedRow */
    public function canView(string $type, array $persistedRow): bool
    {
        if ($this->mode === 'enforce') {
            return $this->unified()->canView($type, $persistedRow);
        }
        $old = $this->legacy()->canView($type, $persistedRow);
        if ($this->mode === 'shadow') {
            try {
                $new = $this->unified()->canView($type, $persistedRow);
                if ($old !== $new) {
                    $this->logOnce($type, 'different decision');
                }
            } catch (Throwable $e) {
                // Never broaden permissions or disrupt already accepted read
                // behavior during explicitly opted-in shadow comparison.
                $this->logOnce($type, 'new evaluator error: ' . get_class($e));
            }
        }
        return $old;
    }

    private function logOnce(string $type, string $message): void
    {
        if ($this->loggedMismatches < 5) {
            // No user IDs, record IDs, PII or policy details in logs.
            log_message('warning', 'M3.2 PBAC shadow comparison for {resource}: {message}', [
                'resource' => $type, 'message' => $message,
            ]);
        }
        $this->loggedMismatches++;
    }

    private function legacy(): ReadViewGate
    {
        return $this->legacy ??= new ReadViewGate($this->user, $this->db, $this->operationInstant);
    }

    private function unified(): UnifiedAccessService
    {
        return $this->unified ??= new UnifiedAccessService($this->user, $this->db, $this->operationInstant);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Access;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Compatibility entrypoint with ONE runtime authorization engine:
 * canonical UnifiedAccessService (formerly called M3.2 enforce).
 * No environment switch, shadow mode, or legacy permission fallback.
 */
final class ReadAccessBridge
{
    private ?UnifiedAccessService $unified = null;
    private readonly DateTimeImmutable $operationInstant;

    public function __construct(
        private readonly User $user,
        private readonly ?BaseConnection $db = null,
        ?DateTimeImmutable $instant = null,
    ) {
        // PHP may accept surplus positional arguments on userland methods.
        // Reject historical fourth-argument mode overrides explicitly.
        if (func_num_args() > 3) {
            throw new UnexpectedValueException('Authorization mode overrides are not supported.');
        }
        $this->operationInstant = ($instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $persistedRow */
    public function canView(string $type, array $persistedRow): bool
    {
        return ($this->unified ??= new UnifiedAccessService(
            $this->user, $this->db, $this->operationInstant
        ))->canView($type, $persistedRow);
    }
}

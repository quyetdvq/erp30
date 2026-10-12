<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Validates a business status against either its attached Lifecycle members
 * or its legacy native vocabulary when no Lifecycle is assigned. Never treat
 * Lifecycle states as a hard-coded ACTIVE/INACTIVE enum.
 */
final class WorkflowBusinessStatus
{
    /**
     * @param list<string> $nativeCodes
     * @param ConnectionInterface<mixed,mixed>|null $db
     */
    public static function normalize(
        string $type,
        mixed $value,
        array $nativeCodes,
        string $errorCode,
        ?ConnectionInterface $db = null,
    ): string {
        if (! is_string($value)) {
            throw new DomainException($errorCode, ['status' => $value]);
        }
        $code = strtoupper(trim($value));
        if ($code === '' || strlen($code) > 50) {
            throw new DomainException($errorCode, ['status' => $code]);
        }
        $resolver = new ResourceWorkflowStateResolver($db);
        $allowed = $resolver->lifecycleId($type) !== null
            ? $resolver->allowedPolicyStateCodes($type)
            : $nativeCodes;
        if (! in_array($code, $allowed, true)) {
            throw new DomainException($errorCode, ['status' => $code]);
        }
        return $code;
    }
}

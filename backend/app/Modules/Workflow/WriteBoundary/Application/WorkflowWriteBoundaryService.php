<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Application;

use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowStateLookupPort;
use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowWriteBoundaryPort;
use App\Modules\Workflow\WriteBoundary\Contracts\WorkflowWriteBoundaryErrors;

/** Application-level create/update state guard; no SQL, grants, audit or transition execution. */
final class WorkflowWriteBoundaryService implements WorkflowWriteBoundaryPort
{
    public function __construct(private readonly WorkflowStateLookupPort $states)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(string $resource, array $data): array
    {
        if ($this->states->lifecycleId($resource) === null) {
            return $data;
        }
        return InitialStateRules::withInitial($resource, $data, $this->states->initialCode($resource));
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $changes
     */
    public function assertNoDirectStateMutation(string $resource, array $current, array $changes): void
    {
        if (! array_key_exists('status', $changes)) {
            return;
        }
        if (is_string($changes['status'])
            && strtoupper(trim($changes['status'])) === strtoupper((string) ($current['status'] ?? ''))) {
            return;
        }
        if ($this->states->lifecycleId($resource) !== null) {
            throw new DomainException(WorkflowWriteBoundaryErrors::ERROR_TRANSITION_REQUIRED, [
                'resource' => $resource,
            ]);
        }
    }

    /** @param list<string> $nativeCodes */
    public function normalizeStatus(string $type, mixed $value, array $nativeCodes, string $errorCode): string
    {
        if (! is_string($value)) {
            throw new DomainException($errorCode, ['status' => $value]);
        }
        $code = strtoupper(trim($value));
        if ($code === '' || strlen($code) > 50) {
            throw new DomainException($errorCode, ['status' => $code]);
        }
        $allowed = $this->states->lifecycleId($type) !== null
            ? $this->states->allowedPolicyStateCodes($type)
            : $nativeCodes;
        if (! in_array($code, $allowed, true)) {
            throw new DomainException($errorCode, ['status' => $code]);
        }
        return $code;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function normalizeInitial(string $resource, array $payload): array
    {
        return InitialStateRules::withInitial($resource, $payload, $this->states->initialCode($resource));
    }
}

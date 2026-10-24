<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Application;

use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Contracts\WorkflowWriteBoundaryErrors;
use App\Services\ResourceNativeStateCatalog;

/** DB-free create rule; lifecycle membership is validated by the upstream BE010 resolver. */
final class InitialStateRules
{
    private function __construct()
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function withInitial(string $resource, array $payload, ?string $initial): array
    {
        if ($initial === null) {
            if (! array_key_exists('status', $payload)
                && in_array('ACTIVE', ResourceNativeStateCatalog::codes($resource), true)) {
                $payload['status'] = 'ACTIVE';
            }
            return $payload;
        }
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $initial) !== 1) {
            throw new DomainException(WorkflowWriteBoundaryErrors::ERROR_UNSUPPORTED_INITIAL, [
                'resource' => $resource, 'initial_status' => $initial,
            ]);
        }
        if (array_key_exists('status', $payload)
            && (! is_string($payload['status']) || strtoupper(trim($payload['status'])) !== $initial)) {
            throw new DomainException(WorkflowWriteBoundaryErrors::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $resource, 'initial_status' => $initial,
            ]);
        }
        $payload['status'] = $initial;
        return $payload;
    }
}

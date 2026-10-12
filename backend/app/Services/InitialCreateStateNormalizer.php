<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/**
 * The persisted Resource Lifecycle, not a form's default value, owns the
 * initial business State. Native (Lifecycle-free) resources keep their domain
 * defaults; a client-provided Lifecycle State can only corroborate the initial.
 */
final class InitialCreateStateNormalizer
{
    public const ERROR_UNSUPPORTED_INITIAL = 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE';

    public function __construct(private readonly ?ResourceWorkflowStateResolver $states = null)
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function normalize(string $resource, array $payload): array
    {
        $initial = ($this->states ?? new ResourceWorkflowStateResolver())->initialCode($resource);

        return self::withInitial($resource, $payload, $initial);
    }

    /**
     * Pure validation path for the creation contract; also regression-tested
     * without needing a database connection. Native resources default ACTIVE
     * when the request does not explicitly supply a valid native State.
     *
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
        // Domain validators and operational rules cannot interpret arbitrary
        // Workflow State codes yet; an incompatible Lifecycle must fail closed.
        if (! in_array($initial, ResourceNativeStateCatalog::codes($resource), true)) {
            throw new DomainException(self::ERROR_UNSUPPORTED_INITIAL, [
                'resource' => $resource, 'initial_status' => $initial,
            ]);
        }
        if (array_key_exists('status', $payload)
            && (! is_string($payload['status']) || strtoupper(trim($payload['status'])) !== $initial)) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $resource, 'initial_status' => $initial,
            ]);
        }

        $payload['status'] = $initial;
        return $payload;
    }
}

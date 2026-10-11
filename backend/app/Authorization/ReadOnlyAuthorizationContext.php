<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Infrastructure\Mutation\OperationContext;

/**
 * Ephemeral memoization for ONE read-only authorization batch. This is not a
 * cross-request/session cache and deliberately does not cache final decisions.
 * Callers must never mutate IAM, employment, organization or policy data while
 * this context is active. AuthorizationService owns its try/finally lifetime.
 */
final class ReadOnlyAuthorizationContext
{
    private const MAX_KEYS_PER_BUCKET = 256;
    private const MAX_POLICY_ROWS_PER_KEY = 256;

    /** @var array<string, array<string, mixed>> */
    private array $entries = [];

    private readonly string $evaluationInstant;

    public function __construct(private readonly OperationContext $operation)
    {
        $this->evaluationInstant = $operation->evaluationTime->utc()->format('U.u');
    }

    public function matches(OperationContext $operation): bool
    {
        return $operation->actorUserId === $this->operation->actorUserId
            && $operation->requestId === $this->operation->requestId
            && $operation->systemActor === $this->operation->systemActor
            && $operation->evaluationTime->utc()->format('U.u') === $this->evaluationInstant;
    }

    /**
     * Caches null/empty results as well as populated values. Loader exceptions
     * are never cached; a subsequent evaluation still fails through its usual
     * authoritative database path. Each bucket is capped to bound RAM usage.
     *
     * @template T
     * @param callable(): T $loader
     * @return T
     */
    public function remember(string $bucket, string $key, callable $loader): mixed
    {
        if (array_key_exists($key, $this->entries[$bucket] ?? [])) {
            return $this->entries[$bucket][$key];
        }

        $value = $loader();
        $tooManyPolicies = $bucket === 'policies'
            && is_array($value)
            && count($value) > self::MAX_POLICY_ROWS_PER_KEY;
        if (! $tooManyPolicies && count($this->entries[$bucket] ?? []) < self::MAX_KEYS_PER_BUCKET) {
            $this->entries[$bucket][$key] = $value;
        }

        return $value;
    }
}

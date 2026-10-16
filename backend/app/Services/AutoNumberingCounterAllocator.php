<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\NumberingFactory;

use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Allocation\Application\AutoNumberingCounterAllocator as CanonicalAllocator;
use App\Repositories\AutoNumberingCounterRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for serialized Auto Number counter allocation. */
final class AutoNumberingCounterAllocator
{
    public const ERROR_CONFIGURATION_INVALID = CanonicalAllocator::ERROR_CONFIGURATION_INVALID;
    public const ERROR_OVERFLOW = CanonicalAllocator::ERROR_OVERFLOW;
    public const ERROR_RUNTIME_CONTEXT_REQUIRED = CanonicalAllocator::ERROR_RUNTIME_CONTEXT_REQUIRED;
    public const ERROR_CANDIDATE_EXHAUSTED = CanonicalAllocator::ERROR_CANDIDATE_EXHAUSTED;
    public const ERROR_TRANSACTION_FAILED = CanonicalAllocator::ERROR_TRANSACTION_FAILED;
    public const ERROR_LOCK_FAILED = CanonicalAllocator::ERROR_LOCK_FAILED;

    private readonly CanonicalAllocator $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?AutoNumberingCounterRepository $counters = null,
        ?DomainLockService $domainLock = null,
    ) {
        $this->delegate = NumberingFactory::allocator($db, $counters, $domainLock);
    }

    /** @param array<string, mixed> $series */
    public function allocate(
        array $series,
        NumberingScopePeriodContext $context,
        ParsedNumberingPattern $pattern,
        AutoNumberingBusinessMutation $business,
        ?OperationContext $operation = null,
    ): AutoNumberingAllocationResult {
        return $this->delegate->allocate($series, $context, $pattern, $business, $operation);
    }

    public function renderCandidate(ParsedNumberingPattern $pattern, NumberingScopePeriodContext $context, int $sequenceValue): string
    {
        return $this->delegate->renderCandidate($pattern, $context, $sequenceValue);
    }
}

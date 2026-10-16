<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\NumberingFactory;

use App\Authorization\EvaluationOrganizationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Numbering\Issuance\Application\AutoNumberService as CanonicalAutoNumberService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for Resource identifier issuance. */
final class AutoNumberService
{
    public const ERROR_RESOURCE_NOT_FOUND = CanonicalAutoNumberService::ERROR_RESOURCE_NOT_FOUND;
    public const ERROR_RESOURCE_AMBIGUOUS = CanonicalAutoNumberService::ERROR_RESOURCE_AMBIGUOUS;
    public const ERROR_MANUAL_NOT_ALLOWED = CanonicalAutoNumberService::ERROR_MANUAL_NOT_ALLOWED;
    public const ERROR_SERIES_NOT_FOUND = CanonicalAutoNumberService::ERROR_SERIES_NOT_FOUND;
    public const ERROR_SERIES_INACTIVE = CanonicalAutoNumberService::ERROR_SERIES_INACTIVE;
    public const ERROR_DEFAULT_AMBIGUOUS = CanonicalAutoNumberService::ERROR_DEFAULT_AMBIGUOUS;

    private readonly CanonicalAutoNumberService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ResourceIdentifierAdapterRegistry $adapters = null,
        ?AutoNumberingSeriesRepository $series = null,
        ?AutoNumberingCounterAllocator $allocator = null,
        ?CanonicalNumberingPatternValidator $patterns = null,
        ?AutoNumberingScopePeriodResolver $scopeResolver = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->delegate = NumberingFactory::issuance($adapters, $series, $allocator, $patterns, $scopeResolver, $db);
    }

    public function issue(
        string $resourceType,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        AutoNumberingBusinessMutation $business,
        ?string $optionalSeriesCode = null,
        ?string $manualIdentifier = null,
    ): AutoNumberingAllocationResult {
        return $this->delegate->issue(
            $resourceType,
            $evaluationOrganization,
            $operation,
            $business,
            $optionalSeriesCode,
            $manualIdentifier,
        );
    }

    public function issueIdentifier(
        string $resourceType,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        AutoNumberingBusinessMutation $business,
        ?string $optionalSeriesCode = null,
        ?string $manualIdentifier = null,
    ): AutoNumberingAllocationResult {
        return $this->delegate->issueIdentifier(
            $resourceType,
            $evaluationOrganization,
            $operation,
            $business,
            $optionalSeriesCode,
            $manualIdentifier,
        );
    }
}

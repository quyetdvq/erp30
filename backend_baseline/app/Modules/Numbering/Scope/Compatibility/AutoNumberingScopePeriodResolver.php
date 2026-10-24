<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Scope\Compatibility;

use App\Services\NumberingScopePeriodContext;
use App\Services\ParsedNumberingPattern;

use App\Bootstrap\NumberingFactory;

use App\Authorization\CompanyOwnerResolver;
use App\Authorization\EvaluationOrganizationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\BusinessTimezoneProvider;
use App\Modules\Numbering\Scope\Application\AutoNumberingScopePeriodResolver as CanonicalScopePeriodResolver;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy compatibility entrypoint for authoritative Auto Numbering scope/period resolution. */
final class AutoNumberingScopePeriodResolver
{
    public const ERROR_SCOPE_INVALID = CanonicalScopePeriodResolver::ERROR_SCOPE_INVALID;
    public const ERROR_CONTEXT_REQUIRED = CanonicalScopePeriodResolver::ERROR_CONTEXT_REQUIRED;
    public const ERROR_SCOPE_RESOLUTION_FAILED = CanonicalScopePeriodResolver::ERROR_SCOPE_RESOLUTION_FAILED;
    public const ERROR_GROUP_ROOT_INVALID = CanonicalScopePeriodResolver::ERROR_GROUP_ROOT_INVALID;
    public const ERROR_COMPANY_REQUIRED = CanonicalScopePeriodResolver::ERROR_COMPANY_REQUIRED;
    public const ERROR_TIMEZONE_REQUIRED = CanonicalScopePeriodResolver::ERROR_TIMEZONE_REQUIRED;
    public const ERROR_TIMEZONE_INVALID = CanonicalScopePeriodResolver::ERROR_TIMEZONE_INVALID;
    public const ERROR_TIMEZONE_RESOLUTION_FAILED = CanonicalScopePeriodResolver::ERROR_TIMEZONE_RESOLUTION_FAILED;
    public const ERROR_PATTERN_METADATA_REQUIRED = CanonicalScopePeriodResolver::ERROR_PATTERN_METADATA_REQUIRED;

    private readonly CanonicalScopePeriodResolver $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationRepository $organizations = null,
        ?CompanyOwnerResolver $companyOwners = null,
        ?BusinessTimezoneProvider $timezones = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->delegate = NumberingFactory::scope($organizations, $companyOwners, $timezones, $db);
    }

    /** @param array<string, mixed> $series */
    public function resolve(
        array $series,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        ?ParsedNumberingPattern $pattern = null,
        ?string $resourceType = null,
    ): NumberingScopePeriodContext {
        return $this->delegate->resolve($series, $evaluationOrganization, $operation, $pattern, $resourceType);
    }
}

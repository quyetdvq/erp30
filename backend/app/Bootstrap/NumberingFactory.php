<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\CompanyOwnerResolver;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Numbering\Allocation\Application\AutoNumberingCounterAllocator as CanonicalAllocator;
use App\Modules\Numbering\Allocation\Application\Ports\NumberingCounterRepositoryInterface;
use App\Modules\Numbering\Authorization\Application\Ports\NumberingAuthorizationPortInterface;
use App\Modules\Numbering\Configuration\Application\AutoNumberingConfigurationService as CanonicalConfigurationService;
use App\Modules\Numbering\Configuration\Application\Ports\NumberingSeriesRepositoryInterface;
use App\Modules\Numbering\Issuance\Application\AutoNumberService as CanonicalAutoNumberService;
use App\Modules\Numbering\Pattern\Application\CanonicalNumberingPatternValidator;
use App\Modules\Numbering\Pattern\Application\Ports\NumberingPatternValidatorInterface;
use App\Modules\Numbering\Scope\Application\AutoNumberingScopePeriodResolver as CanonicalScopeResolver;
use App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface;
use App\Modules\Numbering\Scope\Infrastructure\Settings\CodeIgniterBusinessTimezoneResolver;
use App\Repositories\OrganizationRepository;
use App\Services\AutoNumberingCounterAllocator as LegacyAllocator;
use App\Services\AutoNumberingScopePeriodResolver as LegacyScopeResolver;
use App\Services\CanonicalNumberingPatternValidator as LegacyPatternValidator;
use App\Services\ResourceIdentifierAdapterRegistry;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Settings\Settings;

/** Stateless composition helpers for the Numbering module. */
final class NumberingFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function allocator(
        ?ConnectionInterface $db = null,
        ?NumberingCounterRepositoryInterface $counters = null,
        ?DomainLockService $domainLock = null,
    ): CanonicalAllocator {
        return new CanonicalAllocator($db, $counters, $domainLock);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function configuration(
        ?NumberingSeriesRepositoryInterface $repository = null,
        ?NumberingAuthorizationPortInterface $authorization = null,
        ?NumberingPatternValidatorInterface $patternValidator = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?ResourceIdentifierAdapterRegistry $identifierAdapters = null,
    ): CanonicalConfigurationService {
        return new CanonicalConfigurationService($repository, $authorization, $patternValidator, $db, $mutationContext, $clock, $identifierAdapters);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function scope(
        ?OrganizationRepository $organizations = null,
        ?CompanyOwnerResolver $companyOwners = null,
        ?BusinessTimezoneProviderInterface $timezones = null,
        ?ConnectionInterface $db = null,
    ): CanonicalScopeResolver {
        return new CanonicalScopeResolver($organizations, $companyOwners, $timezones, $db);
    }

    public static function businessTimezone(Settings $settings): CodeIgniterBusinessTimezoneResolver
    {
        return new CodeIgniterBusinessTimezoneResolver($settings);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function issuance(
        ?ResourceIdentifierAdapterRegistry $adapters = null,
        ?NumberingSeriesRepositoryInterface $series = null,
        CanonicalAllocator|LegacyAllocator|null $allocator = null,
        CanonicalNumberingPatternValidator|LegacyPatternValidator|null $patterns = null,
        CanonicalScopeResolver|LegacyScopeResolver|null $scopeResolver = null,
        ?ConnectionInterface $db = null,
    ): CanonicalAutoNumberService {
        return new CanonicalAutoNumberService($adapters, $series, $allocator, $patterns, $scopeResolver, $db);
    }
}

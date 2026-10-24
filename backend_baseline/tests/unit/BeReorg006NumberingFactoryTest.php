<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Numbering composition must preserve collaborator ownership and laziness. */
final class BeReorg006NumberingFactoryTest extends TestCase
{
    public function testCallerSuppliedCollaboratorsAreNeverRebound(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Bootstrap/NumberingFactory.php');
        $this->assertStringContainsString('new CanonicalConfigurationService($repository, $authorization, $patternValidator, $db, $mutationContext, $clock, $identifierAdapters)', $source);
        $this->assertStringContainsString('new CanonicalAllocator($db, $counters, $domainLock)', $source);
        $this->assertStringContainsString('new CanonicalScopeResolver($organizations, $companyOwners, $timezones, $db)', $source);
        $this->assertStringContainsString('new CanonicalAutoNumberService($adapters, $series, $allocator, $patterns, $scopeResolver, $db)', $source);
    }

    public function testSuppliedDbIsSharedAcrossDefaultRepositoriesLocksAndOrganizationDependencies(): void
    {
        $allocator = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php');
        $scope = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Numbering/Scope/Application/AutoNumberingScopePeriodResolver.php');
        $this->assertStringContainsString('$this->db = $db ?? db_connect();', $allocator);
        $this->assertStringContainsString('new SqlNumberingCounterRepository($this->db)', $allocator);
        $this->assertStringContainsString('new MariaDbDomainLockService($this->db)', $allocator);
        $this->assertStringContainsString('$connection = $db ?? db_connect();', $scope);
        $this->assertStringContainsString('new OrganizationRepository($connection)', $scope);
        $this->assertStringContainsString('new CompanyOwnerResolver(null, $connection)', $scope);
    }

    public function testNullDefaultsPreserveLegacyLazyConstructionBoundary(): void
    {
        $configuration = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Numbering/Configuration/Application/AutoNumberingConfigurationService.php');
        $issuance = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Numbering/Issuance/Application/AutoNumberService.php');
        $this->assertStringContainsString('return $this->repository ?? new SqlNumberingSeriesRepository($this->db);', $configuration);
        $this->assertStringContainsString('return $this->authorization ?? new AuthorizationService(db: $this->db);', $configuration);
        $this->assertStringContainsString('return $this->series ?? new SqlNumberingSeriesRepository($this->db);', $issuance);
        $this->assertStringContainsString('return $this->allocator ?? new AutoNumberingCounterAllocator($this->db);', $issuance);
    }

    public function testFactoryDoesNotChangeBusinessTimezoneSharedServiceSemantics(): void
    {
        $factory = new ReflectionClass(App\Bootstrap\NumberingFactory::class);
        $this->assertSame([], $factory->getProperties());
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Bootstrap/NumberingFactory.php');
        $this->assertStringNotContainsString('Config\\Services::businessTimezone', $source);
        $this->assertStringContainsString('new CodeIgniterBusinessTimezoneResolver($settings)', $source);
        $legacy = new ReflectionClass(App\Infrastructure\Settings\BusinessTimezoneResolver::class);
        $this->assertSame(['settings'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()?->getParameters() ?? []));
    }
}

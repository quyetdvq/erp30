<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationContext;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\BusinessTimezoneProvider;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\AutoNumberingScopePeriodResolver;
use App\Services\CanonicalNumberingPatternValidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Num003ScopePeriodResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testGroupResolvesCanonicalRootAndIgnoresCompanyContext(): void
    {
        [$groupId, $companyId] = $this->tree();
        $resolver = $this->resolver();
        $context = $resolver->resolve(
            ['scope_type' => 'GROUP', 'reset_policy' => 'NEVER'],
            new EvaluationOrganizationContext($companyId, 'COMPANY', 'AUTHORITATIVE'),
            $this->operation(),
        );

        $this->assertSame($groupId, $context->scopeOrganizationId);
        $this->assertSame('GROUP', $context->scopeType);
        $this->assertSame('ALL', $context->periodKey);
        $this->assertNull($context->companyId);
        $this->assertNull($context->businessTimezone);
        $this->assertSame('GROUP-ROOT', $context->organizationCode);
    }

    public function testGroupRootMustBeUniqueAndActive(): void
    {
        $this->insertOrganization('GROUP', null, 'ROOT-A');
        $this->insertOrganization('GROUP', null, 'ROOT-B');
        try {
            $this->resolver()->resolve(['scope_type' => 'GROUP', 'reset_policy' => 'NEVER'], null, $this->operation());
            $this->fail('Expected ambiguous GROUP root rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingScopePeriodResolver::ERROR_GROUP_ROOT_INVALID, $exception->getErrorCode());
        }
    }

    public function testCompanyScopeResolvesSelfAndNearestAncestorWithoutSiblingBleed(): void
    {
        [$groupId, $companyA, $branchA, $plantA, $warehouseA, $companyB] = $this->treeWithSiblingCompany();
        $resolver = $this->resolver();
        foreach ([
            [$companyA, 'COMPANY'],
            [$branchA, 'BRANCH'],
            [$plantA, 'PLANT'],
            [$warehouseA, 'WAREHOUSE'],
        ] as [$organizationId, $type]) {
            $resolved = $resolver->resolve(
                ['scope_type' => 'COMPANY', 'reset_policy' => 'NEVER'],
                new EvaluationOrganizationContext($organizationId, $type, 'AUTHORITATIVE'),
                $this->operation(),
            );
            $this->assertSame($companyA, $resolved->scopeOrganizationId);
            $this->assertSame('COMPANY-A', $resolved->companyCode);
            $this->assertNotSame($companyB, $resolved->scopeOrganizationId);
        }
        $this->assertNotSame($groupId, $companyA);
    }

    public function testCompanyScopeFromGroupAndMissingContextFailClosed(): void
    {
        $groupId = $this->insertOrganization('GROUP', null, 'ROOT');
        foreach ([
            [null, AutoNumberingScopePeriodResolver::ERROR_CONTEXT_REQUIRED],
            [new EvaluationOrganizationContext($groupId, 'GROUP', 'AUTHORITATIVE'), AutoNumberingScopePeriodResolver::ERROR_COMPANY_REQUIRED],
        ] as [$context, $expected]) {
            try {
                $this->resolver()->resolve(['scope_type' => 'COMPANY', 'reset_policy' => 'NEVER'], $context, $this->operation());
                $this->fail('Expected COMPANY scope rejection.');
            } catch (DomainException $exception) {
                $this->assertSame($expected, $exception->getErrorCode());
            }
        }
    }

    public function testOrganizationScopePreservesExactIdentityAndDoesNotUseCompanyAncestor(): void
    {
        [$groupId, $companyId, $branchId] = $this->tree();
        $resolved = $this->resolver()->resolve(
            ['scope_type' => 'ORGANIZATION', 'reset_policy' => 'NEVER'],
            new EvaluationOrganizationContext($branchId, 'BRANCH', 'AUTHORITATIVE'),
            $this->operation(),
        );

        $this->assertSame($branchId, $resolved->scopeOrganizationId);
        $this->assertSame('BRANCH-A', $resolved->organizationCode);
        $this->assertNull($resolved->companyId);
        $this->assertNotSame($companyId, $resolved->scopeOrganizationId);
        $this->assertNotSame($groupId, $resolved->scopeOrganizationId);
    }

    public function testPeriodKeysUseOneCapturedEvaluationTimeAndBusinessLocalDate(): void
    {
        [, $companyId] = $this->tree();
        $time = new EvaluationTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00'));
        $operation = new OperationContext(null, $time, 'num003-period', true);
        $timezone = new Num003FakeTimezoneProvider(['company:' . $companyId => 'America/Los_Angeles']);
        $resolver = $this->resolver($timezone);
        $context = new EvaluationOrganizationContext($companyId, 'COMPANY', 'AUTHORITATIVE');
        $validator = new CanonicalNumberingPatternValidator();

        $yearly = $resolver->resolve(['scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY', 'pattern' => '{YYYY}{SEQ:4}'], $context, $operation, $validator->parse('{YYYY}{SEQ:4}'));
        $monthly = $resolver->resolve(['scope_type' => 'COMPANY', 'reset_policy' => 'MONTHLY', 'pattern' => '{YYYY}{MM}{SEQ:4}'], $context, $operation, $validator->parse('{YYYY}{MM}{SEQ:4}'));
        $daily = $resolver->resolve(['scope_type' => 'COMPANY', 'reset_policy' => 'DAILY', 'pattern' => '{YYYY}{MM}{DD}{SEQ:4}'], $context, $operation, $validator->parse('{YYYY}{MM}{DD}{SEQ:4}'));

        $this->assertSame('2025', $yearly->periodKey);
        $this->assertSame('202512', $monthly->periodKey);
        $this->assertSame('20251231', $daily->periodKey);
        $this->assertSame('2025-12-31', $daily->businessDate);
        $this->assertSame($time->utc()->format('c'), $operation->evaluationTime->utc()->format('c'));
        $this->assertSame(3, $timezone->calls);
    }

    public function testCompaniesCanUseDifferentTimezonesForTheSameInstant(): void
    {
        [$groupId, $companyA, , , , $companyB] = $this->treeWithSiblingCompany();
        $provider = new Num003FakeTimezoneProvider([
            'company:' . $companyA => 'America/Los_Angeles',
            'company:' . $companyB => 'Asia/Tokyo',
        ]);
        $resolver = $this->resolver($provider);
        $validator = new CanonicalNumberingPatternValidator();
        $pattern = $validator->parse('{YYYY}{MM}{DD}{SEQ:4}');
        $series = ['scope_type' => 'COMPANY', 'reset_policy' => 'DAILY', 'pattern' => '{YYYY}{MM}{DD}{SEQ:4}'];
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00')), 'num003-tz', true);

        $this->assertSame('20251231', $resolver->resolve($series, new EvaluationOrganizationContext($companyA, 'COMPANY', 'AUTHORITATIVE'), $operation, $pattern)->periodKey);
        $this->assertSame('20260101', $resolver->resolve($series, new EvaluationOrganizationContext($companyB, 'COMPANY', 'AUTHORITATIVE'), $operation, $pattern)->periodKey);
        $this->assertNotSame($groupId, $companyA);
    }

    public function testGroupDateResetAndDateTokenNeverRequireGroupTimezone(): void
    {
        [$groupId] = $this->tree();
        $validator = new CanonicalNumberingPatternValidator();
        $pattern = $validator->parse('{YYYY}{SEQ:4}');
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00')), 'num003-group', true);

        $provider = new Num003FakeTimezoneProvider(['group:' . $groupId => 'Asia/Tokyo']);
        $resolved = $this->resolver($provider)->resolve(
            ['scope_type' => 'GROUP', 'reset_policy' => 'YEARLY', 'pattern' => '{YYYY}{SEQ:4}'],
            null,
            $operation,
            $pattern,
        );
        $this->assertSame('2026', $resolved->periodKey);
        $this->assertSame('2026-01-01', $resolved->businessDate);

        $this->assertNull($this->resolver(new Num003ThrowingTimezoneProvider())->resolve(
            ['scope_type' => 'GROUP', 'reset_policy' => 'NEVER'],
            null,
            $operation,
        )->businessTimezone);
        try {
            $this->resolver(new Num003FakeTimezoneProvider())->resolve(
                ['scope_type' => 'GROUP', 'reset_policy' => 'NEVER', 'pattern' => '{YYYY}{SEQ:4}'],
                null,
                $operation,
                $pattern,
            );
            $this->fail('Expected missing group timezone rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingScopePeriodResolver::ERROR_TIMEZONE_REQUIRED, $exception->getErrorCode());
        }
    }

    public function testTimezoneMissingInvalidAndInfrastructureFailuresAreTyped(): void
    {
        [, $companyId] = $this->tree();
        $context = new EvaluationOrganizationContext($companyId, 'COMPANY', 'AUTHORITATIVE');
        $validator = new CanonicalNumberingPatternValidator();
        $pattern = $validator->parse('{YYYY}{SEQ:4}');
        $series = ['scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY', 'pattern' => '{YYYY}{SEQ:4}'];

        foreach ([
            [new Num003FakeTimezoneProvider(), AutoNumberingScopePeriodResolver::ERROR_TIMEZONE_REQUIRED],
            [new Num003InvalidTimezoneProvider(), AutoNumberingScopePeriodResolver::ERROR_TIMEZONE_INVALID],
            [new Num003InfrastructureTimezoneProvider(), AutoNumberingScopePeriodResolver::ERROR_TIMEZONE_RESOLUTION_FAILED],
        ] as [$provider, $expected]) {
            try {
                $this->resolver($provider)->resolve($series, $context, $this->operation(), $pattern);
                $this->fail('Expected timezone failure.');
            } catch (DomainException $exception) {
                $this->assertSame($expected, $exception->getErrorCode());
            }
        }
    }

    public function testCompanyCodeAndOrgCodeRuntimeContextUseCorrectAuthorities(): void
    {
        [, $companyId, $branchId] = $this->tree();
        $validator = new CanonicalNumberingPatternValidator();
        $companyPattern = $validator->parse('{COMPANY_CODE}{SEQ:4}');
        $company = $this->resolver()->resolve(
            ['scope_type' => 'ORGANIZATION', 'reset_policy' => 'NEVER', 'pattern' => '{COMPANY_CODE}{SEQ:4}'],
            new EvaluationOrganizationContext($branchId, 'BRANCH', 'AUTHORITATIVE'),
            $this->operation(),
            $companyPattern,
        );
        $this->assertSame($companyId, $company->companyId);
        $this->assertSame('COMPANY-A', $company->companyCode);
        $this->assertSame('BRANCH-A', $company->organizationCode);

        $orgPattern = $validator->parse('{ORG_CODE}{SEQ:4}');
        $org = $this->resolver()->resolve(
            ['scope_type' => 'ORGANIZATION', 'reset_policy' => 'NEVER', 'pattern' => '{ORG_CODE}{SEQ:4}'],
            new EvaluationOrganizationContext($branchId, 'BRANCH', 'AUTHORITATIVE'),
            $this->operation(),
            $orgPattern,
        );
        $this->assertSame($branchId, $org->scopeOrganizationId);
        $this->assertNull($org->companyId);
    }

    public function testOrganizationCompanyCodeWithoutOwnerFailsAndInvalidScopeMetadataFails(): void
    {
        $groupId = $this->insertOrganization('GROUP', null, 'ROOT');
        $validator = new CanonicalNumberingPatternValidator();
        $pattern = $validator->parse('{COMPANY_CODE}{SEQ:4}');
        try {
            $this->resolver()->resolve(
                ['scope_type' => 'ORGANIZATION', 'reset_policy' => 'NEVER', 'pattern' => '{COMPANY_CODE}{SEQ:4}'],
                new EvaluationOrganizationContext($groupId, 'GROUP', 'AUTHORITATIVE'),
                $this->operation(),
                $pattern,
            );
            $this->fail('Expected GROUP Company-code rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingScopePeriodResolver::ERROR_COMPANY_REQUIRED, $exception->getErrorCode());
        }
        foreach ([['scope_type' => 'LOCAL', 'reset_policy' => 'NEVER'], ['scope_type' => 'GROUP', 'reset_policy' => 'WEEKLY']] as $series) {
            try {
                $this->resolver()->resolve($series, null, $this->operation());
                $this->fail('Expected invalid enum rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingScopePeriodResolver::ERROR_SCOPE_INVALID, $exception->getErrorCode());
            }
        }
    }

    public function testParsedMetadataIsConsumedWithoutReparsingAndCountersAreUntouched(): void
    {
        [, $companyId] = $this->tree();
        $validator = new CanonicalNumberingPatternValidator();
        $parsed = $validator->parse('{YYYY}{SEQ:4}');
        $series = ['scope_type' => 'COMPANY', 'reset_policy' => 'NEVER', 'pattern' => '{SEQ}'];
        $before = (int) $this->db->table('auto_numbering_counters')->countAllResults();
        $resolved = $this->resolver(new Num003FakeTimezoneProvider(['company:' . $companyId => 'UTC']))->resolve(
            $series,
            new EvaluationOrganizationContext($companyId, 'COMPANY', 'AUTHORITATIVE'),
            $this->operation(),
            $parsed,
            'resource.type',
        );

        $this->assertSame('ALL', $resolved->periodKey);
        $this->assertSame('resource.type', $resolved->resourceType);
        $this->assertSame($before, (int) $this->db->table('auto_numbering_counters')->countAllResults());
        try {
            $this->resolver()->resolve($series, new EvaluationOrganizationContext($companyId, 'COMPANY', 'AUTHORITATIVE'), $this->operation());
            $this->fail('Expected parsed metadata requirement.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingScopePeriodResolver::ERROR_PATTERN_METADATA_REQUIRED, $exception->getErrorCode());
        }
    }

    /** @return array{int, int, int} */
    private function tree(): array
    {
        $groupId = $this->insertOrganization('GROUP', null, 'GROUP-ROOT');
        $companyId = $this->insertOrganization('COMPANY', $groupId, 'COMPANY-A');
        $branchId = $this->insertOrganization('BRANCH', $companyId, 'BRANCH-A');

        return [$groupId, $companyId, $branchId];
    }

    /** @return array{int, int, int, int, int, int} */
    private function treeWithSiblingCompany(): array
    {
        [$groupId, $companyA, $branchA] = $this->tree();
        $plantA = $this->insertOrganization('PLANT', $branchA, 'PLANT-A');
        $warehouseA = $this->insertOrganization('WAREHOUSE', $plantA, 'WAREHOUSE-A');
        $companyB = $this->insertOrganization('COMPANY', $groupId, 'COMPANY-B');

        return [$groupId, $companyA, $branchA, $plantA, $warehouseA, $companyB];
    }

    private function insertOrganization(string $type, ?int $parentId, string $code): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId,
            'type' => $type,
            'code' => $code,
            'name' => $type . ' NUM003 Organization',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function resolver(?BusinessTimezoneProvider $provider = null): AutoNumberingScopePeriodResolver
    {
        return new AutoNumberingScopePeriodResolver(timezones: $provider, db: $this->db);
    }

    private function operation(): OperationContext
    {
        return new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'num003-test', true);
    }
}

final class Num003FakeTimezoneProvider implements BusinessTimezoneProvider
{
    public int $calls = 0;

    /** @param array<string, string> $timezones */
    public function __construct(private readonly array $timezones = [])
    {
    }

    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        $this->calls++;
        $key = $companyId === null ? 'group:' . (string) $groupId : 'company:' . (string) $companyId;
        $timezone = $this->timezones[$key] ?? null;

        return $timezone === null ? null : new DateTimeZone($timezone);
    }
}

final class Num003ThrowingTimezoneProvider implements BusinessTimezoneProvider
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        throw new RuntimeException('timezone provider must not be called');
    }
}

final class Num003InvalidTimezoneProvider implements BusinessTimezoneProvider
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        throw new InvalidArgumentException('invalid timezone');
    }
}

final class Num003InfrastructureTimezoneProvider implements BusinessTimezoneProvider
{
    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        throw new RuntimeException('settings unavailable');
    }
}

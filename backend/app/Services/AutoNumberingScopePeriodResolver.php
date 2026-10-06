<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\CompanyOwnerResolver;
use App\Authorization\EvaluationOrganizationContext;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\BusinessTimezoneProvider;
use App\Infrastructure\Settings\BusinessTimezoneResolver;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Resolves authoritative Auto Numbering scope and business period context. */
final class AutoNumberingScopePeriodResolver
{
    public const ERROR_SCOPE_INVALID = 'NUM_SCOPE_INVALID';
    public const ERROR_CONTEXT_REQUIRED = 'NUM_SCOPE_CONTEXT_REQUIRED';
    public const ERROR_SCOPE_RESOLUTION_FAILED = 'NUM_SCOPE_RESOLUTION_FAILED';
    public const ERROR_GROUP_ROOT_INVALID = 'NUM_GROUP_ROOT_INVALID';
    public const ERROR_COMPANY_REQUIRED = 'NUM_COMPANY_CONTEXT_REQUIRED';
    public const ERROR_TIMEZONE_REQUIRED = 'NUM_BUSINESS_TIMEZONE_REQUIRED';
    public const ERROR_TIMEZONE_INVALID = 'NUM_BUSINESS_TIMEZONE_INVALID';
    public const ERROR_TIMEZONE_RESOLUTION_FAILED = 'NUM_BUSINESS_TIMEZONE_RESOLUTION_FAILED';
    public const ERROR_PATTERN_METADATA_REQUIRED = 'NUM_PATTERN_METADATA_REQUIRED';

    /** @var list<string> */
    private const SCOPES = ['GROUP', 'COMPANY', 'ORGANIZATION'];

    /** @var list<string> */
    private const RESETS = ['NEVER', 'YEARLY', 'MONTHLY', 'DAILY'];

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationRepository $organizations = null,
        ?CompanyOwnerResolver $companyOwners = null,
        ?BusinessTimezoneProvider $timezones = null,
        ?ConnectionInterface $db = null,
    ) {
        $connection = $db ?? db_connect();
        $this->organizations = $organizations ?? new OrganizationRepository($connection);
        $this->companyOwners = $companyOwners ?? new CompanyOwnerResolver(null, $connection);
        $this->timezones = $timezones ?? new BusinessTimezoneResolver(\CodeIgniter\Settings\Config\Services::settings());
    }

    private readonly OrganizationRepository $organizations;
    private readonly CompanyOwnerResolver $companyOwners;
    private readonly BusinessTimezoneProvider $timezones;

    /**
     * @param array<string, mixed> $series
     */
    public function resolve(
        array $series,
        ?EvaluationOrganizationContext $evaluationOrganization,
        OperationContext $operation,
        ?ParsedNumberingPattern $pattern = null,
        ?string $resourceType = null,
    ): NumberingScopePeriodContext {
        $scope = $this->enum($series['scope_type'] ?? null, 'scope_type', self::SCOPES);
        $reset = $this->enum($series['reset_policy'] ?? null, 'reset_policy', self::RESETS);
        $this->assertPatternMetadata($series, $pattern);

        $evaluation = $this->authoritativeOrganization($evaluationOrganization, $scope);
        $requiresTimezone = $reset !== 'NEVER' || ($pattern !== null && $pattern->hasDateDiscriminator);
        if ($scope === 'GROUP') {
            $resolved = $this->resolveGroup($resourceType);
        } elseif ($evaluation === null) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['scope_type' => $scope]);
        } elseif ($scope === 'COMPANY') {
            $resolved = $this->resolveCompany($evaluation, $resourceType);
        } else {
            $resolved = $this->resolveOrganization($evaluation, $resourceType, $pattern, $requiresTimezone);
        }

        if (! $this->validOrganizationCode($resolved['organization']['code'] ?? null)) {
            throw new DomainException(self::ERROR_SCOPE_RESOLUTION_FAILED, [
                'scope_organization_id' => $resolved['organization']['id'] ?? null,
            ]);
        }
        if ($resolved['company'] !== null && ! $this->validOrganizationCode($resolved['company']['code'] ?? null)) {
            throw new DomainException(self::ERROR_COMPANY_REQUIRED, [
                'scope_organization_id' => $resolved['organization']['id'] ?? null,
            ]);
        }

        $requiresCompany = $resolved['requires_company'];
        if ($pattern !== null && $pattern->hasCompanyDiscriminator) {
            $requiresCompany = true;
        }
        if ($requiresCompany && $resolved['company'] === null) {
            throw new DomainException(self::ERROR_COMPANY_REQUIRED, [
                'scope_type' => $scope,
                'scope_organization_id' => $resolved['organization']['id'],
            ]);
        }
        if ($requiresTimezone && $resolved['company'] === null && $scope !== 'GROUP') {
            throw new DomainException(self::ERROR_COMPANY_REQUIRED, [
                'scope_type' => $scope,
                'scope_organization_id' => $resolved['organization']['id'],
            ]);
        }

        $timezone = null;
        if ($requiresTimezone) {
            $timezone = $this->timezone(
                $resolved['company'] === null ? null : (int) $resolved['company']['id'],
                $resolved['group_id'],
            );
        }
        $businessDate = $timezone === null ? null : $operation->evaluationTime->businessDate($timezone);

        return new NumberingScopePeriodContext(
            scopeType: $scope,
            scopeOrganizationId: (int) $resolved['organization']['id'],
            periodKey: $this->periodKey($reset, $businessDate),
            businessTimezone: $timezone,
            businessDate: $businessDate,
            companyId: $resolved['company'] === null ? null : (int) $resolved['company']['id'],
            companyCode: $resolved['company']['code'] ?? null,
            organizationCode: (string) $resolved['organization']['code'],
            resourceType: $resourceType === null ? null : $this->resourceType($resourceType),
        );
    }

    /** @param array<string, mixed> $series */
    private function assertPatternMetadata(array $series, ?ParsedNumberingPattern $pattern): void
    {
        if (array_key_exists('pattern', $series) && $pattern === null) {
            throw new DomainException(self::ERROR_PATTERN_METADATA_REQUIRED, ['field' => 'pattern']);
        }
    }

    /** @return array<string, mixed>|null */
    private function authoritativeOrganization(?EvaluationOrganizationContext $context, string $scope): ?array
    {
        if ($scope === 'GROUP') {
            return null;
        }
        if ($context === null) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['scope_type' => $scope]);
        }
        $organization = $this->organizations->find($context->organizationId);
        if ($organization === null) {
            throw new DomainException(self::ERROR_SCOPE_RESOLUTION_FAILED, [
                'organization_id' => $context->organizationId,
            ]);
        }
        if (($organization['status'] ?? null) !== 'ACTIVE'
            || strtoupper((string) ($organization['type'] ?? '')) !== strtoupper($context->organizationType)) {
            throw new DomainException(self::ERROR_SCOPE_RESOLUTION_FAILED, [
                'organization_id' => $context->organizationId,
            ]);
        }

        return $organization;
    }

    /** @return array{organization: array<string, mixed>, company: array<string, mixed>|null, group_id: int|null, requires_company: bool} */
    private function resolveGroup(?string $resourceType): array
    {
        $roots = $this->organizations->findAll(['type' => 'GROUP', 'parent_id' => null]);
        if (count($roots) !== 1) {
            throw new DomainException(self::ERROR_GROUP_ROOT_INVALID, ['resource' => $resourceType]);
        }
        $root = $roots[0];
        if (($root['status'] ?? null) !== 'ACTIVE' || ! $this->validOrganizationCode($root['code'] ?? null)) {
            throw new DomainException(self::ERROR_GROUP_ROOT_INVALID, ['resource' => $resourceType]);
        }

        return ['organization' => $root, 'company' => null, 'group_id' => (int) $root['id'], 'requires_company' => false];
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array{organization: array<string, mixed>, company: array<string, mixed>|null, group_id: int|null, requires_company: bool}
     */
    private function resolveCompany(array $evaluation, ?string $resourceType): array
    {
        try {
            $company = $this->companyOwners->resolve($evaluation['id'] ?? null);
        } catch (DomainException $exception) {
            throw new DomainException(self::ERROR_COMPANY_REQUIRED, [
                'organization_id' => $evaluation['id'] ?? null,
                'resource' => $resourceType,
            ], $exception);
        }
        return [
            'organization' => $company,
            'company' => $company,
            'group_id' => null,
            'requires_company' => true,
        ];
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array{organization: array<string, mixed>, company: array<string, mixed>|null, group_id: int|null, requires_company: bool}
     */
    private function resolveOrganization(array $evaluation, ?string $resourceType, ?ParsedNumberingPattern $pattern, bool $requiresTimezone): array
    {
        $company = null;
        if (($pattern !== null && $pattern->hasCompanyDiscriminator) || $requiresTimezone) {
            try {
                $company = $this->companyOwners->resolve($evaluation['id'] ?? null);
            } catch (DomainException $exception) {
                throw new DomainException(self::ERROR_COMPANY_REQUIRED, [
                    'organization_id' => $evaluation['id'] ?? null,
                    'resource' => $resourceType,
                ], $exception);
            }
        }
        $requiresCompany = ($pattern !== null && $pattern->hasCompanyDiscriminator) || $requiresTimezone;

        return [
            'organization' => $evaluation,
            'company' => $company,
            'group_id' => null,
            'requires_company' => $requiresCompany,
        ];
    }

    private function timezone(?int $companyId, ?int $groupId): DateTimeZone
    {
        try {
            $timezone = $this->timezones->resolve($companyId, $groupId);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException(self::ERROR_TIMEZONE_INVALID, [], $exception);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_TIMEZONE_RESOLUTION_FAILED, [], $exception);
        }
        if (! $timezone instanceof DateTimeZone) {
            throw new DomainException(self::ERROR_TIMEZONE_REQUIRED, [
                'company_id' => $companyId,
                'group_id' => $groupId,
            ]);
        }

        return $timezone;
    }

    private function periodKey(string $reset, ?string $businessDate): string
    {
        if ($reset === 'NEVER') {
            return 'ALL';
        }
        if ($businessDate === null) {
            throw new DomainException(self::ERROR_TIMEZONE_REQUIRED, ['reset_policy' => $reset]);
        }

        return match ($reset) {
            'YEARLY' => substr($businessDate, 0, 4),
            'MONTHLY' => str_replace('-', '', substr($businessDate, 0, 7)),
            'DAILY' => str_replace('-', '', $businessDate),
            default => throw new DomainException(self::ERROR_SCOPE_INVALID, ['field' => 'reset_policy']),
        };
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, string $field, array $allowed): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_SCOPE_INVALID, ['field' => $field]);
        }
        $normalized = strtoupper(trim($value));
        if (! in_array($normalized, $allowed, true)) {
            throw new DomainException(self::ERROR_SCOPE_INVALID, ['field' => $field]);
        }

        return $normalized;
    }

    private function resourceType(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new DomainException(self::ERROR_SCOPE_INVALID, ['field' => 'resource_type']);
        }

        return $normalized;
    }

    private function validOrganizationCode(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}

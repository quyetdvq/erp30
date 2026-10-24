<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeZone;

/** Immutable runtime scope/date context for later Auto Number rendering. */
final readonly class NumberingScopePeriodContext
{
    public function __construct(
        public string $scopeType,
        public int $scopeOrganizationId,
        public string $periodKey,
        public ?DateTimeZone $businessTimezone,
        public ?string $businessDate,
        public ?int $companyId,
        public ?string $companyCode,
        public string $organizationCode,
        public ?string $resourceType,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scope_type' => $this->scopeType,
            'scope_organization_id' => $this->scopeOrganizationId,
            'period_key' => $this->periodKey,
            'business_timezone' => $this->businessTimezone?->getName(),
            'business_date' => $this->businessDate,
            'company_id' => $this->companyId,
            'company_code' => $this->companyCode,
            'organization_code' => $this->organizationCode,
            'resource_type' => $this->resourceType,
        ];
    }
}

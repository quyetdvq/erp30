<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Services\OrganizationService;
use CodeIgniter\Database\ConnectionInterface;

final class CompanyOwnerResolver
{
    public const ERROR_COMPANY_RESOLUTION_FAILED = OrganizationService::ERROR_COMPANY_RESOLUTION_FAILED;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationService $organizations = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->organizationService = $organizations ?? new OrganizationService(null, $db);
    }

    private readonly OrganizationService $organizationService;

    /** @return array<string, mixed> */
    public function resolve(mixed $evaluationOrganizationId): array
    {
        return $this->organizationService->resolveCompanyOwner($evaluationOrganizationId);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Api010FoundationOrganizationContactService;
use CodeIgniter\HTTP\ResponseInterface;

/** Bounded Organization label hydration, with organization.view independently enforced. */
final class OrganizationLookups extends ApiController
{
    public function resolve(): ResponseInterface
    {
        return $this->respondData((new Api010FoundationOrganizationContactService())
            ->resolveOrganizationLabels($this->jsonPayload()['organization_ids'] ?? null));
    }
}

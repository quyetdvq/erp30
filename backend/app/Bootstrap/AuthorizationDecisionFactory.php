<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationResolver;
use App\Authorization\ShieldIdentityAdapter;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use App\Repositories\EmployeeRepository;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\EmployeeAssignmentService;
use App\Services\OrganizationService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless decision composition root; defaults/connection owned by each engine. */
final class AuthorizationDecisionFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function create(
        ?EffectiveProfileResolver $profiles = null,
        ?EvaluationOrganizationResolver $contexts = null,
        ?PolicyRepository $policies = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeAssignmentService $assignments = null,
        ?OrganizationService $organizations = null,
        ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    ): PbacDecisionPort {
        return new PbacDecisionEngine(
            profiles: $profiles,
            contexts: $contexts,
            policies: $policies,
            employees: $employees,
            assignments: $assignments,
            organizations: $organizations,
            identities: $identities,
            db: $db,
            catalog: $catalog,
            workflowStates: $workflowStates,
        );
    }
}

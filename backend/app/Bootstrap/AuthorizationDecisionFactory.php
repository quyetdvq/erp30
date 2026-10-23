<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\EffectiveProfileResolver;
use App\Authorization\EvaluationOrganizationResolver;
use App\Authorization\ShieldIdentityAdapter;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use App\Modules\Authorization\Decision\Application\Ports\EmployeeScopeReadPort;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
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
        ?EmployeeScopeReadPort $employeeScopeRead = null,
    ): PbacDecisionPort {
        $connection = $db ?? db_connect();
        $employeeScopeRead ??= new AuthorizationEmployeeScopeReadAdapter(
            $employees ?? EmployeeStructureFactory::employeeRepository($connection),
            $assignments ?? EmployeeStructureFactory::assignmentRepository($connection),
        );

        return new PbacDecisionEngine(
            employeeScopeRead: $employeeScopeRead,
            profiles: $profiles,
            contexts: $contexts ?? new EvaluationOrganizationResolver(
                new OrganizationRepository($connection),
                $employees, // Keep the legacy injected context resolver collaborator.
                null,
                $connection,
            ),
            policies: $policies,
            organizations: $organizations,
            identities: $identities,
            db: $connection,
            catalog: $catalog,
            workflowStates: $workflowStates,
        );
    }
}

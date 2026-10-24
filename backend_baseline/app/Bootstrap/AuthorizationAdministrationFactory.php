<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Authorization\PolicyParticipantValidator as LegacyPolicyParticipantValidator;
use App\Authorization\PolicyValidator as LegacyPolicyValidator;
use App\Authorization\ShieldIdentityAdapter;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Authorization\Administration\Groups\Application\AuthorizationGroupService as CanonicalGroupService;
use App\Modules\Authorization\Administration\Groups\Application\Ports\GroupRepositoryPort;
use App\Modules\Authorization\Administration\Groups\Infrastructure\Persistence\SqlAuthorizationGroupRepository;
use App\Modules\Authorization\Administration\Policies\Application\PolicyAdministrationService as CanonicalPolicyService;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;
use App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository;
use App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator as CanonicalPolicyValidator;
use App\Repositories\ResourceCatalogRepository;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless composition entrypoints; never substitutes caller-provided collaborators. */
final class AuthorizationAdministrationFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function group(
        ?ConnectionInterface $db = null,
        ?GroupRepositoryPort $repository = null,
        ?AuthorizationService $authorization = null,
        ?ShieldIdentityAdapter $identities = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): CanonicalGroupService {
        return new CanonicalGroupService($repository, $authorization, $identities, $db, $mutationContext, $clock);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function policy(
        ?ConnectionInterface $db = null,
        ?PolicyAdministrationRepositoryPort $repository = null,
        ?LegacyPolicyValidator $validator = null,
        ?AuthorizationService $authorization = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): CanonicalPolicyService {
        return new CanonicalPolicyService($repository, $validator, $authorization, $db, $mutationContext, $clock);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function groupRepository(?ConnectionInterface $db = null): SqlAuthorizationGroupRepository
    {
        return new SqlAuthorizationGroupRepository($db);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function policyRepository(?ConnectionInterface $db = null): SqlPolicyRepository
    {
        return new SqlPolicyRepository($db);
    }

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function policyValidator(
        PolicyAdministrationRepositoryPort $repository,
        ?LegacyPolicyParticipantValidator $participants = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
        ?ConnectionInterface $db = null,
    ): CanonicalPolicyValidator {
        if ($catalog !== null && $workflowStates !== null) {
            return new CanonicalPolicyValidator($repository, $participants, $catalog, $workflowStates);
        }

        $connection = $db ?? db_connect();
        return new CanonicalPolicyValidator(
            $repository,
            $participants,
            $catalog ?? new ResourceCatalogRepository($connection),
            $workflowStates ?? new ResourceWorkflowStateResolver($connection),
        );
    }
}

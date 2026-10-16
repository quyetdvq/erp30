<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\OrganizationFactory;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Organization\Application\OrganizationService as ModuleOrganizationService;
use App\Modules\Organization\Contracts\OrganizationErrors;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New organization behavior belongs in the module. */
final class OrganizationService
{
    public const ERROR_SECOND_GROUP_ROOT = OrganizationErrors::ERROR_SECOND_GROUP_ROOT;
    public const ERROR_INVALID_PARENT = OrganizationErrors::ERROR_INVALID_PARENT;
    public const ERROR_HIERARCHY_CYCLE = OrganizationErrors::ERROR_HIERARCHY_CYCLE;
    public const ERROR_COMPANY_RESOLUTION_FAILED = OrganizationErrors::ERROR_COMPANY_RESOLUTION_FAILED;
    public const ERROR_ACTIVE_DATE_INVALID = OrganizationErrors::ERROR_ACTIVE_DATE_INVALID;
    public const ERROR_WARD_PROVINCE_MISMATCH = OrganizationErrors::ERROR_WARD_PROVINCE_MISMATCH;
    public const ERROR_REFERENCE_INVALID = OrganizationErrors::ERROR_REFERENCE_INVALID;
    public const ERROR_MUTATION_FAILED = OrganizationErrors::ERROR_MUTATION_FAILED;

    private readonly ModuleOrganizationService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationRepository $repository = null,
        ?ConnectionInterface $db = null,
        ?DomainLockService $domainLock = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->delegate = OrganizationFactory::service(
            repository: $repository, db: $db, domainLock: $domainLock,
            mutationContext: $mutationContext, clock: $clock,
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->delegate->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        return $this->delegate->update($id, $data);
    }

    public function inactivate(int $id): bool
    {
        return $this->delegate->inactivate($id);
    }

    /** @return list<array<string, mixed>> */
    public function resolveAncestorChain(int $organizationId): array
    {
        return $this->delegate->resolveAncestorChain($organizationId);
    }

    /** @return array<string, mixed>|null */
    public function resolveCompanyAncestor(int $organizationId): ?array
    {
        return $this->delegate->resolveCompanyAncestor($organizationId);
    }

    /** @return array<string, mixed> */
    public function resolveCompanyOwner(mixed $organizationId): array
    {
        return $this->delegate->resolveCompanyOwner($organizationId);
    }
}

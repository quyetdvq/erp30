<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\EmployeeStructureFactory;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\EmployeeStructure\Department\Application\DepartmentService as ModuleDepartmentService;
use App\Modules\EmployeeStructure\Department\Contracts\DepartmentErrors;
use App\Repositories\DepartmentRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New Department behavior belongs in EmployeeStructure. */
final class DepartmentService
{
    public const ERROR_CODE_DUPLICATE = DepartmentErrors::ERROR_CODE_DUPLICATE;
    public const ERROR_REFERENCE_INVALID = DepartmentErrors::ERROR_REFERENCE_INVALID;
    public const ERROR_HIERARCHY_CYCLE = DepartmentErrors::ERROR_HIERARCHY_CYCLE;
    public const ERROR_STATUS_INVALID = DepartmentErrors::ERROR_STATUS_INVALID;
    public const ERROR_MUTATION_FAILED = DepartmentErrors::ERROR_MUTATION_FAILED;
    private readonly ModuleDepartmentService $delegate;
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(?DepartmentRepository $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)
    {
        $this->delegate = EmployeeStructureFactory::departmentService(repository: $repository, db: $db, domainLock: $domainLock, mutationContext: $mutationContext, clock: $clock);
    }
    /** @param array<string,mixed> $data */ public function create(array $data): int { return $this->delegate->create($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
}

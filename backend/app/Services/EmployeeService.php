<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\EmployeeStructureFactory;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\EmployeeStructure\Employee\Application\EmployeeService as ModuleEmployeeService;
use App\Modules\EmployeeStructure\Employee\Contracts\EmployeeErrors;
use App\Repositories\EmployeeRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New Employee behavior belongs in EmployeeStructure. */
final class EmployeeService
{
    public const ERROR_MANAGER_SELF = EmployeeErrors::ERROR_MANAGER_SELF;
    public const ERROR_MANAGER_CYCLE = EmployeeErrors::ERROR_MANAGER_CYCLE;
    public const ERROR_MANAGER_INACTIVE = EmployeeErrors::ERROR_MANAGER_INACTIVE;
    public const ERROR_MANAGER_REFERENCE_INVALID = EmployeeErrors::ERROR_MANAGER_REFERENCE_INVALID;
    public const ERROR_MANAGER_MUTATION_FAILED = EmployeeErrors::ERROR_MANAGER_MUTATION_FAILED;
    public const ERROR_CODE_DUPLICATE = EmployeeErrors::ERROR_CODE_DUPLICATE;
    public const ERROR_USER_DUPLICATE = EmployeeErrors::ERROR_USER_DUPLICATE;
    public const ERROR_STATUS_INVALID = EmployeeErrors::ERROR_STATUS_INVALID;
    public const ERROR_GENDER_INVALID = EmployeeErrors::ERROR_GENDER_INVALID;
    private readonly ModuleEmployeeService $delegate;
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(?EmployeeRepository $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)
    {
        $this->delegate = EmployeeStructureFactory::employeeService(repository: $repository, db: $db, domainLock: $domainLock, mutationContext: $mutationContext, clock: $clock);
    }
    /** @param array<string,mixed> $data */ public function create(array $data): int { return $this->delegate->create($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
    public function setManager(int $employeeId, ?int $managerEmployeeId): bool { return $this->delegate->setManager($employeeId, $managerEmployeeId); }
    public function inactivate(int $employeeId): bool { return $this->delegate->inactivate($employeeId); }
}

<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\EmployeeStructure\Assignment\Application\EmployeeAssignmentService;
use App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface;
use App\Modules\EmployeeStructure\Assignment\Infrastructure\Persistence\SqlEmployeeAssignmentRepository;
use App\Modules\EmployeeStructure\Department\Application\DepartmentService;
use App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface;
use App\Modules\EmployeeStructure\Department\Infrastructure\Persistence\SqlDepartmentRepository;
use App\Modules\EmployeeStructure\Employee\Application\EmployeeService;
use App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface;
use App\Modules\EmployeeStructure\Employee\Infrastructure\Persistence\SqlEmployeeRepository;
use App\Modules\EmployeeStructure\Position\Application\PositionService;
use App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface;
use App\Modules\EmployeeStructure\Position\Infrastructure\Persistence\SqlPositionRepository;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use CodeIgniter\Database\ConnectionInterface;

/** Explicit Employee Structure wiring with no retained connection or request state. */
final class EmployeeStructureFactory
{
    private function __construct() {}

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function departmentRepository(?ConnectionInterface $db = null): SqlDepartmentRepository { return new SqlDepartmentRepository(db: $db); }
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function positionRepository(?ConnectionInterface $db = null): SqlPositionRepository { return new SqlPositionRepository(db: $db); }
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function employeeRepository(?ConnectionInterface $db = null): SqlEmployeeRepository { return new SqlEmployeeRepository(db: $db); }
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function assignmentRepository(?ConnectionInterface $db = null): SqlEmployeeAssignmentRepository { return new SqlEmployeeAssignmentRepository(db: $db); }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function departmentService(?DepartmentRepositoryInterface $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null): DepartmentService
    {
        $connection = $db ?? db_connect();
        return new DepartmentService(repository: $repository ?? self::departmentRepository($connection), db: $connection, domainLock: $domainLock, mutationContext: $mutationContext, clock: $clock);
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function positionService(?PositionRepositoryInterface $repository = null, ?ConnectionInterface $db = null, ?MutationContext $mutationContext = null, ?Clock $clock = null): PositionService
    {
        $connection = $db ?? db_connect();
        return new PositionService(repository: $repository ?? self::positionRepository($connection), db: $connection, mutationContext: $mutationContext, clock: $clock);
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function employeeService(?EmployeeRepositoryInterface $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null): EmployeeService
    {
        $connection = $db ?? db_connect();
        return new EmployeeService(repository: $repository ?? self::employeeRepository($connection), db: $connection, domainLock: $domainLock, mutationContext: $mutationContext, clock: $clock);
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function assignmentService(
        ?EmployeeAssignmentRepositoryInterface $repository = null,
        ?ConnectionInterface $db = null,
        ?DomainLockService $domainLock = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?EmployeeRepositoryInterface $employeeRepository = null,
        ?OrganizationRepositoryInterface $organizationRepository = null,
    ): EmployeeAssignmentService {
        $connection = $db ?? db_connect();
        return new EmployeeAssignmentService(
            repository: $repository ?? self::assignmentRepository($connection),
            db: $connection,
            employeeRepository: $employeeRepository ?? self::employeeRepository($connection),
            organizationRepository: $organizationRepository ?? OrganizationFactory::repository($connection),
            domainLock: $domainLock,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }
}

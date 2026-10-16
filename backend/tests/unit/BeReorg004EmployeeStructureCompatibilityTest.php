<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Compatibility is frozen against the accepted BE-REORG-003_FIX01 checkpoint. */
final class BeReorg004EmployeeStructureCompatibilityTest extends TestCase
{
    public function testDepartmentServiceContractMatchesCheckpoint(): void { $this->assertClassContract(App\Services\DepartmentService::class, ['ERROR_CODE_DUPLICATE'=>'DEPARTMENT_CODE_DUPLICATE','ERROR_REFERENCE_INVALID'=>'DEPARTMENT_REFERENCE_INVALID','ERROR_HIERARCHY_CYCLE'=>'DEPARTMENT_HIERARCHY_CYCLE','ERROR_STATUS_INVALID'=>'DEPARTMENT_STATUS_INVALID','ERROR_MUTATION_FAILED'=>'DEPARTMENT_MUTATION_FAILED'], ['create','update'], ['repository','db','domainLock','mutationContext','clock']); }
    public function testPositionServiceContractMatchesCheckpoint(): void { $this->assertClassContract(App\Services\PositionService::class, ['ERROR_CODE_DUPLICATE'=>'POSITION_CODE_DUPLICATE','ERROR_REFERENCE_INVALID'=>'POSITION_REFERENCE_INVALID','ERROR_STATUS_INVALID'=>'POSITION_STATUS_INVALID'], ['create','update'], ['repository','db','mutationContext','clock']); }
    public function testEmployeeServiceContractMatchesCheckpoint(): void { $this->assertClassContract(App\Services\EmployeeService::class, ['ERROR_MANAGER_SELF'=>'EMP_MANAGER_SELF','ERROR_MANAGER_CYCLE'=>'EMP_MANAGER_CYCLE','ERROR_MANAGER_INACTIVE'=>'EMP_MANAGER_INACTIVE','ERROR_MANAGER_REFERENCE_INVALID'=>'EMP_MANAGER_REFERENCE_INVALID','ERROR_MANAGER_MUTATION_FAILED'=>'EMP_MANAGER_MUTATION_FAILED','ERROR_CODE_DUPLICATE'=>'EMPLOYEE_CODE_DUPLICATE','ERROR_USER_DUPLICATE'=>'EMPLOYEE_USER_DUPLICATE','ERROR_STATUS_INVALID'=>'EMPLOYEE_STATUS_INVALID','ERROR_GENDER_INVALID'=>'EMPLOYEE_GENDER_INVALID'], ['create','inactivate','setManager','update'], ['repository','db','domainLock','mutationContext','clock']); }
    public function testAssignmentServiceContractMatchesCheckpoint(): void { $this->assertClassContract(App\Services\EmployeeAssignmentService::class, ['ERROR_REFERENCE_INVALID'=>'EMP_ASSIGNMENT_REFERENCE_INVALID','ERROR_REFERENCE_INACTIVE'=>'EMP_ASSIGNMENT_REFERENCE_INACTIVE','ERROR_DATE_INVALID'=>'EMP_ASSIGNMENT_DATE_INVALID','ERROR_PRIMARY_OVERLAP'=>'EMP_PRIMARY_ASSIGNMENT_OVERLAP','ERROR_ASSIGNMENT_NOT_FOUND'=>'EMP_ASSIGNMENT_NOT_FOUND','ERROR_EMPLOYEE_IMMUTABLE'=>'EMP_ASSIGNMENT_EMPLOYEE_IMMUTABLE','ERROR_EVALUATION_ORGANIZATION_INVALID'=>'EMP_ASSIGNMENT_EVALUATION_ORGANIZATION_INVALID','ERROR_MUTATION_FAILED'=>'EMP_ASSIGNMENT_MUTATION_FAILED'], ['create','end','findEffective','findEffectiveForEmployees','resolveDefaultActorContext','resolveEligible','resolvePrimary','update'], ['repository','db','domainLock','mutationContext','clock','employeeRepository','organizationRepository']); }

    public function testDepartmentRepositoryContractMatchesCheckpoint(): void { $this->assertClassContract(App\Repositories\DepartmentRepository::class, $this->repositoryErrors('DEPARTMENT'), ['find','findAll','findByCode','insert','update'], ['db']); }
    public function testPositionRepositoryContractMatchesCheckpoint(): void { $this->assertClassContract(App\Repositories\PositionRepository::class, $this->repositoryErrors('POSITION'), ['find','findAll','findByCode','insert','update'], ['db']); }
    public function testEmployeeRepositoryContractMatchesCheckpoint(): void { $this->assertClassContract(App\Repositories\EmployeeRepository::class, ['ERROR_PERSISTENCE_FAILED'=>'EMP_PERSISTENCE_FAILED','ERROR_CONNECTION_INVALID'=>'EMP_CONNECTION_INVALID','ERROR_UPDATE_EMPTY'=>'EMP_UPDATE_EMPTY','ERROR_FIELD_UNSUPPORTED'=>'EMP_FIELD_UNSUPPORTED','ERROR_FIELD_REQUIRED'=>'EMP_FIELD_REQUIRED'], ['find','findAll','findByCode','findByUserId','insert','update'], ['db']); }
    public function testAssignmentRepositoryContractMatchesCheckpoint(): void { $this->assertClassContract(App\Repositories\EmployeeAssignmentRepository::class, $this->repositoryErrors('EMP_ASSIGNMENT'), ['departmentExists','departmentIsActive','find','findAll','findByEmployee','findEffective','findEffectiveForEmployees','findOverlappingPrimary','insert','positionExists','positionIsActive','update'], ['db']); }

    public function testDepartmentErrorsExposeExactCodes(): void { $this->assertErrorValues(App\Modules\EmployeeStructure\Department\Contracts\DepartmentErrors::class, ['DEPARTMENT_CODE_DUPLICATE','DEPARTMENT_REFERENCE_INVALID','DEPARTMENT_HIERARCHY_CYCLE','DEPARTMENT_STATUS_INVALID','DEPARTMENT_MUTATION_FAILED','DEPARTMENT_PERSISTENCE_FAILED','DEPARTMENT_CONNECTION_INVALID','DEPARTMENT_UPDATE_EMPTY','DEPARTMENT_FIELD_UNSUPPORTED','DEPARTMENT_FIELD_REQUIRED']); }
    public function testPositionErrorsExposeExactCodes(): void { $this->assertErrorValues(App\Modules\EmployeeStructure\Position\Contracts\PositionErrors::class, ['POSITION_CODE_DUPLICATE','POSITION_REFERENCE_INVALID','POSITION_STATUS_INVALID','POSITION_PERSISTENCE_FAILED','POSITION_CONNECTION_INVALID','POSITION_UPDATE_EMPTY','POSITION_FIELD_UNSUPPORTED','POSITION_FIELD_REQUIRED']); }
    public function testEmployeeErrorsExposeExactCodes(): void { $this->assertErrorValues(App\Modules\EmployeeStructure\Employee\Contracts\EmployeeErrors::class, ['EMP_MANAGER_SELF','EMP_MANAGER_CYCLE','EMP_MANAGER_INACTIVE','EMP_MANAGER_REFERENCE_INVALID','EMP_MANAGER_MUTATION_FAILED','EMPLOYEE_CODE_DUPLICATE','EMPLOYEE_USER_DUPLICATE','EMPLOYEE_STATUS_INVALID','EMPLOYEE_GENDER_INVALID','EMP_PERSISTENCE_FAILED','EMP_CONNECTION_INVALID','EMP_UPDATE_EMPTY','EMP_FIELD_UNSUPPORTED','EMP_FIELD_REQUIRED']); }
    public function testAssignmentErrorsExposeExactCodes(): void { $this->assertErrorValues(App\Modules\EmployeeStructure\Assignment\Contracts\EmployeeAssignmentErrors::class, ['EMP_ASSIGNMENT_REFERENCE_INVALID','EMP_ASSIGNMENT_REFERENCE_INACTIVE','EMP_ASSIGNMENT_DATE_INVALID','EMP_PRIMARY_ASSIGNMENT_OVERLAP','EMP_ASSIGNMENT_NOT_FOUND','EMP_ASSIGNMENT_EMPLOYEE_IMMUTABLE','EMP_ASSIGNMENT_EVALUATION_ORGANIZATION_INVALID','EMP_ASSIGNMENT_MUTATION_FAILED','EMP_ASSIGNMENT_PERSISTENCE_FAILED','EMP_ASSIGNMENT_CONNECTION_INVALID','EMP_ASSIGNMENT_UPDATE_EMPTY','EMP_ASSIGNMENT_FIELD_UNSUPPORTED','EMP_ASSIGNMENT_FIELD_REQUIRED']); }

    public function testDepartmentPortMatchesLegacyMethods(): void { $this->assertPort(App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface::class, ['find','findAll','findByCode','insert','update']); }
    public function testPositionPortMatchesLegacyMethods(): void { $this->assertPort(App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface::class, ['find','findAll','findByCode','insert','update']); }
    public function testEmployeePortMatchesLegacyMethods(): void { $this->assertPort(App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface::class, ['find','findAll','findByCode','findByUserId','insert','update']); }
    public function testAssignmentPortMatchesLegacyMethods(): void { $this->assertPort(App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface::class, ['departmentExists','departmentIsActive','find','findAll','findByEmployee','findEffective','findEffectiveForEmployees','findOverlappingPrimary','insert','positionExists','positionIsActive','update']); }

    public function testLegacyRepositoriesImplementModulePorts(): void
    {
        $this->assertTrue(is_a(App\Repositories\DepartmentRepository::class, App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Repositories\PositionRepository::class, App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Repositories\EmployeeRepository::class, App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Repositories\EmployeeAssignmentRepository::class, App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface::class, true));
    }

    public function testCanonicalRepositoriesImplementModulePorts(): void
    {
        $this->assertTrue(is_a(App\Modules\EmployeeStructure\Department\Infrastructure\Persistence\SqlDepartmentRepository::class, App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Modules\EmployeeStructure\Position\Infrastructure\Persistence\SqlPositionRepository::class, App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Modules\EmployeeStructure\Employee\Infrastructure\Persistence\SqlEmployeeRepository::class, App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Modules\EmployeeStructure\Assignment\Infrastructure\Persistence\SqlEmployeeAssignmentRepository::class, App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface::class, true));
    }

    /** @param class-string $class @param array<string,string> $constants @param list<string> $methods @param list<string> $ctorNames */
    private function assertClassContract(string $class, array $constants, array $methods, array $ctorNames): void
    {
        $r = new ReflectionClass($class);
        $this->assertTrue($r->isFinal());
        $actualConstants = [];
        foreach ($r->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) { if (str_starts_with($constant->getName(), 'ERROR_')) { $actualConstants[$constant->getName()] = $constant->getValue(); } }
        $this->assertSame($constants, $actualConstants);
        $actualMethods = [];
        foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $method) { if ($method->getDeclaringClass()->getName() === $class && $method->getName() !== '__construct') { $actualMethods[] = $method->getName(); } }
        sort($actualMethods); sort($methods); $this->assertSame($methods, $actualMethods);
        $ctor = $r->getConstructor(); $this->assertNotNull($ctor);
        $this->assertSame($ctorNames, array_map(static fn (ReflectionParameter $p): string => $p->getName(), $ctor->getParameters()));
        foreach ($ctor->getParameters() as $p) { $this->assertTrue($p->isOptional()); $this->assertNull($p->getDefaultValue()); }
    }

    /** @return array<string,string> */
    private function repositoryErrors(string $prefix): array { return ['ERROR_PERSISTENCE_FAILED'=>$prefix.'_PERSISTENCE_FAILED','ERROR_CONNECTION_INVALID'=>$prefix.'_CONNECTION_INVALID','ERROR_UPDATE_EMPTY'=>$prefix.'_UPDATE_EMPTY','ERROR_FIELD_UNSUPPORTED'=>$prefix.'_FIELD_UNSUPPORTED','ERROR_FIELD_REQUIRED'=>$prefix.'_FIELD_REQUIRED']; }
    /** @param class-string $class @param list<string> $expected */
    private function assertErrorValues(string $class, array $expected): void { $values = array_values((new ReflectionClass($class))->getConstants()); $this->assertSame($expected, $values); }
    /** @param class-string $class @param list<string> $expected */
    private function assertPort(string $class, array $expected): void { $actual = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass($class))->getMethods()); sort($actual); sort($expected); $this->assertSame($expected, $actual); }
}

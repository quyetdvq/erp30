<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Employee Structure boundaries are an architecture contract. */
final class BeReorg004EmployeeStructureBoundaryTest extends TestCase
{
    public function testCanonicalModuleFilesExistInFourFocusedSubdomains(): void
    {
        foreach ([
            'Department', 'Position', 'Employee', 'Assignment',
        ] as $domain) {
            $root = dirname(__DIR__, 2) . '/app/Modules/EmployeeStructure/' . $domain;
            $this->assertDirectoryExists($root);
            $this->assertFileExists($root . '/Application/' . ($domain === 'Assignment' ? 'EmployeeAssignmentService' : $domain . 'Service') . '.php');
            $this->assertDirectoryExists($root . '/Application/Ports');
            $this->assertDirectoryExists($root . '/Infrastructure/Persistence');
            $this->assertDirectoryExists($root . '/Contracts');
        }
    }

    public function testRepositoryPortsExposeOnlyExistingPersistenceOperations(): void
    {
        $expect = [
            App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface::class => ['find','findAll','findByCode','insert','update'],
            App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface::class => ['find','findAll','findByCode','insert','update'],
            App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface::class => ['find','findAll','findByCode','findByUserId','insert','update'],
            App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface::class => ['departmentExists','departmentIsActive','find','findAll','findByEmployee','findEffective','findEffectiveForEmployees','findOverlappingPrimary','insert','positionExists','positionIsActive','update'],
        ];
        foreach ($expect as $class => $methods) {
            $actual = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass($class))->getMethods());
            sort($actual);
            sort($methods);
            $this->assertSame($methods, $actual, $class);
        }
    }

    public function testCanonicalServicesDoNotDependOnLegacyRepositoriesApiIamOrContact(): void
    {
        foreach ($this->canonicalApplicationFiles() as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('App\\Repositories\\', $source, $file);
            $this->assertStringNotContainsString('App\\Authorization\\', $source, $file);
            $this->assertStringNotContainsString('App\\Controllers\\', $source, $file);
            $this->assertStringNotContainsString('App\\Modules\\Contact\\', $source, $file);
            $this->assertStringNotContainsString('Api020EmployeeStructureService', $source, $file);
        }
    }

    public function testAssignmentCrossModuleDependencyIsOrganizationPortOnly(): void
    {
        $file = dirname(__DIR__, 2) . '/app/Modules/EmployeeStructure/Assignment/Application/EmployeeAssignmentService.php';
        $source = (string) file_get_contents($file);
        $this->assertStringContainsString('App\\Modules\\Organization\\Application\\Ports\\OrganizationRepositoryInterface', $source);
        $this->assertStringNotContainsString('App\\Repositories\\OrganizationRepository', $source);
        $this->assertStringNotContainsString('SqlOrganizationRepository', $source);
    }

    public function testLegacyEntrypointsAreFinalExplicitDelegatesWithoutInheritanceOrMagicDispatch(): void
    {
        foreach ([
            App\Services\DepartmentService::class, App\Services\PositionService::class,
            App\Services\EmployeeService::class, App\Services\EmployeeAssignmentService::class,
            App\Repositories\DepartmentRepository::class, App\Repositories\PositionRepository::class,
            App\Repositories\EmployeeRepository::class, App\Repositories\EmployeeAssignmentRepository::class,
        ] as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isFinal(), $class);
            $this->assertFalse($reflection->getParentClass(), $class);
            $this->assertFalse($reflection->hasMethod('__call'), $class);
            $this->assertTrue($reflection->hasProperty('delegate'), $class);
            $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $reflection->getProperties());
            sort($properties);
            $this->assertSame(['delegate'], $properties, $class . ' must not retain legacy constructor state.');
        }
    }

    public function testFactoryRetainsNoState(): void
    {
        $reflection = new ReflectionClass(App\Bootstrap\EmployeeStructureFactory::class);
        $this->assertSame([], $reflection->getProperties());
    }

    /** @return list<string> */
    private function canonicalApplicationFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/app/Modules/EmployeeStructure';
        return [
            $root . '/Department/Application/DepartmentService.php',
            $root . '/Position/Application/PositionService.php',
            $root . '/Employee/Application/EmployeeService.php',
            $root . '/Assignment/Application/EmployeeAssignmentService.php',
        ];
    }
}

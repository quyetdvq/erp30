<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Repositories\EmployeeRepository;
use App\Services\EmployeeService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Emp001EmployeeRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testEmployeeCrudAndLookups(): void
    {
        $repository = new EmployeeRepository($this->db);
        $id = $repository->insert([
            'user_id' => 910001,
            'employee_code' => 'EMP-REPO-001',
            'employee_name' => 'Repository Employee',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame('EMP-REPO-001', $repository->find($id)['employee_code']);
        $this->assertSame($id, (int) $repository->findByCode('EMP-REPO-001')['id']);
        $this->assertSame($id, (int) $repository->findByUserId(910001)['id']);
        $this->assertCount(1, $repository->findAll(['status' => 'ACTIVE']));
        $this->assertTrue($repository->update($id, ['employee_name' => 'Updated Employee']));
        $this->assertSame('Updated Employee', $repository->find($id)['employee_name']);
    }

    public function testManagerRulesExposeStableCodesWithoutMessageParsing(): void
    {
        $service = new EmployeeService(null, $this->db);
        $managerId = $service->create($this->employee('EMP-MANAGER', 'Manager'));
        $employeeId = $service->create($this->employee('EMP-REPORT', 'Report'));

        $service->setManager($employeeId, $managerId);
        try {
            $service->setManager($managerId, $employeeId);
            $this->fail('Expected manager cycle rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_MANAGER_CYCLE, $exception->getErrorCode());
            $this->assertSame($managerId, $exception->getContext()['employee_id']);
            $this->assertSame($employeeId, $exception->getContext()['manager_employee_id']);
            $this->assertStringNotContainsString('Management cycle', $exception->getMessage());
        }
    }

    public function testSameInvariantHasTheSameIdentityForViAndEn(): void
    {
        $service = new EmployeeService(null, $this->db);
        $employeeId = $service->create($this->employee('EMP-LOCALE', 'Locale'));

        $viCode = $this->managerErrorCode($service, $employeeId, $employeeId, 'vi');
        $enCode = $this->managerErrorCode($service, $employeeId, $employeeId, 'en');

        $this->assertSame(EmployeeService::ERROR_MANAGER_SELF, $viCode);
        $this->assertSame($viCode, $enCode);
    }

    public function testInactiveManagerAndConcurrencyFailureExposeStableCodes(): void
    {
        $service = new EmployeeService(null, $this->db);
        $inactiveManagerId = $service->create($this->employee('EMP-INACTIVE', 'Inactive'));
        $employeeId = $service->create($this->employee('EMP-SELF', 'Self'));
        $service->inactivate($inactiveManagerId);

        try {
            $service->setManager($employeeId, $inactiveManagerId);
            $this->fail('Expected inactive manager rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_MANAGER_INACTIVE, $exception->getErrorCode());
        }

        $lock = new class implements DomainLockService {
            public function acquire(string $domainKey, int $timeoutSeconds = 5): void
            {
                throw new RuntimeException('lock unavailable');
            }

            public function release(string $domainKey): void
            {
            }

            public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
            {
                $this->acquire($domainKey, $timeoutSeconds);

                return $callback();
            }
        };

        try {
            (new EmployeeService(null, $this->db, $lock))->setManager($employeeId, null);
            $this->fail('Expected manager concurrency rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_CONCURRENT_MODIFICATION, $exception->getErrorCode());
            $this->assertSame('lock unavailable', $exception->getDiagnosticMessage());
            $this->assertStringNotContainsString('lock unavailable', $exception->getMessage());
        }
    }

    public function testMissingManagerReferenceUsesStableDomainCode(): void
    {
        $service = new EmployeeService(null, $this->db);
        $employeeId = $service->create($this->employee('EMP-MISSING', 'Missing'));

        try {
            $service->setManager($employeeId, 999999999);
            $this->fail('Expected missing manager rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(EmployeeService::ERROR_INVALID_REFERENCE, $exception->getErrorCode());
            $this->assertSame(999999999, $exception->getContext()['manager_employee_id']);
        }
    }

    private function managerErrorCode(EmployeeService $service, int $employeeId, int $managerId, string $locale): string
    {
        \App\Infrastructure\RequestContext::begin('employee-locale-test', $locale);
        try {
            $service->setManager($employeeId, $managerId);
        } catch (DomainException $exception) {
            \App\Infrastructure\RequestContext::reset();

            return $exception->getErrorCode();
        }
        \App\Infrastructure\RequestContext::reset();

        $this->fail('Expected manager invariant rejection.');

        return '';
    }

    /** @return array<string, mixed> */
    private function employee(string $code, string $name): array
    {
        return [
            'employee_code' => $code . '-' . uniqid(),
            'employee_name' => $name,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }
}

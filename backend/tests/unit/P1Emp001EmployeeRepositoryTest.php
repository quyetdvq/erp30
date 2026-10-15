<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Repositories\EmployeeRepository;
use App\Repositories\LifecycleRepository;
use App\Services\EmployeeService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Emp001EmployeeRepositoryTest extends CIUnitTestCase
{
    private bool $fixtureTransactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        // A dedicated non-persistent connection is essential here: MySQL
        // TEMPORARY tables shadow any permanent tables of the same name in
        // this session only and disappear when the connection is closed.
        // Never run DatabaseTestTrait migrations against a partially migrated
        // erp_test database: its cross-namespace rollbacks are destructive.
        if (ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('EMP_TEST_REQUIRES_TESTING_ENVIRONMENT');
        }
        $this->db = \Config\Database::connect('tests', false);
        $this->db->initialize();
        try {
            $this->createTemporarySchema();
            if (! $this->db->transBegin()) {
                throw new \RuntimeException('EMP_TEST_TRANSACTION_START_FAILED');
            }
            $this->fixtureTransactionStarted = true;
            $this->setUpEmployeeLifecycleFixture();
        } catch (\Throwable $exception) {
            if ($this->fixtureTransactionStarted) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                $this->fixtureTransactionStarted = false;
            }
            $this->db->close();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->fixtureTransactionStarted) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                $this->fixtureTransactionStarted = false;
            }
            // Closing this connection automatically drops ALL TEMPORARY tables.
            if (isset($this->db)) {
                $this->db->close();
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Create only the tables actually exercised by EmployeeService and the
     * Workflow resolver. No permanent DDL, full migrations, or schema cleanup
     * is required; other PHPUnit suites keep seeing the real erp_test tables.
     *
     * MySQL temporary tables cannot declare foreign keys. These tests assert
     * domain-level manager validation, NOT schema/FK constraints (which are
     * covered by the database migration tests).
     */
    private function createTemporarySchema(): void
    {
        $schema = [
            'employees' => <<<'SQL'
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NULL,
                `employee_code` VARCHAR(50) NOT NULL,
                `employee_name` VARCHAR(255) NOT NULL,
                `gender` VARCHAR(20) NULL,
                `date_of_birth` DATE NULL,
                `place_of_origin` VARCHAR(255) NULL,
                `hire_date` DATE NULL,
                `manager_employee_id` BIGINT UNSIGNED NULL,
                `description` TEXT NULL,
                `status` VARCHAR(50) NOT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `user_id` (`user_id`),
                UNIQUE KEY `employee_code` (`employee_code`),
                KEY `manager_employee_id` (`manager_employee_id`)
                SQL,
            'lifecycles' => <<<'SQL'
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `code` (`code`)
                SQL,
            'workflow_states' => <<<'SQL'
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `code` (`code`)
                SQL,
            'lifecycle_states' => <<<'SQL'
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `lifecycle_id` BIGINT UNSIGNED NOT NULL,
                `state_id` BIGINT UNSIGNED NOT NULL,
                `sequence` INT NOT NULL,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_final` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `lifecycle_state` (`lifecycle_id`, `state_id`)
                SQL,
            'resources' => <<<'SQL'
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `lifecycle_id` BIGINT UNSIGNED NULL,
                `type` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `domain` VARCHAR(100) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `type` (`type`)
                SQL,
        ];

        foreach ($schema as $table => $definition) {
            $name = $this->db->prefixTable($table);
            // DBPrefix comes from test configuration, never from test input.
            if (! preg_match('/^[A-Za-z0-9_]+$/D', $name)) {
                throw new \RuntimeException('EMP_TEST_INVALID_TABLE_PREFIX');
            }
            $sql = sprintf("CREATE TEMPORARY TABLE `%s` (\n%s\n) ENGINE=InnoDB", $name, $definition);
            $result = $this->db->query($sql);
            if ($result === false) {
                throw new \RuntimeException('EMP_TEST_TEMPORARY_TABLE_FAILED: ' . $table);
            }
        }
    }

    private function setUpEmployeeLifecycleFixture(): void
    {
        // Employee is NOT one of the ten native-State exceptions. Register it
        // in the Resource catalog and give it a real ACTIVE/INACTIVE Lifecycle.
        $this->assertTrue($this->db->table('resources')->insert([
            'type' => 'employee', 'name' => 'employee', 'domain' => 'MASTER',
            'status' => 'ACTIVE', 'created_at' => '2026-10-15 00:00:00',
        ]));
        foreach (['ACTIVE', 'INACTIVE'] as $code) {
            $this->assertTrue($this->db->table('workflow_states')->insert([
                'code' => $code, 'name' => $code, 'status' => 'ACTIVE',
                'created_at' => '2026-10-15 00:00:00',
            ]));
        }

        $repo = new LifecycleRepository($this->db);
        $lifecycleId = $repo->insertLifecycle([
            'code' => 'EMP-MANAGER-TEST-' . uniqid(),
            'name' => 'Employee manager invariant fixture',
            'status' => 'ACTIVE',
        ]);
        foreach (['ACTIVE', 'INACTIVE'] as $index => $code) {
            $state = $repo->findActiveWorkflowStateByCode($code);
            $this->assertNotNull($state, 'Missing canonical State: ' . $code);
            $repo->insertLifecycleState([
                'lifecycle_id' => $lifecycleId,
                'state_id' => (int) $state['id'],
                'sequence' => $index + 1,
                'is_initial' => $index === 0 ? 1 : 0,
                'is_final' => $index === 1 ? 1 : 0,
            ]);
        }
        $this->assertTrue($this->db->table('resources')
            ->where('type', 'employee')->update(['lifecycle_id' => $lifecycleId]));
    }

    public function testEmployeeCrudAndLookups(): void
    {
        $repository = new EmployeeRepository($this->db);
        $code = 'EMP-REPO-' . uniqid();
        $userId = random_int(100_000_000, 999_999_999);
        $id = $repository->insert([
            'user_id' => $userId,
            'employee_code' => $code,
            'employee_name' => 'Repository Employee',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame($code, $repository->find($id)['employee_code']);
        $this->assertSame($id, (int) $repository->findByCode($code)['id']);
        $this->assertSame($id, (int) $repository->findByUserId($userId)['id']);
        $this->assertCount(1, $repository->findAll(['status' => 'ACTIVE', 'user_id' => $userId]));
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
        $this->assertNotSame(
            lang('Employee.' . $viCode, [], 'vi'),
            lang('Employee.' . $viCode, [], 'en'),
        );
        $this->assertStringNotContainsString('lang(', (string) file_get_contents(APPPATH . 'Services/EmployeeService.php'));
    }

    public function testInactiveManagerAndConcurrencyFailureExposeStableCodes(): void
    {
        $service = new EmployeeService(null, $this->db);
        $inactiveManagerId = $service->create($this->employee('EMP-INACTIVE', 'Inactive'));
        $employeeId = $service->create($this->employee('EMP-SELF', 'Self'));

        try {
            $service->inactivate($inactiveManagerId);
            $this->fail('Direct State changes must use a Lifecycle Transition.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED, $exception->getErrorCode());
        }
        // Test-only persistence setup: simulate a manager already in INACTIVE
        // State without using a forbidden service-level State mutation.
        $this->assertTrue((new EmployeeRepository($this->db))->update($inactiveManagerId, ['status' => 'INACTIVE']));

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
            $this->assertSame(EmployeeService::ERROR_MANAGER_MUTATION_FAILED, $exception->getErrorCode());
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
            $this->assertSame(EmployeeService::ERROR_MANAGER_REFERENCE_INVALID, $exception->getErrorCode());
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

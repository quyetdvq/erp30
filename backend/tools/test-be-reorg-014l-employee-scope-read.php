<?php

declare(strict_types=1);

/**
 * BE-REORG-014L: vendor-free, DB-free characterization of the real PBAC
 * employee-scope read bridge. No database connections or SQL are made.
 * DB/Shield PBAC parity stays in BeReorg008* and NO-DROP.
 * Run: php backend/tools/test-be-reorg-014l-employee-scope-read.php
 */

$backend = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($backend): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $backend . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Authorization\EvaluationOrganizationRequest;
use App\Bootstrap\AuthorizationEmployeeScopeReadAdapter;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\EmployeeScopeReadPort;
use App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface;
use App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface;

function be014lAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

final class Be014lEmployees implements EmployeeRepositoryInterface
{
    public int $reads = 0;
    public int $writes = 0;
    public function find(int $id): ?array { throw new LogicException('Unexpected employee read'); }
    public function findByCode(string $code): ?array { throw new LogicException('Unexpected employee read'); }
    public function findByUserId(int $userId): ?array
    {
        $this->reads++;
        return ['id' => 23, 'user_id' => $userId, 'status' => 'INACTIVE'];
    }
    public function findAll(array $filters = []): array { throw new LogicException('Unexpected employee read'); }
    public function insert(array $data): int { $this->writes++; throw new LogicException('Forbidden employee mutation'); }
    public function update(int $id, array $data): bool { $this->writes++; throw new LogicException('Forbidden employee mutation'); }
}

final class Be014lAssignments implements EmployeeAssignmentRepositoryInterface
{
    public ?string $date = null;
    public int $reads = 0;
    public int $writes = 0;
    public bool $fail = false;
    public function find(int $id): ?array { throw new LogicException('Unexpected assignment read'); }
    public function insert(array $data): int { $this->writes++; throw new LogicException('Forbidden assignment mutation'); }
    public function update(int $id, array $data): bool { $this->writes++; throw new LogicException('Forbidden assignment mutation'); }
    public function findByEmployee(int $employeeId, ?string $status = null): array { throw new LogicException('Unexpected assignment read'); }
    public function findAll(): array { throw new LogicException('Unexpected assignment read'); }
    public function findEffective(int $employeeId, string $onDate, bool $primaryOnly = false): array
    {
        $this->reads++;
        $this->date = $onDate;
        if ($this->fail) {
            throw new RuntimeException('PBAC_ASSIGNMENT_READ_FAILURE');
        }
        return [['id' => 10, 'employee_id' => $employeeId, 'organization_id' => 3,
            'department_id' => 7, 'position_id' => 11, 'status' => 'ACTIVE', 'is_primary' => 1]];
    }
    public function findEffectiveForEmployees(array $employeeIds, string $onDate): array { throw new LogicException('Unexpected batch read'); }
    public function findOverlappingPrimary(int $employeeId, string $effectiveFrom, ?string $effectiveTo = null, ?int $excludeId = null): array { throw new LogicException('Unexpected overlap read'); }
    public function departmentIsActive(int $departmentId): bool { throw new LogicException('Unexpected department read'); }
    public function positionIsActive(int $positionId): bool { throw new LogicException('Unexpected position read'); }
    public function departmentExists(int $departmentId): bool { throw new LogicException('Unexpected department read'); }
    public function positionExists(int $positionId): bool { throw new LogicException('Unexpected position read'); }
}

$checks = [
    'PBAC owns a two-method internal read-only port' => static function (): void {
        $port = new ReflectionClass(EmployeeScopeReadPort::class);
        be014lAssert($port->isInterface(), 'Scope read contract missing');
        $names = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $port->getMethods());
        sort($names);
        be014lAssert($names === ['findByUserId', 'findEffective'], 'Unexpected PBAC port API');
        be014lAssert(str_contains((string) $port->getDocComment(), 'Privileged internal PBAC'), 'Port must document privileged consumer');
    },
    'actual Bootstrap adapter reads both EmployeeStructure providers' => static function (): void {
        $employees = new Be014lEmployees();
        $assignments = new Be014lAssignments();
        $reader = new AuthorizationEmployeeScopeReadAdapter($employees, $assignments);
        be014lAssert($reader instanceof EmployeeScopeReadPort, 'Adapter does not implement port');
        $employee = $reader->findByUserId(101);
        be014lAssert($employee === ['id' => 23, 'user_id' => 101, 'status' => 'INACTIVE'], 'Employee payload modified');
        $time = new EvaluationTime(new DateTimeImmutable('2026-10-22T00:30:00+07:00'));
        $rows = $reader->findEffective(23, $time);
        be014lAssert($assignments->date === '2026-10-21', 'UTC business date drifted');
        be014lAssert($rows === [['id' => 10, 'employee_id' => 23, 'organization_id' => 3,
            'department_id' => 7, 'position_id' => 11, 'status' => 'ACTIVE', 'is_primary' => 1]], 'Assignment shape/order modified');
        be014lAssert($employees->reads === 1 && $assignments->reads === 1, 'Unexpected extra lookups');
        be014lAssert($employees->writes === 0 && $assignments->writes === 0, 'PBAC bridge performed a write');
    },
    'provider read errors propagate rather than yielding eligible empty scopes' => static function (): void {
        $assignments = new Be014lAssignments();
        $assignments->fail = true;
        $reader = new AuthorizationEmployeeScopeReadAdapter(new Be014lEmployees(), $assignments);
        try {
            $reader->findEffective(23, new EvaluationTime(new DateTimeImmutable('2026-10-22T12:00:00Z')));
        } catch (RuntimeException $e) {
            be014lAssert($e->getMessage() === 'PBAC_ASSIGNMENT_READ_FAILURE', 'Read exception rewritten');
            return;
        }
        throw new RuntimeException('Read error silently swallowed');
    },
    'canonical decision engine has no EmployeeStructure or mutable legacy service import' => static function () use ($backend): void {
        $source = (string) file_get_contents($backend . '/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php');
        foreach (['use App\\Repositories\\EmployeeRepository;', 'use App\\Services\\EmployeeAssignmentService;',
            'use App\\Modules\\EmployeeStructure\\', 'use App\\Bootstrap\\'] as $forbidden) {
            be014lAssert(! str_contains($source, $forbidden), 'Disallowed canonical dependency: ' . $forbidden);
        }
        be014lAssert(substr_count($source, '->findByUserId($userId)') === 1
            && substr_count($source, '->findEffective($employeeId, $operation->evaluationTime)') === 1,
            'Employee eligibility read no longer delegates to port');
        be014lAssert(str_contains($source, 'readThrough($batch, \'employee\'')
            && str_contains($source, 'readThrough($batch, \'assignments\''), 'PBAC read-only batch cache changed');
    },
    'factory uses canonical read repositories on one connection, with legacy injection isolated' => static function () use ($backend): void {
        $factory = (string) file_get_contents($backend . '/app/Bootstrap/AuthorizationDecisionFactory.php');
        $adapter = (string) file_get_contents($backend . '/app/Bootstrap/AuthorizationEmployeeScopeReadAdapter.php');
        $facade = (string) file_get_contents($backend . '/app/Authorization/AuthorizationService.php');
        be014lAssert(str_contains($factory, 'EmployeeStructureFactory::employeeRepository($connection)')
            && str_contains($factory, 'EmployeeStructureFactory::assignmentRepository($connection)'), 'No canonical default providers');
        be014lAssert(str_contains($factory, '$employees ??') && str_contains($factory, '$assignments ??')
            && str_contains($factory, 'employeeScopeRead: $employeeScopeRead'), 'Legacy injection or canonical injection lost');
        be014lAssert(! preg_match('/\->(?:authorize|create|update|end|insert|delete)\s*\(/', $adapter), 'Adapter invokes privileged authorization or mutation');
        be014lAssert(str_contains($facade, 'AuthorizationDecisionFactory::create(')
            && str_contains($facade, 'assignments: $assignments,'), 'Stable legacy facade changed');
    },
    'recursive PBAC call through read seam is rejected and guard is exception-safe' => static function (): void {
        // Build the actual decision engine without opening a CI4 connection;
        // only its recursive authorization guard is exercised here.
        $engine = (new ReflectionClass(PbacDecisionEngine::class))->newInstanceWithoutConstructor();
        $reader = new ReflectionMethod(PbacDecisionEngine::class, 'readEmployeeScope');
        $time = new EvaluationTime(new DateTimeImmutable('2026-10-22T00:00:00Z'));
        $op = new OperationContext(7, $time, 'be014l-recursion', false);
        try {
            $reader->invoke($engine, static fn (): mixed => $engine->authorize(
                $op, 'employee', 'view', new EvaluationOrganizationRequest()));
        } catch (LogicException $e) {
            be014lAssert(str_contains($e->getMessage(), 'Recursive PBAC'), 'Wrong reentrancy reason');
            be014lAssert((new ReflectionProperty($engine, 'employeeScopeReadActive'))->getValue($engine) === false,
                'Guard leaked after nested authorization');
            be014lAssert($reader->invoke($engine, static fn (): string => 'ok') === 'ok', 'Guard did not reset');
            return;
        }
        throw new RuntimeException('Recursive authorization was allowed');
    },
    'MariaDB parity keeps a separate engine oracle and recursion spy' => static function () use ($backend): void {
        $parity = (string) file_get_contents($backend . '/tests/database/BeReorg008DecisionParityTest.php');
        $factoryTest = (string) file_get_contents($backend . '/tests/unit/BeReorg008DecisionFactoryTest.php');
        be014lAssert(str_contains($parity, 'new PbacDecisionEngine(')
            && str_contains($parity, 'new AuthorizationEmployeeScopeReadAdapter(')
            && str_contains($parity, 'new EmployeeAssignmentRepository($this->db)'),
            'DB parity no longer constructs an independent engine/provider');
        be014lAssert(str_contains($factoryTest, 'createMock(\\App\\Modules\\Authorization\\Decision\\Application\\Ports\\EmployeeScopeReadPort::class)')
            && str_contains($factoryTest, "expectExceptionMessage('Recursive PBAC employee scope read is forbidden.')"),
            'DB negative recursive-PBAC spy was removed');
    },
    'read exceptions restore the guard without caching a fabricated allow' => static function (): void {
        $engine = (new ReflectionClass(PbacDecisionEngine::class))->newInstanceWithoutConstructor();
        $reader = new ReflectionMethod(PbacDecisionEngine::class, 'readEmployeeScope');
        try {
            $reader->invoke($engine, static function (): never { throw new RuntimeException('read-failed'); });
        } catch (RuntimeException $e) {
            be014lAssert($e->getMessage() === 'read-failed', 'PBAC scope read error altered');
            be014lAssert((new ReflectionProperty($engine, 'employeeScopeReadActive'))->getValue($engine) === false,
                'Guard leaked after failure');
            return;
        }
        throw new RuntimeException('Read exception was swallowed');
    },
];

try {
    foreach ($checks as $label => $check) {
        $check();
        echo '  ' . $label . ": PASS\n";
    }
    echo 'BE-REORG-014L EMPLOYEE SCOPE READ SEAM PASS: ' . count($checks)
        . " checks; production port/adapter/guard; DB/Shield/HTTP NOT_RUN.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'BE-REORG-014L FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}

<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Real MariaDB named-lock contention for the three accepted Employee Structure keys. */
final class BeReorg004EmployeeStructureLockTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    /** @var list<BaseConnection<mixed,mixed>> */ private array $peers = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->peers as $peer) { try { (new MariaDbDomainLockService($peer, 0))->release('department-tree'); (new MariaDbDomainLockService($peer, 0))->release('employee-manager-tree'); } catch (Throwable) {} finally { $peer->close(); } }
        $this->peers = [];
        parent::tearDown();
    }

    public function testDepartmentTreeNamedLockContendsAndReleasesAcrossConnections(): void
    {
        $this->assertNamedLockContention('department-tree');
    }

    public function testEmployeeManagerTreeNamedLockContendsAndReleasesAcrossConnections(): void
    {
        $this->assertNamedLockContention('employee-manager-tree');
    }

    public function testEmployeeAssignmentNamedLockIsEmployeeSpecificAndReleases(): void
    {
        [$holder, $contender] = $this->connections();
        $a = new MariaDbDomainLockService($holder, 0);
        $b = new MariaDbDomainLockService($contender, 0);
        $a->acquire('employee-assignment:700001');
        try {
            $b->acquire('employee-assignment:700002');
            $b->release('employee-assignment:700002');
            $this->assertTimeout(fn () => $b->acquire('employee-assignment:700001'));
        } finally {
            $a->release('employee-assignment:700001');
        }
        $b->acquire('employee-assignment:700001');
        $b->release('employee-assignment:700001');
        $this->addToAssertionCount(1);
    }

    private function assertNamedLockContention(string $key): void
    {
        [$holder, $contender] = $this->connections();
        $a = new MariaDbDomainLockService($holder, 0);
        $b = new MariaDbDomainLockService($contender, 0);
        $a->acquire($key);
        try { $this->assertTimeout(fn () => $b->acquire($key)); } finally { $a->release($key); }
        $b->acquire($key);
        $b->release($key);
        $this->addToAssertionCount(1);
    }

    private function assertTimeout(callable $attempt): void
    {
        $failure = null;
        try { $attempt(); } catch (ConcurrencyException $exception) { $failure = $exception; }
        $this->assertNotNull($failure, 'Competing named lock unexpectedly succeeded.');
        $this->assertSame('CONCURRENT_MODIFICATION', $failure->getErrorCode());
        $this->assertSame(['operation' => 'lock.acquire.timeout'], $failure->getContext());
    }

    /** @return array{BaseConnection<mixed,mixed>,BaseConnection<mixed,mixed>} */
    private function connections(): array
    {
        $a = db_connect('tests', false); $this->peers[] = $a;
        $b = db_connect('tests', false); $this->peers[] = $b;
        $first = $a->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $second = $b->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')->getRowArray();
        $this->assertSame('erp_test', $first['db']);
        $this->assertSame('erp_test', $second['db']);
        $this->assertNotSame((string)$first['id'], (string)$second['id']);
        return [$a, $b];
    }
}

<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\ContactLifecycleAdapter;
use App\Services\LifecycleService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Contact\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Existing ContactLifecycleAdapter must remain compatible with repository wrappers. */
final class BeReorg003ContactLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    private RuntimeFixture $fixture;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->fixture = new RuntimeFixture($this->db);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->fixture)) {
                $this->fixture->cleanupRollbackBlockers();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testOrganizationIdentityLoadsOnlyThroughOrganizationResource(): void
    {
        $id = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003 org identity');
        $operation = $this->fixture->operation();
        $snapshot = (new ContactLifecycleAdapter('organization_contact', $this->db))->load($id, $operation);
        $this->assertSame('organization_contact', $snapshot->resourceType);
        $this->assertSame($id, $snapshot->targetId);
        $this->domainError(LifecycleService::ERROR_TARGET_NOT_FOUND,
            fn () => (new ContactLifecycleAdapter('employee_contact', $this->db))->load($id, $operation));
    }

    public function testEmployeeIdentityLoadsOnlyThroughEmployeeResource(): void
    {
        $id = $this->fixture->contact('EMPLOYEE', $this->fixture->ownerEmployee, 'BR003 employee identity');
        $operation = $this->fixture->operation();
        $snapshot = (new ContactLifecycleAdapter('employee_contact', $this->db))->load($id, $operation);
        $this->assertSame('employee_contact', $snapshot->resourceType);
        $this->assertSame($id, $snapshot->targetId);
        $this->domainError(LifecycleService::ERROR_TARGET_NOT_FOUND,
            fn () => (new ContactLifecycleAdapter('organization_contact', $this->db))->load($id, $operation));
    }

    public function testOrganizationRealSetStatePersistsAndAudits(): void
    {
        $id = $this->fixture->contact('ORGANIZATION', $this->fixture->company, 'BR003 org transition');
        $this->transition('organization_contact', $id);
    }

    public function testEmployeeRealSetStatePersistsAndAudits(): void
    {
        $id = $this->fixture->contact('EMPLOYEE', $this->fixture->ownerEmployee, 'BR003 employee transition');
        $this->transition('employee_contact', $id);
    }

    private function transition(string $resource, int $id): void
    {
        $operation = $this->fixture->operation();
        $adapter = new ContactLifecycleAdapter($resource, $this->db);
        $snapshot = $adapter->load($id, $operation);
        $this->assertSame($this->fixture->states['ACTIVE'], $snapshot->currentStateId);
        $adapter->transactional(function () use ($adapter, $snapshot, $operation): void {
            $locked = $adapter->lockAndReload($snapshot, $operation);
            $adapter->mutate($locked, $this->fixture->states['ACTIVE'], $this->fixture->states['INACTIVE'], $operation);
        });
        $row = $this->db->table('contacts')->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('INACTIVE', $row['status']);
        $this->assertSame($this->fixture->writer, (int) $row['updated_by']);
        $this->assertSame('2026-10-16 07:00:00', $row['updated_at']);
    }

    private function domainError(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected domain error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

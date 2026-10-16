<?php

declare(strict_types=1);

use App\Bootstrap\OrganizationFactory;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Database\WorkflowLifecycleFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Real named-lock contention on independent MySQL connections; not browser UAT. */
final class BeReorg002OrganizationLockTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    /** @var list<BaseConnection<mixed,mixed>> */
    private array $peers = [];

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        WorkflowLifecycleFixture::bind($this->db, ['organization']);
    }

    protected function tearDown(): void
    {
        foreach ($this->peers as $peer) {
            try { $peer->transRollback(); } finally { $peer->close(); }
        }
        $this->peers = [];
        parent::tearDown();
    }

    /** @return array<string,array{bool}> */
    public static function entrypoints(): array { return ['legacy' => [false], 'module' => [true]]; }

    #[DataProvider('entrypoints')]
    public function testHeldTreeLockBlocksCreateAndReleaseAllowsExactlyOneRoot(bool $module): void
    {
        [$holder, $contender] = $this->connections();
        $lock = new MariaDbDomainLockService($holder, 0);
        $contenderLock = new MariaDbDomainLockService($contender, 0);
        $service = $module
            ? OrganizationFactory::service(db: $contender, domainLock: $contenderLock)
            : new OrganizationService(db: $contender, domainLock: $contenderLock);
        $payload = ['type' => 'GROUP', 'code' => 'BR002-LOCK-G', 'name' => 'Root', 'status' => 'DRAFT'];
        $lock->acquire('organization-tree');
        try {
            try {
                $service->create($payload);
                $this->fail('A competing connection acquired the tree lock while held.');
            } catch (ConcurrencyException $exception) {
                $this->assertSame('CONCURRENT_MODIFICATION', $exception->getErrorCode());
                $this->assertSame(['operation' => 'lock.acquire.timeout'], $exception->getContext());
            }
            $this->assertSame(0, (int) $contender->table('organizations')->countAllResults());
        } finally {
            $lock->release('organization-tree');
        }
        $id = $service->create($payload);
        $this->assertGreaterThan(0, $id);
        try {
            $service->create(['type' => 'GROUP', 'code' => 'BR002-LOCK-G2', 'name' => 'Second', 'status' => 'DRAFT']);
            $this->fail('Second root accepted after the first committed.');
        } catch (DomainException $exception) {
            $this->assertSame('ORG_SECOND_GROUP_ROOT', $exception->getErrorCode());
        }
        $this->assertSame(1, (int) $contender->table('organizations')->where('type', 'GROUP')->countAllResults());
    }

    public function testFailedHierarchyMutationReleasesLockForAnotherConnection(): void
    {
        [$a, $b] = $this->connections();
        $service = OrganizationFactory::service(db: $a, domainLock: new MariaDbDomainLockService($a, 0));
        $group = $service->create(['type' => 'GROUP', 'code' => 'BR002-RELEASE-G', 'name' => 'Root', 'status' => 'DRAFT']);
        try {
            $service->create(['type' => 'BRANCH', 'parent_id' => $group, 'code' => 'BR002-REJECT', 'name' => 'Invalid', 'status' => 'DRAFT']);
            $this->fail('Invalid hierarchy accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('ORG_INVALID_PARENT', $exception->getErrorCode());
        }
        $other = OrganizationFactory::service(db: $b, domainLock: new MariaDbDomainLockService($b, 0));
        $company = $other->create(['type' => 'COMPANY', 'parent_id' => $group, 'code' => 'BR002-RELEASE-C', 'name' => 'Company', 'status' => 'DRAFT']);
        $this->assertGreaterThan($group, $company);
        $this->assertSame(0, (int) $b->table('organizations')->where('code', 'BR002-REJECT')->countAllResults());
        $this->assertSame(2, (int) $b->table('organizations')->countAllResults());
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
        $this->assertNotSame((string) $first['id'], (string) $second['id']);
        $a->transStrict(false); $b->transStrict(false);
        return [$a, $b];
    }
}

<?php

declare(strict_types=1);

use App\Bootstrap\OrganizationFactory;
use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Organization\Application\OrganizationService as ModuleOrganizationService;
use App\Repositories\OrganizationRepository;
use App\Services\OrganizationService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Database\WorkflowLifecycleFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Fixed expected business results for BOTH public entrypoints. */
final class BeReorg002OrganizationCharacterizationTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        WorkflowLifecycleFixture::bind($this->db, ['organization']);
    }

    /** @return array<string,array{bool}> */
    public static function entrypoints(): array
    {
        return ['legacy' => [false], 'module' => [true]];
    }

    #[DataProvider('entrypoints')]
    public function testFullAncestorOrderAndNearestCompanyDoNotBleedAcrossSiblings(bool $module): void
    {
        $service = $this->service($module);
        $group = $service->create($this->organizationPayload('GROUP'));
        $company = $service->create($this->organizationPayload('COMPANY', $group));
        $other = $service->create($this->organizationPayload('COMPANY', $group));
        $branch = $service->create($this->organizationPayload('BRANCH', $company));
        $plant = $service->create($this->organizationPayload('PLANT', $branch));
        $warehouse = $service->create($this->organizationPayload('WAREHOUSE', $plant));
        $this->assertSame([$group, $company, $branch, $plant, $warehouse], array_map(
            static fn (array $row): int => (int) $row['id'], $service->resolveAncestorChain($warehouse),
        ));
        $this->assertSame($company, (int) $service->resolveCompanyOwner((string) $warehouse)['id']);
        $this->assertSame($other, (int) $service->resolveCompanyOwner($other)['id']);
        $this->assertSame($company, (int) $service->resolveCompanyAncestor($company)['id']);
        $this->assertNull($service->resolveCompanyAncestor($group));
        $this->domainError('ORG_COMPANY_RESOLUTION_FAILED', static fn () => $service->resolveCompanyOwner($group));
    }

    #[DataProvider('entrypoints')]
    public function testInactiveRootStillPreventsAnotherRootAndMatrixFailuresDoNotWrite(bool $module): void
    {
        $service = $this->service($module);
        $group = $service->create($this->organizationPayload('GROUP'));
        $this->db->table('organizations')->where('id', $group)->update(['status' => 'INACTIVE']);
        $before = $this->organizationRows();
        $this->domainError('ORG_SECOND_GROUP_ROOT', fn () => $service->create($this->organizationPayload('GROUP')));
        $this->assertSame($before, $this->organizationRows());
        $company = $service->create($this->organizationPayload('COMPANY', $group));
        $warehouse = $service->create($this->organizationPayload('WAREHOUSE', $company));
        foreach ([['COMPANY', $company], ['BRANCH', $group], ['WAREHOUSE', $warehouse], ['COMPANY', null]] as [$type, $parent]) {
            $before = $this->organizationRows();
            $this->domainError('ORG_INVALID_PARENT', fn () => $service->create($this->organizationPayload($type, $parent)));
            $this->assertSame($before, $this->organizationRows());
        }
    }

    #[DataProvider('entrypoints')]
    public function testReparentUpdatesCompanyOwnerAndRejectsIncompatibleExistingChildren(bool $module): void
    {
        $service = $this->service($module);
        $group = $service->create($this->organizationPayload('GROUP'));
        $a = $service->create($this->organizationPayload('COMPANY', $group));
        $b = $service->create($this->organizationPayload('COMPANY', $group));
        $branch = $service->create($this->organizationPayload('BRANCH', $a));
        $plant = $service->create($this->organizationPayload('PLANT', $branch));
        $this->assertTrue($service->update($branch, ['parent_id' => (string) $b]));
        $this->assertSame($b, (int) $service->resolveCompanyOwner($plant)['id']);
        $before = $this->organizationRows();
        $this->domainError('ORG_INVALID_PARENT', static fn () => $service->update($branch, ['type' => 'PLANT']));
        $this->assertSame($before, $this->organizationRows());
        // Matrix validation precedes cycle validation in the established contract.
        $this->domainError('ORG_INVALID_PARENT', static fn () => $service->update($b, ['parent_id' => $plant]));
        $this->assertSame($before, $this->organizationRows());
    }

    #[DataProvider('entrypoints')]
    public function testExistingCyclesAndUnknownReferencesKeepStableErrors(bool $module): void
    {
        $service = $this->service($module);
        $group = $service->create($this->organizationPayload('GROUP'));
        $company = $service->create($this->organizationPayload('COMPANY', $group));
        // Deliberately corrupted historical tree, like the existing P1-ORG test.
        $this->db->table('organizations')->where('id', $group)->update(['parent_id' => $company]);
        $this->domainError('ORG_HIERARCHY_CYCLE', static fn () => $service->resolveAncestorChain($company));
        $this->domainError('ORG_REFERENCE_INVALID', static fn () => $service->resolveAncestorChain(999999999));
        foreach (['0', '-1', '1.5', '1e3', '9223372036854775808', null] as $id) {
            $this->domainError('ORG_COMPANY_RESOLUTION_FAILED', static fn () => $service->resolveCompanyOwner($id));
        }
    }

    #[DataProvider('entrypoints')]
    public function testDatesLocationPairsAndNullableReferencesRetainTheirSemantics(bool $module): void
    {
        $service = $this->service($module);
        $group = $service->create($this->organizationPayload('GROUP'));
        $company = $service->create($this->organizationPayload('COMPANY', $group));
        foreach ([['active_from' => '2026-02-30'], ['active_from' => '2026-10-16', 'active_to' => '2026-10-15']] as $changes) {
            $before = $this->organizationRows();
            $this->domainError('ORG_ACTIVE_DATE_INVALID', static fn () => $service->update($company, $changes));
            $this->assertSame($before, $this->organizationRows());
        }
        $this->db->table('provinces')->insert(['code' => 'BR002-P1', 'name' => 'One', 'status' => 'INACTIVE', 'created_at' => '2026-10-16 00:00:00']);
        $one = (int) $this->db->insertID();
        $this->db->table('provinces')->insert(['code' => 'BR002-P2', 'name' => 'Two', 'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00']);
        $two = (int) $this->db->insertID();
        $this->db->table('wards')->insert(['code' => 'BR002-W1', 'name' => 'Ward', 'province_id' => $one, 'status' => 'INACTIVE', 'created_at' => '2026-10-16 00:00:00']);
        $ward = (int) $this->db->insertID();
        // Organization validates existence/pairing, not ACTIVE status of historical locations.
        $this->assertTrue($service->update($company, ['province_id' => $one, 'ward_id' => $ward]));
        $before = $this->organizationRows();
        $this->domainError('ORG_WARD_PROVINCE_MISMATCH', static fn () => $service->update($company, ['province_id' => $two]));
        $this->assertSame($before, $this->organizationRows());
        $this->domainError('ORG_REFERENCE_INVALID', static fn () => $service->update($company, ['province_id' => 999999999]));
        $this->assertSame($before, $this->organizationRows());
        $this->assertTrue($service->update($company, ['province_id' => null, 'ward_id' => null]));
        $row = (new OrganizationRepository($this->db))->find($company);
        $this->assertNull($row['province_id']);
        $this->assertNull($row['ward_id']);
    }

    #[DataProvider('entrypoints')]
    public function testAuditIsServerOwnedAndFreshServiceUsesItsOwnActorAndClock(bool $module): void
    {
        $clockA = $this->fixedClock('2026-10-16T03:00:00Z');
        $clockB = $this->fixedClock('2026-10-16T04:00:00Z');
        $a = $this->service($module, new MutationContext($clockA, 501, 'br002-a', false), $clockA);
        $b = $this->service($module, new MutationContext($clockB, 502, 'br002-b', false), $clockB);
        $id = $a->create($this->organizationPayload('GROUP') + ['created_by' => 999, 'updated_by' => 999, 'created_at' => '2000-01-01 00:00:00']);
        $this->assertTrue($b->update($id, ['name' => 'New name', 'updated_by' => 999, 'created_by' => 999, 'created_at' => '2000-01-01 00:00:00']));
        $row = (new OrganizationRepository($this->db))->find($id);
        $this->assertSame(501, (int) $row['created_by']);
        $this->assertSame(502, (int) $row['updated_by']);
        $this->assertSame('2026-10-16 03:00:00', $row['created_at']);
        $this->assertSame('2026-10-16 04:00:00', $row['updated_at']);
    }

    #[DataProvider('entrypoints')]
    public function testWorkflowInitialStateAndDirectTransitionGuardStayIntact(bool $module): void
    {
        $service = $this->service($module);
        $payload = $this->organizationPayload('GROUP');
        unset($payload['status']);
        $id = $service->create($payload);
        $before = $this->organizationRows();
        $this->assertSame('DRAFT', (new OrganizationRepository($this->db))->find($id)['status']);
        $this->domainError('WF_TRANSITION_REQUIRED', static fn () => $service->inactivate($id));
        $this->assertSame($before, $this->organizationRows());
        $this->assertTrue($service->update($id, ['status' => 'DRAFT', 'name' => 'Same state']));
        $this->assertSame('DRAFT', (new OrganizationRepository($this->db))->find($id)['status']);
    }

    #[DataProvider('entrypoints')]
    public function testTreeLockAppliesToHierarchyWritesNotOrdinaryNameUpdates(bool $module): void
    {
        $keys = [];
        $real = new MariaDbDomainLockService($this->db);
        $lock = new class ($real, $keys) implements DomainLockService {
            public function __construct(private DomainLockService $real, private array &$keys) {}
            public function acquire(string $domainKey, int $timeoutSeconds = 5): void { $this->real->acquire($domainKey, $timeoutSeconds); }
            public function release(string $domainKey): void { $this->real->release($domainKey); }
            public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
            {
                $this->keys[] = $domainKey;
                return $this->real->synchronized($domainKey, $callback, $timeoutSeconds);
            }
        };
        $service = $this->service($module, lock: $lock);
        $group = $service->create($this->organizationPayload('GROUP'));
        $company = $service->create($this->organizationPayload('COMPANY', $group));
        $service->update($company, ['name' => 'Ordinary update']);
        $this->assertSame(['organization-tree', 'organization-tree'], $keys);
        $service->update($company, ['parent_id' => $group]);
        $this->assertSame(['organization-tree', 'organization-tree', 'organization-tree'], $keys);
    }

    private function service(bool $module, ?MutationContext $context = null, ?Clock $clock = null, ?DomainLockService $lock = null): OrganizationService|ModuleOrganizationService
    {
        return $module
            ? OrganizationFactory::service(db: $this->db, domainLock: $lock, mutationContext: $context, clock: $clock)
            : new OrganizationService(db: $this->db, domainLock: $lock, mutationContext: $context, clock: $clock);
    }

    private function fixedClock(string $instant): Clock
    {
        return new class ($instant) implements Clock {
            public function __construct(private string $instant) {}
            public function now(): DateTimeImmutable { return new DateTimeImmutable($this->instant); }
        };
    }

    /** @return array<string,mixed> */
    private function organizationPayload(string $type, ?int $parent = null): array
    {
        return ['type' => $type, 'parent_id' => $parent, 'code' => 'BR002-' . ++$this->sequence,
            'name' => $type, 'status' => 'DRAFT'];
    }

    /** @return list<array<string,mixed>> */
    private function organizationRows(): array { return (new OrganizationRepository($this->db))->findAll(); }

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

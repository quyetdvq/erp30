<?php

declare(strict_types=1);

use App\Authorization\CompanyOwnerResolver;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Repositories\OrganizationRepository;
use App\Services\OrganizationService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Ctx002CompanyOwnerResolverTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testCompanyResolvesToItself(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);

        $owner = (new CompanyOwnerResolver(null, $this->db))->resolve((string) $companyId);

        $this->assertSame($companyId, (int) $owner['id']);
        $this->assertSame('COMPANY', $owner['type']);
    }

    public function testBranchPlantAndWarehouseResolveNearestCompanyAncestor(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $plantId = $this->insertOrganization('PLANT', $branchId);
        $warehouseId = $this->insertOrganization('WAREHOUSE', $plantId);
        $resolver = new CompanyOwnerResolver(null, $this->db);

        foreach ([$branchId, $plantId, $warehouseId] as $organizationId) {
            $this->assertSame($companyId, (int) $resolver->resolve($organizationId)['id']);
        }
    }

    public function testNestedLegalHierarchyDoesNotBleedIntoSiblingCompany(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyA = $this->insertOrganization('COMPANY', $groupId);
        $branchA = $this->insertOrganization('BRANCH', $companyA);
        $plantA = $this->insertOrganization('PLANT', $branchA);
        $this->insertOrganization('WAREHOUSE', $plantA);
        $companyB = $this->insertOrganization('COMPANY', $groupId);

        $owner = (new CompanyOwnerResolver(null, $this->db))->resolve($plantA);

        $this->assertSame($companyA, (int) $owner['id']);
        $this->assertNotSame($companyB, (int) $owner['id']);
    }

    public function testGroupAndHierarchyWithoutCompanyFailClosed(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $resolver = new CompanyOwnerResolver(null, $this->db);

        try {
            $resolver->resolve($groupId);
            $this->fail('Expected GROUP to be rejected when a Company owner is required.');
        } catch (DomainException $exception) {
            $this->assertSame(CompanyOwnerResolver::ERROR_COMPANY_RESOLUTION_FAILED, $exception->getErrorCode());
        }
    }

    public function testMalformedHierarchyFailsClosed(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $this->assertTrue($this->db->table('organizations')->where('id', $groupId)->update([
            'parent_id' => $branchId,
        ]));

        try {
            (new CompanyOwnerResolver(null, $this->db))->resolve($branchId);
            $this->fail('Expected a malformed hierarchy to fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_HIERARCHY_CYCLE, $exception->getErrorCode());
        }
    }

    public function testPersistenceFailurePropagatesDistinctly(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $service = new OrganizationService(new OrganizationRepository($db), $db);

        try {
            (new CompanyOwnerResolver($service))->resolve(1);
            $this->fail('Expected persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(OrganizationRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
    }

    public function testResultIsDeterministicAndHasNoActorOrClientHintInput(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $resolver = new CompanyOwnerResolver(null, $this->db);

        $first = $resolver->resolve($branchId);
        $second = $resolver->resolve($branchId);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($companyId, (int) $first['id']);
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId,
            'type' => $type,
            'code' => uniqid('CTX2-O-', true),
            'name' => $type . ' Context 2 Organization',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}

<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Repositories\OrganizationRepository;
use App\Services\OrganizationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Org002OrganizationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testValidHierarchyAncestorChainAndCompanyResolution(): void
    {
        $service = new OrganizationService(null, $this->db);
        $groupId = $service->create($this->organization('GROUP'));
        $companyId = $service->create($this->organization('COMPANY', $groupId));
        $warehouseId = $service->create($this->organization('WAREHOUSE', $companyId));

        $chain = $service->resolveAncestorChain($warehouseId);
        $this->assertSame([$groupId, $companyId, $warehouseId], array_map(
            static fn (array $row): int => (int) $row['id'],
            $chain,
        ));
        $this->assertSame($companyId, (int) $service->resolveCompanyAncestor($warehouseId)['id']);
        $this->assertNull($service->resolveCompanyAncestor($groupId));
    }

    public function testSecondRootAndInvalidParentMatrixAreRejected(): void
    {
        $service = new OrganizationService(null, $this->db);
        $groupId = $service->create($this->organization('GROUP'));

        try {
            $service->create($this->organization('GROUP'));
            $this->fail('Expected second GROUP root rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_SECOND_GROUP_ROOT, $exception->getErrorCode());
        }

        $companyId = $service->create($this->organization('COMPANY', $groupId));
        try {
            $service->create($this->organization('COMPANY', $companyId));
            $this->fail('Expected same-type parent rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_INVALID_PARENT, $exception->getErrorCode());
        }
    }

    public function testReparentCycleAndInactivationPreserveStableErrors(): void
    {
        $service = new OrganizationService(null, $this->db);
        $groupId = $service->create($this->organization('GROUP'));
        $companyId = $service->create($this->organization('COMPANY', $groupId));
        $branchId = $service->create($this->organization('BRANCH', $companyId));
        $warehouseId = $service->create($this->organization('WAREHOUSE', $branchId));

        try {
            $service->update($companyId, ['parent_id' => $warehouseId]);
            $this->fail('Expected parent matrix rejection before cycle validation.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_INVALID_PARENT, $exception->getErrorCode());
        }

        $this->assertTrue($this->db->table('organizations')->where('id', $groupId)->update([
            'parent_id' => $warehouseId,
        ]));
        try {
            $service->resolveAncestorChain($groupId);
            $this->fail('Expected existing hierarchy cycle rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_HIERARCHY_CYCLE, $exception->getErrorCode());
        }

        $this->assertTrue($service->inactivate($branchId));
        $this->assertSame('INACTIVE', (new OrganizationRepository($this->db))->find($branchId)['status']);
    }

    public function testHierarchyMutationUsesOrganizationTreeLock(): void
    {
        $keys = [];
        $lock = new class ($keys) implements DomainLockService {
            /** @param list<string> $keys */
            public function __construct(private array &$keys)
            {
            }

            public function acquire(string $domainKey, int $timeoutSeconds = 5): void
            {
                $this->keys[] = $domainKey;
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
        $service = new OrganizationService(null, $this->db, $lock);
        $groupId = $service->create($this->organization('GROUP'));
        $companyId = $service->create($this->organization('COMPANY', $groupId));
        $service->update($companyId, ['name' => 'Updated Company', 'parent_id' => $groupId]);

        $this->assertNotEmpty($keys);
        $this->assertSame(['organization-tree', 'organization-tree', 'organization-tree'], $keys);
    }

    public function testLockFailureExposesStableMutationCode(): void
    {
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
        $service = new OrganizationService(null, $this->db, $lock);

        try {
            $service->create($this->organization('GROUP'));
            $this->fail('Expected organization mutation failure.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_MUTATION_FAILED, $exception->getErrorCode());
            $this->assertSame('lock unavailable', $exception->getDiagnosticMessage());
            $this->assertStringNotContainsString('lock unavailable', $exception->getMessage());
        }
    }

    public function testOrganizationCatalogUsesCodeIgniterLanguageAtBoundary(): void
    {
        $this->assertNotSame(
            lang('Organization.' . OrganizationService::ERROR_INVALID_PARENT, [], 'vi'),
            lang('Organization.' . OrganizationService::ERROR_INVALID_PARENT, [], 'en'),
        );
        $this->assertStringNotContainsString('lang(', (string) file_get_contents(APPPATH . 'Services/OrganizationService.php'));
    }

    public function testInvariantCodeIsIndependentOfRequestLocale(): void
    {
        $service = new OrganizationService(null, $this->db);
        $groupId = $service->create($this->organization('GROUP'));
        $codes = [];

        foreach (['vi', 'en'] as $locale) {
            \App\Infrastructure\RequestContext::begin('organization-locale-test', $locale);
            try {
                $service->update($groupId, ['parent_id' => $groupId]);
                $this->fail('Expected invalid parent rejection.');
            } catch (DomainException $exception) {
                $codes[] = $exception->getErrorCode();
            } finally {
                \App\Infrastructure\RequestContext::reset();
            }
        }

        $this->assertSame([
            OrganizationService::ERROR_INVALID_PARENT,
            OrganizationService::ERROR_INVALID_PARENT,
        ], $codes);
    }

    /** @return array<string, mixed> */
    private function organization(string $type, ?int $parentId = null): array
    {
        return [
            'parent_id' => $parentId,
            'type' => $type,
            'code' => 'ORG-SVC-' . $type . '-' . uniqid(),
            'name' => $type . ' Organization',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ];
    }
}

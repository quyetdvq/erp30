<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;

/** @internal */
final class P1Prd002ProductUomMasterServiceTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testRepositoryAndMasterServiceCreateFindAndListDeterministically(): void
    {
        [$service, $operation] = $this->fixture(['create', 'edit', 'setstate']);
        $firstId = $service->create(['code' => 'EA', 'name' => 'Each', 'description' => null], $operation);
        $secondId = $service->create(['code' => 'BOX', 'name' => 'Box', 'description' => 'Multiple each'], $operation);

        $repository = new ProductUomRepository($this->db);
        $first = $repository->findById($firstId);
        $second = $repository->findByCode('BOX');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('EA', $first['code']);
        $this->assertSame($secondId, (int) $second['id']);
        $this->assertSame([$secondId, $firstId], array_map(
            static fn (array $row): int => (int) $row['id'],
            $service->listAll(),
        ));
        $this->assertSame($firstId, (int) $service->requireActiveProductUom($firstId)['id']);
    }

    public function testValidationRejectsInvalidValuesWithoutTruncation(): void
    {
        [$service, $operation] = $this->fixture(['create']);
        $cases = [
            [['code' => ' ', 'name' => 'Each'], ProductUomMasterService::ERROR_REQUIRED_CODE],
            [['code' => str_repeat('C', 31), 'name' => 'Each'], ProductUomMasterService::ERROR_CODE_TOO_LONG],
            [['code' => 'EA', 'name' => ' '], ProductUomMasterService::ERROR_REQUIRED_NAME],
            [['code' => 'EA', 'name' => str_repeat('N', 101)], ProductUomMasterService::ERROR_NAME_TOO_LONG],
            [['code' => 'EA', 'name' => 'Each', 'description' => str_repeat('D', 256)], ProductUomMasterService::ERROR_DESCRIPTION_TOO_LONG],
            [['code' => 'EA', 'name' => 'Each', 'status' => 'BROKEN'], ProductUomMasterService::ERROR_INVALID_STATUS],
        ];
        foreach ($cases as [$payload, $code]) {
            $this->assertDomainCode(fn () => $service->create($payload, $operation), $code);
        }

        $id = $service->create(['code' => 'NULL-DESC', 'name' => 'Null description', 'description' => null], $operation);
        $row = $service->findById($id);
        $this->assertNotNull($row);
        $this->assertNull($row['description']);
        $this->assertSame(1, (int) $this->db->table('product_uoms')->where('code', 'NULL-DESC')->countAllResults());
    }

    public function testDuplicateCodeIsTypedForCreateAndEdit(): void
    {
        [$service, $operation] = $this->fixture(['create', 'edit']);
        $firstId = $service->create(['code' => 'EA', 'name' => 'Each'], $operation);
        $secondId = $service->create(['code' => 'BOX', 'name' => 'Box'], $operation);

        $this->assertDomainCode(
            fn () => $service->create(['code' => 'EA', 'name' => 'Duplicate'], $operation),
            ProductUomMasterService::ERROR_DUPLICATE_CODE,
        );
        $this->assertDomainCode(
            fn () => $service->update($secondId, ['code' => 'EA'], $operation),
            ProductUomMasterService::ERROR_DUPLICATE_CODE,
        );
        $second = $service->findById($secondId);
        $this->assertNotNull($second);
        $this->assertSame('BOX', $second['code']);
        $this->assertNotNull($service->findById($firstId));
    }

    public function testValidEditUsesTrustedAuditAndCannotSmuggleStatus(): void
    {
        [$service, $operation, $_rootId, $userId] = $this->fixture(['create', 'edit', 'setstate']);
        $id = $service->create([
            'code' => 'EA', 'name' => 'Each', 'created_by' => 999999999,
            'created_at' => '2000-01-01 00:00:00',
        ], $operation);
        $this->assertDomainCode(
            fn () => $service->update($id, ['status' => 'INACTIVE'], $operation),
            ProductUomMasterService::ERROR_STATUS_MUTATION_FORBIDDEN,
        );
        $this->assertTrue($service->update($id, [
            'code' => 'EACH', 'name' => 'Each updated', 'description' => 'Updated',
            'updated_by' => 999999999, 'updated_at' => '2000-01-01 00:00:00',
        ], $operation));
        $row = $service->findById($id);
        $this->assertNotNull($row);
        $this->assertSame('EACH', $row['code']);
        $this->assertSame('Each updated', $row['name']);
        $this->assertSame('Updated', $row['description']);
        $this->assertSame((string) $userId, (string) $row['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['created_at']);
        $this->assertSame((string) $userId, (string) $row['updated_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['updated_at']);
    }

    public function testStateTransitionsAuditAndActiveReferenceBoundary(): void
    {
        [$service, $operation, $rootId] = $this->fixture(['create', 'edit', 'setstate']);
        $id = $service->create(['code' => 'EA', 'name' => 'Each'], $operation);
        $beforeAudit = (int) $this->db->table('authorization_logs')->countAllResults();

        $this->assertTrue($service->inactivate($id, $operation));
        $inactive = $service->findById($id);
        $this->assertNotNull($inactive);
        $this->assertSame('INACTIVE', $inactive['status']);
        $this->assertSame($beforeAudit + 1, (int) $this->db->table('authorization_logs')->countAllResults());
        $this->assertDomainCode(fn () => $service->requireActiveProductUom($id), ProductUomMasterService::ERROR_INACTIVE_REFERENCE);
        $this->assertTrue($service->activate($id, $operation));
        $active = $service->requireActiveProductUom($id);
        $this->assertSame($id, (int) $active['id']);
        $auditResult = $this->db->table('authorization_logs')->orderBy('id', 'DESC')->get();
        $this->assertNotFalse($auditResult);
        $auditRow = $auditResult->getRowArray();
        $this->assertNotNull($auditRow);
        $this->assertSame($rootId, (int) $auditRow['organization_id']);
    }

    public function testInactivationDoesNotCascadeToProductConversionsOrPriceItems(): void
    {
        [$service, $operation] = $this->fixture(['create', 'setstate']);
        $uomId = $service->create(['code' => 'EA', 'name' => 'Each'], $operation);
        $this->db->table('products')->insert([
            'code' => 'P2-PRODUCT', 'product_name' => 'Product 2', 'product_type' => 'SIMPLE',
            'base_uom_id' => $uomId, 'serial_management_enabled' => 0,
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $productId = (int) $this->db->insertID();
        $this->db->table('product_uom_conversions')->insert([
            'product_id' => $productId, 'uom_id' => $uomId, 'conversion_factor' => '2.000000',
            'usage_type' => 'BOTH', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $conversionId = (int) $this->db->insertID();

        $this->assertTrue($service->inactivate($uomId, $operation));
        $productResult = $this->db->table('products')->where('id', $productId)->get();
        $conversionResult = $this->db->table('product_uom_conversions')->where('id', $conversionId)->get();
        $this->assertNotFalse($productResult);
        $this->assertNotFalse($conversionResult);
        $product = $productResult->getRowArray();
        $conversion = $conversionResult->getRowArray();
        $this->assertNotNull($product);
        $this->assertNotNull($conversion);
        $this->assertSame($uomId, (int) $product['base_uom_id']);
        $this->assertSame('ACTIVE', $conversion['status']);
    }

    public function testCreateEditAndSetStateRequireTheirOwnBasePermissions(): void
    {
        [$service, $operation] = $this->fixture(['create']);
        $id = $service->create(['code' => 'EA', 'name' => 'Each'], $operation);
        $this->assertDomainCode(fn () => $service->update($id, ['name' => 'No edit'], $operation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
        $this->assertDomainCode(fn () => $service->setState($id, 'INACTIVE', $operation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);

        [$editService, $editOperation] = $this->fixture(['edit']);
        $editId = (new ProductUomRepository($this->db))->insert(['code' => 'EDIT-ONLY', 'name' => 'Edit only', 'status' => 'ACTIVE'], $editOperation);
        $this->assertTrue($editService->update($editId, ['name' => 'Edited'], $editOperation));
        $this->assertDomainCode(fn () => $editService->setState($editId, 'INACTIVE', $editOperation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
    }

    public function testNoBasePermissionPbacDenyAbsoluteDenyAndSystemAdminHaveNoBypass(): void
    {
        [$service, $operation, $rootId, $userId, $groupId] = $this->fixture([], 'regular');
        $before = (int) $this->db->table('product_uoms')->countAllResults();
        $this->assertDomainCode(fn () => $service->create(['code' => 'NO-BASE', 'name' => 'Denied'], $operation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
        $this->assertSame($before, (int) $this->db->table('product_uoms')->countAllResults());

        [$deniedService, $deniedOperation, $_root, $_user, $deniedGroup] = $this->fixture(['create'], 'deny');
        // A regular DENY must affect all PBAC lanes; a GROUP-only DENY can be
        // outvoted by an assignment lane with no matching policy.
        $this->insertPolicy($rootId, $deniedGroup, 'DENY', 'ORGANIZATION');
        $before = (int) $this->db->table('product_uoms')->countAllResults();
        $this->assertDomainCode(fn () => $deniedService->create(['code' => 'PBAC-DENY', 'name' => 'Denied'], $deniedOperation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
        $this->assertSame($before, (int) $this->db->table('product_uoms')->countAllResults());

        [$absoluteService, $absoluteOperation] = $this->fixture(['create'], 'absolute');
        $absoluteGroup = $this->currentFixtureGroupId();
        $this->insertPolicy($rootId, $absoluteGroup, 'ABSOLUTE_DENY');
        $before = (int) $this->db->table('product_uoms')->countAllResults();
        $this->assertDomainCode(fn () => $absoluteService->create(['code' => 'ABS-DENY', 'name' => 'Denied'], $absoluteOperation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
        $this->assertSame($before, (int) $this->db->table('product_uoms')->countAllResults());

        [$adminService, $adminOperation] = $this->fixture([], 'SYSTEM_ADMIN');
        $adminBefore = (int) $this->db->table('product_uoms')->countAllResults();
        $this->assertDomainCode(fn () => $adminService->create(['code' => 'ADMIN', 'name' => 'No bypass'], $adminOperation), ProductUomMasterService::ERROR_AUTHORIZATION_DENIED);
        $this->assertSame($adminBefore, (int) $this->db->table('product_uoms')->countAllResults());
        $this->assertSame($rootId, $this->groupRootId());
        $this->assertGreaterThan(0, $userId);
        $this->assertGreaterThan(0, $groupId);
    }

    public function testClientOrganizationHintCannotOverrideGroupAuthority(): void
    {
        [$service, $operation, $rootId] = $this->fixture(['create']);
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => 'P2-COMPANY-' . uniqid(), 'name' => 'Company',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $companyId = (int) $this->db->insertID();
        $this->assertNotSame($rootId, $companyId);
        $this->assertDomainCode(
            fn () => $service->create(['code' => 'HINT', 'name' => 'Hint', 'evaluation_organization_id' => $companyId], $operation),
            'EVALUATION_ORGANIZATION_HINT_MISMATCH',
        );
    }

    public function testProductUomRepositoryDistinguishesNotFoundFromPersistenceFailure(): void
    {
        $repository = new ProductUomRepository($this->db);
        $this->assertNull($repository->findById(987654321));
        $this->assertFalse($repository->update(987654321, ['name' => 'Missing']));
        $this->assertDomainCode(fn () => $repository->update(1, []), ProductUomRepository::ERROR_UPDATE_EMPTY);
    }

    /** @param list<string> $permissions
     *  @return array{ProductUomMasterService, OperationContext, int, int, int}
     */
    private function fixture(array $permissions, string $label = 'fixture'): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $rootId = $this->groupRootId();
        $userId = $this->insertUser('p2-' . $label);
        EligibleActorFixture::assignAtOrganization($this->db, $userId, $rootId);
        $code = $label === 'SYSTEM_ADMIN'
            ? 'SYSTEM_ADMIN'
            : 'P2-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $label) ?: 'FIX', 0, 10)) . '-' . substr(uniqid(), -6);
        $this->db->table('groups')->insert(['code' => $code, 'name' => 'P2 ' . $label, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $groupId,
                'resource_action_id' => $this->resourceActionId('product_uom', $action),
                'created_at' => '2026-10-06 04:05:06',
            ]);
        }
        $operation = new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'prd002-' . $label, false);

        return [new ProductUomMasterService(db: $this->db), $operation, $rootId, $userId, $groupId];
    }

    private function groupRootId(): int
    {
        $result = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get();
        $this->assertNotFalse($result);
        $rows = $result->getResultArray();
        if ($rows !== []) {
            return (int) $rows[0]['id'];
        }
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'P2-GROUP-' . uniqid(), 'name' => 'P2 Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $prefix): int
    {
        $this->db->table('users')->insert([
            'username' => substr($prefix, 0, 12) . '-' . substr(uniqid(), -8),
            'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function resourceActionId(string $resource, string $action): int
    {
        $result = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $action)->get();
        $this->assertNotFalse($result);
        $row = $result->getRowArray();
        $this->assertNotNull($row);

        return (int) ($row['id'] ?? 0);
    }

    private function resourceId(string $resource): int
    {
        $result = $this->db->table('resources')->where('type', $resource)->get();
        $this->assertNotFalse($result);
        $row = $result->getRowArray();
        $this->assertNotNull($row);

        return (int) $row['id'];
    }

    private function insertPolicy(int $rootId, int $groupId, string $effect, string $participantType = 'GROUP'): int
    {
        $this->db->table('policies')->insert([
            'organization_id' => $rootId, 'resource_id' => $this->resourceId('product_uom'),
            'state_id' => null, 'participant_type' => $participantType,
            'participant_ref_id' => $participantType === 'ORGANIZATION' ? $rootId : $groupId,
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $policyId = (int) $this->db->insertID();
        $this->db->table('policy_actions')->insert([
            'policy_id' => $policyId, 'resource_action_id' => $this->resourceActionId('product_uom', 'create'),
            'effect' => $effect, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return $policyId;
    }

    private function currentFixtureGroupId(): int
    {
        $result = $this->db->table('groups')->orderBy('id', 'DESC')->get();
        $this->assertNotFalse($result);
        $row = $result->getRowArray();
        $this->assertNotNull($row);

        return (int) $row['id'];
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product UOM domain failure: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

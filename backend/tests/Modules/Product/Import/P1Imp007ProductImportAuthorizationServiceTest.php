<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Import\Reader\ImportDocument;
use App\Infrastructure\Import\Reader\ImportRow;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Import\ProductImportAuthorizationService;
use App\Modules\Product\Import\ProductImportModelBuilder;
use App\Modules\Product\Import\ProductImportPlan;
use App\Modules\Product\Import\ProductImportRowMutationService;
use App\Modules\Product\Import\ProductImportTemplateValidator;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Imp007ProductImportAuthorizationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testMissingImportPermissionDeniesBeforeJobCreation(): void
    {
        [$operation, , , $groupRootId] = $this->fixture(['create']);
        $service = $this->service();

        try {
            $service->startImport($this->jobData(), $operation);
            $this->fail('Expected Product import start denial.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductImportAuthorizationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }

        $this->assertSame(0, (int) $this->db->table('import_jobs')->where('created_by', $operation->actorUserId)->countAllResults());
        $this->assertGreaterThan(0, $groupRootId);
    }

    public function testImportGatePassesButCreateGateDeniesWithoutMutation(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'edit']);
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'new', 'product_code' => 'IMP007-DENY', 'product_name' => 'Denied', 'sku' => 'IMP007-DENY', 'price' => '1.00'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[0]->errorCode);
        $this->assertSame(0, (int) $this->db->table('products')->where('code', 'IMP007-DENY')->countAllResults());
    }

    public function testImportAndCreateGatesAllowCreate(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'new', 'product_code' => 'IMP007-CREATE', 'product_name' => 'Created', 'sku' => 'IMP007-CREATE', 'price' => '1.00'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertTrue($result->rows[0]->success);
        $this->assertSame('IMP007-CREATE', $result->rows[0]->entityKey);
    }

    public function testExistingProductEditRequiresEditPermission(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $id = $this->product('IMP007-EDIT', $uomCode, $groupCode, 'Original');
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP007-EDIT', 'description' => 'Denied'],
        ], ProductImportTemplateValidator::MODE_UPDATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[0]->errorCode);
        $this->assertSame('Original', $this->db->table('products')->where('id', $id)->get()->getRowArray()['product_name']);
    }

    public function testUpsertUsesEditAuthorizationForExistingTarget(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $id = $this->product('IMP007-UPSERT', $uomCode, $groupCode, 'Original');
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP007-UPSERT', 'description' => 'Denied update', 'price' => '4.00'],
        ], ProductImportTemplateValidator::MODE_UPSERT, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[0]->errorCode);
        $this->assertSame('Original', $this->db->table('products')->where('id', $id)->get()->getRowArray()['product_name']);
    }

    public function testUomAndComboRowsUseProductEditPermission(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $productId = $this->product('IMP007-UOM', $uomCode, $groupCode, 'UOM owner');
        $comboId = $this->product('IMP007-COMBO', $uomCode, $groupCode, 'Combo', 'COMBO');
        $componentId = $this->product('IMP007-COMP', $uomCode, $groupCode, 'Component');
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'UOM_CONVERSION', 'product_code' => 'IMP007-UOM', 'uom_code' => $uomCode, 'conversion_factor' => '2', 'price' => '3.00'],
            ['record_type' => 'COMBO_ITEM', 'combo_product_code' => 'IMP007-COMBO', 'component_product_code' => 'IMP007-COMP', 'quantity' => '1'],
        ], ProductImportTemplateValidator::MODE_UPSERT, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertFalse($result->rows[1]->success);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[0]->errorCode);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[1]->errorCode);
        $this->assertGreaterThan(0, $productId);
        $this->assertGreaterThan(0, $comboId);
        $this->assertGreaterThan(0, $componentId);
        $this->assertSame(0, (int) $this->db->table('product_uom_conversions')->where('product_id', $productId)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('product_combo_items')->where('combo_product_id', $comboId)->countAllResults());
    }

    public function testStateChangeRequiresSetstateAndEditCannotSubstitute(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'edit']);
        $id = $this->product('IMP007-STATE', $uomCode, $groupCode, 'State', 'STANDARD', 'ACTIVE');
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'state', 'product_code' => 'IMP007-STATE', 'status' => 'INACTIVE'],
        ], ProductImportTemplateValidator::MODE_UPDATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $result->rows[0]->errorCode);
        $this->assertSame('ACTIVE', $this->db->table('products')->where('id', $id)->get()->getRowArray()['status']);
    }

    public function testDeniedAutoRequiredCreateDoesNotConsumeNumber(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['import', 'edit']);
        $this->series('IMP007-AUTO-' . uniqid(), 'AUTO_REQUIRED', $groupRootId);
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'auto', 'product_name' => 'Denied auto', 'sku' => 'IMP007-AUTO'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertFalse($result->rows[0]->success);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->countAllResults());
        $this->assertSame(0, (int) $this->db->table('products')->where('sku', 'IMP007-AUTO')->countAllResults());
    }

    public function testStartGateUsesGroupAndRejectsClientContextOverride(): void
    {
        [$operation, , , $groupRootId] = $this->fixture(['import']);
        $companyId = $this->insertOrganization('COMPANY', $groupRootId);

        try {
            $this->service()->authorizeStart($operation, $companyId);
            $this->fail('Expected client evaluation context mismatch.');
        } catch (DomainException $exception) {
            $this->assertSame('EVALUATION_ORGANIZATION_HINT_MISMATCH', $exception->getErrorCode());
        }

        $decision = $this->service()->authorizeStart($operation);
        $this->assertSame($groupRootId, $decision->evaluationOrganizationId);
    }

    public function testAuthorizedGeneratedCodeStillResolvesSourceKey(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['import', 'create', 'edit']);
        $this->series('IMP007-SOURCE-' . uniqid(), 'AUTO_REQUIRED', $groupRootId);
        $result = $this->service()->executeRows($this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'parent', 'product_name' => 'Parent', 'sku' => 'IMP007-PARENT', 'price' => '5.00'],
            ['record_type' => 'PRODUCT', 'source_key' => 'child', 'product_child_name' => 'Child', 'parent_source_key' => 'parent', 'sku' => 'IMP007-CHILD', 'price' => '2.00'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode), $operation);

        $this->assertTrue($result->rows[0]->success);
        $this->assertTrue($result->rows[1]->success);
        $this->assertArrayHasKey('parent', $result->sourceMap);
        $child = $this->db->table('products')->where('sku', 'IMP007-CHILD')->get()->getRowArray();
        $this->assertSame($result->sourceMap['parent']['product_id'], (int) $child['parent_product_id']);
    }

    public function testSystemActorCannotBypassImportPermission(): void
    {
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp007-system', true);

        $this->expectException(DomainException::class);
        $this->service()->startImport($this->jobData(), $operation);
    }

    private function service(): ProductImportAuthorizationService
    {
        return new ProductImportAuthorizationService(
            authorization: new App\Authorization\AuthorizationService(db: $this->db),
            jobs: new App\Modules\Product\Import\ProductImportJobService(db: $this->db),
            rows: new ProductImportRowMutationService(db: $this->db),
        );
    }

    /** @param list<string> $permissions
     *  @return array{OperationContext,string,string,int}
     */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupRoot = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->orderBy('id', 'DESC')->get()->getRowArray();
        if ($groupRoot === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'IMP007-GROUP-' . uniqid(), 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $groupCode = 'IMP007-PG-' . uniqid();
        $uomCode = 'IMP007-UOM-' . uniqid();
        $this->db->table('product_groups')->insert(['code' => $groupCode, 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('product_uoms')->insert(['code' => $uomCode, 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('users')->insert(['username' => 'imp007-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $authCode = 'IMP007-AUTH-' . uniqid();
        $this->db->table('groups')->insert(['code' => $authCode, 'name' => 'Import actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($authCode), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }

        return [new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp007-' . uniqid(), false), $uomCode, $groupCode, (int) $groupRoot['id']];
    }

    /** @return array<string,mixed> */
    private function jobData(): array
    {
        return ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'];
    }

    private function product(string $code, string $uomCode, string $groupCode, string $name, string $type = 'STANDARD', string $status = 'ACTIVE'): int
    {
        $uomId = (int) $this->db->table('product_uoms')->where('code', $uomCode)->get()->getRowArray()['id'];
        $groupId = (int) $this->db->table('product_groups')->where('code', $groupCode)->get()->getRowArray()['id'];
        $this->db->table('products')->insert([
            'code' => $code, 'sku' => 'SKU-' . $code, 'product_name' => $name, 'product_type' => $type,
            'product_group_id' => $groupId, 'base_uom_id' => $uomId, 'price' => '10.00', 'status' => $status,
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<array<string,mixed>> $rows */
    private function plan(array $rows, string $mode, string $uomCode, string $groupCode): ProductImportPlan
    {
        $headers = array_values(array_unique(array_merge(...array_map('array_keys', $rows))));
        if (in_array('PRODUCT', array_map(static fn (array $row): string => (string) ($row['record_type'] ?? ''), $rows), true)) {
            $headers = array_values(array_unique(array_merge($headers, ['product_group_code', 'base_uom_code'])));
        }
        $sourceRows = [];
        foreach ($rows as $index => $row) {
            if (($row['record_type'] ?? null) === 'PRODUCT') {
                $row['product_group_code'] ??= $groupCode;
                $row['base_uom_code'] ??= $uomCode;
            }
            $sourceRows[] = new ImportRow($index + 2, array_map(static fn (string $header): mixed => $row[$header] ?? null, $headers));
        }
        $document = new ImportDocument($headers, $sourceRows);

        return (new ProductImportModelBuilder(db: $this->db))->build((new ProductImportTemplateValidator())->validate($document, $mode));
    }

    private function series(string $code, string $mode, int $groupRootId): int
    {
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();

        return (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => (int) $resource['id'], 'series_code' => $code, 'name' => $code, 'scope_type' => 'GROUP',
            'pattern' => 'IMP{SEQ:3}', 'reset_policy' => 'NEVER', 'generation_mode' => $mode, 'start_number' => 1,
            'increment_by' => 1, 'is_default' => 1, 'status' => 'ACTIVE',
        ]);
    }

    private function insertOrganization(string $type, int $parentId): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('IMP007-O-', true),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}

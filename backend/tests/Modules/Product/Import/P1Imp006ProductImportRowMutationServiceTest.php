<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Import\ProductImportModelBuilder;
use App\Modules\Product\Import\ProductImportPlan;
use App\Modules\Product\Import\ProductImportRowMutationService;
use App\Modules\Product\Import\ProductImportTemplateValidator;
use App\Modules\Product\Import\ProductImportValidationResult;
use App\Modules\Product\Product\ProductMasterService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Imp006ProductImportRowMutationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testGeneratedProductCodePropagatesToChildSourceReference(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['create', 'edit']);
        $this->series('IMP006-AUTO-' . uniqid(), 'AUTO_REQUIRED', $groupRootId);
        $plan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'child', 'product_child_name' => 'Blue', 'parent_source_key' => 'parent', 'sku' => 'IMP006-CHILD', 'price' => '2.00'],
            ['record_type' => 'PRODUCT', 'source_key' => 'parent', 'product_name' => 'Parent', 'sku' => 'IMP006-PARENT', 'price' => '5.00'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode);

        $result = (new ProductImportRowMutationService(db: $this->db))->execute($plan, $operation);
        $this->assertCount(2, array_filter($result->rows, static fn ($row): bool => $row->success));
        $this->assertArrayHasKey('parent', $result->sourceMap);
        $this->assertNotSame('', $result->sourceMap['parent']['product_code']);
        $child = $this->db->table('products')->where('sku', 'IMP006-CHILD')->get()->getRowArray();
        $this->assertNotNull($child);
        $this->assertSame($result->sourceMap['parent']['product_id'], (int) $child['parent_product_id']);
    }

    public function testProductModeSemanticsAndUpdateBlankVersusExplicitNull(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['create', 'edit']);
        $existingId = $this->product('IMP006-EXISTING', $uomCode, $groupCode, 'Original');
        $createPlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP006-EXISTING', 'product_name' => 'Nope', 'sku' => 'IMP006-DUP'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode);
        $createResult = (new ProductImportRowMutationService(db: $this->db))->execute($createPlan, $operation);
        $this->assertFalse($createResult->rows[0]->success);

        $updatePlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP006-EXISTING', 'description' => 'Changed'],
        ], ProductImportTemplateValidator::MODE_UPDATE_ONLY, $uomCode, $groupCode);
        $updateResult = (new ProductImportRowMutationService(db: $this->db))->execute($updatePlan, $operation);
        $this->assertTrue($updateResult->rows[0]->success);
        $this->assertSame('Changed', $this->db->table('products')->where('id', $existingId)->get()->getRowArray()['description']);

        $blankPlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP006-EXISTING', 'description' => ''],
        ], ProductImportTemplateValidator::MODE_UPDATE_ONLY, $uomCode, $groupCode);
        (new ProductImportRowMutationService(db: $this->db))->execute($blankPlan, $operation);
        $this->assertSame('Changed', $this->db->table('products')->where('id', $existingId)->get()->getRowArray()['description']);

        $nullPlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'existing', 'product_code' => 'IMP006-EXISTING', 'description' => '__NULL__'],
        ], ProductImportTemplateValidator::MODE_UPDATE_ONLY, $uomCode, $groupCode);
        (new ProductImportRowMutationService(db: $this->db))->execute($nullPlan, $operation);
        $this->assertNull($this->db->table('products')->where('id', $existingId)->get()->getRowArray()['description']);
    }

    public function testUomUpsertAndComboMergePreserveUnrelatedItems(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['create', 'edit']);
        $alternateCode = 'IMP006-BOX-' . uniqid();
        $this->db->table('product_uoms')->insert(['code' => $alternateCode, 'name' => 'Box', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $ownerId = $this->product('IMP006-COMBO', $uomCode, $groupCode, 'Combo', 'COMBO');
        $componentId = $this->product('IMP006-COMPONENT', $uomCode, $groupCode, 'Component');
        $otherId = $this->product('IMP006-OTHER', $uomCode, $groupCode, 'Other');
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $ownerId, 'component_product_id' => $otherId, 'quantity' => '2.000000', 'created_at' => '2026-10-06 04:05:06',
        ]);

        $plan = $this->plan([
            ['record_type' => 'UOM_CONVERSION', 'product_code' => 'IMP006-COMPONENT', 'uom_code' => $alternateCode, 'conversion_factor' => '12', 'price' => '20.00'],
            ['record_type' => 'COMBO_ITEM', 'combo_product_code' => 'IMP006-COMBO', 'component_product_code' => 'IMP006-COMPONENT', 'quantity' => '3'],
        ], ProductImportTemplateValidator::MODE_UPSERT, $uomCode, $groupCode, ['record_type', 'product_code', 'uom_code', 'conversion_factor', 'price', 'combo_product_code', 'component_product_code', 'quantity']);
        $result = (new ProductImportRowMutationService(db: $this->db))->execute($plan, $operation);
        $this->assertTrue($result->rows[0]->success);
        $this->assertTrue($result->rows[1]->success);
        $this->assertSame(2, (int) $this->db->table('product_combo_items')->where('combo_product_id', $ownerId)->countAllResults());
        $this->assertSame(1, (int) $this->db->table('product_uom_conversions')->where('product_id', $componentId)->countAllResults());
    }

    public function testAutoRequiredManualIdentifierIsRejectedAndCounterRollsBackOnCreateFailure(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['create', 'edit']);
        $this->series('IMP006-REQ-' . uniqid(), 'AUTO_REQUIRED', $groupRootId);
        $manualPlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'manual', 'product_code' => 'MANUAL-IMP006', 'product_name' => 'Manual', 'sku' => 'IMP006-MANUAL'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode);
        $manual = (new ProductImportRowMutationService(db: $this->db))->execute($manualPlan, $operation);
        $this->assertFalse($manual->rows[0]->success);
        $this->assertSame(0, (int) $this->db->table('products')->where('code', 'MANUAL-IMP006')->countAllResults());

        $failedPlan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'failed', 'product_name' => 'Missing SKU'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode);
        $failed = (new ProductImportRowMutationService(db: $this->db))->execute($failedPlan, $operation);
        $this->assertFalse($failed->rows[0]->success);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->countAllResults());
    }

    public function testResultsKeepOriginalRowsAndDoNotPersistImportRows(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['create', 'edit']);
        $plan = $this->plan([
            ['record_type' => 'PRODUCT', 'source_key' => 'one', 'product_code' => 'IMP006-ONE-CODE', 'product_name' => 'One', 'sku' => 'IMP006-ONE', 'price' => '1.00'],
            ['record_type' => 'PRODUCT', 'source_key' => 'two', 'product_code' => 'IMP006-TWO-CODE', 'product_name' => 'Two', 'sku' => 'IMP006-TWO', 'price' => '2.00'],
        ], ProductImportTemplateValidator::MODE_CREATE_ONLY, $uomCode, $groupCode);
        $result = (new ProductImportRowMutationService(db: $this->db))->execute($plan, $operation);

        $this->assertSame([2, 3], array_map(static fn ($row): int => $row->rowNumber, $result->rows));
        $this->assertSame(0, (int) $this->db->table('import_job_rows')->countAllResults());
        $this->assertArrayHasKey('one', $result->sourceMap);
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
        $this->assertStringNotContainsString('Repository', (string) file_get_contents(dirname(__DIR__, 4) . '/app/Modules/Product/Import/ProductImportRowMutationService.php'));
    }

    /** @param list<string> $permissions
     *  @return array{OperationContext,string,string,int}
     */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupRoot = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->orderBy('id', 'DESC')->get()->getRowArray();
        if ($groupRoot === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'IMP006-GROUP-' . uniqid(), 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $groupCode = 'IMP006-G-' . uniqid();
        $uomCode = 'IMP006-U-' . uniqid();
        $this->db->table('product_groups')->insert(['code' => $groupCode, 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('product_uoms')->insert(['code' => $uomCode, 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('users')->insert(['username' => 'imp006-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $authCode = 'IMP006-A-' . uniqid();
        $this->db->table('groups')->insert(['code' => $authCode, 'name' => 'Import actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($authCode), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }

        return [new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp006-' . uniqid(), false), $uomCode, $groupCode, (int) $groupRoot['id']];
    }

    private function product(string $code, string $uomCode, string $groupCode, string $name, string $type = 'STANDARD'): int
    {
        $uomId = (int) $this->db->table('product_uoms')->where('code', $uomCode)->get()->getRowArray()['id'];
        $groupId = (int) $this->db->table('product_groups')->where('code', $groupCode)->get()->getRowArray()['id'];
        $this->db->table('products')->insert([
            'code' => $code, 'sku' => 'SKU-' . $code, 'product_name' => $name, 'product_type' => $type,
            'product_group_id' => $groupId, 'base_uom_id' => $uomId, 'price' => '10.00', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<array<string,mixed>> $rows
     *  @param list<string>|null $headers
     */
    private function plan(array $rows, string $mode, string $uomCode, string $groupCode, ?array $headers = null): ProductImportPlan
    {
        if ($headers === null) {
            $headers = array_values(array_unique(array_merge(...array_map('array_keys', $rows))));
            if (in_array('PRODUCT', array_map(static fn (array $row): string => (string) ($row['record_type'] ?? ''), $rows), true)) {
                $headers = array_values(array_unique(array_merge($headers, ['product_group_code', 'base_uom_code'])));
            }
        }
        $sourceRows = [];
        foreach ($rows as $index => $row) {
            if (($row['record_type'] ?? null) === 'PRODUCT') {
                $row['product_group_code'] ??= $groupCode;
                $row['base_uom_code'] ??= $uomCode;
            }
            $sourceRows[] = new App\Infrastructure\Import\Reader\ImportRow($index + 2, array_map(static fn (string $header): mixed => $row[$header] ?? null, $headers));
        }
        $document = new App\Infrastructure\Import\Reader\ImportDocument($headers, $sourceRows);
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
}

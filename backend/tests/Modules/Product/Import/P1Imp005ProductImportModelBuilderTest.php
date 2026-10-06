<?php

declare(strict_types=1);

use App\Infrastructure\Import\Reader\ImportDocument;
use App\Infrastructure\Import\Reader\ImportRow;
use App\Modules\Product\Import\ProductImportGraphException;
use App\Modules\Product\Import\ProductImportModelBuilder;
use App\Modules\Product\Import\ProductImportPlan;
use App\Modules\Product\Import\ProductImportTemplateValidator;
use App\Modules\Product\Import\ProductImportValidationResult;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Imp005ProductImportModelBuilderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private string $uomCode = '';
    private int $uomId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uomCode = 'EA-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $this->db->table('product_uoms')->insert([
            'code' => $this->uomCode,
            'name' => 'Each',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $this->uomId = (int) $this->db->insertID();
    }

    public function testProductSourceKeysAndCodesAreUniqueAndRequired(): void
    {
        $this->assertGraphCode($this->document([
            ['PRODUCT', 'same'], ['PRODUCT', 'same'],
        ]), ProductImportGraphException::ERROR_DUPLICATE_SOURCE_KEY);

        $this->assertGraphCode($this->document([
            ['PRODUCT', 'one', 'P-1'], ['PRODUCT', 'two', 'P-1'],
        ], ['record_type', 'source_key', 'product_code']), ProductImportGraphException::ERROR_DUPLICATE_PRODUCT_CODE);

        $this->assertGraphCode($this->document([
            ['PRODUCT', ''],
        ]), ProductImportGraphException::ERROR_SOURCE_KEY_REQUIRED);
    }

    public function testForwardParentReferenceProducesParentBeforeChild(): void
    {
        $plan = $this->build($this->document([
            ['PRODUCT', 'child', '', 'parent'],
            ['PRODUCT', 'parent', '', ''],
        ], ['record_type', 'source_key', 'product_code', 'parent_source_key']));

        $this->assertSame(['parent', 'child'], array_map(
            static fn ($row): ?string => $row->sourceKey,
            array_filter($plan->processingRows, static fn ($row): bool => $row->sourceKey !== null),
        ));
        $this->assertSame([2, 3], array_map(static fn ($row): int => $row->getRowNumber(), $plan->rows));
    }

    public function testUomBeforeOwnerAndComboBeforeOwnerComponentAreReordered(): void
    {
        $uomPlan = $this->build($this->document([
            ['record_type' => 'UOM_CONVERSION', 'product_source_key' => 'owner', 'uom_code' => $this->uomCode],
            ['record_type' => 'PRODUCT', 'source_key' => 'owner', 'product_type' => 'STANDARD'],
        ], ['record_type', 'product_source_key', 'uom_code', 'source_key', 'product_type']));
        $this->assertSame(['owner', null], array_map(
            static fn ($row): ?string => $row->sourceKey,
            $uomPlan->processingRows,
        ));

        $comboPlan = $this->build($this->document([
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'owner', 'component_source_key' => 'component'],
            ['record_type' => 'PRODUCT', 'source_key' => 'owner', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'component', 'product_type' => 'STANDARD'],
        ], ['record_type', 'combo_source_key', 'component_source_key', 'source_key', 'product_type']));
        $this->assertSame('PRODUCT', $comboPlan->processingRows[0]->getRecordType());
        $this->assertSame('COMBO_ITEM', $comboPlan->processingRows[2]->getRecordType());
    }

    public function testNestedComboOrderAndDiamondDependenciesAreDeterministic(): void
    {
        $rows = [
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'a', 'component_source_key' => 'b'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'b', 'component_source_key' => 'c'],
            ['record_type' => 'PRODUCT', 'source_key' => 'a', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'b', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'c', 'product_type' => 'STANDARD'],
        ];
        $headers = ['record_type', 'combo_source_key', 'component_source_key', 'source_key', 'product_type'];
        $first = $this->build($this->document($rows, $headers));
        $second = $this->build($this->document($rows, $headers));

        $firstOrder = array_map(static fn ($row): string => $row->nodeId, $first->processingRows);
        $this->assertSame($firstOrder, array_map(static fn ($row): string => $row->nodeId, $second->processingRows));
        $this->assertLessThan(array_search('row:2', $firstOrder, true), array_search('row:1', $firstOrder, true));

        $diamond = $this->build($this->document([
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'a', 'component_source_key' => 'b'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'a', 'component_source_key' => 'c'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'b', 'component_source_key' => 'd'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'c', 'component_source_key' => 'd'],
            ['record_type' => 'PRODUCT', 'source_key' => 'a', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'b', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'c', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'd', 'product_type' => 'STANDARD'],
        ], ['record_type', 'combo_source_key', 'component_source_key', 'source_key', 'product_type']));
        $this->assertCount(8, $diamond->processingRows);
    }

    public function testDirectAndDeepComboCyclesFailButMissingReferencesAreTyped(): void
    {
        $cycle = [
            ['record_type' => 'PRODUCT', 'source_key' => 'a', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'b', 'product_type' => 'COMBO'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'a', 'component_source_key' => 'b'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'b', 'component_source_key' => 'a'],
        ];
        $cycleHeaders = ['record_type', 'source_key', 'product_type', 'combo_source_key', 'component_source_key'];
        $this->assertGraphCode($this->document($cycle, $cycleHeaders), ProductImportGraphException::ERROR_CYCLE);

        $deep = [
            ['record_type' => 'PRODUCT', 'source_key' => 'a', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'b', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'c', 'product_type' => 'COMBO'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'a', 'component_source_key' => 'b'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'b', 'component_source_key' => 'c'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'c', 'component_source_key' => 'a'],
        ];
        $this->assertGraphCode($this->document($deep, $cycleHeaders), ProductImportGraphException::ERROR_CYCLE);

        $this->assertGraphCode($this->document([
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'missing', 'component_source_key' => 'component'],
        ], ['record_type', 'combo_source_key', 'component_source_key']), ProductImportGraphException::ERROR_MISSING_SOURCE_REFERENCE);
    }

    public function testPersistentProductCodeResolvesReadOnlyAndUpdateRequiresIt(): void
    {
        $code = 'EXISTING-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $this->db->table('products')->insert([
            'code' => $code, 'sku' => $code . '-SKU', 'product_name' => 'Existing',
            'product_type' => 'STANDARD', 'base_uom_id' => $this->uomId,
            'price' => '10.00', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $id = (int) $this->db->insertID();
        $plan = $this->build($this->document([
            ['PRODUCT', 'existing', $code],
        ], ['record_type', 'source_key', 'product_code'], ProductImportTemplateValidator::MODE_UPDATE_ONLY));
        $this->assertSame(ProductImportModelBuilder::TARGET_UPDATE, $plan->rows[0]->targetPath);
        $this->assertSame($id, $plan->rows[0]->existingProductId);

        $this->assertGraphCode($this->document([
            ['PRODUCT', 'only-source'],
        ], ['record_type', 'source_key'], ProductImportTemplateValidator::MODE_UPDATE_ONLY), ProductImportGraphException::ERROR_PRODUCT_CODE_REQUIRED);
        $this->assertGraphCode($this->document([
            ['UOM_CONVERSION', '', 'DOES-NOT-EXIST', $this->uomCode],
        ], ['record_type', 'product_source_key', 'product_code', 'uom_code']), ProductImportGraphException::ERROR_MISSING_PRODUCT_REFERENCE);
    }

    public function testDuplicateResolvedUomAndComboIdentitiesAreRejected(): void
    {
        $this->assertGraphCode($this->document([
            ['record_type' => 'PRODUCT', 'source_key' => 'owner'],
            ['record_type' => 'UOM_CONVERSION', 'product_source_key' => 'owner', 'uom_code' => $this->uomCode],
            ['record_type' => 'UOM_CONVERSION', 'product_source_key' => 'owner', 'uom_code' => $this->uomCode],
        ], ['record_type', 'source_key', 'product_source_key', 'uom_code']), ProductImportGraphException::ERROR_DUPLICATE_UOM_CONVERSION);

        $this->assertGraphCode($this->document([
            ['record_type' => 'PRODUCT', 'source_key' => 'owner', 'product_code' => 'OWNER-CODE', 'product_type' => 'COMBO'],
            ['record_type' => 'PRODUCT', 'source_key' => 'component', 'product_code' => 'COMP-CODE', 'product_type' => 'STANDARD'],
            ['record_type' => 'COMBO_ITEM', 'combo_source_key' => 'owner', 'component_source_key' => 'component'],
            ['record_type' => 'COMBO_ITEM', 'combo_product_code' => 'OWNER-CODE', 'component_product_code' => 'COMP-CODE'],
        ], ['record_type', 'source_key', 'product_code', 'product_type', 'combo_source_key', 'component_source_key', 'combo_product_code', 'component_product_code']), ProductImportGraphException::ERROR_DUPLICATE_COMBO_ITEM);
    }

    public function testNoMutationNumberingAuthorizationOrRowPersistenceIsPerformed(): void
    {
        $before = (int) $this->db->table('products')->countAllResults();
        $plan = $this->build($this->document([
            ['PRODUCT', 'source-only'],
        ]));
        $this->assertCount(1, $plan->rows);
        $this->assertSame($before, (int) $this->db->table('products')->countAllResults());
        $this->assertNull($plan->rows[0]->productCode);
        $this->assertSame('source-only', $plan->rows[0]->sourceKey);

        $builderPath = dirname(__DIR__, 4) . '/app/Modules/Product/Import/ProductImportModelBuilder.php';
        $contents = (string) file_get_contents($builderPath);
        $this->assertStringNotContainsString('AutoNumberService', $contents);
        $this->assertStringNotContainsString('AuthorizationService', $contents);
        $this->assertStringNotContainsString('import_job_rows', $contents);
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
    }

    /** @param list<list<string>|array<string,mixed>> $rows */
    private function document(array $rows, array $headers = ['record_type', 'source_key'], string $mode = ProductImportTemplateValidator::MODE_CREATE_ONLY): ProductImportValidationResult
    {
        $sourceRows = [];
        foreach ($rows as $index => $row) {
            $cells = array_is_list($row)
                ? $row
                : array_map(static fn (string $header): mixed => $row[$header] ?? null, $headers);
            $sourceRows[] = new ImportRow($index + 2, $cells);
        }

        $document = new ImportDocument($headers, $sourceRows);

        return (new ProductImportTemplateValidator())->validate($document, $mode);
    }

    private function build(ProductImportValidationResult $document): ProductImportPlan
    {
        return (new ProductImportModelBuilder(db: $this->db))->build($document);
    }

    private function assertGraphCode(ProductImportValidationResult $document, string $code): void
    {
        try {
            $this->build($document);
            $this->fail('Expected Product import graph failure: ' . $code);
        } catch (ProductImportGraphException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

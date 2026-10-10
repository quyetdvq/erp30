<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use App\Infrastructure\Import\Reader\XlsxParseException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Import\ProductImportAuthorizationService;
use App\Modules\Product\Import\ProductImportJobService;
use App\Modules\Product\Import\ProductImportRowMutationService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @internal */
final class P1Imp090ProductImportRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->temporaryFiles = [];
        parent::tearDown();
    }

    public function testCsvAndXlsxRunThroughEquivalentCanonicalPipeline(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $csvJob = $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'],
            " RECORD_TYPE , Product_Code , Product_Name , SKU , PRICE , Product_Group_Code , Base_Uom_Code , Source_Key \nPRODUCT,IMP090-CSV,Điện thoại CSV,IMP090-CSV,1.00,{$groupCode},{$uomCode},csv\n",
            $operation,
        );
        $xlsxPath = $this->workbook([
            [' RECORD_TYPE ', ' Product_Code ', ' Product_Name ', ' SKU ', ' PRICE ', ' Product_Group_Code ', ' Base_Uom_Code ', ' Source_Key '],
            ['PRODUCT', 'IMP090-XLSX', 'Điện thoại XLSX', 'IMP090-XLSX', '1.00', $groupCode, $uomCode, 'xlsx'],
        ]);
        $xlsxSpreadsheet = IOFactory::load($xlsxPath);
        $xlsxSpreadsheet->getActiveSheet()->setCellValueExplicit('E2', '1.00', DataType::TYPE_STRING);
        (new Xlsx($xlsxSpreadsheet))->save($xlsxPath);
        $xlsxSpreadsheet->disconnectWorksheets();

        $xlsxJob = $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'XLSX'],
            $xlsxPath,
            $operation,
        );

        foreach ([$csvJob, $xlsxJob] as $jobId) {
            $job = (new ProductImportJobService(db: $this->db))->findById($jobId);
            $rows = (new ImportJobRowRepository($this->db))->findByJobId($jobId);
            $this->assertSame('COMPLETED', $job['status'], json_encode($rows, JSON_THROW_ON_ERROR));
            $this->assertSame('1', (string) $job['total_rows']);
            $this->assertSame('1', (string) $job['success_rows']);
            $this->assertSame(['SUCCESS'], array_column($rows, 'row_status'));
            $this->assertSame([2], array_map(static fn (array $row): int => (int) $row['row_number'], $rows));
        }

        $this->assertSame(1, (int) $this->db->table('products')->where('code', 'IMP090-CSV')->countAllResults());
        $this->assertSame(1, (int) $this->db->table('products')->where('code', 'IMP090-XLSX')->countAllResults());
        $this->assertSame('Điện thoại XLSX', $this->db->table('products')->where('code', 'IMP090-XLSX')->get()->getRowArray()['product_name']);
    }

    public function testFormulaFailureIsFatalAndDoesNotPersistRows(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $path = $this->workbook([
            ['record_type', 'product_code', 'product_name', 'sku', 'price', 'product_group_code', 'base_uom_code', 'source_key'],
            ['PRODUCT', 'IMP090-FORMULA', 'Formula', 'IMP090-FORMULA', '1.00', $groupCode, $uomCode, 'formula'],
        ]);
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getActiveSheet()->getCell('C2')->setValue('=1+1')->setCalculatedValue(2);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $this->service()->run(
                ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'XLSX'],
                $path,
                $operation,
            );
            $this->fail('Expected formula rejection.');
        } catch (XlsxParseException $exception) {
            $this->assertSame(XlsxParseException::ERROR_FORMULA_CELL, $exception->getErrorCode());
        }

        $job = $this->latestJob((int) $operation->actorUserId);
        $this->assertSame('FAILED', $job['status']);
        $this->assertNotNull($job['completed_at']);
        $this->assertSame(0, (int) $this->db->table('import_job_rows')->where('import_job_id', $job['id'])->countAllResults());
        $this->assertSame(0, (int) $this->db->table('products')->where('code', 'IMP090-FORMULA')->countAllResults());
    }

    public function testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['import', 'create', 'edit']);
        $this->series('IMP090-AUTO-' . uniqid(), $groupRootId);
        $source = implode("\n", [
            'record_type,source_key,product_code,product_name,product_child_name,sku,price,product_group_code,base_uom_code,parent_source_key',
            "PRODUCT,child,,,Child,IMP090-CHILD,2.00,{$groupCode},{$uomCode},parent",
            "PRODUCT,parent,,Parent,,IMP090-PARENT,5.00,{$groupCode},{$uomCode},",
            '',
        ]);

        $jobId = $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'],
            $source,
            $operation,
        );
        $rows = (new ImportJobRowRepository($this->db))->findByJobId($jobId);
        $parent = $this->db->table('products')->where('sku', 'IMP090-PARENT')->get()->getRowArray();
        $child = $this->db->table('products')->where('sku', 'IMP090-CHILD')->get()->getRowArray();

        $this->assertSame([2, 3], array_map(static fn (array $row): int => (int) $row['row_number'], $rows));
        $this->assertSame(['SUCCESS', 'SUCCESS'], array_column($rows, 'row_status'), json_encode($rows, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($rows[0]['entity_key']);
        $this->assertNotEmpty($rows[1]['entity_key']);
        $this->assertNotSame('', $parent['code']);
        $this->assertNotSame('', $child['code']);
        $this->assertSame((int) $parent['id'], (int) $child['parent_product_id']);
    }

    public function testIdenticalSourceHashCreatesARealRerun(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'edit']);
        $this->product('IMP090-RERUN', $uomCode, $groupCode, 'Before');
        $data = [
            'entity_type' => 'PRODUCT', 'mode' => 'UPDATE_ONLY', 'source_format' => 'CSV',
            'source_file_name' => 'rerun.csv', 'source_file_hash' => 'same-hash',
        ];
        $source = "record_type,source_key,product_code,product_name\nPRODUCT,rerun,IMP090-RERUN,After\n";

        $first = $this->service()->run($data, $source, $operation);
        $second = $this->service()->run($data, $source, $operation);

        $this->assertNotSame($first, $second);
        $this->assertSame('COMPLETED', (new ProductImportJobService(db: $this->db))->findById($first)['status']);
        $this->assertSame('COMPLETED', (new ProductImportJobService(db: $this->db))->findById($second)['status']);
        $this->assertSame(2, (int) $this->db->table('import_jobs')->where('source_file_hash', 'same-hash')->countAllResults());
        $this->assertSame('After', $this->db->table('products')->where('code', 'IMP090-RERUN')->get()->getRowArray()['product_name']);
    }

    public function testModesNullSemanticsAndUnmentionedDependenciesRemainCanonical(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create', 'edit']);
        $alternateUom = 'IMP090-BOX-' . uniqid();
        $this->db->table('product_uoms')->insert(['code' => $alternateUom, 'name' => 'Box', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $productId = $this->product('IMP090-MODE', $uomCode, $groupCode, 'Original');
        $alternateUomId = (int) $this->db->table('product_uoms')->where('code', $alternateUom)->get()->getRowArray()['id'];
        $this->db->table('product_uom_conversions')->insert([
            'product_id' => $productId, 'uom_id' => $alternateUomId, 'conversion_factor' => '12.000000',
            'price' => '20.00', 'usage_type' => 'BOTH', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $comboId = $this->product('IMP090-COMBO', $uomCode, $groupCode, 'Combo', 'COMBO');
        $componentId = $this->product('IMP090-COMP', $uomCode, $groupCode, 'Component');
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $comboId, 'component_product_id' => $componentId, 'quantity' => '2.000000', 'created_at' => '2026-10-06 04:05:06',
        ]);

        $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'UPDATE_ONLY', 'source_format' => 'CSV'],
            "record_type,source_key,product_code,description\nPRODUCT,mode,IMP090-MODE,\n",
            $operation,
        );
        $this->assertSame('Original', $this->db->table('products')->where('id', $productId)->get()->getRowArray()['description']);

        $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'UPDATE_ONLY', 'source_format' => 'CSV'],
            "record_type,source_key,product_code,description\nPRODUCT,mode,IMP090-MODE,__NULL__\n",
            $operation,
        );
        $this->assertNull($this->db->table('products')->where('id', $productId)->get()->getRowArray()['description']);

        $jobId = $this->service()->run(
            ['entity_type' => 'PRODUCT', 'mode' => 'UPSERT', 'source_format' => 'CSV'],
            "record_type,source_key,product_code,product_name\nPRODUCT,mode,IMP090-MODE,Upserted\n",
            $operation,
        );
        $this->assertSame('COMPLETED', (new ProductImportJobService(db: $this->db))->findById($jobId)['status']);
        $this->assertSame(1, (int) $this->db->table('product_uom_conversions')->where('product_id', $productId)->countAllResults());
        $this->assertSame(1, (int) $this->db->table('product_combo_items')->where('combo_product_id', $comboId)->countAllResults());
        $this->assertSame('Upserted', $this->db->table('products')->where('id', $productId)->get()->getRowArray()['product_name']);
    }

    public function testGateOneDenialAndSystemActorCannotStartPipeline(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['create']);
        $before = (int) $this->db->table('import_jobs')->where('created_by', $operation->actorUserId)->countAllResults();
        $source = "record_type,source_key,product_code,product_name,sku,price,product_group_code,base_uom_code\nPRODUCT,denied,IMP090-DENIED,Denied,IMP090-DENIED,1.00,{$groupCode},{$uomCode}\n";

        try {
            $this->service()->run(
                ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'],
                $source,
                $operation,
            );
            $this->fail('Expected Gate 1 denial.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductImportAuthorizationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertSame($before, (int) $this->db->table('import_jobs')->where('created_by', $operation->actorUserId)->countAllResults());

        $system = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp090-system', true);
        try {
            $this->service()->run(
                ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'],
                $source,
                $system,
            );
            $this->fail('Expected System Admin denial.');
        } catch (DomainException $exception) {
            $this->assertSame('AUTH_CONTEXT_REQUIRED', $exception->getErrorCode());
        }
    }

    private function service(): ProductImportAuthorizationService
    {
        return new ProductImportAuthorizationService(
            authorization: new AuthorizationService(db: $this->db),
            jobs: new ProductImportJobService(db: $this->db),
            rows: new ProductImportRowMutationService(db: $this->db),
            jobRows: new ImportJobRowRepository($this->db),
            db: $this->db,
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
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'IMP090-GROUP-' . uniqid(), 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $groupCode = 'IMP090-PG-' . uniqid();
        $uomCode = 'IMP090-UOM-' . uniqid();
        $this->db->table('product_groups')->insert(['code' => $groupCode, 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('product_uoms')->insert(['code' => $uomCode, 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('users')->insert(['username' => 'imp090-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $authCode = 'IMP090-AUTH-' . uniqid();
        $this->db->table('groups')->insert(['code' => $authCode, 'name' => 'Import actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($authCode), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }

        $this->db->table('employees')->insert([
            'user_id' => $userId, 'employee_code' => 'IMP090-E-' . uniqid(),
            'employee_name' => 'Import actor', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $employeeId = (int) $this->db->insertID();
        $this->db->table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'organization_id' => (int) $groupRoot['id'],
            'department_id' => null, 'position_id' => null, 'is_primary' => 0,
            'effective_from' => '2026-01-01', 'effective_to' => null,
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return [new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp090-' . uniqid(), false), $uomCode, $groupCode, (int) $groupRoot['id']];
    }

    private function product(string $code, string $uomCode, string $groupCode, string $name, string $type = 'STANDARD'): int
    {
        $uomId = (int) $this->db->table('product_uoms')->where('code', $uomCode)->get()->getRowArray()['id'];
        $groupId = (int) $this->db->table('product_groups')->where('code', $groupCode)->get()->getRowArray()['id'];
        $this->db->table('products')->insert([
            'code' => $code, 'sku' => 'SKU-' . $code, 'product_name' => $name, 'product_type' => $type,
            'product_group_id' => $groupId, 'base_uom_id' => $uomId, 'price' => '10.00', 'description' => $name, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function series(string $code, int $groupRootId): int
    {
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();

        return (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => (int) $resource['id'], 'series_code' => $code, 'name' => $code, 'scope_type' => 'GROUP',
            'pattern' => 'IMP{SEQ:3}', 'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1,
            'increment_by' => 1, 'is_default' => 1, 'status' => 'ACTIVE',
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'p1-imp090-');
        $this->assertNotFalse($path);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /** @return array<string,mixed> */
    private function latestJob(int $actorId): array
    {
        return $this->db->table('import_jobs')->where('created_by', $actorId)->orderBy('id', 'DESC')->get()->getRowArray();
    }
}

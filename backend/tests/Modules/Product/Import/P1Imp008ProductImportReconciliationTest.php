<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Import\Persistence\ImportJobRepository;
use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Import\ProductImportAuthorizationService;
use App\Modules\Product\Import\ProductImportJobService;
use App\Modules\Product\Import\ProductImportRowMutationService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Imp008ProductImportReconciliationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testRowRepositoryPersistsFieldsAndRejectsDuplicateJobRow(): void
    {
        [$operation] = $this->fixture(['import']);
        $jobId = (new ProductImportJobService(db: $this->db))->create($this->jobData(), $operation);
        $rows = new ImportJobRowRepository($this->db);
        $rowId = $rows->insert([
            'import_job_id' => $jobId, 'row_number' => 7, 'row_status' => 'ERROR',
            'entity_key' => 'IMP008-ERR', 'target_ref_id' => 45,
            'error_code' => 'PRODUCT_AUTHORIZATION_DENIED', 'error_message' => 'denied',
        ], $operation);
        $stored = $rows->findById($rowId);
        $this->assertSame(7, (int) $stored['row_number']);
        $this->assertSame('ERROR', $stored['row_status']);
        $this->assertSame('IMP008-ERR', $stored['entity_key']);
        $this->assertSame('45', (string) $stored['target_ref_id']);
        $this->assertSame('PRODUCT_AUTHORIZATION_DENIED', $stored['error_code']);
        $this->assertSame('denied', $stored['error_message']);

        $this->expectException(DomainException::class);
        $rows->insert([
            'import_job_id' => $jobId, 'row_number' => 7, 'row_status' => 'SUCCESS',
        ], $operation);
    }

    public function testReconcileCountsPersistedRowsAndPreservesSkipped(): void
    {
        [$operation] = $this->fixture(['import']);
        $jobs = new ProductImportJobService(db: $this->db);
        $jobId = $jobs->create($this->jobData(), $operation);
        $jobs->startProcessing($jobId, $operation);
        $rows = new ImportJobRowRepository($this->db);
        $rows->insertMany([
            ['import_job_id' => $jobId, 'row_number' => 9, 'row_status' => 'SKIPPED'],
            ['import_job_id' => $jobId, 'row_number' => 2, 'row_status' => 'ERROR', 'error_code' => 'ROW_ERROR'],
            ['import_job_id' => $jobId, 'row_number' => 5, 'row_status' => 'SUCCESS', 'entity_key' => 'IMP008-OK', 'target_ref_id' => 99],
        ], $operation);

        $job = $jobs->reconcile($jobId, $rows, $operation);
        $this->assertSame('COMPLETED_WITH_ERRORS', $job['status']);
        $this->assertSame('3', (string) $job['total_rows']);
        $this->assertSame('1', (string) $job['success_rows']);
        $this->assertSame('1', (string) $job['error_rows']);
        $this->assertNotNull($job['completed_at']);
        $this->assertSame([2, 5, 9], array_map(static fn (array $row): int => (int) $row['row_number'], $rows->findByJobId($jobId)));
        $this->assertStringContainsString('FOR UPDATE', strtoupper((string) file_get_contents(dirname(__DIR__, 4) . '/app/Infrastructure/Import/Persistence/ImportJobRepository.php')));
    }

    public function testAllSuccessPipelineCompletesAndPersistsOriginalRows(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $jobId = $this->service()->run($this->jobData(), $this->csv([
            ['one', 'IMP008-ONE', 'One', 'IMP008-ONE', '1.00'],
            ['two', 'IMP008-TWO', 'Two', 'IMP008-TWO', '2.00'],
        ], $uomCode, $groupCode), $operation);
        $job = (new ProductImportJobService(db: $this->db))->findById($jobId);
        $rows = (new ImportJobRowRepository($this->db))->findByJobId($jobId);

        $this->assertSame('COMPLETED', $job['status']);
        $this->assertSame('2', (string) $job['total_rows']);
        $this->assertSame('2', (string) $job['success_rows']);
        $this->assertSame('0', (string) $job['error_rows']);
        $this->assertSame([2, 3], array_map(static fn (array $row): int => (int) $row['row_number'], $rows));
        $this->assertSame('IMP008-ONE', $rows[0]['entity_key']);
        $this->assertGreaterThan(0, (int) $rows[0]['target_ref_id']);
    }

    public function testMixedPipelineCompletesWithErrorsAndDoesNotCountErrorAsSuccess(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['import', 'create']);
        $this->product('IMP008-DUP', $uomCode, $groupCode);
        $jobId = $this->service()->run($this->jobData(), $this->csv([
            ['new', 'IMP008-NEW', 'New', 'IMP008-NEW', '1.00'],
            ['duplicate', 'IMP008-DUP', 'Duplicate', 'IMP008-DUP-ROW', '2.00'],
        ], $uomCode, $groupCode), $operation);
        $job = (new ProductImportJobService(db: $this->db))->findById($jobId);
        $rows = (new ImportJobRowRepository($this->db))->findByJobId($jobId);

        $this->assertSame('COMPLETED_WITH_ERRORS', $job['status']);
        $this->assertSame('2', (string) $job['total_rows']);
        $this->assertSame('1', (string) $job['success_rows']);
        $this->assertSame('1', (string) $job['error_rows']);
        $this->assertSame('SUCCESS', $rows[0]['row_status']);
        $this->assertSame('ERROR', $rows[1]['row_status']);
        $this->assertNotSame('SUCCESS', $rows[1]['row_status']);
        $this->assertNotEmpty($rows[1]['error_code']);
    }

    public function testFatalReaderFailureMarksJobFailed(): void
    {
        [$operation] = $this->fixture(['import', 'create']);
        try {
            $this->service()->run($this->jobData(), "record_type,source_key\nPRODUCT,\"unterminated\n", $operation);
            $this->fail('Expected malformed CSV failure.');
        } catch (Throwable) {
            $job = $this->latestJob((int) $operation->actorUserId);
            $this->assertSame('FAILED', $job['status']);
            $this->assertNotNull($job['started_at']);
            $this->assertNotNull($job['completed_at']);
            $this->assertSame(0, (int) $this->db->table('import_job_rows')->where('import_job_id', $job['id'])->countAllResults());
        }
    }

    public function testGateOneDenialCreatesNoJob(): void
    {
        [$operation, $uomCode, $groupCode] = $this->fixture(['create']);
        $before = (int) $this->db->table('import_jobs')->where('created_by', $operation->actorUserId)->countAllResults();
        try {
            $this->service()->run($this->jobData(), $this->csv([
                ['denied', 'IMP008-DENIED', 'Denied', 'IMP008-DENIED', '1.00'],
            ], $uomCode, $groupCode), $operation);
            $this->fail('Expected import start denial.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductImportAuthorizationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertSame($before, (int) $this->db->table('import_jobs')->where('created_by', $operation->actorUserId)->countAllResults());
    }

    public function testGeneratedProductCodeIsPersistedAndReorderedRowsKeepSourceNumbers(): void
    {
        [$operation, $uomCode, $groupCode, $groupRootId] = $this->fixture(['import', 'create', 'edit']);
        $this->series('IMP008-AUTO-' . uniqid(), 'AUTO_REQUIRED', $groupRootId);
        $jobId = $this->service()->run($this->jobData(), $this->csv([
            ['child', '', 'Child', 'IMP008-CHILD', '2.00', 'parent'],
            ['parent', '', 'Parent', 'IMP008-PARENT', '5.00', ''],
        ], $uomCode, $groupCode, true), $operation);
        $rows = (new ImportJobRowRepository($this->db))->findByJobId($jobId);

        $this->assertSame([2, 3], array_map(static fn (array $row): int => (int) $row['row_number'], $rows));
        $this->assertNotSame('', (string) $rows[0]['entity_key']);
        $this->assertNotSame('', (string) $rows[1]['entity_key']);
        $this->assertSame('COMPLETED', (new ProductImportJobService(db: $this->db))->findById($jobId)['status']);
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
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'IMP008-GROUP-' . uniqid(), 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $groupCode = 'IMP008-PG-' . uniqid();
        $uomCode = 'IMP008-UOM-' . uniqid();
        $this->db->table('product_groups')->insert(['code' => $groupCode, 'name' => 'Import Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('product_uoms')->insert(['code' => $uomCode, 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->db->table('users')->insert(['username' => 'imp008-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $authCode = 'IMP008-AUTH-' . uniqid();
        $this->db->table('groups')->insert(['code' => $authCode, 'name' => 'Import actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($authCode), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }

        return [new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'imp008-' . uniqid(), false), $uomCode, $groupCode, (int) $groupRoot['id']];
    }

    /** @return array<string, string> */
    private function jobData(): array
    {
        return ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'];
    }

    /** @param list<array{0:string,1:string,2:string,3:string,4:string,5?:string}> $rows */
    private function csv(array $rows, string $uomCode, string $groupCode, bool $withParent = false): string
    {
        $header = $withParent
            ? 'record_type,source_key,product_code,product_name,product_child_name,sku,price,product_group_code,base_uom_code,parent_source_key'
            : 'record_type,source_key,product_code,product_name,sku,price,product_group_code,base_uom_code';
        $lines = [$header];
        foreach ($rows as $row) {
            $parent = $row[5] ?? '';
            if ($withParent) {
                $isChild = $parent !== '';
                $lines[] = implode(',', [
                    'PRODUCT', $row[0], $row[1], $isChild ? '' : $row[2], $isChild ? $row[2] : '',
                    $row[3], $row[4], $groupCode, $uomCode, $parent,
                ]);
            } else {
                $lines[] = implode(',', ['PRODUCT', $row[0], $row[1], $row[2], $row[3], $row[4], $groupCode, $uomCode]);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function product(string $code, string $uomCode, string $groupCode): int
    {
        $uomId = (int) $this->db->table('product_uoms')->where('code', $uomCode)->get()->getRowArray()['id'];
        $groupId = (int) $this->db->table('product_groups')->where('code', $groupCode)->get()->getRowArray()['id'];
        $this->db->table('products')->insert([
            'code' => $code, 'sku' => 'SKU-' . $code, 'product_name' => 'Existing', 'product_type' => 'STANDARD',
            'product_group_id' => $groupId, 'base_uom_id' => $uomId, 'price' => '10.00', 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
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

    /** @return array<string,mixed> */
    private function latestJob(int $actorId): array
    {
        return $this->db->table('import_jobs')->where('created_by', $actorId)->orderBy('id', 'DESC')->get()->getRowArray();
    }
}

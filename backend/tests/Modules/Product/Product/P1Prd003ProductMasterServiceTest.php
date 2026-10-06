<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Product\NoProductHistoryProvider;
use App\Modules\Product\Product\ProductHistoryProvider;
use App\Modules\Product\Product\ProductMasterService;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Prd003ProductMasterServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testRootComboChildAndFirstChildConversionAreAtomic(): void
    {
        [$service, $operation, $uomId, $groupId] = $this->fixture(['create', 'edit', 'setstate']);
        $rootId = $service->create($this->product('ROOT-003', $uomId, $groupId), $operation);
        $root = $service->findById($rootId);
        $this->assertNotNull($root);
        $this->assertSame('STANDARD', $root['product_type']);
        $this->assertSame('12.00', (string) $root['price']);

        $childId = $service->create([
            'code' => 'CHILD-003', 'sku' => 'SKU-CHILD-003', 'product_child_name' => 'Blue',
            'parent_product_id' => $rootId, 'base_uom_id' => $uomId, 'price' => '4.50',
        ], $operation);
        $parent = $service->findById($rootId);
        $child = $service->findById($childId);
        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertNull($parent['price']);
        $this->assertSame('0', (string) $parent['serial_management_enabled']);
        $this->assertNull($child['product_name']);
        $this->assertSame('Blue', $child['product_child_name']);

        $secondId = $service->create([
            'code' => 'CHILD-003-B', 'sku' => 'SKU-CHILD-003-B', 'product_child_name' => 'Red',
            'parent_product_id' => $rootId, 'base_uom_id' => $uomId, 'price' => '5.00',
        ], $operation);
        $this->assertGreaterThan(0, $secondId);

        $comboId = $service->create([
            'code' => 'COMBO-003', 'sku' => 'SKU-COMBO-003', 'product_name' => 'Bundle',
            'product_type' => 'COMBO', 'base_uom_id' => $uomId, 'price' => '20.00',
            'serial_management_enabled' => false,
        ], $operation);
        $combo = $service->findById($comboId);
        $this->assertNotNull($combo);
        $this->assertSame('COMBO', $combo['product_type']);
        $this->assertSame('0', (string) $combo['serial_management_enabled']);
    }

    public function testValidationAndTypedUniqueKeys(): void
    {
        [$service, $operation, $uomId, $groupId] = $this->fixture(['create']);
        $service->create(array_merge($this->product('UNIQUE-003', $uomId, $groupId), ['barcode' => 'BARCODE-003']), $operation);
        $this->assertDomainCode(
            fn () => $service->create($this->product('UNIQUE-003', $uomId, $groupId), $operation),
            'PRODUCT_DUPLICATE_CODE',
        );
        $this->assertDomainCode(
            fn () => $service->create(array_merge($this->product('OTHER-003', $uomId, $groupId), ['sku' => 'SKU-UNIQUE-003']), $operation),
            'PRODUCT_DUPLICATE_SKU',
        );
        $this->assertDomainCode(
            fn () => $service->create(array_merge($this->product('BAR-003', $uomId, $groupId), ['barcode' => 'BARCODE-003']), $operation),
            'PRODUCT_DUPLICATE_BARCODE',
        );
        $this->assertDomainCode(
            fn () => $service->create(array_merge($this->product('NEG-003', $uomId, $groupId), ['price' => '-1.00']), $operation),
            ProductMasterService::ERROR_NEGATIVE_PRICE,
        );
    }

    public function testHistoryBlocksConversionAndSemanticEdit(): void
    {
        [$service, $operation, $uomId, $groupId, $history] = $this->fixture(['create', 'edit'], 'history', true);
        $rootId = $service->create($this->product('HISTORY-003', $uomId, $groupId), $operation);
        $this->assertDomainCode(fn () => $service->create([
            'code' => 'HISTORY-CHILD-003', 'sku' => 'SKU-HISTORY-CHILD-003', 'product_child_name' => 'Blocked',
            'parent_product_id' => $rootId, 'base_uom_id' => $uomId, 'price' => '2.00',
        ], $operation), ProductMasterService::ERROR_CONVERSION_HISTORY);
        $this->db->table('product_uoms')->insert(['code' => 'PRD003-HISTORY-U-' . uniqid(), 'name' => 'Other', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $otherUomId = (int) $this->db->insertID();
        $this->assertDomainCode(fn () => $service->update($rootId, ['base_uom_id' => $otherUomId], $operation), ProductMasterService::ERROR_SEMANTIC_HISTORY);
        $this->assertNotNull($history);
        $this->assertSame(0, (int) $this->db->table('products')->where('parent_product_id', $rootId)->countAllResults());
    }

    public function testSetStateUsesTrustedAuditAndAuthorization(): void
    {
        [$service, $operation, $uomId, $groupId] = $this->fixture(['create', 'setstate']);
        $id = $service->create($this->product('STATE-003', $uomId, $groupId), $operation);
        $before = (int) $this->db->table('authorization_logs')->countAllResults();
        $this->assertTrue($service->inactivate($id, $operation));
        $row = $service->findById($id);
        $this->assertNotNull($row);
        $this->assertSame('INACTIVE', $row['status']);
        $this->assertSame($before + 1, (int) $this->db->table('authorization_logs')->countAllResults());
    }

    public function testAutoNumberAndFirstChildConversionRollbackTogether(): void
    {
        [$service, $operation, $uomId, $groupId] = $this->fixture(['create']);
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => (int) $resource['id'], 'series_code' => 'PRD003-' . uniqid(), 'name' => 'Products',
            'scope_type' => 'GROUP', 'pattern' => 'P{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_IF_EMPTY', 'start_number' => 1, 'increment_by' => 1,
            'is_default' => 1, 'status' => 'ACTIVE',
        ]);
        $rootId = $service->create(array_merge($this->product('', $uomId, $groupId), ['code' => null, 'sku' => 'AUTO-ROOT']), $operation);
        $root = $service->findById($rootId);
        $this->assertSame('P001', $root['code']);
        $counterBefore = (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value'];
        $this->assertSame(1, $counterBefore);

        $this->assertDomainCode(fn () => $service->create([
            'code' => null, 'sku' => 'AUTO-ROOT', 'product_child_name' => 'Duplicate SKU',
            'parent_product_id' => $rootId, 'base_uom_id' => $uomId, 'price' => '2.00',
        ], $operation), 'PRODUCT_DUPLICATE_SKU');
        $parent = $service->findById($rootId);
        $this->assertNotNull($parent);
        $this->assertSame('12.00', (string) $parent['price']);
        $this->assertSame(1, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
        $this->assertSame(0, (int) $this->db->table('products')->where('parent_product_id', $rootId)->countAllResults());
    }

    public function testRealMariaDbSameRootChildCreatesSerializeThroughDomainLock(): void
    {
        [$service, $operation, $uomId, $groupId, $_history, $actorId] = $this->fixture(['create']);
        $rootId = $service->create($this->product('RACE-ROOT-003', $uomId, $groupId), $operation);
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-prd003-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../../../fixtures/Product/product_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['A', 'B'] as $code) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $code;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $code . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $worker, (string) $actorId, (string) $rootId, (string) $uomId, $readyPath, $startPath, $resultPath, 'RACE-CHILD-003-' . $code]));
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                $processes[] = $process;
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 15.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-A') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-B')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-A');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-B');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $results = array_map(static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR), $resultPaths);
            $this->assertCount(2, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
            $this->assertSame(2, (int) $this->db->table('products')->where('parent_product_id', $rootId)->countAllResults());
            $parent = $service->findById($rootId);
            $this->assertNotNull($parent);
            $this->assertNull($parent['price']);
            $this->assertSame('0', (string) $parent['serial_management_enabled']);
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @return array{ProductMasterService,OperationContext,int,int,?ProductHistoryProvider,int} */
    private function fixture(array $permissions, string $label = 'fixture', bool $history = false): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupRoot = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($groupRoot === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'PRD003-GROUP-' . uniqid(), 'name' => 'P3 Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $this->db->table('product_groups')->insert(['code' => 'PRD003-G-' . uniqid(), 'name' => 'Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $groupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert(['code' => 'PRD003-U-' . uniqid(), 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $uomId = (int) $this->db->insertID();
        $this->db->table('users')->insert(['username' => 'prd003-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $this->db->table('groups')->insert(['code' => 'PRD003-A-' . uniqid(), 'name' => 'Product actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower((string) $this->db->table('groups')->where('id', $authGroupId)->get()->getRowArray()['code']), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }
        $operation = new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'prd003-' . $label, false);
        $provider = $history ? new class implements ProductHistoryProvider { public function hasTransactionHistory(int $productId): bool { return true; } } : new NoProductHistoryProvider();
        return [new ProductMasterService(db: $this->db, history: $provider), $operation, $uomId, $groupId, $provider, $userId];
    }

    /** @return array<string, mixed> */
    private function product(string $code, int $uomId, int $groupId): array
    {
        return ['code' => $code, 'sku' => 'SKU-' . $code, 'barcode' => null, 'product_name' => 'Product ' . $code, 'product_group_id' => $groupId, 'base_uom_id' => $uomId, 'price' => '12.00', 'serial_management_enabled' => false];
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

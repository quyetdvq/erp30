<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomMasterService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Uom001ProductUomServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testBaseAndAlternateUomPersistConvertAndDerivePackagingNetWeight(): void
    {
        [$service, $operation] = $this->fixture(['edit']);
        $baseId = $this->master('PCS');
        $boxId = $this->master('BOX');
        $productId = $this->product($baseId, ['net_weight_g' => '2.500']);

        $this->assertSame($baseId, (int) $service->getBaseUom($productId)['id']);
        $conversionId = $service->create($productId, [
            'uom_id' => $boxId,
            'conversion_factor' => '12.000000',
            'price' => '115000.00',
            'package_gross_weight_g' => '35.500',
            'package_length_mm' => '100.000',
            'package_width_mm' => '80.000',
            'package_height_mm' => '60.000',
            'usage_type' => 'BOTH',
        ], $operation);
        $row = $service->findById($conversionId);
        $this->assertNotNull($row);
        $this->assertSame('115000.00', (string) $row['price']);
        $this->assertSame('35.500', (string) $row['package_gross_weight_g']);
        $this->assertSame('30', $service->convertToBase($productId, $boxId, '2.5'));
        $this->assertSame('2.5', $service->convertToBase($productId, $baseId, '2.5'));
        $this->assertSame('30', $service->deriveAlternateNetWeight($productId, $row));
        $this->assertSame(0, (int) $this->db->table('product_uom_conversions')->where('product_id', $productId)->where('uom_id', $baseId)->countAllResults());
    }

    public function testRoleEligibilityBaseRulesAndProductMasterDependencyBoundary(): void
    {
        [$service, $operation, $actorId, $groupId] = $this->fixture(['edit', 'create']);
        $baseId = $this->master('ROLE-PCS');
        $alternateId = $this->master('ROLE-BOX');
        $simpleId = $this->product($baseId);
        $childId = $this->product($baseId, ['product_name' => null, 'product_child_name' => 'Blue', 'parent_product_id' => $simpleId]);
        $comboId = $this->product($baseId, ['product_type' => 'COMBO', 'product_name' => 'Bundle']);
        $parentId = $this->product($baseId, ['price' => null]);
        $this->product($baseId, ['product_name' => null, 'product_child_name' => 'Red', 'parent_product_id' => $parentId]);
        $parentRow = $this->db->table('products')->where('id', $parentId)->get()->getRowArray();
        $this->assertNotFalse($parentRow);
        $this->assertSame([], $service->validateBaseUom(array_merge($parentRow, ['base_uom_id' => null])));
        $this->assertSame($baseId, (int) $service->validateBaseUom($simpleId)['id']);
        $this->assertSame($baseId, (int) $service->validateBaseUom($childId)['id']);
        $this->assertSame($baseId, (int) $service->validateBaseUom($comboId)['id']);
        $this->assertDomainCode(fn () => $service->create($parentId, ['uom_id' => $alternateId, 'conversion_factor' => '2', 'price' => '20.00'], $operation), ProductUomService::ERROR_INVALID_CONVERSION);
        $this->assertDomainCode(fn () => $service->create($comboId, ['uom_id' => $alternateId, 'conversion_factor' => '2', 'price' => '20.00'], $operation), ProductUomService::ERROR_INVALID_CONVERSION);

        $productMaster = new ProductMasterService(db: $this->db);
        $this->assertGreaterThan(0, $actorId);
        $this->assertGreaterThan(0, $groupId);
        $this->assertDomainCode(fn () => $productMaster->create([
            'code' => 'UOM001-BASE-REQUIRED', 'sku' => 'UOM001-BASE-REQUIRED', 'product_name' => 'Missing UOM',
            'product_group_id' => $this->productGroup(), 'price' => '1.00',
        ], $operation), ProductMasterService::ERROR_UOM_REQUIRED);
    }

    public function testCreateValidationUsagePriceFactorReferenceAndDecimalBoundaries(): void
    {
        [$service, $operation] = $this->fixture(['edit']);
        $baseId = $this->master('VAL-PCS');
        $alternateId = $this->master('VAL-BOX');
        $inactiveId = $this->master('VAL-INACTIVE', 'INACTIVE');
        $productId = $this->product($baseId);
        $cases = [
            [['uom_id' => $alternateId, 'conversion_factor' => '0', 'price' => '1.00'], ProductUomService::ERROR_INVALID_CONVERSION],
            [['uom_id' => $alternateId, 'conversion_factor' => '-1', 'price' => '1.00'], ProductUomService::ERROR_INVALID_CONVERSION],
            [['uom_id' => $baseId, 'conversion_factor' => '2', 'price' => '1.00'], ProductUomService::ERROR_INVALID_CONVERSION],
            [['uom_id' => $inactiveId, 'conversion_factor' => '2', 'price' => '1.00'], ProductUomService::ERROR_INACTIVE_REFERENCE],
            [['uom_id' => $alternateId, 'conversion_factor' => '2', 'usage_type' => 'SALES'], ProductUomService::ERROR_PRICE_REQUIRED],
            [['uom_id' => $alternateId, 'conversion_factor' => '2', 'usage_type' => 'BOTH'], ProductUomService::ERROR_PRICE_REQUIRED],
            [['uom_id' => $alternateId, 'conversion_factor' => '2', 'price' => '-1.00'], ProductUomService::ERROR_INVALID_CONVERSION],
            [['uom_id' => $alternateId, 'conversion_factor' => '2', 'price' => null, 'usage_type' => 'PURCHASE'], null],
            [['uom_id' => $alternateId, 'conversion_factor' => '2', 'price' => '1.00', 'usage_type' => 'NOPE'], ProductUomService::ERROR_INVALID_USAGE_TYPE],
            [['uom_id' => $alternateId, 'conversion_factor' => '1.0000001', 'price' => '1.00'], ProductUomService::ERROR_INVALID_DECIMAL],
            [['uom_id' => $alternateId, 'conversion_factor' => 1.25, 'price' => '1.00'], ProductUomService::ERROR_INVALID_DECIMAL],
        ];
        foreach ($cases as [$payload, $error]) {
            if ($error === null) {
                $this->assertGreaterThan(0, $service->create($productId, $payload, $operation));
                continue;
            }
            $this->assertDomainCode(fn () => $service->create($productId, $payload, $operation), $error);
        }
    }

    public function testDuplicateIsolationEditStatusHistoricalReadAndReactivationRules(): void
    {
        [$service, $operation] = $this->fixture(['edit']);
        $baseId = $this->master('EDIT-PCS');
        $boxId = $this->master('EDIT-BOX');
        $bagId = $this->master('EDIT-BAG');
        $productId = $this->product($baseId, ['net_weight_g' => null]);
        $otherProductId = $this->product($baseId, ['code' => 'UOM001-OTHER', 'sku' => 'UOM001-OTHER']);
        $conversionId = $service->create($productId, ['uom_id' => $boxId, 'conversion_factor' => '12', 'price' => '100.00'], $operation);
        $this->assertDomainCode(fn () => $service->create($productId, ['uom_id' => $boxId, 'conversion_factor' => '12', 'price' => '100.00'], $operation), ProductUomService::ERROR_DUPLICATE);
        $this->assertGreaterThan(0, $service->create($otherProductId, ['uom_id' => $boxId, 'conversion_factor' => '12', 'price' => '100.00'], $operation));
        $this->assertTrue($service->update($conversionId, ['conversion_factor' => '10.500000', 'price' => '99.00'], $operation));
        $this->assertSame('10.500000', (string) $service->findById($conversionId)['conversion_factor']);
        $this->assertTrue($service->inactivate($conversionId, $operation));
        $this->assertNotNull($service->findById($conversionId));
        $this->assertDomainCode(fn () => $service->convertToBase($productId, $boxId, '1'), ProductUomService::ERROR_INVALID_CONVERSION);
        $this->db->table('product_uoms')->where('id', $boxId)->update(['status' => 'INACTIVE']);
        $this->assertDomainCode(fn () => $service->activate($conversionId, $operation), ProductUomService::ERROR_INACTIVE_REFERENCE);
        $this->db->table('product_uoms')->where('id', $boxId)->update(['status' => 'ACTIVE']);
        $this->assertTrue($service->activate($conversionId, $operation));
        $this->assertSame('ACTIVE', $service->findById($conversionId)['status']);
        $this->assertGreaterThan(0, $service->create($productId, ['uom_id' => $bagId, 'conversion_factor' => '3', 'price' => '20.00'], $operation));
    }

    public function testTrustedAuditAndProductEditAuthorizationOnly(): void
    {
        [$service, $operation, $actorId] = $this->fixture(['edit']);
        $baseId = $this->master('AUTH-PCS');
        $boxId = $this->master('AUTH-BOX');
        $productId = $this->product($baseId);
        $conversionId = $service->create($productId, [
            'uom_id' => $boxId, 'conversion_factor' => '2', 'price' => '20.00',
            'created_by' => 999999999, 'created_at' => '2000-01-01 00:00:00',
        ], $operation);
        $row = $service->findById($conversionId);
        $this->assertNotNull($row);
        $this->assertSame((string) $actorId, (string) $row['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['created_at']);
        $this->assertTrue($service->update($conversionId, [
            'price' => '21.00', 'updated_by' => 999999999, 'updated_at' => '2000-01-01 00:00:00',
        ], $operation));
        $row = $service->findById($conversionId);
        $this->assertNotNull($row);
        $this->assertSame((string) $actorId, (string) $row['updated_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['updated_at']);

        [$denied, $deniedOperation] = $this->fixture(['product_uom.edit']);
        $deniedProduct = $this->product($baseId, ['code' => 'UOM001-DENIED', 'sku' => 'UOM001-DENIED']);
        $this->assertDomainCode(fn () => $denied->create($deniedProduct, ['uom_id' => $boxId, 'conversion_factor' => '2', 'price' => '20.00'], $deniedOperation), ProductUomService::ERROR_AUTHORIZATION_DENIED);
    }

    public function testRealMariaDbChildAndConversionRaceLeavesOneValidOrdering(): void
    {
        [$service, $operation, $actorId] = $this->fixture(['create', 'edit']);
        $baseId = $this->master('RACE-PCS');
        $alternateId = $this->master('RACE-BOX');
        $groupId = $this->productGroup();
        $this->db->table('products')->insert([
            'code' => 'UOM001-RACE', 'sku' => 'UOM001-RACE', 'product_name' => 'Race Root',
            'product_group_id' => $groupId, 'product_type' => 'STANDARD', 'base_uom_id' => $baseId,
            'price' => '12.00', 'serial_management_enabled' => 0, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $rootId = (int) $this->db->insertID();
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-uom001-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $childWorker = realpath(__DIR__ . '/../../../fixtures/Product/product_concurrency_worker.php');
        $conversionWorker = realpath(__DIR__ . '/../../../fixtures/Product/product_uom_concurrency_worker.php');
        $this->assertNotFalse($childWorker);
        $this->assertNotFalse($conversionWorker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach ([['child', $childWorker], ['conversion', $conversionWorker]] as [$label, $worker]) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $label;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $label . '.json';
                $resultPaths[$label] = $resultPath;
                $args = [$worker, (string) $actorId, (string) $rootId];
                if ($label === 'child') {
                    array_push($args, (string) $baseId, $readyPath, $startPath, $resultPath, 'UOM001-RACE-CHILD');
                } else {
                    array_push($args, (string) $alternateId, $readyPath, $startPath, $resultPath);
                }
                $command = implode(' ', array_map('escapeshellarg', array_merge([PHP_BINARY], $args)));
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                $processes[] = $process;
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 15.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-child') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-conversion')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-child');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-conversion');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $child = json_decode((string) file_get_contents($resultPaths['child']), true, 512, JSON_THROW_ON_ERROR);
            $conversion = json_decode((string) file_get_contents($resultPaths['conversion']), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue((bool) (($child['ok'] ?? false) xor ($conversion['ok'] ?? false)));
            $childCount = (int) $this->db->table('products')->where('parent_product_id', $rootId)->countAllResults();
            $conversionCount = (int) $this->db->table('product_uom_conversions')->where('product_id', $rootId)->countAllResults();
            $this->assertSame(1, $childCount + $conversionCount);
            $this->assertFalse($childCount === 1 && $conversionCount > 0);
            $role = (new ProductRoleService(db: $this->db))->getRole($rootId);
            $this->assertSame($childCount === 1 ? ProductRoleService::ROLE_PARENT : ProductRoleService::ROLE_SIMPLE, $role);
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

    /** @param list<string> $permissions
     *  @return array{ProductUomService, OperationContext, int, int}
     */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupRoot = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($groupRoot === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'UOM001-GROUP-' . uniqid(), 'name' => 'UOM Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $groupRoot = ['id' => $this->db->insertID()];
        }
        $this->db->table('users')->insert(['username' => 'uom001-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $code = 'UOM001-A-' . substr(uniqid(), -8);
        $this->db->table('groups')->insert(['code' => $code, 'name' => 'UOM actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission, 2);
            $resource = count($parts) === 2 ? $parts[0] : 'product';
            $action = count($parts) === 2 ? $parts[1] : $permission;
            $result = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get();
            $row = $result->getRowArray();
            $this->assertNotNull($row);
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $row['id'], 'created_at' => '2026-10-06 04:05:06']);
        }
        return [new ProductUomService(db: $this->db), new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'uom001-' . uniqid(), false), $userId, (int) $groupRoot['id']];
    }

    private function master(string $code, string $status = 'ACTIVE'): int
    {
        $this->db->table('product_uoms')->insert(['code' => $code . '-' . substr(uniqid(), -5), 'name' => $code, 'status' => $status, 'created_at' => '2026-10-06 04:05:06']);
        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $overrides */
    private function product(int $baseUomId, array $overrides = []): int
    {
        $data = array_merge([
            'code' => 'UOM001-P-' . substr(uniqid(), -8), 'sku' => 'UOM001-S-' . substr(uniqid(), -8),
            'product_name' => 'Product', 'product_child_name' => null, 'parent_product_id' => null,
            'product_type' => 'STANDARD', 'base_uom_id' => $baseUomId, 'price' => '12.00',
            'net_weight_g' => '2.500', 'serial_management_enabled' => 0, 'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ], $overrides);
        $this->db->table('products')->insert($data);
        return (int) $this->db->insertID();
    }

    private function productGroup(): int
    {
        $this->db->table('product_groups')->insert(['code' => 'UOM001-G-' . substr(uniqid(), -8), 'name' => 'Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        return (int) $this->db->insertID();
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product UOM error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

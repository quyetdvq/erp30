<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\PriceList\PriceListService;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Prc001PriceListServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testHeaderScopesIntervalOverlapEditAndStateLifecycle(): void
    {
        [$service, $operation] = $this->fixture(['create', 'edit', 'setstate']);
        $groupId = $this->groupRoot();
        $companyId = $this->organization('COMPANY', $groupId);
        $branchId = $this->organization('BRANCH', $companyId);

        $groupList = $service->create($this->header('GROUP-PRICE', $groupId), $operation);
        $companyList = $service->create($this->header('COMPANY-PRICE', $companyId), $operation);
        $overlap = $service->create($this->header('COMPANY-OVERLAP', $companyId, [
            'effective_from' => '2026-06-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
            'priority' => -2,
        ]), $operation);
        $this->assertGreaterThan(0, $groupList);
        $this->assertGreaterThan(0, $companyList);
        $this->assertGreaterThan(0, $overlap);
        $this->assertSame(-2, (int) $service->findById($overlap)['priority']);

        $this->assertDomainCode(fn () => $service->create($this->header('BRANCH-PRICE', $branchId), $operation), PriceListService::ERROR_INVALID_SCOPE);
        $this->assertDomainCode(fn () => $service->create($this->header('', $companyId), $operation), PriceListService::ERROR_REQUIRED_FIELD);
        $this->assertDomainCode(fn () => $service->create($this->header('TOO-LONG-' . str_repeat('X', 50), $companyId), $operation), PriceListService::ERROR_REQUIRED_FIELD);
        $this->assertDomainCode(fn () => $service->create($this->header('BAD-DATE', $companyId, ['effective_to' => '2026-01-01 00:00:00']), $operation), PriceListService::ERROR_INVALID_INTERVAL);
        $this->assertDomainCode(fn () => $service->create($this->header('BAD-STATUS', $companyId, ['status' => 'DRAFT']), $operation), PriceListService::ERROR_INVALID_STATUS);
        $this->assertDomainCode(fn () => $service->create($this->header('NO-ORG', null), $operation), PriceListService::ERROR_INVALID_REFERENCE);
        $this->assertDomainCode(fn () => $service->create($this->header('BAD-HINT', $companyId, ['evaluation_organization_id' => $groupId]), $operation), 'EVALUATION_ORGANIZATION_HINT_MISMATCH');

        $this->assertTrue($service->update($companyList, ['name' => 'Edited', 'organization_id' => $groupId], $operation));
        $this->assertSame($groupId, (int) $service->findById($companyList)['organization_id']);
        $this->assertTrue($service->inactivate($companyList, $operation));
        $this->assertSame('INACTIVE', $service->findById($companyList)['status']);
        $this->assertGreaterThan(0, (int) $this->db->table('authorization_logs')->where('action', 'setstate')->countAllResults());
        $this->assertNotNull($service->findById($companyList));
        $this->assertTrue($service->activate($companyList, $operation));
        $this->assertSame('ACTIVE', $service->findById($companyList)['status']);
    }

    public function testAuthorizationAndAuditUsePriceListOwnerWithoutProductBypass(): void
    {
        [$service, $operation, $actorId] = $this->fixture(['create', 'edit', 'setstate']);
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $id = $service->create($this->header('AUTH-PRICE', $companyId, [
            'created_by' => 999999,
            'created_at' => '2000-01-01 00:00:00',
        ]), $operation);
        $row = $service->findById($id);
        $this->assertSame((string) $actorId, (string) $row['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['created_at']);

        [$denied, $deniedOperation] = $this->fixture(['product.edit']);
        $this->assertDomainCode(fn () => $denied->create($this->header('DENIED-PRICE', $companyId), $deniedOperation), PriceListService::ERROR_AUTHORIZATION_DENIED);
    }

    public function testItemEligibilityUsesProductRoleAndProductUomBoundaries(): void
    {
        [$service, $operation] = $this->fixture(['view', 'create', 'edit']);
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $listId = $service->create($this->header('ITEM-PRICE', $companyId), $operation);
        $baseId = $this->uom('ITEM-PCS');
        $salesId = $this->uom('ITEM-BOX');
        $purchaseId = $this->uom('ITEM-BAG');
        $inactiveId = $this->uom('ITEM-INACTIVE');
        $simpleId = $this->product($baseId, 'ITEM-SIMPLE');
        $childRoot = $this->product($baseId, 'ITEM-ROOT');
        $childId = $this->product($baseId, 'ITEM-CHILD', ['parent_product_id' => $childRoot, 'product_name' => null, 'product_child_name' => 'Blue']);
        $comboId = $this->product($baseId, 'ITEM-COMBO', ['product_type' => 'COMBO']);
        $parentId = $this->product($baseId, 'ITEM-PARENT');
        $this->product($baseId, 'ITEM-PARENT-CHILD', ['parent_product_id' => $parentId, 'product_name' => null, 'product_child_name' => 'Red']);
        $inactiveProductId = $this->product($baseId, 'ITEM-INACTIVE-PRODUCT', ['status' => 'INACTIVE']);
        $this->conversion($simpleId, $salesId, 'SALES', 'ACTIVE');
        $this->conversion($simpleId, $purchaseId, 'PURCHASE', 'ACTIVE');
        $this->conversion($simpleId, $inactiveId, 'BOTH', 'INACTIVE');
        $this->conversion($childId, $salesId, 'BOTH', 'ACTIVE');

        $this->assertCount(3, $service->replaceItems($listId, [
            ['product_id' => $simpleId, 'uom_id' => $baseId, 'price' => '100.00'],
            ['product_id' => $childId, 'uom_id' => $salesId, 'price' => '200.00'],
            ['product_id' => $comboId, 'uom_id' => $baseId, 'price' => '300.00'],
        ], $operation));
        $this->assertCount(3, $service->listItems($listId, $operation));
        $this->assertCount(1, $service->replaceItems($listId, [['product_id' => $simpleId, 'uom_id' => $salesId, 'price' => '101.25']], $operation));
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $simpleId, 'uom_id' => $purchaseId, 'price' => '101.25']], $operation), ProductUomService::ERROR_INVALID_CONVERSION);
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $simpleId, 'uom_id' => $inactiveId, 'price' => '101.25']], $operation), 'INACTIVE_REFERENCE');
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $comboId, 'uom_id' => $salesId, 'price' => '101.25']], $operation), ProductUomService::ERROR_INVALID_CONVERSION);
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $parentId, 'uom_id' => $baseId, 'price' => '101.25']], $operation), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $inactiveProductId, 'uom_id' => $baseId, 'price' => '101.25']], $operation), PriceListService::ERROR_PRODUCT_INACTIVE);
        $secondList = $service->create($this->header('ITEM-PRICE-2', $companyId), $operation);
        $this->assertCount(1, $service->replaceItems($secondList, [['product_id' => $simpleId, 'uom_id' => $salesId, 'price' => '0.00']], $operation));
        [$denied, $deniedOperation] = $this->fixture(['product.edit']);
        $this->assertDomainCode(fn () => $denied->replaceItems($secondList, [['product_id' => $simpleId, 'uom_id' => $salesId, 'price' => '1.00']], $deniedOperation), PriceListService::ERROR_AUTHORIZATION_DENIED);
    }

    public function testAtomicReplacementDuplicateDecimalAndHistoricalInactiveBehavior(): void
    {
        [$service, $operation] = $this->fixture(['view', 'create', 'edit']);
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $listId = $service->create($this->header('BATCH-PRICE', $companyId), $operation);
        $baseId = $this->uom('BATCH-PCS');
        $productId = $this->product($baseId, 'BATCH-PRODUCT');
        $this->assertCount(1, $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '123.45']], $operation));
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [
            ['product_id' => $productId, 'uom_id' => $baseId, 'price' => '10.00'],
            ['product_id' => $productId, 'uom_id' => $baseId, 'price' => '11.00'],
        ], $operation), PriceListService::ERROR_ITEM_DUPLICATE);
        $item = $service->listItems($listId, $operation)[0];
        $this->assertSame('123.45', (string) $item['price']);
        $this->assertNotNull($item['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $item['created_at']);
        $this->assertCount(1, $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '123.46']], $operation));
        $updatedItem = $service->listItems($listId, $operation)[0];
        $this->assertSame('123.46', (string) $updatedItem['price']);
        $this->assertNotNull($updatedItem['updated_by']);
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '-1.00']], $operation), PriceListService::ERROR_INVALID_PRICE);
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '1.000']], $operation), PriceListService::ERROR_INVALID_PRICE);

        $this->db->table('products')->where('id', $productId)->update(['status' => 'INACTIVE']);
        $this->assertCount(1, $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '123.46']], $operation));
        $this->assertDomainCode(fn () => $service->replaceItems($listId, [['product_id' => $productId, 'uom_id' => $baseId, 'price' => '123.47']], $operation), PriceListService::ERROR_PRODUCT_INACTIVE);
        $this->assertCount(0, $service->replaceItems($listId, [], $operation));
        $this->assertSame(0, (int) $this->db->table('product_price_list_items')->where('price_list_id', $listId)->countAllResults());
    }

    public function testConcurrentIndependentReplacementBatchesCommitAsWholeSets(): void
    {
        [$service, $operation, $actorId] = $this->fixture(['view', 'create', 'edit']);
        $companyId = $this->organization('COMPANY', $this->groupRoot());
        $listId = $service->create($this->header('RACE-PRICE', $companyId), $operation);
        $baseId = $this->uom('RACE-PCS');
        $productA = $this->product($baseId, 'RACE-A');
        $productB = $this->product($baseId, 'RACE-B');
        $productC = $this->product($baseId, 'RACE-C');
        $worker = realpath(__DIR__ . '/../../../fixtures/Pricing/price_list_batch_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-prc001-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            $batches = [
                'a' => [
                    ['product_id' => $productA, 'uom_id' => $baseId, 'price' => '100.00'],
                    ['product_id' => $productB, 'uom_id' => $baseId, 'price' => '200.00'],
                ],
                'b' => [['product_id' => $productC, 'uom_id' => $baseId, 'price' => '300.00']],
            ];
            foreach ($batches as $label => $batch) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $label;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $label . '.json';
                $resultPaths[$label] = $resultPath;
                $args = [$worker, (string) $actorId, (string) $listId, $readyPath, $startPath, $resultPath, base64_encode(json_encode($batch, JSON_THROW_ON_ERROR))];
                $command = implode(' ', array_map('escapeshellarg', array_merge([PHP_BINARY], $args)));
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                $processes[] = $process;
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 15.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-a') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-b')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-a');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-b');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $resultA = json_decode((string) file_get_contents($resultPaths['a']), true, 512, JSON_THROW_ON_ERROR);
            $resultB = json_decode((string) file_get_contents($resultPaths['b']), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue((bool) ($resultA['ok'] ?? false), json_encode($resultA, JSON_THROW_ON_ERROR));
            $this->assertTrue((bool) ($resultB['ok'] ?? false), json_encode($resultB, JSON_THROW_ON_ERROR));
            $rows = $service->listItems($listId, $operation);
            $actual = array_map(static fn (array $row): string => $row['product_id'] . ':' . $row['uom_id'] . ':' . $row['price'], $rows);
            sort($actual);
            $expectedA = [$productA . ':' . $baseId . ':100.00', $productB . ':' . $baseId . ':200.00'];
            $expectedB = [$productC . ':' . $baseId . ':300.00'];
            sort($expectedA);
            sort($expectedB);
            $this->assertTrue($actual === $expectedA || $actual === $expectedB);
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
     *  @return array{PriceListService, OperationContext, int}
     */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->groupRoot();
        $this->db->table('users')->insert(['username' => 'prc001-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $code = 'PRC001-A-' . substr(uniqid(), -8);
        $this->db->table('groups')->insert(['code' => $code, 'name' => 'Pricing actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission, 2);
            $resource = count($parts) === 2 ? $parts[0] : 'price_list';
            $action = count($parts) === 2 ? $parts[1] : $parts[0];
            $row = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get()->getRowArray();
            $this->assertNotNull($row);
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $row['id'], 'created_at' => '2026-10-06 04:05:06']);
        }

        return [
            new PriceListService(db: $this->db),
            new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'prc001-' . uniqid(), false),
            $userId,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function header(string $code, ?int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => $code . ' list',
            'organization_id' => $organizationId,
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
            'priority' => 0,
            'status' => 'ACTIVE',
            'description' => null,
        ], $overrides);
    }

    private function groupRoot(): int
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'PRC001-GROUP-' . uniqid(), 'name' => 'Pricing Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);

        return (int) $this->db->insertID();
    }

    private function organization(string $type, int $parentId): int
    {
        $this->db->table('organizations')->insert(['type' => $type, 'parent_id' => $parentId, 'code' => 'PRC001-' . $type . '-' . uniqid(), 'name' => $type . ' Pricing', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);

        return (int) $this->db->insertID();
    }

    private function uom(string $code, string $status = 'ACTIVE'): int
    {
        $this->db->table('product_uoms')->insert(['code' => $code . '-' . substr(uniqid(), -5), 'name' => $code, 'status' => $status, 'created_at' => '2026-10-06 04:05:06']);

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $overrides */
    private function product(int $baseUomId, string $code, array $overrides = []): int
    {
        $data = array_merge([
            'code' => $code . '-' . substr(uniqid(), -6), 'sku' => 'SKU-' . $code . '-' . substr(uniqid(), -5),
            'product_name' => $code, 'product_child_name' => null, 'parent_product_id' => null,
            'product_type' => 'STANDARD', 'base_uom_id' => $baseUomId, 'price' => '12.00',
            'serial_management_enabled' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ], $overrides);
        $this->db->table('products')->insert($data);

        return (int) $this->db->insertID();
    }

    private function conversion(int $productId, int $uomId, string $usageType, string $status): int
    {
        $this->db->table('product_uom_conversions')->insert([
            'product_id' => $productId, 'uom_id' => $uomId, 'conversion_factor' => '2.000000',
            'usage_type' => $usageType, 'status' => $status, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected domain error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

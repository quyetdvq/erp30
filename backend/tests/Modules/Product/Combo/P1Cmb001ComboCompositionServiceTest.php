<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Combo\ComboCompositionService;
use App\Modules\Product\Product\ProductMasterService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Cmb001ComboCompositionServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testReplacementSupportsLeafChildNestedComboDiffsAndTrustedAudit(): void
    {
        [$operation, $userId, $uomId, $groupId] = $this->fixture(['view', 'edit']);
        $owner = $this->product('COMBO', $uomId, $groupId, 'COMBO');
        $simple = $this->product('SIMPLE', $uomId, $groupId);
        $parent = $this->product('PARENT', $uomId, $groupId);
        $child = $this->product('CHILD', $uomId, $groupId, 'STANDARD', $parent);
        $nested = $this->product('NESTED', $uomId, $groupId, 'COMBO');
        $leaf = $this->product('LEAF', $uomId, $groupId);

        $service = new ComboCompositionService(db: $this->db);
        $rows = $service->replaceItems($owner, [
            ['component_product_id' => $child, 'quantity' => '2.000000'],
            ['component_product_id' => $simple, 'quantity' => '1.5'],
            ['component_product_id' => $nested, 'quantity' => '3'],
        ], $operation);

        $this->assertSame([$simple, $child, $nested], array_map(static fn (array $row): int => (int) $row['component_product_id'], $rows));
        $this->assertSame($userId, (int) $rows[0]['created_by']);
        $this->assertSame('1.500000', (string) $rows[0]['quantity']);
        $this->assertNotNull($rows[0]['created_at']);

        $rows = $service->replaceItems($owner, [
            ['component_product_id' => $nested, 'quantity' => '4.000000'],
            ['component_product_id' => $leaf, 'quantity' => '1'],
        ], $operation);
        $this->assertSame([$nested, $leaf], array_map(static fn (array $row): int => (int) $row['component_product_id'], $rows));
        $this->assertSame(2, (int) $this->db->table('product_combo_items')->where('combo_product_id', $owner)->countAllResults());

        $this->assertSame([], $service->replaceItems($owner, [], $operation));
        $this->assertSame(0, (int) $this->db->table('product_combo_items')->where('combo_product_id', $owner)->countAllResults());
    }

    public function testValidationRejectsInvalidRolesReferencesSelfDuplicatesAndQuantities(): void
    {
        [$operation, $_userId, $uomId, $groupId] = $this->fixture(['edit']);
        $owner = $this->product('VALIDATION-OWNER', $uomId, $groupId, 'COMBO');
        $parent = $this->product('VALIDATION-PARENT', $uomId, $groupId);
        $this->product('VALIDATION-CHILD', $uomId, $groupId, 'STANDARD', $parent);
        $inactive = $this->product('VALIDATION-INACTIVE', $uomId, $groupId, 'STANDARD', null, 'INACTIVE');
        $leaf = $this->product('VALIDATION-LEAF', $uomId, $groupId);
        $service = new ComboCompositionService(db: $this->db);

        $this->assertDomainCode(fn () => $service->replaceItems(987654321, [], $operation), 'PRODUCT_NOT_FOUND');
        $this->assertDomainCode(fn () => $service->replaceItems($parent, [], $operation), 'PRODUCT_INVALID_ROLE');
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $owner, 'quantity' => '1']], $operation), ComboCompositionService::ERROR_SELF_REFERENCE);
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $parent, 'quantity' => '1']], $operation), 'PRODUCT_INVALID_ROLE');
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $inactive, 'quantity' => '1']], $operation), 'INACTIVE_REFERENCE');
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => 987654321, 'quantity' => '1']], $operation), 'PRODUCT_NOT_FOUND');
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [
            ['component_product_id' => $parent, 'quantity' => '1'],
            ['component_product_id' => $parent, 'quantity' => '2'],
        ], $operation), 'DUPLICATE_KEY');
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $leaf, 'quantity' => '0']], $operation), 'VALIDATION_ERROR');
        foreach (['1.0000001', '-1', '01', '1000000000000', 1.2] as $quantity) {
            $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $leaf, 'quantity' => $quantity]], $operation), 'VALIDATION_ERROR');
        }
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $leaf, 'quantity' => '1']], $operation, 999999999), 'EVALUATION_ORGANIZATION_HINT_MISMATCH');
    }

    public function testPersistedAndDeepCyclesAndCorruptParentEdgesFailClosed(): void
    {
        [$operation, $_userId, $uomId, $groupId] = $this->fixture(['edit']);
        $a = $this->product('CYCLE-A', $uomId, $groupId, 'COMBO');
        $b = $this->product('CYCLE-B', $uomId, $groupId, 'COMBO');
        $c = $this->product('CYCLE-C', $uomId, $groupId, 'COMBO');
        $d = $this->product('CYCLE-D', $uomId, $groupId, 'COMBO');
        $this->edge($b, $c);
        $this->edge($c, $d);
        $this->edge($d, $a);
        $service = new ComboCompositionService(db: $this->db);
        $this->assertDomainCode(fn () => $service->replaceItems($a, [['component_product_id' => $b, 'quantity' => '1']], $operation), ComboCompositionService::ERROR_CYCLE);

        $parent = $this->product('CORRUPT-PARENT', $uomId, $groupId);
        $this->product('CORRUPT-CHILD', $uomId, $groupId, 'STANDARD', $parent);
        $this->edge($a, $parent);
        $this->assertDomainCode(fn () => $service->replaceItems($a, [['component_product_id' => $parent, 'quantity' => '1']], $operation), 'PRODUCT_INVALID_ROLE');
        $this->assertSame(1, (int) $this->db->table('product_combo_items')->where('combo_product_id', $a)->countAllResults());
    }

    public function testAuthorizationRequiresEditAndOwnerCannotConvertWithComposition(): void
    {
        [$operation, $_userId, $uomId, $groupId] = $this->fixture(['view']);
        $owner = $this->product('AUTH-OWNER', $uomId, $groupId, 'COMBO');
        $component = $this->product('AUTH-COMPONENT', $uomId, $groupId);
        $service = new ComboCompositionService(db: $this->db);
        $this->assertDomainCode(fn () => $service->replaceItems($owner, [['component_product_id' => $component, 'quantity' => '1']], $operation), ComboCompositionService::ERROR_AUTHORIZATION_DENIED);
        $this->grantPermission($operation->actorUserId, 'edit');

        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $owner, 'component_product_id' => $component,
            'quantity' => '1.000000', 'created_by' => $operation->actorUserId,
            'created_at' => '2026-10-06 04:05:06',
        ]);
        $master = new ProductMasterService(db: $this->db);
        $this->assertDomainCode(fn () => $master->update($owner, ['product_type' => 'STANDARD'], $operation), ProductMasterService::ERROR_TYPE_CHANGE_INVALID);
        $this->assertSame('COMBO', $this->db->table('products')->where('id', $owner)->get()->getRowArray()['product_type']);
    }

    public function testIndependentReciprocalAndDeepCycleMutationsSerialize(): void
    {
        [$operation, $_userId, $uomId, $groupId] = $this->fixture(['edit']);
        $a = $this->product('RACE-A', $uomId, $groupId, 'COMBO');
        $b = $this->product('RACE-B', $uomId, $groupId, 'COMBO');
        $results = $this->runWorkers($operation->actorUserId, $uomId, [
            ['owner' => $a, 'component' => $b, 'mode' => 'combo', 'code' => 'RACE-AB'],
            ['owner' => $b, 'component' => $a, 'mode' => 'combo', 'code' => 'RACE-BA'],
        ]);
        $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
        $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['code'] ?? null) === ComboCompositionService::ERROR_CYCLE));
        $this->assertLessThanOrEqual(1, (int) $this->db->table('product_combo_items')->whereIn('combo_product_id', [$a, $b])->countAllResults());

        $c = $this->product('RACE-C', $uomId, $groupId, 'COMBO');
        $d = $this->product('RACE-D', $uomId, $groupId, 'COMBO');
        $this->edge($b, $c);
        $this->edge($c, $d);
        $results = $this->runWorkers($operation->actorUserId, $uomId, [
            ['owner' => $a, 'component' => $b, 'mode' => 'combo', 'code' => 'RACE-DEEP-AB'],
            ['owner' => $d, 'component' => $a, 'mode' => 'combo', 'code' => 'RACE-DEEP-DA'],
        ]);
        $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
        $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['code'] ?? null) === ComboCompositionService::ERROR_CYCLE));
    }

    public function testSimpleComponentAndFirstChildRaceLeavesOneValidStructure(): void
    {
        [$operation, $userId, $uomId, $groupId] = $this->fixture(['edit', 'create']);
        $combo = $this->product('RACE-COMBO', $uomId, $groupId, 'COMBO');
        $simple = $this->product('RACE-SIMPLE', $uomId, $groupId);
        $results = $this->runWorkers($userId, $uomId, [
            ['owner' => $combo, 'component' => $simple, 'mode' => 'combo', 'code' => 'RACE-COMPONENT'],
            ['owner' => $simple, 'component' => 0, 'mode' => 'child', 'code' => 'RACE-FIRST-CHILD'],
        ]);
        $this->assertCount(2, $results);
        $parent = $this->db->table('products')->where('id', $simple)->get()->getRowArray();
        $this->assertNotNull($parent);
        $edgeCount = (int) $this->db->table('product_combo_items')->where('combo_product_id', $combo)->where('component_product_id', $simple)->countAllResults();
        $childCount = (int) $this->db->table('products')->where('parent_product_id', $simple)->countAllResults();
        $this->assertFalse($edgeCount === 1 && $childCount > 0, 'A SIMPLE component cannot become PARENT while retained in COMBO composition.');
        if ($childCount > 0) {
            $this->assertSame('PARENT', (new \App\Modules\Product\Product\ProductRoleService(db: $this->db))->getRole($simple));
            $this->assertSame(0, $edgeCount);
        }
    }

    /** @return array{OperationContext,int,int,int} */
    private function fixture(array $permissions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupRoot = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->orderBy('id', 'DESC')->get()->getRowArray();
        if ($groupRoot === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'CMB-GROUP-' . uniqid(), 'name' => 'Combo Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        }
        $this->db->table('product_groups')->insert(['code' => 'CMB-G-' . uniqid(), 'name' => 'Combo Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $groupId = (int) $this->db->insertID();
        $this->db->table('product_uoms')->insert(['code' => 'CMB-U-' . uniqid(), 'name' => 'Each', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $uomId = (int) $this->db->insertID();
        $this->db->table('users')->insert(['username' => 'cmb-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 04:05:06']);
        $userId = (int) $this->db->insertID();
        $this->db->table('groups')->insert(['code' => 'CMB-A-' . uniqid(), 'name' => 'Combo actors', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $authGroupId = (int) $this->db->insertID();
        $groupCode = strtolower((string) $this->db->table('groups')->where('id', $authGroupId)->get()->getRowArray()['code']);
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => $groupCode, 'created_at' => '2026-10-06 04:05:06']);
        foreach ($permissions as $action) {
            $actionRow = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
            $this->assertNotNull($actionRow);
            $this->db->table('group_permissions')->insert(['group_id' => $authGroupId, 'resource_action_id' => (int) $actionRow['id'], 'created_at' => '2026-10-06 04:05:06']);
        }
        return [new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'cmb-' . uniqid(), false), $userId, $uomId, $groupId];
    }

    private function product(string $label, int $uomId, int $groupId, string $type = 'STANDARD', ?int $parentId = null, string $status = 'ACTIVE'): int
    {
        $data = [
            'code' => 'CMB-' . $label . '-' . bin2hex(random_bytes(3)), 'sku' => 'SKU-CMB-' . $label . '-' . bin2hex(random_bytes(3)),
            'product_type' => $type, 'status' => $status, 'base_uom_id' => $uomId,
            'product_group_id' => $groupId, 'parent_product_id' => $parentId,
            'product_name' => $parentId === null ? $label : null,
            'product_child_name' => $parentId === null ? null : $label,
            'price' => $parentId === null ? '10.00' : '2.00', 'serial_management_enabled' => 0,
            'created_at' => '2026-10-06 04:05:06',
        ];
        $this->db->table('products')->insert($data);
        return (int) $this->db->insertID();
    }

    private function edge(int $owner, int $component): void
    {
        $this->db->table('product_combo_items')->insert([
            'combo_product_id' => $owner, 'component_product_id' => $component,
            'quantity' => '1.000000', 'created_at' => '2026-10-06 04:05:06',
        ]);
    }

    private function grantPermission(?int $userId, string $action): void
    {
        $group = $this->db->table('auth_groups_users')->where('user_id', $userId)->get()->getRowArray();
        $authGroup = $this->db->table('groups')->where('code', $group['group'])->get()->getRowArray();
        $resourceAction = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'product')->where('ra.code', $action)->get()->getRowArray();
        $this->db->table('group_permissions')->insert(['group_id' => (int) $authGroup['id'], 'resource_action_id' => (int) $resourceAction['id'], 'created_at' => '2026-10-06 04:05:06']);
    }

    /** @param list<array{owner:int,component:int,mode:string,code:string}> $jobs
     *  @return list<array<string,mixed>>
     */
    private function runWorkers(?int $actorId, int $uomId, array $jobs): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-cmb-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../../../fixtures/Product/combo_concurrency_worker.php');
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach ($jobs as $index => $job) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $index;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $index . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [
                    PHP_BINARY, $worker, (string) $actorId, (string) $job['owner'], (string) $job['component'],
                    (string) $uomId, $readyPath, $startPath, $resultPath, $job['mode'], $job['code'],
                ]));
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                $processes[] = $process;
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 15.0;
            foreach (array_keys($jobs) as $index) {
                while (! is_file($directory . DIRECTORY_SEPARATOR . 'ready-' . $index) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-' . $index);
            }
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            return array_map(static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR), $resultPaths);
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

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected combo error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

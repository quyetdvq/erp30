<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\ProductGroupRepository;
use App\Services\ProductGroupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Prd001ProductGroupServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testRepositoryAndServiceSupportArbitraryDepthAndDeterministicChildren(): void
    {
        [$service, $operation] = $this->authorizedService();
        $rootId = $service->create($this->group('ROOT'), $operation);
        $childId = $service->create($this->group('CHILD', $rootId), $operation);
        $grandchildId = $service->create($this->group('GRANDCHILD', $childId), $operation);
        $greatGrandchildId = $service->create($this->group('GREAT', $grandchildId), $operation);

        $repository = new ProductGroupRepository($this->db);
        $root = $repository->findById($rootId);
        $parent = $repository->findParent($greatGrandchildId);
        $this->assertNotNull($root);
        $this->assertNotNull($parent);
        $this->assertSame('ROOT', $root['code']);
        $this->assertSame($grandchildId, (int) $parent['id'], var_export([$rootId, $childId, $grandchildId, $greatGrandchildId], true));
        $this->assertSame([$childId], array_map(static fn (array $row): int => (int) $row['id'], $repository->listChildren($rootId)));
        $this->assertSame([$rootId, $childId, $grandchildId, $greatGrandchildId], array_map(
            static fn (array $row): int => (int) $row['id'],
            $service->resolveAncestorChain($greatGrandchildId),
        ));
    }

    public function testParentValidationDuplicateAndCyclesAreTyped(): void
    {
        [$service, $operation] = $this->authorizedService();
        $rootId = $service->create($this->group('A'), $operation);
        $childId = $service->create($this->group('B', $rootId), $operation);
        $grandchildId = $service->create($this->group('C', $childId), $operation);

        $this->assertDomainCode(fn () => $service->create($this->group('MISSING', 999999999), $operation), ProductGroupService::ERROR_PARENT_NOT_FOUND);
        $this->assertDomainCode(fn () => $service->create($this->group('A'), $operation), ProductGroupService::ERROR_DUPLICATE_CODE);
        $this->assertDomainCode(fn () => $service->reparent($childId, $childId, $operation), ProductGroupService::ERROR_SELF_PARENT);
        $this->assertDomainCode(fn () => $service->reparent($rootId, $grandchildId, $operation), ProductGroupService::ERROR_HIERARCHY_CYCLE);
    }

    public function testReparentAllowsNullAndDoesNotRewriteDescendants(): void
    {
        [$service, $operation] = $this->authorizedService();
        $firstRoot = $service->create($this->group('FIRST'), $operation);
        $secondRoot = $service->create($this->group('SECOND'), $operation);
        $childId = $service->create($this->group('CHILD', $firstRoot), $operation);
        $grandchildId = $service->create($this->group('GRAND', $childId), $operation);

        $this->assertTrue($service->reparent($childId, $secondRoot, $operation));
        $child = $service->findById($childId);
        $grandchild = $service->findById($grandchildId);
        $this->assertNotNull($child);
        $this->assertNotNull($grandchild);
        $this->assertSame($secondRoot, (int) $child['parent_id']);
        $this->assertSame($childId, (int) $grandchild['parent_id']);
        $this->assertTrue($service->reparent($childId, null, $operation));
        $childAfter = $service->findById($childId);
        $this->assertNotNull($childAfter);
        $this->assertNull($childAfter['parent_id']);
    }

    public function testEffectiveActiveAndSetStateHaveNoCascade(): void
    {
        [$service, $operation] = $this->authorizedService();
        $rootId = $service->create($this->group('ACTIVE-ROOT'), $operation);
        $childId = $service->create($this->group('ACTIVE-CHILD', $rootId), $operation);
        $grandchildId = $service->create($this->group('ACTIVE-GRAND', $childId), $operation);
        $this->db->table('products')->insert([
            'code' => 'PRD001-HISTORICAL', 'product_group_id' => $grandchildId,
            'product_type' => 'STANDARD', 'serial_management_enabled' => 0,
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00',
        ]);
        $beforeAudit = (int) $this->db->table('authorization_logs')->countAllResults();
        $this->assertTrue($service->isEffectivelyActive($grandchildId));

        $this->assertTrue($service->inactivate($rootId, $operation));
        $this->assertSame($beforeAudit + 1, (int) $this->db->table('authorization_logs')->countAllResults());
        $this->assertFalse($service->isEffectivelyActive($childId));
        $this->assertFalse($service->isEffectivelyActive($grandchildId));
        $child = $service->findById($childId);
        $grandchild = $service->findById($grandchildId);
        $productResult = $this->db->table('products')->where('code', 'PRD001-HISTORICAL')->get();
        $product = $productResult === false ? null : $productResult->getRowArray();
        $this->assertNotNull($child);
        $this->assertNotNull($grandchild);
        $this->assertNotNull($product);
        $this->assertSame('ACTIVE', $child['status']);
        $this->assertSame('ACTIVE', $grandchild['status']);
        $this->assertSame((string) $grandchildId, (string) $product['product_group_id']);
        $this->assertDomainCode(fn () => $service->requireSelectableProductGroup($grandchildId), ProductGroupService::ERROR_NOT_SELECTABLE);

        $this->assertTrue($service->activate($rootId, $operation));
        $this->assertTrue($service->isEffectivelyActive($grandchildId));
        $this->assertSame($grandchildId, (int) $service->requireSelectableProductGroup($grandchildId)['id']);
    }

    public function testCorruptedExistingCycleFailsClosedAndTerminates(): void
    {
        [$service, $operation] = $this->authorizedService();
        $aId = $service->create($this->group('CORRUPT-A'), $operation);
        $bId = $service->create($this->group('CORRUPT-B', $aId), $operation);
        $this->assertTrue($this->db->table('product_groups')->where('id', $aId)->update(['parent_id' => $bId]));

        $this->assertDomainCode(fn () => $service->resolveAncestorChain($aId), ProductGroupService::ERROR_CORRUPTED_HIERARCHY);
        $this->assertTrue($service->reparent($aId, null, $operation));
    }

    public function testEditCannotSmuggleStateAndDeniedCreateHasZeroMutation(): void
    {
        [$service, $operation] = $this->authorizedService();
        $rootId = $service->create($this->group('EDIT-ROOT'), $operation);
        $this->assertDomainCode(fn () => $service->update($rootId, ['status' => 'INACTIVE'], $operation), ProductGroupService::ERROR_STATUS_MUTATION_FORBIDDEN);

        $before = (int) $this->db->table('product_groups')->countAllResults();
        $denied = $this->unauthorizedOperation();
        $this->assertDomainCode(fn () => $service->create($this->group('DENIED'), $denied), ProductGroupService::ERROR_AUTHORIZATION_DENIED);
        $this->assertSame($before, (int) $this->db->table('product_groups')->countAllResults());
    }

    public function testClientGroupHintCannotOverrideCanonicalGroup(): void
    {
        [$service, $operation, $groupId] = $this->authorizedService();
        $this->assertDomainCode(
            fn () => $service->create($this->group('HINT', null, $groupId + 999), $operation),
            'EVALUATION_ORGANIZATION_HINT_MISMATCH',
        );
    }

    public function testHierarchyMutationUsesProductGroupTreeLock(): void
    {
        [$service, $operation] = $this->authorizedService();
        $keys = [];
        $lock = new class ($keys) implements DomainLockService {
            /** @param list<string> $keys */
            public function __construct(private array &$keys)
            {
            }
            public function acquire(string $domainKey, int $timeoutSeconds = 5): void
            {
                $this->keys[] = $domainKey;
            }
            public function release(string $domainKey): void
            {
            }
            public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
            {
                $this->acquire($domainKey, $timeoutSeconds);
                return $callback();
            }
            /** @return list<string> */
            public function recordedKeys(): array
            {
                return $this->keys;
            }
        };
        $lockedService = new ProductGroupService(db: $this->db, domainLock: $lock);
        $rootId = $lockedService->create($this->group('LOCK-ROOT'), $operation);
        $childId = $lockedService->create($this->group('LOCK-CHILD', $rootId), $operation);
        $lockedService->reparent($childId, null, $operation);
        $this->assertSame(['product-group-tree', 'product-group-tree'], $lock->recordedKeys());
    }

    public function testRealMariaDbReciprocalReparentSerializesAndRejectsCycle(): void
    {
        [$service, $operation, $groupId, $actorId] = $this->authorizedService();
        $aId = $service->create($this->group('RACE-A'), $operation);
        $bId = $service->create($this->group('RACE-B'), $operation);
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-prd001-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../fixtures/product_group_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach ([['a', $aId, $bId], ['b', $bId, $aId]] as [$name, $target, $parent]) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $name;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $name . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $worker, (string) $actorId, (string) $target, (string) $parent, $readyPath, $startPath, $resultPath]));
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    $this->fail('Could not start Product Group concurrency worker.');
                }
                $processes[$name] = $process;
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
            $results = array_map(static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR), $resultPaths);
            $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
            $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['code'] ?? '') === ProductGroupService::ERROR_HIERARCHY_CYCLE));
            $fresh = db_connect('tests', false);
            $rowResult = $fresh->table('product_groups')->whereIn('id', [$aId, $bId])->get();
            $this->assertNotFalse($rowResult);
            $rows = $rowResult->getResultArray();
            $fresh->close();
            $this->assertCount(1, array_filter($rows, static fn (array $row): bool => $row['parent_id'] !== null));
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

    /** @return array{ProductGroupService, OperationContext, int, int} */
    private function authorizedService(): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupId = $this->insertOrganizationGroup();
        $userId = $this->insertUser();
        $authGroupId = $this->insertAuthorizationGroup($userId);
        foreach (['create', 'edit', 'setstate'] as $action) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $authGroupId,
                'resource_action_id' => $this->resourceActionId('product_group', $action),
                'created_at' => '2026-10-06 00:00:00',
            ]);
        }
        $operation = new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'prd001-test', false);

        return [new ProductGroupService(db: $this->db), $operation, $groupId, $userId];
    }

    private function unauthorizedOperation(): OperationContext
    {
        $userId = $this->insertUser();
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-06T04:05:06+00:00')), 'prd001-denied', false);
    }

    /** @return array<string, mixed> */
    private function group(string $code, ?int $parentId = null, mixed $hint = null): array
    {
        return ['code' => $code, 'name' => $code . ' Product Group', 'parent_id' => $parentId, 'status' => 'ACTIVE', 'evaluation_organization_id' => $hint];
    }

    private function insertOrganizationGroup(): int
    {
        $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => uniqid('PRD001-G-', true), 'name' => 'P1 Group', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        return (int) $this->db->insertID();
    }

    private function insertUser(): int
    {
        $this->db->table('users')->insert(['username' => uniqid('prd001-', true), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-06 00:00:00']);
        return (int) $this->db->insertID();
    }

    private function insertAuthorizationGroup(int $userId): int
    {
        $code = uniqid('PRD001-AUTH-', true);
        $this->db->table('groups')->insert(['code' => $code, 'name' => 'P1 Product Group Test', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $id = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($code), 'created_at' => '2026-10-06 00:00:00']);
        return $id;
    }

    private function resourceActionId(string $resource, string $action): int
    {
        $result = $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)->where('ra.code', $action)->get();
        $row = $result === false ? null : $result->getRowArray();
        return (int) ($row['id'] ?? 0);
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product Group domain failure: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}

<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Product\Combo\ComboCompositionService;
use App\Modules\Product\Product\ProductRoleService;
use App\Services\Api050ProductCatalogService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Authorization\EligibleActorFixture;
use Tests\Support\Database\CanonicalGroupRootFixture;
use Tests\Support\Kir001\Kir001FixtureCleaner;
use Tests\Support\Kir001\Kir001RuntimeGuard;

/**
 * KIR-001 remediation verification.
 *
 * The command-level Product COMBO transaction remains the physical owner.
 * AUTO Numbering must participate in that transaction when it already exists,
 * while standalone AUTO issuance keeps allocator-owned transaction semantics.
 *
 * @internal
 */
final class Kir001ProductComboAutoTransactionRemediationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        Kir001RuntimeGuard::assertReady();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        try {
            Kir001FixtureCleaner::cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function testAutoIfEmptyManualIdentifierComboWithItemsStillSucceeds(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_IF_EMPTY');
        $manual = 'KIR-MAN-' . strtoupper(bin2hex(random_bytes(4)));
        $payload = $this->comboPayload(
            $fixture['uom'],
            $series['code'],
            $manual,
            [['component_product_id' => $fixture['component'], 'quantity' => '2']],
        );

        $created = $this->api($fixture['user'])->createProduct($payload);

        $this->assertSame($manual, $created['product_code']);
        $this->assertSame('COMBO', $created['product_type']);
        $this->assertSame(1, (int) $this->db->table('products')->where('code', $manual)->countAllResults());
        $this->assertSame(1, (int) $this->db->table('product_combo_items')
            ->where('combo_product_id', (int) $created['id'])
            ->where('component_product_id', $fixture['component'])
            ->countAllResults());
        $this->assertSame(0, $this->counterCount($series['id']));
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredComboWithItemsSucceedsInsideCommandTransaction(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $payload = $this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        );

        $created = $this->api($fixture['user'])->createProduct($payload);

        $this->assertSame($series['first_identifier'], $created['product_code']);
        $this->assertSame(1, $this->counterLastValue($series['id']));
        $this->assertSame(1, (int) $this->db->table('product_combo_items')
            ->where('combo_product_id', (int) $created['id'])
            ->where('component_product_id', $fixture['component'])
            ->countAllResults());
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredComboWithExplicitEmptyItemsSucceedsInsideCommandTransaction(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $payload = $this->comboPayload($fixture['uom'], $series['code'], null, []);

        $created = $this->api($fixture['user'])->createProduct($payload);

        $this->assertSame($series['first_identifier'], $created['product_code']);
        $this->assertSame(1, $this->counterLastValue($series['id']));
        $this->assertSame(0, (int) $this->db->table('product_combo_items')
            ->where('combo_product_id', (int) $created['id'])->countAllResults());
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredComboWithoutCompositionKeyKeepsStandaloneAllocatorOwnership(): void
    {
        $fixture = $this->fixture(['create', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $payload = $this->comboPayload($fixture['uom'], $series['code'], null, null);

        $created = $this->api($fixture['user'])->createProduct($payload);

        $this->assertSame($series['first_identifier'], $created['product_code']);
        $this->assertSame('COMBO', $created['product_type']);
        $this->assertSame(1, $this->counterLastValue($series['id']));
        $this->assertSame(0, (int) $this->db->table('product_combo_items')
            ->where('combo_product_id', (int) $created['id'])->countAllResults());
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredInvalidCompositionRollsBackParentAndFirstCounterThenReusesIdentifier(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $invalid = $this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => 999999999, 'quantity' => '1']],
        );
        $failedSku = (string) $invalid['sku'];

        try {
            $this->api($fixture['user'])->createProduct($invalid);
            $this->fail('Expected invalid COMBO component to fail after AUTO allocation.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductRoleService::ERROR_NOT_FOUND, $exception->getErrorCode());
        }

        $this->assertSame(0, (int) $this->db->table('products')->where('sku', $failedSku)->countAllResults());
        $this->assertSame(0, $this->counterCount($series['id']));
        $this->assertSame(0, (int) $this->db->transDepth);

        $retry = $this->api($fixture['user'])->createProduct($this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        ));
        $this->assertSame($series['first_identifier'], $retry['product_code']);
        $this->assertSame(1, $this->counterLastValue($series['id']));
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredExistingCounterRollbackRestoresValueAndReusesSequence(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $first = $this->api($fixture['user'])->createProduct($this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        ));
        $this->assertSame($series['first_identifier'], $first['product_code']);
        $this->assertSame(1, $this->counterLastValue($series['id']));

        $invalid = $this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => 999999999, 'quantity' => '1']],
        );
        $failedSku = (string) $invalid['sku'];
        try {
            $this->api($fixture['user'])->createProduct($invalid);
            $this->fail('Expected invalid COMBO component to roll back an existing counter update.');
        } catch (DomainException $exception) {
            $this->assertSame(ProductRoleService::ERROR_NOT_FOUND, $exception->getErrorCode());
        }

        $this->assertSame(0, (int) $this->db->table('products')->where('sku', $failedSku)->countAllResults());
        $this->assertSame(1, $this->counterLastValue($series['id']));

        $retry = $this->api($fixture['user'])->createProduct($this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        ));
        $this->assertSame($series['second_identifier'], $retry['product_code']);
        $this->assertSame(2, $this->counterLastValue($series['id']));
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testAutoRequiredCreateAllowedButComboEditDeniedRollsBackParentAndCounter(): void
    {
        $fixture = $this->fixture(['create', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $payload = $this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        );
        $sku = (string) $payload['sku'];

        try {
            $this->api($fixture['user'])->createProduct($payload);
            $this->fail('Expected COMBO composition to require product.edit independently of product.create.');
        } catch (DomainException $exception) {
            $this->assertSame(ComboCompositionService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }

        $this->assertSame(0, (int) $this->db->table('products')->where('sku', $sku)->countAllResults());
        $this->assertSame(0, $this->counterCount($series['id']));
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testOccupiedGeneratedIdentifierAdvancesInsideComboTransaction(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $this->db->table('products')->insert([
            'code' => $series['first_identifier'],
            'sku' => 'KIR001-OCC-' . strtoupper(bin2hex(random_bytes(4))),
            'product_type' => 'STANDARD',
            'product_name' => 'KIR-001 Occupied Identifier',
            'base_uom_id' => $fixture['uom'],
            'price' => '5.00',
            'serial_management_enabled' => 0,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);

        $created = $this->api($fixture['user'])->createProduct($this->comboPayload(
            $fixture['uom'],
            $series['code'],
            null,
            [['component_product_id' => $fixture['component'], 'quantity' => '1']],
        ));

        $this->assertSame($series['second_identifier'], $created['product_code']);
        $this->assertSame(2, $this->counterLastValue($series['id']));
        $this->assertSame(1, (int) $this->db->table('product_combo_items')
            ->where('combo_product_id', (int) $created['id'])
            ->where('component_product_id', $fixture['component'])
            ->countAllResults());
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    public function testConcurrentAutoRequiredComboCreatesUseDistinctSequencesAndSingleCounter(): void
    {
        $fixture = $this->fixture(['create', 'edit', 'view']);
        $series = $this->series('AUTO_REQUIRED');
        $results = $this->runComboWorkers($fixture, $series['code']);

        $successful = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
        $this->assertCount(2, $successful, json_encode($results, JSON_THROW_ON_ERROR));
        $codes = array_map(static fn (array $result): string => (string) $result['product_code'], $successful);
        sort($codes, SORT_STRING);
        $expected = [$series['first_identifier'], $series['second_identifier']];
        sort($expected, SORT_STRING);
        $this->assertSame($expected, $codes);
        $this->assertSame(1, $this->counterCount($series['id']));
        $this->assertSame(2, $this->counterLastValue($series['id']));

        foreach ($successful as $result) {
            $productId = (int) ($result['product_id'] ?? 0);
            $this->assertGreaterThan(0, $productId);
            $this->assertSame(0, (int) ($result['trans_depth'] ?? -1));
            $this->assertSame(1, (int) $this->db->table('product_combo_items')
                ->where('combo_product_id', $productId)
                ->where('component_product_id', $fixture['component'])
                ->countAllResults());
        }
        $this->assertSame(0, (int) $this->db->transDepth);
    }

    /** @param list<string> $productActions
     *  @return array{user:int,group:int,company:int,uom:int,component:int}
     */
    private function fixture(array $productActions): array
    {
        (new ResourceActionSeeder(new \Config\Database(), $this->db))->run();

        $group = CanonicalGroupRootFixture::findOrCreate($this->db, function (): int {
            $this->db->table('organizations')->insert([
                'type' => 'GROUP',
                'parent_id' => null,
                'code' => 'KIR001-G-' . strtoupper(bin2hex(random_bytes(4))),
                'name' => 'KIR-001 Group',
                'status' => 'ACTIVE',
                'created_at' => '2026-10-22 06:15:00',
            ]);
            return (int) $this->db->insertID();
        });

        $this->db->table('organizations')->insert([
            'type' => 'COMPANY',
            'parent_id' => $group,
            'code' => 'KIR001-C-' . strtoupper(bin2hex(random_bytes(4))),
            'name' => 'KIR-001 Company',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);
        $company = (int) $this->db->insertID();

        $this->db->table('users')->insert([
            'username' => 'kir001-' . bin2hex(random_bytes(6)),
            'status' => 'active',
            'active' => 1,
            'created_at' => '2026-10-22 06:15:00',
        ]);
        $user = (int) $this->db->insertID();

        $groupCode = 'KIR001-AUTH-' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->table('groups')->insert([
            'code' => $groupCode,
            'name' => 'KIR-001 Product Authorizers',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);
        $authorizationGroup = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $user,
            'group' => strtolower($groupCode),
            'created_at' => '2026-10-22 06:15:00',
        ]);
        foreach ($productActions as $action) {
            $this->db->table('group_permissions')->insert([
                'group_id' => $authorizationGroup,
                'resource_action_id' => $this->actionId('product', $action),
                'created_at' => '2026-10-22 06:15:00',
            ]);
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $company);

        $this->db->table('product_uoms')->insert([
            'code' => 'KIR001-U-' . strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Each',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);
        $uom = (int) $this->db->insertID();

        $componentCode = 'KIR001-LEAF-' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->table('products')->insert([
            'code' => $componentCode,
            'sku' => 'SKU-' . $componentCode,
            'product_type' => 'STANDARD',
            'product_name' => 'KIR-001 Component',
            'base_uom_id' => $uom,
            'price' => '5.00',
            'serial_management_enabled' => 0,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);
        $component = (int) $this->db->insertID();

        return compact('user', 'group', 'company', 'uom', 'component');
    }

    /** @return array{id:int,code:string,first_identifier:string,second_identifier:string} */
    private function series(string $mode): array
    {
        $resource = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        $this->assertNotNull($resource);
        $seriesCode = 'KIR001-' . $mode . '-' . strtoupper(bin2hex(random_bytes(3)));
        $prefix = 'K' . strtoupper(bin2hex(random_bytes(3))) . '-';
        $this->db->table('auto_numbering_series')->insert([
            'resource_id' => (int) $resource['id'],
            'series_code' => $seriesCode,
            'name' => 'KIR-001 ' . $mode,
            'scope_type' => 'GROUP',
            'pattern' => $prefix . '{SEQ:4}',
            'reset_policy' => 'NEVER',
            'generation_mode' => $mode,
            'start_number' => 1,
            'increment_by' => 1,
            'is_default' => 0,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-22 06:15:00',
        ]);

        return [
            'id' => (int) $this->db->insertID(),
            'code' => $seriesCode,
            'first_identifier' => $prefix . '0001',
            'second_identifier' => $prefix . '0002',
        ];
    }

    /**
     * @param list<array{component_product_id:int,quantity:string}>|null $items
     * @return array<string,mixed>
     */
    private function comboPayload(int $uomId, string $seriesCode, ?string $code, ?array $items): array
    {
        $token = strtoupper(bin2hex(random_bytes(4)));
        $payload = [
            'code' => $code,
            'sku' => 'KIR001-SKU-' . $token,
            'product_type' => 'COMBO',
            'product_name' => 'KIR-001 Combo ' . $token,
            'base_uom_id' => $uomId,
            'price' => '10.00',
            'serial_management_enabled' => false,
            'numbering_series_code' => $seriesCode,
        ];
        if ($items !== null) {
            $payload['combo_items'] = $items;
        }
        return $payload;
    }

    private function api(int $userId): Api050ProductCatalogService
    {
        return new Api050ProductCatalogService(
            db: $this->db,
            authorization: new AuthorizationService(db: $this->db),
            mutationContext: new MutationContext(service('clock'), $userId, 'kir001-' . bin2hex(random_bytes(4)), false),
        );
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)
            ->where('ra.code', $action)
            ->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['id'];
    }

    private function counterCount(int $seriesId): int
    {
        return (int) $this->db->table('auto_numbering_counters')
            ->where('numbering_series_id', $seriesId)
            ->countAllResults();
    }

    private function counterLastValue(int $seriesId): int
    {
        $row = $this->db->table('auto_numbering_counters')
            ->where('numbering_series_id', $seriesId)
            ->get()->getRowArray();
        $this->assertNotNull($row);
        return (int) $row['last_value'];
    }

    /**
     * @param array{user:int,group:int,company:int,uom:int,component:int} $fixture
     * @return list<array<string,mixed>>
     */
    private function runComboWorkers(array $fixture, string $seriesCode): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-kir001-' . bin2hex(random_bytes(8));
        if (! mkdir($directory) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create KIR-001 concurrency directory.');
        }
        $worker = realpath(__DIR__ . '/../fixtures/kir001_auto_combo_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['a', 'b'] as $name) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $name;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $name . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [
                    PHP_BINARY,
                    $worker,
                    (string) $fixture['user'],
                    (string) $fixture['uom'],
                    (string) $fixture['component'],
                    $seriesCode,
                    $readyPath,
                    $startPath,
                    $resultPath,
                    $name . '-' . bin2hex(random_bytes(4)),
                ]));
                $processes[$name] = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($processes[$name]);
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
            return array_map(static function (string $path): array {
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new RuntimeException('KIR-001 concurrency worker did not write a result.');
                }
                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            }, $resultPaths);
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
}

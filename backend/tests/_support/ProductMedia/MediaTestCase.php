<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\RequestContext;
use App\Models\UserModel;
use App\Services\Api050ProductCatalogService;
use App\Services\ProductImageService;
use App\Services\ProductImageStorage;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture;

/** Synthetic data only. Refuses ordinary application WRITEPATH and unguarded execution. */
abstract class MediaTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    // This test DB can contain Product images; downgrading migration 000017
    // between tests is intentionally forbidden when media metadata exists.
    // Follow the existing UserIdentityGroupCreationTest/UserAvatarServiceTest
    // convention: preserve the schema, isolate each test with a DB transaction.
    protected $refresh = false;
    protected $migrateOnce = true;

    protected ProductMediaFixture $fixture;
    protected int $productId;
    private bool $mediaReady = false;
    private bool $mediaTransactionOpen = false;
    private string $originalTimezone = 'UTC';

    protected function setUp(): void
    {
        if (! defined('PRODUCT_MEDIA_GUARDED_RUN') || PRODUCT_MEDIA_GUARDED_RUN !== true) {
            throw new \RuntimeException('Run tools/test-product-media.php, not an unguarded PHPUnit command.');
        }
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        $expectedWrite = getenv('ERP_PM_WRITEPATH');
        if (! is_string($expectedWrite) || realpath(WRITEPATH) !== realpath($expectedWrite)
            || ! is_file(WRITEPATH . '.product-media-test-sandbox')) {
            throw new \RuntimeException('Product Media tests require the isolated runner WRITEPATH.');
        }
        // Actual DB was checked by guarded_bootstrap BEFORE DatabaseTestTrait.
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('Unable to begin isolated Product Media fixture transaction.');
        }
        $this->mediaTransactionOpen = true;
        $this->originalTimezone = date_default_timezone_get();
        $this->fixture = new ProductMediaFixture($this->db);
        foreach ([$this->fixture->writer, $this->fixture->reader] as $actor) {
            BeReorg011BusinessActionsFixture::rootActor($this->db, $actor, $this->fixture->group);
        }
        foreach (['view', 'edit'] as $action) {
            BeReorg011BusinessActionsFixture::grant($this->db, 'product', $action, $this->fixture->writerGroupCode);
        }
        BeReorg011BusinessActionsFixture::grant($this->db, 'product', 'view', $this->fixture->readerGroupCode);
        $this->assertTrue($this->db->table('products')->insert([
            'code' => 'PM0-' . bin2hex(random_bytes(7)), 'product_type' => 'STANDARD',
            'product_name' => 'Synthetic Product Media fixture', 'status' => 'ACTIVE',
            'serial_management_enabled' => 0, 'created_at' => '2026-10-16 02:30:00',
        ]));
        $this->productId = (int) $this->db->insertID();
        $this->mediaReady = true;
        $this->asActor($this->fixture->writer);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->mediaReady) {
                date_default_timezone_set($this->originalTimezone);
                $directory = WRITEPATH . 'uploads/product-images';
                if (is_dir($directory) && ! is_link($directory)) {
                    foreach (glob($directory . '/*') ?: [] as $file) {
                        if (is_file($file) || is_link($file)) {
                            $this->assertTrue(unlink($file), 'Synthetic image cleanup failed.');
                        }
                    }
                }
                RequestContext::reset();
                $this->resetServices();
            }
        } finally {
            try {
                if ($this->mediaTransactionOpen) {
                    $this->mediaTransactionOpen = false;
                    if (! $this->db->transRollback()) {
                        throw new \RuntimeException('Unable to roll back Product Media fixture transaction.');
                    }
                    $this->db->resetTransStatus();
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    protected function asActor(int $actor): void
    {
        $this->resetServices();
        $this->mockCache(); $this->mockEmail(); $this->mockSession();
        RequestContext::reset();
        Services::injectMock('clock', ProductMediaFixture::clock());
        $user = (new UserModel())->find($actor);
        $this->assertInstanceOf(User::class, $user);
        $this->actingAs($user);
    }

    /** @param ConnectionInterface<mixed,mixed>|null $metadataDb */
    protected function media(?int $actor = null, ?ConnectionInterface $metadataDb = null): ProductImageService
    {
        $actor ??= $this->fixture->writer;
        $this->asActor($actor);
        return new ProductImageService(
            db: $metadataDb ?? $this->db,
            catalog: new Api050ProductCatalogService(
                db: $this->db,
                mutationContext: new MutationContext(ProductMediaFixture::clock(), $actor, 'pm0-service', false),
            ),
            storage: new ProductImageStorage(),
        );
    }

    protected function source(string $extension = 'png'): string
    {
        $path = TESTPATH . 'fixtures/product-media/pixel.' . $extension;
        $this->assertFileExists($path);
        return $path;
    }

    /** @return array<string,mixed> */
    protected function row(): array
    {
        $row = $this->db->table('products')->where('id', $this->productId)->get()->getRowArray();
        $this->assertIsArray($row);
        return $row;
    }

    protected function seedImage(): string
    {
        $storage = new ProductImageStorage();
        $stored = $storage->store($this->source());
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update([
            'image_filename' => $stored['filename'], 'image_mime' => $stored['mime'], 'image_size_bytes' => $stored['size'],
        ]));
        return $storage->path($stored['filename']);
    }

    /** @return list<string> */
    protected function imageFiles(): array
    {
        // glob() can return mixed separators on Windows when the glob pattern
        // contains '/'. ProductImageStorage::path() uses DIRECTORY_SEPARATOR.
        // Normalize only the path representation: never filter or drop files.
        $files = glob(WRITEPATH . 'uploads/product-images/*') ?: [];
        $files = array_map(
            static fn (string $file): string => str_replace('/', DIRECTORY_SEPARATOR, $file),
            $files,
        );
        sort($files);
        return array_values($files);
    }

    protected function domain(string $code, callable $operation): DomainException
    {
        try { $operation(); } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
            return $exception;
        }
        $this->fail('Expected ' . $code);
    }

    protected function assertPublicProduct(array $dto, bool $hasImage): void
    {
        $this->assertSame((string) $this->productId, (string) $dto['id']);
        $this->assertSame($hasImage, $dto['image_available']);
        $this->assertIsBool($dto['serial_management_enabled']);
        foreach (['product_code', 'role', 'full_name', 'selectable', 'leaf_transactionable'] as $key) {
            $this->assertArrayHasKey($key, $dto);
        }
        foreach (['image_filename', 'image_mime', 'image_size_bytes', 'code'] as $key) {
            $this->assertArrayNotHasKey($key, $dto);
        }
        $this->assertStringNotContainsString(WRITEPATH, json_encode($dto, JSON_THROW_ON_ERROR));
    }
}

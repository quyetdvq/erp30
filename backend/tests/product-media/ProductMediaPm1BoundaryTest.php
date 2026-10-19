<?php

declare(strict_types=1);

use App\Modules\Product\Media\Application\ProductImageApplicationService;
use App\Modules\Product\Media\Ports\ProductImageMetadataPort;
use App\Modules\Product\Media\Ports\ProductImageStoragePort;
use App\Modules\Product\Media\Ports\ProductMediaAccessPort;
use PHPUnit\Framework\TestCase;

/** Pure PM-1 port tests. They do not touch the DB, filesystem or authentication. */
final class ProductMediaPm1BoundaryTest extends TestCase
{
    public function testReadRequiresAuthorizedRowAndPrivateFileExistence(): void
    {
        [$service, $access, $metadata, $storage, $log] = $this->setupService();
        self::assertSame(['path' => '/private/old.png', 'mime' => 'image/png'], $service->read(5));
        self::assertSame(['access:view:5', 'storage:path:old.png', 'storage:is_file:/private/old.png'], $log->calls);
        self::assertSame([], $metadata->writes);
    }

    public function testUploadRetainsAuthorizeStoreMetadataCleanupReadbackOrder(): void
    {
        [$service, $access, $metadata, $storage, $log] = $this->setupService();
        self::assertSame(['id' => '5'], $service->upload(5, '/source/image'));
        self::assertSame([
            'access:edit:5', 'storage:store:/source/image',
            'metadata:5:new.png:image/png:4', 'storage:delete:old.png', 'access:public:5',
        ], $log->calls);
        self::assertSame([[5, 'new.png', 'image/png', 4]], $metadata->writes);
    }

    public function testFailedMetadataWriteCleansNewFileWithoutDeletingOldFile(): void
    {
        [$service, $access, $metadata, $storage, $log] = $this->setupService();
        $metadata->failure = new RuntimeException('injected failure');
        try {
            $service->upload(5, '/source/image');
            self::fail('Metadata failure must propagate');
        } catch (RuntimeException $exception) {
            self::assertSame($metadata->failure, $exception);
        }
        self::assertSame([
            'access:edit:5', 'storage:store:/source/image',
            'metadata:5:new.png:image/png:4', 'storage:delete:new.png',
        ], $log->calls);
    }

    public function testRemoveWritesMetadataBeforeFileCleanupAndReadback(): void
    {
        [$service, $access, $metadata, $storage, $log] = $this->setupService();
        self::assertSame(['id' => '5'], $service->remove(5));
        self::assertSame([
            'access:edit:5', 'metadata:5:NULL:NULL:NULL',
            'storage:delete:old.png', 'access:public:5',
        ], $log->calls);
    }

    public function testPostMutationViewDenialDoesNotUndoCompletedSideEffects(): void
    {
        [$service, $access, $metadata, $storage, $log] = $this->setupService();
        $access->denyPublicRead = true;
        try {
            $service->upload(5, '/source/image');
            self::fail('Post-mutation read must not be bypassed');
        } catch (RuntimeException $exception) {
            self::assertSame('read denied', $exception->getMessage());
        }
        self::assertSame([
            'access:edit:5', 'storage:store:/source/image',
            'metadata:5:new.png:image/png:4', 'storage:delete:old.png', 'access:public:5',
        ], $log->calls);
    }

    /** @return array{ProductImageApplicationService,Pm1FakeAccess,Pm1FakeMetadata,Pm1FakeStorage,Pm1FakeEvents} */
    private function setupService(): array
    {
        $log = new Pm1FakeEvents();
        $access = new Pm1FakeAccess($log);
        $metadata = new Pm1FakeMetadata($log);
        $storage = new Pm1FakeStorage($log);
        return [new ProductImageApplicationService($access, $metadata, $storage), $access, $metadata, $storage, $log];
    }
}

final class Pm1FakeEvents
{
    /** @var list<string> */
    public array $calls = [];
}

final class Pm1FakeAccess implements ProductMediaAccessPort
{
    public bool $denyPublicRead = false;

    public function __construct(private readonly Pm1FakeEvents $log)
    {
    }

    /** @return array<string,mixed> */
    public function authorizedProduct(int $id, string $action): array
    {
        $this->log->calls[] = 'access:' . $action . ':' . $id;
        return ['image_filename' => 'old.png', 'image_mime' => 'image/png'];
    }

    /** @return array<string,mixed> */
    public function publicProduct(int $id): array
    {
        $this->log->calls[] = 'access:public:' . $id;
        if ($this->denyPublicRead) {
            throw new RuntimeException('read denied');
        }
        return ['id' => (string) $id];
    }
}

final class Pm1FakeMetadata implements ProductImageMetadataPort
{
    /** @var list<array{int,?string,?string,?int}> */
    public array $writes = [];
    public ?RuntimeException $failure = null;

    public function __construct(private readonly Pm1FakeEvents $log)
    {
    }

    public function write(int $id, ?string $filename, ?string $mime, ?int $size): void
    {
        $this->log->calls[] = 'metadata:' . $id . ':' . ($filename ?? 'NULL')
            . ':' . ($mime ?? 'NULL') . ':' . ($size === null ? 'NULL' : (string) $size);
        $this->writes[] = [$id, $filename, $mime, $size];
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final class Pm1FakeStorage implements ProductImageStoragePort
{
    public function __construct(private readonly Pm1FakeEvents $log)
    {
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        $this->log->calls[] = 'storage:store:' . $temporaryFile;
        return ['filename' => 'new.png', 'mime' => 'image/png', 'size' => 4];
    }

    public function path(string $filename): string
    {
        $this->log->calls[] = 'storage:path:' . $filename;
        return '/private/' . $filename;
    }

    public function isFile(string $path): bool
    {
        $this->log->calls[] = 'storage:is_file:' . $path;
        return true;
    }

    public function delete(string $filename): void
    {
        $this->log->calls[] = 'storage:delete:' . $filename;
    }
}

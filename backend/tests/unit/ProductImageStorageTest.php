<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\ProductImageStorage;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ProductImageStorageTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZ8AAAAASUVORK5CYII=';

    public function testValidPngIsDetectedFromBytesAndStoredUnderGeneratedName(): void
    {
        $path = $this->temporaryFile(base64_decode(self::PNG_1X1, true));
        $storage = new ProductImageStorage();
        try {
            $info = $storage->inspect($path);
            self::assertSame('image/png', $info['mime']);
            self::assertSame('png', $info['extension']);
            $stored = $storage->store($path);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\.png\z/', $stored['filename']);
            self::assertFileExists($storage->path($stored['filename']));
            $storage->delete($stored['filename']);
            self::assertFileDoesNotExist($storage->path($stored['filename']));
        } finally {
            unlink($path);
        }
    }

    public function testExecutableAndFakeImageContentsAreRejected(): void
    {
        foreach (['<?php echo "hello";', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'] as $content) {
            $path = $this->temporaryFile($content);
            try {
                $this->expectInvalid($path, 'PRODUCT_IMAGE_INVALID_CONTENT');
            } finally {
                unlink($path);
            }
        }
    }

    public function testOversizedFileAndArbitraryStoragePathsAreRejected(): void
    {
        $path = $this->temporaryFile(str_repeat('X', ProductImageStorage::MAX_BYTES + 1));
        try {
            $this->expectInvalid($path, 'PRODUCT_IMAGE_INVALID_SIZE');
        } finally {
            unlink($path);
        }
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PRODUCT_IMAGE_NOT_FOUND');
        (new ProductImageStorage())->path('../../.env');
    }

    private function temporaryFile(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-image-');
        self::assertIsString($path);
        file_put_contents($path, $body);
        return $path;
    }

    private function expectInvalid(string $path, string $expectedCode): void
    {
        try {
            (new ProductImageStorage())->inspect($path);
            self::fail('Invalid file was accepted.');
        } catch (DomainException $exception) {
            self::assertSame($expectedCode, $exception->getErrorCode());
        }
    }
}

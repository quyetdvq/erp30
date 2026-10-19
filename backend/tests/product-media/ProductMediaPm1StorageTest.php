<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Product\Media\Infrastructure\Storage\LocalProductImageStorage;
use App\Modules\Product\Media\Ports\ProductImageStoragePort;
use App\Services\ProductImageStorage;
use PHPUnit\Framework\TestCase;

/** PM-1/02: canonical storage and legacy API must agree on real private files. */
final class ProductMediaPm1StorageTest extends TestCase
{
    public function testCanonicalStorageImplementsPortAndLegacySurfacePreservesSignatures(): void
    {
        $canonical = new LocalProductImageStorage();
        self::assertInstanceOf(ProductImageStoragePort::class, $canonical);
        self::assertSame(3 * 1024 * 1024, LocalProductImageStorage::MAX_BYTES);
        self::assertSame(5000, LocalProductImageStorage::MAX_DIMENSION);
        self::assertSame(LocalProductImageStorage::MAX_BYTES, ProductImageStorage::MAX_BYTES);
        self::assertSame(LocalProductImageStorage::MAX_DIMENSION, ProductImageStorage::MAX_DIMENSION);

        foreach (['inspect' => ['temporaryFile'], 'store' => ['temporaryFile'],
            'path' => ['filename'], 'delete' => ['filename']] as $name => $args) {
            $method = new ReflectionMethod(ProductImageStorage::class, $name);
            self::assertTrue($method->isPublic());
            self::assertSame($args, array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters()));
        }
        self::assertFalse((new ReflectionClass(ProductImageStorage::class))->hasMethod('__construct'));
    }

    public function testFilenameValidationAndNotFoundCodeStayIdentical(): void
    {
        $canonical = new LocalProductImageStorage();
        $legacy = new ProductImageStorage();
        $good = str_repeat('a', 40) . '.png';
        self::assertSame($canonical->path($good), $legacy->path($good));
        foreach (['../../.env', '../' . $good, strtoupper($good), 'evil.svg', str_repeat('0', 39) . '.jpg'] as $name) {
            foreach ([$canonical, $legacy] as $storage) {
                try {
                    $storage->path($name);
                    self::fail('A non-canonical filename was accepted');
                } catch (DomainException $exception) {
                    self::assertSame('PRODUCT_IMAGE_NOT_FOUND', $exception->getErrorCode());
                }
            }
        }
    }

    public function testCanonicalAndLegacyRejectTheSameMalformedAndOversizedContent(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'pm1-media-');
        self::assertIsString($temporary);
        try {
            foreach ([
                ['bytes' => 'not an image', 'code' => 'PRODUCT_IMAGE_INVALID_CONTENT'],
                ['bytes' => str_repeat('z', ProductImageStorage::MAX_BYTES + 1), 'code' => 'PRODUCT_IMAGE_INVALID_SIZE'],
            ] as $invalid) {
                $bytes = $invalid['bytes'];
                $expected = $invalid['code'];
                self::assertNotFalse(file_put_contents($temporary, $bytes));
                foreach ([new LocalProductImageStorage(), new ProductImageStorage()] as $storage) {
                    try {
                        $storage->inspect($temporary);
                        self::fail('Invalid image was accepted');
                    } catch (DomainException $exception) {
                        self::assertSame($expected, $exception->getErrorCode());
                    }
                }
            }
        } finally {
            @unlink($temporary);
        }
    }

    public function testCanonicalAndLegacyWriteIndependentValidPrivateImages(): void
    {
        $source = TESTPATH . 'fixtures/product-media/pixel.png';
        self::assertFileExists($source);
        $canonical = new LocalProductImageStorage();
        $legacy = new ProductImageStorage();
        $files = [];
        try {
            foreach ([$canonical, $legacy] as $storage) {
                $stored = $storage->store($source);
                $files[] = $stored['filename'];
                self::assertSame('image/png', $stored['mime']);
                self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\.png\z/', $stored['filename']);
                self::assertSame($stored['size'], filesize($storage->path($stored['filename'])));
                self::assertTrue($canonical->isFile($storage->path($stored['filename'])));
                self::assertSame($canonical->path($stored['filename']), $legacy->path($stored['filename']));
            }
            self::assertNotSame($files[0], $files[1]);
            $canonical->delete($files[0]);
            self::assertFalse($canonical->isFile($canonical->path($files[0])));
            $legacy->delete($files[1]);
            self::assertFalse($canonical->isFile($canonical->path($files[1])));
        } finally {
            foreach ($files as $filename) {
                $canonical->delete($filename);
            }
        }
    }
}

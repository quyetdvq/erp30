<?php

declare(strict_types=1);

use App\Services\ProductImageService;
use App\Services\ProductImageStorage;
use PHPUnit\Framework\TestCase;

final class ProductMediaCompatibilityTest extends TestCase
{
    public function testConstructorNamedArgumentsAndNullableDefaultsRemainStable(): void
    {
        $parameters = (new ReflectionMethod(ProductImageService::class, '__construct'))->getParameters();
        self::assertSame(['db', 'catalog', 'storage'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $parameters));
        foreach ($parameters as $parameter) {
            self::assertTrue($parameter->allowsNull()); self::assertNull($parameter->getDefaultValue());
        }
    }

    public function testPublicMethodParameterNamesAndReturnTypesRemainStable(): void
    {
        foreach (['read' => ['id'], 'upload' => ['id', 'temporaryFile'], 'remove' => ['id']] as $name => $expected) {
            $method = new ReflectionMethod(ProductImageService::class, $name);
            self::assertTrue($method->isPublic());
            self::assertSame($expected, array_map(static fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters()));
            self::assertSame('array', (string) $method->getReturnType());
        }
    }

    public function testStorageLimitsAndPublicMethodsRemainStable(): void
    {
        self::assertSame(3 * 1024 * 1024, ProductImageStorage::MAX_BYTES);
        self::assertSame(5000, ProductImageStorage::MAX_DIMENSION);
        foreach (['inspect', 'store', 'path', 'delete'] as $method) {
            self::assertTrue((new ReflectionMethod(ProductImageStorage::class, $method))->isPublic());
        }
    }
}

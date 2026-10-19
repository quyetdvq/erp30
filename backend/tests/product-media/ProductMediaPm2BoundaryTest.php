<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** PM-2: verify the owning module boundary does not call back into API-050. */
final class ProductMediaPm2BoundaryTest extends TestCase
{
    public function testCanonicalProductMediaHasNoApi050OrTransitionalAdapter(): void
    {
        $media = APPPATH . 'Modules/Product/Media';
        self::assertDirectoryExists($media);
        self::assertFileDoesNotExist($media . '/Infrastructure/Access/Api050MediaAccessAdapter.php');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($media));
        $count = 0;
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$count;
            $contents = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('Api050', $contents, $file->getPathname());
            self::assertStringNotContainsString('productForImage(', $contents, $file->getPathname());
            self::assertStringNotContainsString('getProduct(', $contents, $file->getPathname());
        }
        self::assertGreaterThan(5, $count);
        $factory = (string) file_get_contents(APPPATH . 'Bootstrap/ProductMediaFactory.php');
        self::assertStringNotContainsString('new Api050ProductCatalogService(', $factory);
    }
}

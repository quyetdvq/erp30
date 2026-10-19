<?php

declare(strict_types=1);

use App\Bootstrap\ProductMediaFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Product\Media\Application\ProductImageApplicationService;
use App\Modules\Product\Media\Infrastructure\Storage\LegacyProductImageStorageAdapter;
use App\Modules\Product\Media\Infrastructure\Storage\LocalProductImageStorage;
use App\Services\Api050ProductCatalogService;
use App\Services\ProductImageStorage;
use Tests\Support\ProductMedia\MediaTestCase;
use Tests\Support\ProductMedia\ProductMediaFixture;

/** PM-1/02: verify real dependency composition on the guarded test connection. */
final class ProductMediaPm1CompositionTest extends MediaTestCase
{
    public function testFactoryUsesCanonicalStorageByDefaultAndLegacyAdapterWhenInjected(): void
    {
        $catalog = new Api050ProductCatalogService(
            db: $this->db,
            mutationContext: new MutationContext(ProductMediaFixture::clock(), $this->fixture->writer, 'pm1-02-factory', false),
        );
        $default = ProductMediaFactory::create(db: $this->db, catalog: $catalog);
        $legacy = ProductMediaFactory::create(db: $this->db, catalog: $catalog, storage: new ProductImageStorage());
        self::assertInstanceOf(ProductImageApplicationService::class, $default);
        self::assertInstanceOf(ProductImageApplicationService::class, $legacy);

        $property = new ReflectionProperty(ProductImageApplicationService::class, 'storage');
        self::assertInstanceOf(LocalProductImageStorage::class, $property->getValue($default));
        self::assertInstanceOf(LegacyProductImageStorageAdapter::class, $property->getValue($legacy));

        // Same authorized read against one fixture irrespective of construction path.
        // The fixture's row does not have an image yet, so the error identity is stable.
        foreach ([$default, $legacy] as $service) {
            $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $service->read($this->productId));
        }
    }
}

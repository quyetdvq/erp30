<?php

declare(strict_types=1);

use App\Bootstrap\ProductMediaFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Product\Media\Application\ProductImageApplicationService;
use App\Modules\Product\Media\Infrastructure\Access\ProductReadMediaAccessAdapter;
use App\Modules\Product\Product\Application\AuthorizedProductReadService;
use App\Services\Api050ProductCatalogService;
use Tests\Support\ProductMedia\MediaTestCase;
use Tests\Support\ProductMedia\ProductMediaFixture;

/** PM-2: guarded real-DB parity, PBAC and actor/clock compatibility tests. */
final class ProductMediaPm2AccessTest extends MediaTestCase
{
    private function catalog(int $actor): Api050ProductCatalogService
    {
        return new Api050ProductCatalogService(
            db: $this->db,
            mutationContext: new MutationContext(ProductMediaFixture::clock(), $actor, 'pm2-access', false),
        );
    }

    public function testApi050AndSharedReadUseSamePersistedRowAndPublicDto(): void
    {
        $catalog = $this->catalog($this->fixture->writer);
        $access = $catalog->productReadAccess();
        self::assertInstanceOf(AuthorizedProductReadService::class, $access);
        self::assertSame($catalog->productForImage($this->productId, 'view'), $access->authorizedRow($this->productId, 'view'));
        self::assertSame($catalog->productForImage($this->productId, 'edit'), $access->authorizedRow($this->productId, 'edit'));
        self::assertSame($catalog->getProduct($this->productId), $access->publicProduct($this->productId));
        $this->assertPublicProduct($access->publicProduct($this->productId), false);
    }

    public function testMediaFactoryUsesNewProductBoundaryForDefaultAndInjectedCatalog(): void
    {
        $default = ProductMediaFactory::create(db: $this->db);
        $injected = ProductMediaFactory::create(db: $this->db, catalog: $this->catalog($this->fixture->writer));
        foreach ([$default, $injected] as $service) {
            self::assertInstanceOf(ProductImageApplicationService::class, $service);
            $access = (new ReflectionProperty(ProductImageApplicationService::class, 'access'))->getValue($service);
            self::assertInstanceOf(ProductReadMediaAccessAdapter::class, $access);
            $reader = (new ReflectionProperty(ProductReadMediaAccessAdapter::class, 'products'))->getValue($access);
            self::assertInstanceOf(AuthorizedProductReadService::class, $reader);
            $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $service->read($this->productId));
        }
    }

    public function testInvalidActionPrecedesMissingProductAndMissingProductPrecedesAuthorization(): void
    {
        $access = $this->catalog($this->fixture->reader)->productReadAccess();
        $this->domain('PRODUCT_INVALID_ACTION', fn () => $access->authorizedRow(999999999, 'delete'));
        $this->domain('PRODUCT_NOT_FOUND', fn () => $access->authorizedRow(999999999, 'edit'));
        $this->domain('PRODUCT_NOT_FOUND', fn () => $access->publicProduct(999999999));
    }

    public function testNativeStatePolicyUsesActualRowForBothSharedAndApi050(): void
    {
        $catalog = $this->catalog($this->fixture->writer);
        $reader = $catalog->productReadAccess();
        $this->fixture->deny('product', 'edit', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        self::assertSame($this->row(), $reader->authorizedRow($this->productId, 'edit'));
        self::assertTrue($this->db->table('products')->where('id', $this->productId)->update(['status' => 'INACTIVE']));
        foreach ([
            fn () => $reader->authorizedRow($this->productId, 'edit'),
            fn () => $catalog->productForImage($this->productId, 'edit'),
            fn () => ProductMediaFactory::create(db: $this->db)->upload($this->productId, $this->source()),
        ] as $action) {
            $denied = $this->domain('PRODUCT_AUTHORIZATION_DENIED', $action);
            self::assertSame(['action' => 'edit'], $denied->getContext());
        }
        self::assertSame([], $this->imageFiles());
    }

    public function testDefaultCompositionRequiresIndependentViewAfterSuccessfulEdit(): void
    {
        $old = $this->seedImage();
        $this->fixture->deny('product', 'view', $this->fixture->writer);
        $denied = $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () =>
            ProductMediaFactory::create(db: $this->db)->upload($this->productId, $this->source()));
        self::assertSame(['action' => 'view'], $denied->getContext());
        self::assertNotNull($this->row()['image_filename']);
        $this->assertFileDoesNotExist($old);
        self::assertCount(1, $this->imageFiles());
    }

    public function testInjectedCatalogMutationContextRetainsAuthorityWhenAmbientActorChanges(): void
    {
        $catalog = $this->catalog($this->fixture->writer);
        $this->asActor($this->fixture->reader);
        // The ambient reader lacks edit. A supplied catalog must retain writer
        // authority, just as the legacy constructor did before PM-2.
        $result = ProductMediaFactory::create(db: $this->db, catalog: $catalog)->remove($this->productId);
        $this->assertPublicProduct($result, false);
    }

    public function testProjectionRedactsMediaAndCoercesDriverVariantsWithoutChangingNames(): void
    {
        foreach ([0, '0', false, 1, '1', true] as $raw) {
            $dto = AuthorizedProductReadService::projectForApi([
                'id' => 10,
                'code' => 'PROD-10',
                'image_filename' => 'secret.png',
                'image_mime' => 'image/png',
                'image_size_bytes' => 123,
                'serial_management_enabled' => $raw,
            ]);
            self::assertSame('PROD-10', $dto['product_code']);
            self::assertSame(in_array($raw, [1, '1', true], true), $dto['serial_management_enabled']);
            self::assertTrue($dto['image_available']);
            foreach (['code', 'image_filename', 'image_mime', 'image_size_bytes'] as $secret) {
                self::assertArrayNotHasKey($secret, $dto);
            }
        }
        $without = AuthorizedProductReadService::projectForApi(['image_filename' => null]);
        self::assertFalse($without['image_available']);
    }
}

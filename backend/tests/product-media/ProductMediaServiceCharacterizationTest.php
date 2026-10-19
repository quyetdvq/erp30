<?php

declare(strict_types=1);

use App\Exceptions\PersistenceException;
use App\Services\ProductImageStorage;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use Tests\Support\ProductMedia\MediaTestCase;

/** Real DB, real Api050 and real PBAC; only fault-injected metadata calls use mocks. */
final class ProductMediaServiceCharacterizationTest extends MediaTestCase
{
    public function testFirstUploadPersistsMetadataAndReturnsPublicProduct(): void
    {
        $before = date('Y-m-d H:i:s');
        $dto = $this->media()->upload($this->productId, $this->source());
        $after = date('Y-m-d H:i:s');
        $row = $this->row();
        $this->assertPublicProduct($dto, true);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{40}\.png\z/', $row['image_filename']);
        $this->assertSame('image/png', $row['image_mime']);
        $this->assertSame(filesize($this->source()), (int) $row['image_size_bytes']);
        $this->assertSame($this->fixture->writer, (int) $row['updated_by']);
        $this->assertGreaterThanOrEqual($before, $row['updated_at']);
        $this->assertLessThanOrEqual($after, $row['updated_at']);
        $this->assertCount(1, $this->imageFiles());
    }

    public function testAuthorizedReadReturnsExactBytes(): void
    {
        $path = $this->seedImage();
        $image = $this->media($this->fixture->reader)->read($this->productId);
        $this->assertSame(['path' => $path, 'mime' => 'image/png'], $image);
        $this->assertSame(file_get_contents($this->source()), file_get_contents($image['path']));
    }

    public function testMissingImageMetadataIsNotFound(): void
    {
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->read($this->productId));
    }

    public function testMissingDiskFileIsNotFound(): void
    {
        $path = $this->seedImage(); $this->assertTrue(unlink($path));
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->read($this->productId));
    }

    public function testInvalidStoredMimeIsNotFound(): void
    {
        $this->seedImage();
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update(['image_mime' => 'text/plain']));
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->read($this->productId));
    }

    public function testStoredTraversalFilenameIsNotFound(): void
    {
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update([
            'image_filename' => '../../.env', 'image_mime' => 'image/png',
        ]));
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->read($this->productId));
    }

    public function testMissingProductErrorPrecedesImageNotFound(): void
    {
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->delete());
        $this->domain('PRODUCT_NOT_FOUND', fn () => $this->media()->read($this->productId));
    }

    public function testReplacementDeletesOldFileAfterChangingMetadata(): void
    {
        $old = $this->seedImage();
        $this->assertPublicProduct($this->media()->upload($this->productId, $this->source('jpg')), true);
        $this->assertFileDoesNotExist($old);
        $this->assertCount(1, $this->imageFiles());
        $this->assertSame('image/jpeg', $this->row()['image_mime']);
    }

    public function testDeniedEditPrecedesInvalidSourceAndHasNoSideEffects(): void
    {
        $old = $this->seedImage(); $before = $this->row();
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media($this->fixture->reader)->upload($this->productId, '/missing/source'));
        $this->assertSame($before, $this->row()); $this->assertSame([$old], $this->imageFiles());
    }

    public function testAbsoluteDenyBlocksReadEvenWithBaseViewGrant(): void
    {
        $old = $this->seedImage();
        $this->fixture->deny('product', 'view', $this->fixture->writer);
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media()->read($this->productId));
        $this->assertSame([$old], $this->imageFiles());
    }

    public function testInvalidContentPreservesExistingMetadataAndFile(): void
    {
        $old = $this->seedImage(); $before = $this->row();
        $bad = WRITEPATH . 'pm-invalid-content'; file_put_contents($bad, '<svg>not accepted</svg>');
        try { $this->domain('PRODUCT_IMAGE_INVALID_CONTENT', fn () => $this->media()->upload($this->productId, $bad)); }
        finally { unlink($bad); }
        $this->assertSame($before, $this->row()); $this->assertSame([$old], $this->imageFiles());
    }

    public function testFalseMetadataUpdateCleansNewFileRetainsOldAndWrapsFailure(): void
    {
        $this->assertMetadataFailure(false, false);
    }

    public function testThrownMetadataUpdateCleansNewFileRetainsOldAndChainsFailure(): void
    {
        $this->assertMetadataFailure(true, false);
    }

    public function testRemoveMetadataFailureDoesNotDeleteOldFile(): void
    {
        $this->assertMetadataFailure(false, true);
    }

    public function testRemoveClearsMetadataAndReturnsPublicProduct(): void
    {
        $old = $this->seedImage();
        $this->assertPublicProduct($this->media()->remove($this->productId), false);
        foreach (['image_filename', 'image_mime', 'image_size_bytes'] as $key) { $this->assertNull($this->row()[$key]); }
        $this->assertFileDoesNotExist($old);
    }

    public function testRepeatedRemoveRemainsSuccessful(): void
    {
        $service = $this->media();
        $this->assertPublicProduct($service->remove($this->productId), false);
        $this->assertPublicProduct($service->remove($this->productId), false);
        $this->assertSame([], $this->imageFiles());
    }

    public function testRemoveToleratesMissingDiskFile(): void
    {
        $path = $this->seedImage(); unlink($path);
        $this->assertPublicProduct($this->media()->remove($this->productId), false);
    }

    public function testDeniedRemovePreservesMetadataAndFile(): void
    {
        $old = $this->seedImage(); $before = $this->row();
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media($this->fixture->reader)->remove($this->productId));
        $this->assertSame($before, $this->row()); $this->assertSame([$old], $this->imageFiles());
    }

    public function testUploadViewDeniedAfterWriteDoesNotUndoMetadataOrCleanup(): void
    {
        $old = $this->seedImage(); $before = $this->row();
        $this->fixture->deny('product', 'view', $this->fixture->writer);
        $error = $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media()->upload($this->productId, $this->source()));
        $this->assertSame(['action' => 'view'], $error->getContext());
        $this->assertNotSame($before['image_filename'], $this->row()['image_filename']);
        $this->assertFileDoesNotExist($old); $this->assertCount(1, $this->imageFiles());
    }

    public function testRemoveViewDeniedAfterWriteDoesNotUndoMetadataOrCleanup(): void
    {
        $old = $this->seedImage();
        $this->fixture->deny('product', 'view', $this->fixture->writer);
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media()->remove($this->productId));
        $this->assertNull($this->row()['image_filename']); $this->assertFileDoesNotExist($old);
    }

    public function testInvalidLegacyFilenameFailsCleanupAfterUploadMetadataWrite(): void
    {
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update(['image_filename' => '../old.png']));
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->upload($this->productId, $this->source()));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{40}\.png\z/', $this->row()['image_filename']);
        $this->assertCount(1, $this->imageFiles());
    }

    public function testInvalidLegacyFilenameFailsCleanupAfterRemoveMetadataWrite(): void
    {
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update(['image_filename' => '../old.png']));
        $this->domain('PRODUCT_IMAGE_NOT_FOUND', fn () => $this->media()->remove($this->productId));
        $this->assertNull($this->row()['image_filename']);
    }

    public function testCallerRollbackRestoresMetadataButNotDeletedOldFile(): void
    {
        // Characterization of the BASELINE limitation, not desired future behavior.
        $old = $this->seedImage(); $before = $this->row();
        // The shared fixture already has an outer rollback-only transaction.
        // CI4 nested transBegin/transRollback only change transaction depth;
        // a SQL SAVEPOINT is needed to exercise an actual caller rollback here.
        $this->assertNotFalse($this->db->query('SAVEPOINT pm0_caller_before_upload'));
        try { $this->media()->upload($this->productId, $this->source('jpg')); }
        finally {
            $this->assertNotFalse($this->db->query('ROLLBACK TO SAVEPOINT pm0_caller_before_upload'));
            $this->assertNotFalse($this->db->query('RELEASE SAVEPOINT pm0_caller_before_upload'));
        }
        $this->assertSame($before['image_filename'], $this->row()['image_filename']);
        $this->assertFileDoesNotExist($old, 'Do not claim DB/filesystem atomicity in the baseline.');
        $this->assertCount(1, $this->imageFiles(), 'New file remains after caller rollback in baseline.');
    }

    public function testMetadataTimestampUsesProcessTimezoneNotInjectedEvaluationClock(): void
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $service = $this->media(); $before = date('Y-m-d H:i:s');
        $service->remove($this->productId); $after = date('Y-m-d H:i:s');
        $this->assertGreaterThanOrEqual($before, $this->row()['updated_at']);
        $this->assertLessThanOrEqual($after, $this->row()['updated_at']);
    }

    public function testNativeStateScopedViewDenyUsesPersistedState(): void
    {
        $this->seedImage();
        $this->assertTrue($this->db->table('resources')->where('type', 'product')->update(['lifecycle_id' => null]));
        $this->fixture->deny('product', 'view', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        $this->media()->read($this->productId);
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update(['status' => 'INACTIVE']));
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media()->read($this->productId));
    }

    public function testLifecycleStateScopedEditDenyUsesPersistedState(): void
    {
        $lifecycle = \Tests\Support\Workflow\BeReorg009LifecycleFixture::lifecycle($this->db);
        \Tests\Support\Workflow\BeReorg009LifecycleFixture::bind($this->db, $lifecycle, $this->fixture->states['ACTIVE'], 1, true);
        \Tests\Support\Workflow\BeReorg009LifecycleFixture::bind($this->db, $lifecycle, $this->fixture->states['INACTIVE'], 2);
        $this->assertTrue($this->db->table('resources')->where('type', 'product')->update(['lifecycle_id' => $lifecycle]));
        $this->fixture->deny('product', 'edit', $this->fixture->writer, $this->fixture->states['INACTIVE']);
        $this->assertPublicProduct($this->media()->upload($this->productId, $this->source()), true);
        $files = $this->imageFiles();
        $this->assertTrue($this->db->table('products')->where('id', $this->productId)->update(['status' => 'INACTIVE']));
        $before = $this->row();
        $this->domain('PRODUCT_AUTHORIZATION_DENIED', fn () => $this->media()->upload($this->productId, $this->source('jpg')));
        $this->assertSame($before, $this->row()); $this->assertSame($files, $this->imageFiles());
    }

    public function testStorageDirectoryFailureLeavesMetadataUntouched(): void
    {
        $directory = WRITEPATH . 'uploads/product-images';
        if (is_dir($directory)) { $this->assertTrue(rmdir($directory)); }
        $this->assertNotFalse(file_put_contents($directory, 'synthetic obstruction'));
        $before = $this->row();
        try {
            try { $this->media()->upload($this->productId, $this->source()); $this->fail('Expected storage failure.'); }
            catch (RuntimeException $error) { $this->assertSame('Cannot create Product image directory.', $error->getMessage()); }
            $this->assertSame($before, $this->row());
        } finally {
            unlink($directory); mkdir($directory, 0700);
        }
    }

    private function assertMetadataFailure(bool $throw, bool $remove): void
    {
        $old = $this->seedImage(); $before = $this->row();
        // Only metadata persistence is fault-injected. Catalog reads and PBAC remain real.
        $builder = $this->getMockBuilder(BaseBuilder::class)->disableOriginalConstructor()
            ->onlyMethods(['where', 'update'])->getMock();
        $builder->expects($this->once())->method('where')->with('id', $this->productId)->willReturnSelf();
        $failure = new RuntimeException('Synthetic metadata failure');
        $update = $builder->expects($this->once())->method('update');
        if ($throw) { $update->willThrowException($failure); } else { $update->willReturn(false); }
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->once())->method('table')->with('products')->willReturn($builder);
        $service = $this->media(metadataDb: $db);
        $error = $this->domain('PRODUCT_PERSISTENCE_FAILED', fn () => $remove
            ? $service->remove($this->productId) : $service->upload($this->productId, $this->source()));
        $this->assertInstanceOf(PersistenceException::class, $error);
        $this->assertSame(['operation' => 'writeProductImage'], $error->getContext());
        if ($throw) { $this->assertSame($failure, $error->getPrevious()); }
        else { $this->assertSame('Product image metadata write failed', $error->getPrevious()?->getMessage()); }
        $this->assertSame($before, $this->row()); $this->assertSame([$old], $this->imageFiles());
    }
}

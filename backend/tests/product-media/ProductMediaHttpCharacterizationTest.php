<?php

declare(strict_types=1);

use App\Debug\ApiExceptionHandler;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Api\ApiErrorMapper;
use App\Services\ProductImageStorage;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\App;
use Tests\Support\ProductMedia\MediaTestCase;

/**
 * Real CI4 routing/controller/service/PBAC/DB, with only getFile()/upload provenance
 * substituted for in-process CLI requests. This is NOT a wire-level multipart,
 * Apache, browser, or secure-runtime CSRF test. Those gates remain separate.
 */
final class ProductMediaHttpCharacterizationTest extends MediaTestCase
{
    use FeatureTestTrait { setupRequest as private setupFrameworkRequest; }

    private ?UploadedFile $uploaded = null;
    private int $requestNumber = 0;

    public function testGetReturnsExactBinaryAndPrivateHeaders(): void
    {
        $this->seedImage();
        $result = $this->requestAs($this->fixture->reader, 'GET');
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'image/png');
        $result->assertHeader('Content-Disposition', 'inline');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $result->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $this->assertSame(file_get_contents($this->source()), $result->response()->getBody());
    }

    public function testGetWithoutImageReturnsJsonNotFound(): void
    {
        $this->error($this->requestAs($this->fixture->reader, 'GET'), 404, 'PRODUCT_IMAGE_NOT_FOUND');
    }

    public function testDeniedReadReturnsNoBytesOrPrivatePath(): void
    {
        $this->seedImage(); $this->fixture->deny('product', 'view', $this->fixture->reader);
        $result = $this->requestAs($this->fixture->reader, 'GET');
        $this->error($result, 403, 'PRODUCT_AUTHORIZATION_DENIED');
        $this->assertStringNotContainsString(WRITEPATH, (string) $result->response()->getBody());
    }

    public function testUnauthenticatedReadReturns401(): void
    {
        $this->seedImage();
        $this->requestAs(null, 'GET')->assertStatus(401);
    }

    public function testDeniedEditPrecedesMissingImageValidation(): void
    {
        $before = $this->row();
        $this->error($this->requestAs($this->fixture->reader, 'POST'), 403, 'PRODUCT_AUTHORIZATION_DENIED');
        $this->assertSame($before, $this->row()); $this->assertSame([], $this->imageFiles());
    }

    public function testMissingUploadIs422ForAuthorizedEditor(): void
    {
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_CONTENT');
    }

    public function testIniLimitMapsToImageSizeError(): void
    {
        $this->uploaded = $this->upload(UPLOAD_ERR_INI_SIZE, 1);
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_SIZE');
    }

    public function testFormLimitMapsToImageSizeError(): void
    {
        $this->uploaded = $this->upload(UPLOAD_ERR_FORM_SIZE, 1);
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_SIZE');
    }

    public function testPartialUploadMapsToContentError(): void
    {
        $this->uploaded = $this->upload(UPLOAD_ERR_PARTIAL, 1);
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_CONTENT');
    }

    public function testZeroSizeIsRejectedBeforeServiceStore(): void
    {
        $this->uploaded = $this->upload(UPLOAD_ERR_OK, 0);
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_SIZE');
        $this->assertSame([], $this->imageFiles());
    }

    public function testOversizedTransportValueIsRejectedBeforeServiceStore(): void
    {
        $this->uploaded = $this->upload(UPLOAD_ERR_OK, ProductImageStorage::MAX_BYTES + 1);
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 422, 'PRODUCT_IMAGE_INVALID_SIZE');
        $this->assertSame([], $this->imageFiles());
    }

    public function testUploadReturnsEnvelopeAndRedactedProduct(): void
    {
        $this->uploaded = $this->upload();
        $body = $this->json($this->requestAs($this->fixture->writer, 'POST'), 200);
        $this->assertPublicProduct($body['data'], true);
    }

    public function testDeleteReturnsEnvelopeAndRedactedProduct(): void
    {
        $old = $this->seedImage();
        $body = $this->json($this->requestAs($this->fixture->writer, 'DELETE'), 200);
        $this->assertPublicProduct($body['data'], false);
        $this->assertFileDoesNotExist($old);
    }

    public function testForgedMultipartTargetFieldsCannotBypassAbsoluteDeny(): void
    {
        $before = $this->row(); $this->fixture->deny('product', 'edit', $this->fixture->writer);
        $this->uploaded = $this->upload();
        $this->error($this->requestAs($this->fixture->writer, 'POST', [
            'status' => 'ACTIVE', 'organization_id' => $this->fixture->group,
            'evaluation_organization_id' => $this->fixture->group, 'id' => 999999,
        ]), 403, 'PRODUCT_AUTHORIZATION_DENIED');
        $this->assertSame($before, $this->row()); $this->assertSame([], $this->imageFiles());
    }

    public function testResponseViewDenialReturns403AfterUploadSideEffects(): void
    {
        $old = $this->seedImage(); $this->fixture->deny('product', 'view', $this->fixture->writer);
        $this->uploaded = $this->upload();
        $this->error($this->requestAs($this->fixture->writer, 'POST'), 403, 'PRODUCT_AUTHORIZATION_DENIED');
        $this->assertFileDoesNotExist($old); $this->assertCount(1, $this->imageFiles());
    }

    public function testPersistenceMapperRedactsInternalCodeAndDiagnostic(): void
    {
        // Mapper contract, NOT a claimed HTTP SQL fault-injection test.
        $mapped = ApiErrorMapper::map(new PersistenceException(
            'PRODUCT_PERSISTENCE_FAILED', 'writeProductImage', new RuntimeException('private-sql-diagnostic'),
        ));
        $this->assertSame(500, $mapped['status']);
        $this->assertSame('INTERNAL_SERVER_ERROR', $mapped['code']);
        $this->assertSame([], $mapped['details']);
        $this->assertStringNotContainsString('private-sql-diagnostic', json_encode($mapped, JSON_THROW_ON_ERROR));
    }

    protected function setupRequest(string $method, ?string $path = null): IncomingRequest
    {
        $base = $this->setupFrameworkRequest($method, $path);
        $request = $this->getMockBuilder(IncomingRequest::class)
            ->setConstructorArgs([config(App::class), $base->getUri(), '', $base->getUserAgent()])
            ->onlyMethods(['getFile'])->getMock();
        $request->method('getFile')->with('image')->willReturn($this->uploaded);
        $request->setMethod($method); $request->setProtocolVersion('1.1');
        $request->setGlobal('server', $base->getServer());
        return $request;
    }

    private function upload(int $error = UPLOAD_ERR_OK, ?int $size = null): UploadedFile
    {
        $file = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$this->source(), 'client.png', 'image/png', $size ?? filesize($this->source()), $error])
            ->onlyMethods(['isValid'])->getMock();
        // CLI cannot create is_uploaded_file() provenance. Do not change production validation.
        $file->method('isValid')->willReturn($error === UPLOAD_ERR_OK);
        return $file;
    }

    private function requestAs(?int $actor, string $method, ?array $form = null): TestResponse
    {
        if ($actor !== null) { $this->asActor($actor); }
        else {
            $this->resetServices(); $this->mockCache(); $this->mockEmail(); $this->mockSession();
            \App\Infrastructure\RequestContext::reset();
        }
        $this->app = $this->createApplication(); $this->routes = null; $this->session = [];
        $path = '/api/v1/products/' . $this->productId . '/image';
        $headers = ['Accept' => 'application/json', 'Accept-Language' => 'en', 'X-Request-ID' => 'pm0-http-' . ++$this->requestNumber];
        if ($method !== 'GET') {
            $headers[config('Security')->headerName] = service('security')->getHash();
            if ($method === 'POST') { $headers['Content-Type'] = 'multipart/form-data; boundary=pm0-in-process'; }
        }
        $this->withSession(); $this->withHeaders($headers)->withBodyFormat('')->withBody('');
        $globals = service('superglobals'); $previous = $globals->server('REQUEST_URI'); $level = ob_get_level();
        try {
            $globals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path, $form);
        } catch (DomainException|PageNotFoundException|BadRequestException $exception) {
            $response = service('response'); ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);
            return new TestResponse($response);
        } finally {
            if ($previous === null) { $globals->unsetServer('REQUEST_URI'); } else { $globals->setServer('REQUEST_URI', $previous); }
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }

    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('pm0-http-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']); $this->assertArrayNotHasKey('data', $body);
    }
}

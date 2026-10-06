<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiErrorMapper;
use App\Infrastructure\Api\ApiTransport;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\RequestContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Api001TransportTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testSuccessEnvelopeUsesRequestIdSnakeCaseAndStringDomainNumbers(): void
    {
        RequestContext::begin('api-001-request', 'en');
        $response = ApiTransport::success(service('response'), [
            'productId' => 9223372036854775807,
            'unitPrice' => new Decimal('12.50'),
            'nestedValue' => ['rowVersion' => 7],
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('api-001-request', $response->getHeaderLine('X-Request-ID'));
        $this->assertSame('en', $response->getHeaderLine('Content-Language'));
        $this->assertSame('api-001-request', $payload['meta']['request_id']);
        $this->assertSame('9223372036854775807', $payload['data']['product_id']);
        $this->assertSame('12.50', $payload['data']['unit_price']);
        $this->assertSame('7', $payload['data']['nested_value']['row_version']);
    }

    public function testMissingRequestContextGetsUuidAndErrorEnvelope(): void
    {
        $response = ApiTransport::error(service('response'), 'RESOURCE_NOT_FOUND', 'not found', 404);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $payload['meta']['request_id']);
        $this->assertSame($payload['meta']['request_id'], $response->getHeaderLine('X-Request-ID'));
        $this->assertSame('RESOURCE_NOT_FOUND', $payload['error']['code']);
        $this->assertSame([], $payload['error']['details']);
    }

    public function testNoContentStillEchoesRequestIdAndLanguage(): void
    {
        RequestContext::begin('api-001-no-content', 'en');
        $response = ApiTransport::success(service('response'), null, 204);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('api-001-no-content', $response->getHeaderLine('X-Request-ID'));
        $this->assertSame('en', $response->getHeaderLine('Content-Language'));
    }

    public function testMapperKeepsStableCodesAndCanonicalStatuses(): void
    {
        $cases = [
            [new PageNotFoundException('private-target'), 404, 'RESOURCE_NOT_FOUND'],
            [new DomainException('AUTH_POLICY_DENIED'), 403, 'AUTH_POLICY_DENIED'],
            [new ConcurrencyException('test'), 409, 'CONCURRENT_MODIFICATION'],
            [new DomainException('PRODUCT_INVALID_FIELD', ['field' => 'code']), 422, 'PRODUCT_INVALID_FIELD'],
            [new RuntimeException('SQL secret /internal/path'), 500, 'INTERNAL_SERVER_ERROR'],
        ];

        foreach ($cases as [$exception, $status, $code]) {
            $mapped = ApiErrorMapper::map($exception);
            $this->assertSame($status, $mapped['status']);
            $this->assertSame($code, $mapped['code']);
            $this->assertStringNotContainsString('SQL', $mapped['message']);
            $this->assertStringNotContainsString('/internal/path', $mapped['message']);
        }
    }

    public function testLanguageNegotiationMessagesUseViFallbackAndEnglish(): void
    {
        RequestContext::begin('locale-vi', 'vi');
        $vi = ApiTransport::message('INTERNAL_SERVER_ERROR', 500);

        RequestContext::begin('locale-en', 'en');
        $en = ApiTransport::message('INTERNAL_SERVER_ERROR', 500);

        RequestContext::begin('locale-fallback', 'fr');
        $fallback = ApiTransport::message('INTERNAL_SERVER_ERROR', 500);

        $this->assertNotSame($vi, $en);
        $this->assertSame($vi, $fallback);
    }
}

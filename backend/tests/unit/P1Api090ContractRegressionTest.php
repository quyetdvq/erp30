<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiErrorMapper;
use App\Infrastructure\Api\ApiTransport;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\RequestContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal Gate 7 contract regression guard for P1-API-090. */
final class P1Api090ContractRegressionTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testRuntimeAndOpenApiOperationInventoriesHaveExactPathVerbParity(): void
    {
        $runtime = $this->runtimeInventory();
        $openapi = $this->openApiInventory();

        $this->assertNotEmpty($runtime);
        $this->assertNotEmpty($openapi['operations']);
        $this->assertSame([], array_diff_key($runtime, $openapi['operations']));
        $this->assertSame([], array_diff_key($openapi['operations'], $runtime));
        $this->assertSame(
            count($openapi['operation_ids']),
            count(array_unique($openapi['operation_ids'])),
            'OpenAPI operation IDs must be unique.',
        );
        $this->assertSame(
            count($runtime),
            count($openapi['operations']),
            'Runtime and OpenAPI operation inventories must have the same size.',
        );
    }

    public function testCanonicalTransportPreservesEnvelopeRequestIdLocaleAndDomainStrings(): void
    {
        RequestContext::begin('api-090-request', 'en');
        $response = ApiTransport::success(service('response'), [
            'recordId' => 9223372036854775807,
            'unitPrice' => '12.50',
            'conversionFactor' => 1.25,
        ]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('api-090-request', $response->getHeaderLine('X-Request-ID'));
        $this->assertSame('en', $response->getHeaderLine('Content-Language'));
        $this->assertSame('api-090-request', $payload['meta']['request_id']);
        $this->assertSame('9223372036854775807', $payload['data']['record_id']);
        $this->assertSame('12.50', $payload['data']['unit_price']);
        $this->assertSame('1.25', $payload['data']['conversion_factor']);
    }

    public function testErrorTaxonomyUsesStableCodesAndNeverLeaksExceptionDetails(): void
    {
        $cases = [
            [new BadRequestException('SQL secret'), 400, 'MALFORMED_REQUEST'],
            [new RuntimeException('SQL secret /internal/path'), 401, 'UNAUTHENTICATED'],
            [new DomainException('AUTH_POLICY_DENIED'), 403, 'AUTH_POLICY_DENIED'],
            [new PageNotFoundException('private-target'), 404, 'RESOURCE_NOT_FOUND'],
            [new ConcurrencyException('row version'), 409, 'CONCURRENT_MODIFICATION'],
            [new DomainException('PRODUCT_INVALID_FIELD', ['field' => 'code']), 422, 'PRODUCT_INVALID_FIELD'],
        ];

        foreach ($cases as [$exception, $status, $code]) {
            $mapped = ApiErrorMapper::map($exception, $status);
            $this->assertSame($status, $mapped['status']);
            $this->assertSame($code, $mapped['code']);
            $this->assertStringNotContainsString('SQL', $mapped['message']);
            $this->assertStringNotContainsString('/internal/path', $mapped['message']);
        }
    }

    public function testListQueryContractRejectsUnknownInputAndScopesTotalBeforePagination(): void
    {
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(
            search: ['name'],
            filters: ['status'],
            sort: ['name'],
        );

        $query = $adapter->parse([
            'q' => 'visible',
            'page' => '2',
            'page_size' => '1',
            'sort' => '-name',
            'filter' => ['status' => ['ACTIVE']],
        ], $allowlist);
        $this->assertSame(2, $query->page);
        $this->assertSame(1, $query->pageSize);
        $this->assertSame([
            ['field' => 'name', 'direction' => 'DESC'],
            ['field' => 'id', 'direction' => 'ASC'],
        ], array_map(static fn ($term): array => $term->toArray(), $query->sortTerms()));

        $source = new AuthorizedArrayListSource([
            ['id' => '1', 'name' => 'visible', 'status' => 'ACTIVE'],
            ['id' => '2', 'name' => 'hidden', 'status' => 'INACTIVE'],
        ]);
        $result = $adapter->paginate(['filter' => ['status' => 'ACTIVE']], $allowlist, $source);
        $this->assertSame('1', $result->total);
        $this->assertCount(1, iterator_to_array($result->data));

        foreach ([['secret' => 'x'], ['sort' => 'secret'], ['page_size' => '0']] as $invalid) {
            try {
                $adapter->parse($invalid, $allowlist);
                $this->fail('Expected malformed query to be rejected.');
            } catch (BadRequestException $exception) {
                $this->assertSame(400, $exception->getCode());
            }
        }
    }

    public function testContractPendingSurfacesRemainAbsentAndResolvedGenderIsConstrained(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        $this->assertIsString($routes);
        $this->assertIsString($openapi);

        foreach ([
            '/api/v1/users',
            '/api/v1/users/{id}',
            '/api/v1/users/{id}/actions/set-state',
            '/api/v1/users/{id}/actions/reset-password',
            '/api/v1/users/{id}/permissions',
        ] as $approvedUserRoute) {
            $this->assertStringContainsString($approvedUserRoute, $openapi);
        }
        $this->assertStringNotContainsString('/api/v1/shield/users', $routes . "\n" . $openapi);
        $this->assertStringNotContainsString('/api/v1/resource-actions', $routes . "\n" . $openapi);
        $this->assertStringContainsString('name: include', $openapi);
        $this->assertStringContainsString('enum: [actions]', $openapi);
        $this->assertDoesNotMatchRegularExpression('#^\s{2}/api/v1/[^:]*export[^:]*:#im', $openapi);
        $this->assertDoesNotMatchRegularExpression('#/export(?:[\'"\s])#i', $routes);
        $this->assertDoesNotMatchRegularExpression('#price[-_]?resolution|resolvePrice#i', $routes . "\n" . $openapi);
        $this->assertDoesNotMatchRegularExpression('#preview[-_]?combo|combo[-_]?preview#i', $routes . "\n" . $openapi);
        $this->assertDoesNotMatchRegularExpression('#/api/v1/[^\r\n:]*(?:selector|options)[^\r\n:]*:#i', $openapi);
        $this->assertDoesNotMatchRegularExpression('#\$routes->(?:get|post|put|patch|delete)\(\'api/v1/[^\']*(?:selector|options)[^\']*\'#i', $routes);
        $this->assertStringContainsString("gender: {type: [string, 'null'], enum: [MALE, FEMALE, OTHER, null]}", $openapi);
    }

    /** @return array<string, string> */
    private function runtimeInventory(): array
    {
        $routes = service('routes');
        $routes->loadRoutes();

        $inventory = [];
        foreach (['GET', 'POST', 'PATCH', 'PUT', 'DELETE'] as $verb) {
            foreach ($routes->getRoutes($verb, false) as $path => $handler) {
                if (! str_starts_with($path, 'api/v1/')) {
                    continue;
                }
                $signature = $verb . ' ' . $this->normalizeRuntimePath($path);
                $inventory[$signature] = (string) $handler;
            }
        }

        return $inventory;
    }

    /** @return array{operations: array<string, string>, operation_ids: list<string>} */
    private function openApiInventory(): array
    {
        $lines = file(__DIR__ . '/../../../contracts/openapi/openapi.yaml', FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);

        $operations = [];
        $operationIds = [];
        $path = null;
        $method = null;
        foreach ($lines as $line) {
            if (preg_match('/^  (\/api\/v1\/[^:]+):\s*$/', $line, $pathMatch) === 1) {
                $path = $pathMatch[1];
                $method = null;
                continue;
            }
            if ($path !== null && preg_match('/^    (get|post|patch|put|delete):\s*$/', $line, $methodMatch) === 1) {
                $method = strtoupper($methodMatch[1]);
                continue;
            }
            if ($path !== null && $method !== null && preg_match('/^      operationId:\s*([A-Za-z0-9_]+)\s*$/', $line, $operationMatch) === 1) {
                $operationIds[] = $operationMatch[1];
                $operations[$method . ' ' . $this->normalizeOpenApiPath($path)] = $operationMatch[1];
            }
        }

        return ['operations' => $operations, 'operation_ids' => $operationIds];
    }

    private function normalizeRuntimePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return (string) preg_replace('/\([^)]*\)/', '{id}', $path);
    }

    private function normalizeOpenApiPath(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{id}', $path);
    }
}

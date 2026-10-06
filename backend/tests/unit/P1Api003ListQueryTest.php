<?php

declare(strict_types=1);

use App\Infrastructure\Api\ApiTransport;
use App\Infrastructure\Api\AuthorizedListSource;
use App\Infrastructure\Api\CommonListQuery;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Api003ListQueryTest extends CIUnitTestCase
{
    public function testDefaultsAndDeterministicIdTieBreaker(): void
    {
        $query = $this->adapter()->parse([], $this->allowlist());

        $this->assertSame(1, $query->page);
        $this->assertSame(50, $query->pageSize);
        $this->assertSame(0, $query->offset);
        $this->assertSame(['name'], $query->searchFields());
        $this->assertSame([['field' => 'id', 'direction' => 'ASC']], array_map(
            static fn ($term): array => $term->toArray(),
            $query->sortTerms(),
        ));
        $this->assertSame([], $query->filters);
    }

    public function testValidSearchScalarAndRepeatedFiltersAndSort(): void
    {
        $query = $this->adapter()->parse([
            'q' => '  alpha  ',
            'page' => '2',
            'page_size' => '25',
            'sort' => '-name,code',
            'filter' => ['status' => 'ACTIVE', 'company_id' => ['1', '2']],
        ], $this->allowlist());

        $this->assertSame('alpha', $query->q);
        $this->assertSame(['name'], $query->searchFields());
        $this->assertSame(2, $query->page);
        $this->assertSame(25, $query->pageSize);
        $this->assertSame(25, $query->offset);
        $this->assertSame([
            ['field' => 'name', 'direction' => 'DESC'],
            ['field' => 'code', 'direction' => 'ASC'],
            ['field' => 'id', 'direction' => 'ASC'],
        ], array_map(static fn ($term): array => $term->toArray(), $query->sortTerms()));
        $this->assertSame(['ACTIVE'], $query->filterValues('status'));
        $this->assertSame(['1', '2'], $query->filterValues('company_id'));
    }

    public function testInvalidPaginationAndPageSizeLimitAreBadRequest(): void
    {
        foreach ([
            ['page' => '0'],
            ['page' => '-1'],
            ['page' => '1.5'],
            ['page' => 'abc'],
            ['page_size' => '0'],
            ['page_size' => '201'],
            ['page_size' => '1.5'],
        ] as $input) {
            try {
                $this->adapter()->parse($input, $this->allowlist());
                $this->fail('Expected malformed pagination to be rejected.');
            } catch (BadRequestException $exception) {
                $this->assertSame(400, $exception->getCode());
            }
        }
    }

    public function testUnknownFieldsAndMalformedSortAreBadRequest(): void
    {
        foreach ([
            ['filter' => ['secret' => 'x']],
            ['sort' => 'secret'],
            ['sort' => '-'],
            ['sort' => 'name,'],
            ['sort' => 'name,name'],
            ['sort' => 'name;DROP TABLE'],
        ] as $input) {
            try {
                $this->adapter()->parse($input, $this->allowlist());
                $this->fail('Expected rejected query syntax/field.');
            } catch (BadRequestException $exception) {
                $this->assertSame(400, $exception->getCode());
            }
        }
    }

    public function testMalformedFilterShapeAndUnknownTopLevelKeyAreBadRequest(): void
    {
        foreach ([
            ['filter' => []],
            ['filter' => ['status' => ['nested' => 'x']]],
            ['filter' => ['status' => []]],
            ['include_hidden' => '1'],
        ] as $input) {
            $this->expectException(BadRequestException::class);
            $this->adapter()->parse($input, $this->allowlist());
        }
    }

    public function testAuthorizedTotalIsCalculatedThroughScopedSourceAndEmptyMetadataIsPreserved(): void
    {
        $source = new class implements AuthorizedListSource {
            public int $countCalls = 0;
            public int $fetchCalls = 0;

            public function countAuthorized(CommonListQuery $query): int|string
            {
                $this->countCalls++;
                return '0';
            }

            public function fetchAuthorized(CommonListQuery $query): iterable
            {
                $this->fetchCalls++;
                return [];
            }
        };

        $result = $this->adapter()->paginate([], $this->allowlist(), $source);

        $this->assertSame('0', $result->total);
        $this->assertSame(1, $source->countCalls);
        $this->assertSame(1, $source->fetchCalls);
        $this->assertSame([], iterator_to_array($result->data));
    }

    public function testCollectionEnvelopePreservesRequestIdAndStringTotal(): void
    {
        $response = ApiTransport::collection(service('response'), [
            ['id' => 9223372036854775807, 'amount' => '12.50'],
        ], 2, 25, '9223372036854775808');
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2, $payload['meta']['page']);
        $this->assertSame(25, $payload['meta']['page_size']);
        $this->assertSame('9223372036854775808', $payload['meta']['total']);
        $this->assertArrayHasKey('request_id', $payload['meta']);
        $this->assertSame('9223372036854775807', $payload['data'][0]['id']);
        $this->assertSame('12.50', $payload['data'][0]['amount']);
    }

    private function adapter(): CommonListQueryAdapter
    {
        return new CommonListQueryAdapter();
    }

    private function allowlist(): CommonListQueryAllowlist
    {
        return new CommonListQueryAllowlist(
            search: ['name'],
            filters: ['status', 'company_id'],
            sort: ['name', 'code'],
        );
    }
}

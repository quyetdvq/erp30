<?php

declare(strict_types=1);

use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Api010FoundationOrganizationContactApiTest extends CIUnitTestCase
{
    public function testAuthorizedRowsAreFilteredBeforeCountAndPaginatedDeterministically(): void
    {
        $query = (new CommonListQueryAdapter())->parse([
            'q' => 'ward',
            'page' => '1',
            'page_size' => '2',
            'sort' => 'name',
            'filter' => ['status' => ['ACTIVE', 'INACTIVE']],
        ], new CommonListQueryAllowlist(
            search: ['name'],
            filters: ['status'],
            sort: ['name'],
        ));
        $source = new AuthorizedArrayListSource([
            ['id' => '12', 'name' => 'Ward B', 'status' => 'ACTIVE'],
            ['id' => '11', 'name' => 'Ward A', 'status' => 'ACTIVE'],
            ['id' => '13', 'name' => 'Hidden', 'status' => 'ACTIVE'],
        ]);

        $this->assertSame(2, $source->countAuthorized($query));
        $rows = iterator_to_array($source->fetchAuthorized($query));
        $this->assertSame(['11', '12'], array_column($rows, 'id'));
    }

    public function testRepeatedFilterValuesUseOrWithinFieldAndEmptyScopeHasZeroTotal(): void
    {
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(search: [], filters: ['province_id'], sort: ['id']);
        $source = new AuthorizedArrayListSource([
            ['id' => '1', 'province_id' => '10'],
            ['id' => '2', 'province_id' => '20'],
        ]);

        $query = $adapter->parse(['filter' => ['province_id' => ['10', '30']]], $allowlist);
        $this->assertSame(1, $source->countAuthorized($query));
        $empty = $adapter->parse(['filter' => ['province_id' => '99']], $allowlist);
        $this->assertSame(0, $source->countAuthorized($empty));
        $this->assertSame([], iterator_to_array($source->fetchAuthorized($empty)));
    }

    public function testUnknownSortAndFilterRemainBadRequestForApi010AllowlistShape(): void
    {
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(search: ['name'], filters: ['status'], sort: ['name']);

        $this->expectException(\CodeIgniter\HTTP\Exceptions\BadRequestException::class);
        $adapter->parse(['sort' => 'secret', 'filter' => ['secret' => 'x']], $allowlist);
    }
}

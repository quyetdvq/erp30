<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom\Application;

use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Modules\Product\Lookup\Application\ProductMasterListSqlFastPath;
use App\Modules\Product\Uom\Application\Ports\ProductUomListAuthorizationPort;
use App\Modules\Product\Uom\ProductUomMasterService;

/**
 * Product-owned orchestration for the existing GET /api/v1/product-uoms.
 *
 * 014F established the ownership boundary. 015A adds an opt-in SQL path
 * only for provably exact ID list queries; all other requests retain the
 * legacy full materialization and Unicode/PHP comparator semantics.
 */
final class ProductUomListQueryService
{
    public function __construct(
        private readonly ProductUomMasterService $uoms,
        private readonly ProductUomListAuthorizationPort $authorization,
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?ProductMasterListSqlFastPath $sqlFastPath = null,
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function list(array $query = []): array
    {
        $allowlist = new CommonListQueryAllowlist(
            search: ['code', 'name'],
            filters: ['code', 'name', 'status'],
            sort: ['id', 'code', 'name', 'status'],
        );
        if ($this->sqlFastPath !== null && ($fast = $this->sqlFastPath->list($query, $allowlist)) !== null) {
            return $fast;
        }

        // Intentionally load before the State-policy probe and query parse,
        // matching API-050 error ordering and injected service behavior.
        $rows = $this->uoms->listAll();
        if ($this->authorization->requiresRowStateAuthorization()) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => $this->authorization->rowVisible($row),
            ));
        } else {
            $this->authorization->authorizeBaseView();
        }

        $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $allowlist,
            new AuthorizedArrayListSource($rows),
        );

        return [
            'items' => array_values(iterator_to_array($result->data)),
            'page' => $result->query->page,
            'page_size' => $result->query->pageSize,
            'total' => $result->total,
        ];
    }
}

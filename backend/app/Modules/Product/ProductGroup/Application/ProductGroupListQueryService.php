<?php

declare(strict_types=1);

namespace App\Modules\Product\ProductGroup\Application;

use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Modules\Product\Lookup\Application\ProductMasterListSqlFastPath;
use App\Modules\Product\ProductGroup\Application\Ports\ProductGroupListAuthorizationPort;
use App\Modules\Product\ProductGroup\ProductGroupService;

/**
 * Product-owned list orchestration for GET /api/v1/product-groups.
 *
 * BE-REORG-014G moves only ownership, not query/security semantics: load the
 * complete flat result (including persisted parent_id), evaluate each row
 * before count/page when State PBAC is active, then use the existing PHP list
 * adapter. 015A adds a separately gated SQL candidate for byte-exact ID
 * pagination; fallback retains this frozen PHP algorithm. Do NOT perform
 * ancestor pruning or re-parent visible children: API-050 never did either.
 */
final class ProductGroupListQueryService
{
    public function __construct(
        private readonly ProductGroupService $groups,
        private readonly ProductGroupListAuthorizationPort $authorization,
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
            filters: ['code', 'name', 'parent_id', 'status'],
            sort: ['id', 'code', 'name', 'parent_id', 'status'],
        );
        // Only eligible, byte-exact SQL plans bypass full materialization.
        // For everything else, the existing sequence below is unchanged.
        if ($this->sqlFastPath !== null && ($fast = $this->sqlFastPath->list($query, $allowlist)) !== null) {
            return $fast;
        }

        // Preserve API-050 order: repository read, State-policy probe, PBAC,
        // then query parse/count/page. Failure precedence is observable.
        $rows = $this->groups->listAll();
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

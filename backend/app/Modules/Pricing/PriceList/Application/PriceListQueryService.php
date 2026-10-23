<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Modules\Pricing\PriceList\Application\Ports\PriceListListAuthorizationPort;
use App\Modules\Pricing\PriceList\PriceListRepository;
use App\Modules\Pricing\PriceList\PriceListService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Pricing-owned orchestration for the existing Price List collection API.
 *
 * Preserve API-050's SQL owner/State buckets, short REPEATABLE READ snapshot,
 * precise COUNT/page and the original whole-list PHP/PBAC fallback. No
 * pricing resolution, money arithmetic or persisted writes are changed.
 */
final class PriceListQueryService
{
    /**
     * The connection is lazy because legacy API-050 validates query shape
     * before resolving the DB; even invalid-query error precedence matters.
     *
     * @param \Closure():ConnectionInterface<mixed,mixed> $connection
     */
    public function __construct(
        private readonly \Closure $connection,
        private readonly PriceListListAuthorizationPort $authorization,
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?PriceListService $priceLists = null,
        private readonly ?PriceListRepository $repository = null,
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
            filters: ['code', 'name', 'organization_id', 'status'],
            sort: ['id', 'code', 'effective_from', 'name', 'organization_id', 'priority', 'status'],
        );
        $adapter = $this->queryAdapter ?? new CommonListQueryAdapter();
        $parsed = $adapter->parse($query, $allowlist);
        $db = ($this->connection)();

        if (SqlPriceListListSource::supports($parsed)
            && $db instanceof BaseConnection && $db->transDepth === 0) {
            $repository = $this->repository ?? new PriceListRepository($db);
            $operation = $this->authorization->operation();
            $optimized = $repository->withConsistentListSnapshot(function () use ($db, $operation, $adapter, $query, $parsed, $allowlist): ?array {
                return $this->authorization->withReadOnlyBatch($operation, function () use ($db, $operation, $adapter, $query, $parsed, $allowlist): ?array {
                    // Preserve the signed-priority SQL/PHP comparator guard.
                    $probe = new SqlPriceListListSource($db, []);
                    if (! $probe->canServe($parsed)) {
                        return null;
                    }
                    $buckets = $this->authorization->authorizedBuckets($operation, $db);
                    if ($buckets === null) {
                        return null;
                    }
                    $result = $adapter->paginate($query, $allowlist,
                        new SqlPriceListListSource($db, $buckets));
                    $items = [];
                    foreach ($result->data as $row) {
                        if (! is_array($row)) {
                            return null;
                        }
                        try {
                            if (! $this->authorization->rowAllowed($row, $operation)) {
                                return null;
                            }
                        } catch (DomainException $_) {
                            // Uncertain snapshot/owner: rerun the legacy
                            // full-list path, never return a partial page.
                            return null;
                        }
                        $items[] = $row;
                    }
                    return ['items' => $items, 'page' => $result->query->page,
                        'page_size' => $result->query->pageSize, 'total' => $result->total];
                });
            });
            if ($optimized !== null) {
                return $optimized;
            }
        }

        // Search, Unicode/text sorts, noncanonical owner filters, negative
        // priority and ambiguous PBAC buckets retain the original semantics:
        // authorize every persisted row BEFORE filtering/count/pagination.
        $visible = [];
        $priceLists = $this->priceLists ?? new PriceListService(db: $db);
        foreach ($priceLists->listAll() as $row) {
            if ($this->authorization->rowAllowed($row)) {
                $visible[] = $row;
            }
        }
        $result = $adapter->paginate($query, $allowlist, new AuthorizedArrayListSource($visible));
        return [
            'items' => array_values(iterator_to_array($result->data)),
            'page' => $result->query->page,
            'page_size' => $result->query->pageSize,
            'total' => $result->total,
        ];
    }
}

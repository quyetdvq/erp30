<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Modules\Product\Product\ProductRepository;
use App\Modules\Product\Product\ProductRoleService;

/**
 * SQL-backed Product list entered only after the product.view authorization
 * gate. Product is a MASTER resource without row-level PBAC in this endpoint.
 *
 * For id sorting with no text search, COUNT and LIMIT/OFFSET run in SQL.
 * For other canonical list queries, SQL projects only searchable/sortable
 * keys and PHP performs its original Unicode/numeric-aware comparisons.
 * Full Product rows and their relationships are loaded only for the page.
 */
final class SqlProductListSource implements AuthorizedListSource
{
    private ?AuthorizedArrayListSource $projected = null;
    private ?CommonListQuery $projectedQuery = null;
    private bool $structureValidated = false;

    public function __construct(
        private readonly ProductRepository $repository,
        private readonly ProductRoleService $roles,
    ) {
    }

    public function countAuthorized(CommonListQuery $query): int
    {
        $this->assertCatalogStructure();
        if ($this->repository->supportsSqlList($query)) {
            return $this->repository->countListMatching($query);
        }
        return $this->projected($query)->countAuthorized($this->withoutSqlFilters($query));
    }

    /** @return list<array<string, mixed>> */
    public function fetchAuthorized(CommonListQuery $query): iterable
    {
        $this->assertCatalogStructure();
        if ($this->repository->supportsSqlList($query)) {
            return $this->roles->describePage($this->repository->listPageMatching($query));
        }

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->projected($query)->fetchAuthorized($this->withoutSqlFilters($query)),
        );
        if ($ids === []) {
            return [];
        }
        $byId = [];
        foreach ($this->repository->findByIds($ids) as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $pageRows = [];
        foreach ($ids as $id) {
            if (! isset($byId[$id])) {
                // Concurrent deletion between key projection and page read:
                // fail closed rather than return a silently incomplete page.
                throw new PersistenceException(ProductRepository::ERROR_PERSISTENCE_FAILED, 'listProjectedPage');
            }
            $pageRows[] = $byId[$id];
        }
        return $this->roles->describePage($pageRows);
    }

    private function assertCatalogStructure(): void
    {
        if ($this->structureValidated) {
            return;
        }
        $invalid = $this->repository->firstInvalidListStructure();
        if ($invalid !== null) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                'product_id' => $invalid['id'], 'reason' => $invalid['reason'],
            ]);
        }
        $this->structureValidated = true;
    }

    private function projected(CommonListQuery $query): AuthorizedArrayListSource
    {
        if ($this->projected === null || $this->projectedQuery !== $query) {
            $this->projected = new AuthorizedArrayListSource($this->repository->listProjectedKeys($query));
            $this->projectedQuery = $query;
        }
        return $this->projected;
    }

    /**
     * SQL has already applied safe exact filters. Do not filter again against
     * the key projection (which deliberately omits foreign-key columns).
     */
    private function withoutSqlFilters(CommonListQuery $query): CommonListQuery
    {
        return new CommonListQuery(
            $query->q,
            $query->page,
            $query->pageSize,
            $query->offset,
            $query->sort,
            [],
            $query->searchFields(),
        );
    }
}
